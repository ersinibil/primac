<?php
/* OTS Personel — paylaşılan fonksiyonlar (web + mobil).
 * personnel_new.php / personnel_edit.php / mobile/personnel_new.php / mobile/personnel_view.php ortak kullanır.
 * CV/özgeçmiş dosya eki: database/migrations/036_personnel_cv.sql
 * Desen checks_notes_lib.php::checks_notes_handle_upload() ile birebir aynı (2026-07-03). */

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
