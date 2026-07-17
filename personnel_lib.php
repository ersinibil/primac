<?php
/* OTS Personel — paylaşılan fonksiyonlar (web + mobil).
 * personnel_new.php / personnel_edit.php / mobile/personnel_new.php / mobile/personnel_view.php ortak kullanır.
 * CV/özgeçmiş dosya eki: database/migrations/036_personnel_cv.sql
 * Desen checks_notes_lib.php::checks_notes_handle_upload() ile birebir aynı (2026-07-03). */

// RELEASE 0.9 — PİLOT KULLANIMA HAZIRLIK / Personel-Kullanıcı-Yetki Sadeleştirme (2026-07-17,
// Product Owner kararı: "Personel oluşturmak veya düzenlemek için farklı ekranlar arasında
// dolaşılmayacak — tek Personel Yönetimi ekranı"). Admin büyük çoğunlukla bu hazır rollerden birini
// seçer, yalnızca istisnai durumda "Gelişmiş Yetkiler" ile tek tek modül işaretler. 'Yönetici' DB'de
// role='yonetici' olarak saklanır (is_admin() bunu tam yetkili sayar — boot.php:151-154 — bu rol için
// permissions listesi hiç okunmaz/önemsizdir). Diğer tüm roller role='personel' + permissions[] ile
// çalışır. Modül anahtarları module_list()'ten (boot.php) gelir — yeni bir yetki kavramı icat edilmedi.
function personnel_role_presets(){
    return [
        'yonetici'=>['label'=>'Yönetici','role'=>'yonetici','perms'=>[]],
        'muhasebe'=>['label'=>'Muhasebe','role'=>'personel','perms'=>['dashboard','finance','muhasebe','contacts','report']],
        'satis'=>['label'=>'Satış','role'=>'personel','perms'=>['dashboard','contacts','teklif','stock','report']],
        'satinalma'=>['label'=>'Satın Alma','role'=>'personel','perms'=>['dashboard','stock','contacts','report']],
        'uretim'=>['label'=>'Üretim','role'=>'personel','perms'=>['dashboard','jobs','tasks','report']],
        'personel'=>['label'=>'Personel','role'=>'personel','perms'=>['dashboard','tasks']],
    ];
}

// Var olan role+permissions kombinasyonu hazır şablonlardan biriyle birebir eşleşiyorsa o şablonun
// key'ini, eşleşmiyorsa 'custom' döner — düzenleme ekranında hangi seçeneğin işaretli görüneceğini
// belirler (bkz. personnel_role_permission_fields_html()).
function personnel_detect_preset($role, $perms){
    if(!is_array($perms)) $perms=[];
    $p=array_values($perms); sort($p);
    foreach(personnel_role_presets() as $key=>$def){
        if($def['role']!==$role) continue;
        $dp=$def['perms']; sort($dp);
        if($dp===$p) return $key;
    }
    return 'custom';
}

// Sunulan preset key'ini güvenli role+permissions çiftine çözer. Rol yükseltmesi ('Yönetici' —
// is_admin()'in tam yetkili saydığı role='yonetici') SADECE oturumdaki kullanıcı zaten is_admin() ise
// izin verilir — HOTFIX-02 (2026-07-17, users.php) ile AYNI kural: 'personnel_accounts' yetkili bir
// alt yönetici hesap açabilir/düzenleyebilir ama kimseyi (kendisi dahil) admin/yönetici seviyesine
// çıkaramaz. Bu ekran artık module_list()'teki 'users' anahtarını hiçbir zaman vermez (aşağıda
// filtrelenir) — modül yönetimi yetkisi hâlâ yalnızca gerçek admin/yönetici rolü ile gelir.
// $currentRole: 'custom' (veya tanınmayan bir preset key'i) gönderildiğinde döndürülecek rol —
// çağıran taraf hesabın DB'deki GÜNCEL rolünü verir. Bu olmadan 'custom' durumu varsayılan olarak
// 'personel'e düşer ve ekranda "Özel" seçiliyken bile örn. role='admin' olan (id=1 dışı, eski
// users.php'den elle atanmış) bir hesap sessizce indirgenebilirdi — bu fallback SADECE o riski kapatır.
function personnel_resolve_role_preset($presetKey, $currentRole='personel'){
    $presets=personnel_role_presets();
    if(!isset($presets[$presetKey])){
        return ['label'=>'Özel','role'=>$currentRole,'perms'=>[]];
    }
    $def=$presets[$presetKey];
    if($def['role']!=='personel' && !(function_exists('is_admin') && is_admin())){
        $def=$presets['personel'];
    }
    return $def;
}

// $_POST['permissions'] gibi ham bir diziyi, bu ekrandan asla verilemeyecek 'users' anahtarından
// arındırılmış, indeksleri sıfırlanmış temiz bir listeye çevirir — hem web hem mobil tüm hesap/yetki
// formlarında tek doğrulama noktası.
function personnel_sanitize_permissions($rawPerms){
    if(!is_array($rawPerms)) $rawPerms=[];
    return array_values(array_diff($rawPerms, ['users']));
}

// Rol şablonu <select> + "Gelişmiş Yetkiler" açılır kutusu — personnel_new/personnel_edit (web) ve
// mobile/personnel_new/personnel_view'da BİREBİR aynı görünür, tek yerden bakımı kolaylaşsın diye
// buraya alındı. $selectedPreset: personnel_detect_preset() çıktısı ('personel' yeni kayıtta makul
// varsayılan). $checkedPerms: mevcut permissions dizisi (yeni kayıtta boş). $idSuffix: aynı sayfada
// birden fazla kopya varsa (yok ama ileriye dönük) DOM id çakışmasını önler.
function personnel_role_permission_fields_html($selectedPreset, $checkedPerms, $idSuffix=''){
    if(!is_array($checkedPerms)) $checkedPerms=[];
    $presets=personnel_role_presets();
    $isAdminSession = function_exists('is_admin') && is_admin();
    $selId='rolePreset'.$idSuffix; $gridId='permGrid'.$idSuffix;
    $out ='<div>';
    $out.='<label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Rol</label>';
    $out.='<select name="role_preset" id="'.h($selId).'">';
    foreach($presets as $key=>$def){
        if($def['role']!=='personel' && !$isAdminSession) continue; // yükseltme seçeneği sadece admin/yöneticiye görünür
        $out.='<option value="'.h($key).'" '.($selectedPreset===$key?'selected':'').'>'.h($def['label']).'</option>';
    }
    if($selectedPreset==='custom') $out.='<option value="custom" selected>Özel (elle düzenlenmiş)</option>';
    $out.='</select>';
    $out.='<details style="margin-top:10px" '.($selectedPreset==='custom'?'open':'').'>';
    $out.='<summary style="cursor:pointer;font-size:13px;font-weight:600">Gelişmiş Yetkiler (tek tek modül seç)</summary>';
    $out.='<p style="margin:6px 0;font-size:12px;color:var(--df-ink-500,#667085)">Genelde hazır rol yeterlidir. Sadece özel bir durumda tek tek işaretleyin.</p>';
    // Grid/chip için satır-içi stil kullanılıyor (class'lar da bırakıldı) — bu fonksiyon web (body.
    // nav-compact scope'lu sayfa-yerel <style>) VE mobil (o scope hiç yok) sayfalarda ORTAK
    // kullanılıyor, sayfa-yerel CSS'e bağımlı kalmadan her iki yüzeyde de doğru görünsün diye.
    $out.='<div id="'.h($gridId).'" class="df-permission-grid" style="margin-top:8px;display:grid;grid-template-columns:repeat(2,minmax(140px,1fr));gap:8px">';
    foreach(module_list() as $mkey=>$mlabel){
        if($mkey==='users') continue; // Kullanıcı/Yetki modülü bu ekrandan asla verilemez
        $out.='<label class="df-permission-chip" style="display:flex;align-items:center;gap:8px;background:var(--df-surface-sunken,rgba(127,127,127,.12));border-radius:8px;padding:8px 10px;font-size:13px">';
        $out.='<input type="checkbox" name="permissions[]" value="'.h($mkey).'" '.(in_array($mkey,$checkedPerms,true)?'checked':'').' style="width:auto"> '.h($mlabel);
        $out.='</label>';
    }
    $out.='</div></details>';
    $out.='<script>(function(){var P='.json_encode(array_map(function($d){return $d['perms'];},$presets),JSON_UNESCAPED_UNICODE).';var s=document.getElementById('.json_encode($selId).'),g=document.getElementById('.json_encode($gridId).');if(!s||!g)return;s.addEventListener("change",function(){if(!P.hasOwnProperty(s.value))return;var perms=P[s.value];var boxes=g.querySelectorAll("input[type=checkbox]");for(var i=0;i<boxes.length;i++){boxes[i].checked=perms.indexOf(boxes[i].value)!==-1;}});})();</script>';
    $out.='</div>';
    return $out;
}

/**
 * PDP-001 (2026-07-15): personnel_edit.php (web) için çıkarıldı — çağıran taraf yetki kontrolünü
 * ($canManageAccounts: admin veya 'personnel_accounts' yetkili "alt yönetici", SECURITY SPRINT-001
 * 2026-07-04) kendisi yapar, bu fonksiyon SADECE paylaşılan DB işlemini içerir, yetki kontrolü YAPMAZ.
 * RELEASE 0.9 (2026-07-17): $role/$permissions parametreleri eklendi — Personel/Kullanıcı/Yetki
 * sadeleştirmesiyle artık hesap oluşturulurken rol/yetki de aynı adımda seçilebiliyor. Rol yükseltme
 * koruması ÇAĞIRAN TARAFTA (personnel_resolve_role_preset()) yapılır, bu fonksiyon güvendiği rolü
 * olduğu gibi yazar — tıpkı önceki sabit 'personel' değerini olduğu gibi yazdığı gibi.
 * @throws Exception kullanıcı adı çakışması
 * @return array ['user_id'=>int,'phone'=>string]
 */
function personnel_create_login($pdo, $personnelId, $username, $password, $role='personel', $permissions=[]){
    $username=trim($username);
    $permissions=personnel_sanitize_permissions($permissions);
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
    $pdo->prepare("INSERT INTO app_users(username,full_name,phone,email,password_hash,role,personnel_id,permissions,active,created_at) VALUES(?,?,?,?,?,?,?,?,1,NOW())")
        ->execute([$username,$prow['name'],$prow['phone'],$prow['email'],password_hash($password,PASSWORD_DEFAULT),$role,$personnelId,json_encode($permissions,JSON_UNESCAPED_UNICODE)]);
    $uid=(int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE personnel SET user_id=?,login_enabled=1 WHERE id=?")->execute([$uid,$personnelId]);
    return ['user_id'=>$uid,'phone'=>$prow['phone']??''];
}

// RELEASE 0.9 — Personel/Kullanıcı/Yetki Sadeleştirme (2026-07-17): bağlı hesabın rol+yetkilerini
// tek yerden günceller — personnel_edit.php (web) ve mobile/personnel_view.php artık users.php'ye
// gitmeden aynı ekrandan rol/yetki değiştirebiliyor. Ana yönetici (id=1) korunur: rolü hiçbir zaman
// düşürülemez (users.php'deki "$uid===1 => role='admin'" kuralıyla aynı — HOTFIX-02, 2026-07-17).
// Rol yükseltme koruması (yonetici) ÇAĞIRAN TARAFTA personnel_resolve_role_preset() ile yapılır.
function personnel_update_account_role($pdo, $userId, $role, $permissions){
    $userId=(int)$userId;
    $permissions=personnel_sanitize_permissions($permissions);
    if($userId===1) $role='admin';
    $pdo->prepare("UPDATE app_users SET role=?, permissions=? WHERE id=?")
        ->execute([$role, json_encode($permissions,JSON_UNESCAPED_UNICODE), $userId]);
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
