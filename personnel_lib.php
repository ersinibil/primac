<?php
/* OTS Personel — paylaşılan fonksiyonlar (web + mobil).
 * personnel_new.php / personnel_edit.php / mobile/personnel_new.php / mobile/personnel_view.php ortak kullanır.
 * CV/özgeçmiş dosya eki: database/migrations/036_personnel_cv.sql
 * Desen checks_notes_lib.php::checks_notes_handle_upload() ile birebir aynı (2026-07-03). */

/**
 * PDP-001 (2026-07-15): personnel_edit.php (web) için çıkarıldı — çağıran taraf yetki kontrolünü
 * ($canManageAccounts: admin veya 'personnel_accounts' yetkili "alt yönetici", SECURITY SPRINT-001
 * 2026-07-04) kendisi yapar, bu fonksiyon SADECE paylaşılan DB işlemini içerir, yetki kontrolü YAPMAZ.
 * mobile/personnel_view.php'nin kendi (birebir aynı, ayrı) make_login/reset_pw mantığı bu sprintte
 * hâlâ ayrı bırakıldı — çalışan mobil akışa dokunmamak için, gelecekte tek fonksiyona birleştirilebilir.
 * @throws Exception kullanıcı adı çakışması
 * @return array ['user_id'=>int,'phone'=>string]
 */
function personnel_create_login($pdo, $personnelId, $username, $password){
    $username=trim($username);
    $ex=$pdo->prepare("SELECT id FROM app_users WHERE username=?"); $ex->execute([$username]);
    if($ex->fetch()) throw new Exception('Bu kullanıcı adı zaten var.');
    // P0-AUTH-01 (2026-07-17): aynı personele ikinci bir hesap daha açılması, hangi hesabın
    // "güncel" sayılacağını belirsizleştiriyordu (bkz. personnel_reset_password() üstündeki not —
    // gerçek kullanıcı testinde personelin yeni şifreyle giriş yapamaması bu köke bağlandı). Zaten
    // bağlı bir hesap varsa (personnel.user_id VEYA app_users.personnel_id üzerinden) yeni hesap
    // oluşturulmasını engelle. Ece'nin P0-AUTH-01 re-review'ında bulduğu düzeltme: personnel.user_id
    // sadece varlığı değil, HÂLÂ bu personele ait olup olmadığı (personnel_id=?) da doğrulanır —
    // users.php'den bir hesap başka bir personele taşınmışsa (bkz. users.php update_user), eski
    // personelin user_id'si "yetim" değil ama artık BAŞKASINA ait olur; salt varlık kontrolü bunu
    // yanlışlıkla "hâlâ bağlı" sayıp meşru yeni hesap açılmasını engelleyebilirdi.
    $pr0=$pdo->prepare("SELECT user_id FROM personnel WHERE id=?"); $pr0->execute([$personnelId]); $p0=$pr0->fetch();
    $existingUid=(int)($p0['user_id'] ?? 0);
    $hasLink = false;
    if($existingUid){
        $chk=$pdo->prepare("SELECT id FROM app_users WHERE id=? AND personnel_id=?"); $chk->execute([$existingUid,$personnelId]);
        $hasLink = (bool)$chk->fetch();
    }
    if(!$hasLink){
        $le=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? LIMIT 1"); $le->execute([$personnelId]);
        $hasLink = (bool)$le->fetch();
    }
    if($hasLink) throw new Exception('Bu personelin zaten bağlı bir giriş hesabı var. Yeni hesap oluşturmak yerine mevcut hesabın şifresini sıfırlayın.');
    $pr=$pdo->prepare("SELECT name,phone,email FROM personnel WHERE id=?"); $pr->execute([$personnelId]); $prow=$pr->fetch();
    $pdo->prepare("INSERT INTO app_users(username,full_name,phone,email,password_hash,role,personnel_id,active,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())")
        ->execute([$username,$prow['name'],$prow['phone'],$prow['email'],password_hash($password,PASSWORD_DEFAULT),'personel',$personnelId]);
    $uid=(int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE personnel SET user_id=?,login_enabled=1 WHERE id=?")->execute([$uid,$personnelId]);
    return ['user_id'=>$uid,'phone'=>$prow['phone']??''];
}

/**
 * PDP-001 (2026-07-15): bkz. personnel_create_login() üstündeki not. Hedef hesap her zaman
 * $personnelId üzerinden DB'den çözülür — çağıran tarafın POST'tan bir uid'e güvenmesi gerekmez
 * (IDOR'a kapalı — mobile/personnel_view.php'deki aynı güvenlik notuyla aynı gerekçe).
 * @throws Exception bağlı hesap yoksa
 * @return array ['user_id'=>int,'username'=>string,'phone'=>string]
 */
function personnel_reset_password($pdo, $personnelId, $newPassword){
    // P0-AUTH-01 (2026-07-17): gerçek kullanıcı testinde "şifre sıfırlandı" mesajı gösterilmesine
    // rağmen personelin yeni şifreyle giriş yapamadığı bildirildi. Kök neden: eski sorgu
    // (LEFT JOIN + OR + ORDER BY'sız LIMIT 1) personele birden fazla app_users kaydı bağlıysa
    // (bkz. personnel_create_login() üstündeki not — önceden bu ihtimale karşı bir engel yoktu)
    // HANGİ kaydın döneceğini garanti etmiyordu; MySQL bazen personelin GERÇEKTEN kullandığı
    // hesap yerine eski/artık kullanılmayan bir hesabı seçip onu güncelleyebiliyordu. Artık hedef
    // hesap TEK ve deterministik bir kaynaktan çözülüyor: personnel.user_id — bu alan "personel
    // için güncel hesap" anlamına geliyor ve personnel_create_login() tarafından tutuluyor.
    $pr=$pdo->prepare("SELECT user_id FROM personnel WHERE id=?"); $pr->execute([$personnelId]); $prow=$pr->fetch();
    $boundUid=(int)($prow['user_id'] ?? 0);
    if($boundUid){
        // Ece'nin P0-AUTH-01 re-review'ında bulduğu düzeltme: sadece hesabın VAR olduğunu değil,
        // hâlâ BU personele ait (personnel_id=?) olduğunu da doğrula — aksi halde başka bir
        // personele taşınmış bir hesabın şifresi yanlışlıkla değiştirilebilir.
        $chk=$pdo->prepare("SELECT id FROM app_users WHERE id=? AND personnel_id=?"); $chk->execute([$boundUid,$personnelId]);
        if(!$chk->fetch()) $boundUid=0; // yetim veya artık başka personele ait
    }
    if(!$boundUid){
        // Geriye dönük uyumluluk: user_id hiç set edilmemiş eski kayıtlar için tek deterministik
        // yedek yol — en eski (ilk oluşturulan) bağlı hesap, web'in mevcut görüntüleme sorgusuyla
        // (personnel_edit.php) aynı ORDER BY id ASC kuralını izler.
        $fb=$pdo->prepare("SELECT id FROM app_users WHERE personnel_id=? ORDER BY id ASC LIMIT 1"); $fb->execute([$personnelId]);
        $fr=$fb->fetch();
        $boundUid=(int)($fr['id'] ?? 0);
    }
    if(!$boundUid) throw new Exception('Bu personele bağlı bir giriş hesabı yok.');
    $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($newPassword,PASSWORD_DEFAULT),$boundUid]);
    $cu=$pdo->prepare("SELECT username FROM app_users WHERE id=?"); $cu->execute([$boundUid]); $cr=$cu->fetch();
    $pp=$pdo->prepare("SELECT phone FROM personnel WHERE id=?"); $pp->execute([$personnelId]); $ppr=$pp->fetch();
    return ['user_id'=>$boundUid,'username'=>$cr['username']??'','phone'=>$ppr['phone']??''];
}

// $_FILES['cv'] varsa yükler, uploads/personnel_cv altına taşır ve kök-göreli yolu döner.
// Dosya seçilmediyse null döner (mevcut CV korunur). Gerçek bir yükleme hatası olursa Exception fırlatır.
function personnel_handle_cv_upload(){
    if(empty($_FILES['cv']) || $_FILES['cv']['error']===UPLOAD_ERR_NO_FILE){
        return null;
    }
    $f=$_FILES['cv'];
    if($f['error'] !== UPLOAD_ERR_OK){
        $errors=[
            UPLOAD_ERR_INI_SIZE=>"Dosya sunucunun izin verdiği boyuttan büyük.",
            UPLOAD_ERR_FORM_SIZE=>"Dosya form limitinden büyük.",
            UPLOAD_ERR_PARTIAL=>"Dosya eksik yüklendi.",
            UPLOAD_ERR_NO_TMP_DIR=>"Sunucuda geçici klasör yok.",
            UPLOAD_ERR_CANT_WRITE=>"Dosya sunucuya yazılamadı.",
            UPLOAD_ERR_EXTENSION=>"PHP eklentisi yüklemeyi durdurdu."
        ];
        throw new Exception(isset($errors[$f['error']]) ? $errors[$f['error']] : "Dosya yükleme hatası. Kod: ".$f['error']);
    }

    $uploadDir=__DIR__.'/uploads/personnel_cv';
    if(!is_dir($uploadDir)){
        if(!mkdir($uploadDir,0755,true)){
            throw new Exception("uploads/personnel_cv klasörü oluşturulamadı.");
        }
    }
    if(!is_writable($uploadDir)){
        throw new Exception("uploads/personnel_cv klasörü yazılabilir değil. cPanel izinlerini kontrol et.");
    }

    $original=$f['name'];
    $tmp=$f['tmp_name'];
    $size=(int)$f['size'];
    $ext=strtolower(pathinfo($original, PATHINFO_EXTENSION));

    $allowed=['pdf','doc','docx','jpg','jpeg','png'];
    if(!in_array($ext,$allowed,true)){
        throw new Exception("Bu dosya türüne izin verilmiyor: ".$ext.". İzin verilenler: ".implode(', ',$allowed));
    }
    if($size > 15*1024*1024){
        throw new Exception("Dosya 15 MB üzerinde olamaz.");
    }

    $stored='cv_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$ext;
    $target=$uploadDir.'/'.$stored;
    if(!move_uploaded_file($tmp,$target)){
        throw new Exception("Dosya yüklenemedi. Sunucu yazma izni veya dosya limiti olabilir.");
    }
    return 'uploads/personnel_cv/'.$stored;
}

// personnel.cv_path kolonu henüz migration çalışmamış eski bir kurulumda olmayabilir — güvenli kontrol.
function personnel_has_cv_column($pdo){
    static $has=null;
    if($has===null){
        try{
            $chk=$pdo->query("SHOW COLUMNS FROM personnel LIKE 'cv_path'");
            $has = (bool)$chk->fetch();
        }catch(Throwable $e){ $has=false; }
    }
    return $has;
}
