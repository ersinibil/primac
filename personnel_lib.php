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
    // Elif'in PDP-001 re-review'ında bulduğu düzeltme (2026-07-15): mobildeki orijinal akış İKİ
    // ayrı sorguydu — hesabı BULMAK için p.user_id=u.id yönünde (personnel.user_id bu hesaba
    // işaret ediyorsa app_users.personnel_id boş/yanlış olsa bile hesabı bulan bir OR yedek yolu
    // sağlıyordu), sonra username/phone'u ÇEKMEK için ayrıca p.id=u.personnel_id yönünde. Bu ikisi
    // tek sorguda birleştirilince OR yedek yolu sessizce ölü koda dönüşüyordu — iki sorgu deseni
    // birebir geri getirildi.
    $bu=$pdo->prepare("SELECT u.id FROM app_users u LEFT JOIN personnel p ON p.user_id=u.id WHERE u.personnel_id=? OR p.id=? LIMIT 1");
    $bu->execute([$personnelId,$personnelId]); $br=$bu->fetch();
    if(!$br) throw new Exception('Bu personele bağlı bir giriş hesabı yok.');
    $boundUid=(int)$br['id'];
    $pdo->prepare("UPDATE app_users SET password_hash=? WHERE id=?")->execute([password_hash($newPassword,PASSWORD_DEFAULT),$boundUid]);
    $cu=$pdo->prepare("SELECT u.username,p.phone FROM app_users u LEFT JOIN personnel p ON p.id=u.personnel_id WHERE u.id=?");
    $cu->execute([$boundUid]); $cr=$cu->fetch();
    return ['user_id'=>$boundUid,'username'=>$cr['username']??'','phone'=>$cr['phone']??''];
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
