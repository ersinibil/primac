<?php
// ACANS OTS — Ortak paylaşım (WhatsApp + Mail) — mobil+web
// wa.me sadece METİN taşır (PDF eki için rapor PDF paylaşımı kullanılır).

// ---------------------------------------------------------------------------
// Uygulama ayar deposu (app_settings tablosu)
// ---------------------------------------------------------------------------
function get_setting($key, $default=null){
    if(!function_exists('db')) return $default;
    try{
        $st=db()->prepare("SELECT `value` FROM app_settings WHERE `key`=? LIMIT 1");
        $st->execute([$key]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        return ($row!==false) ? $row['value'] : $default;
    }catch(Throwable $e){ return $default; }
}

function set_setting($key, $val){
    if(!function_exists('db')) return false;
    $pdo=db();
    try{
        // Tablo yoksa oluştur
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            `key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `value` TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $st=$pdo->prepare("INSERT INTO app_settings (`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW()");
        return $st->execute([$key,$val]);
    }catch(Throwable $e){ return false; }
}

// ---------------------------------------------------------------------------

function wa_link($text,$phone=''){
    $p=preg_replace('/\D/','',(string)$phone);
    return 'https://wa.me/'.$p.'?text='.rawurlencode($text);
}

// Telefon normalize: TR 0XXX→90XXX, 10 hane→90 ekle
function _wa_normalize_phone($phone){
    $p=preg_replace('/\D/','',(string)$phone);
    if(strlen($p)<10) return '';
    if(substr($p,0,1)==='0') $p='90'.substr($p,1);
    elseif(strlen($p)===10) $p='90'.$p;
    return $p;
}

// Otomatik WhatsApp gönderimi.
// Önce app_settings'ten (admin panelden) okur; yoksa eski WA_GATEWAY_URL sabitine düşer.
// wa_enabled=0 ise hiç göndermez.
function wa_send($phone,$text){
    if(!function_exists('curl_init')) return false;

    // --- app_settings'ten oku ---
    $enabled  = get_setting('wa_enabled','0');
    $provider = get_setting('wa_provider','');
    $instance = get_setting('wa_instance','');
    $token    = get_setting('wa_token','');
    $wa_url   = get_setting('wa_url','');

    // Hiç ayar girilmemişse eski config sabitine düş
    $has_db_config = ($provider && ($instance || $wa_url));
    if(!$has_db_config){
        // Eski yol
        if(!defined('WA_GATEWAY_URL') || !WA_GATEWAY_URL) return false;
        $enabled='1'; $provider='custom'; $wa_url=WA_GATEWAY_URL;
        if(defined('WA_GATEWAY_TOKEN') && WA_GATEWAY_TOKEN) $token=WA_GATEWAY_TOKEN;
    }

    if($enabled!=='1') return false;

    $p=_wa_normalize_phone($phone);
    if(!$p) return false;

    try{
        if($provider==='ultramsg' && $instance){
            // UltraMsg: POST https://api.ultramsg.com/{instance}/messages/chat
            $url='https://api.ultramsg.com/'.rawurlencode($instance).'/messages/chat';
            $payload='token='.urlencode($token).'&to='.urlencode($p).'&body='.urlencode($text);
        } else {
            // Genel/custom gateway
            if(!$wa_url) return false;
            $url=$wa_url;
            $d=['to'=>$p,'body'=>$text];
            if($token) $d['token']=$token;
            $payload=http_build_query($d);
        }
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>10,
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $r=curl_exec($ch);
        curl_close($ch);
        return $r!==false;
    }catch(Throwable $e){ return false; }
}
// Medya (görsel/video/ses/belge) gönderimi — sadece UltraMsg'in ayrı medya uç noktalarını (image/
// video/audio/document) destekler; genel/custom gateway'lerde medya şeması bilinmediği için mesaj
// metnine linki ekleyip düz metin olarak gönderir (2026-07-03, kullanıcı isteği: WhatsApp'tan
// dosya/video/ses atabilme). $mediaUrl DIŞARIDAN ERİŞİLEBİLİR (public) bir URL olmalı.
function wa_send_media($phone,$mediaUrl,$type,$caption=''){
    if(!function_exists('curl_init')) return false;
    $enabled  = get_setting('wa_enabled','0');
    $provider = get_setting('wa_provider','');
    $instance = get_setting('wa_instance','');
    $token    = get_setting('wa_token','');

    $has_db_config = ($provider && $instance);
    if(!$has_db_config){
        if(!defined('WA_GATEWAY_URL') || !WA_GATEWAY_URL) return false;
        $enabled='1';
    }
    if($enabled!=='1') return false;

    $p=_wa_normalize_phone($phone);
    if(!$p) return false;

    if($provider!=='ultramsg' || !$instance){
        // Medya uç noktası bilinmeyen bir gateway — linki metin olarak gönder (elden gitmesin)
        return wa_send($phone, ($caption!==''?$caption."\n":'').$mediaUrl);
    }

    $epMap=['image'=>'image','video'=>'video','audio'=>'audio','document'=>'document'];
    $ep = $epMap[$type] ?? 'document';
    $url='https://api.ultramsg.com/'.rawurlencode($instance).'/messages/'.$ep;
    $d=['token'=>$token,'to'=>$p,$ep=>$mediaUrl];
    if($caption!=='' && $ep!=='audio') $d['caption']=$caption;
    if($ep==='document') $d['filename']=basename(parse_url($mediaUrl,PHP_URL_PATH) ?: 'dosya');

    try{
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>http_build_query($d),
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>15,
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $r=curl_exec($ch);
        curl_close($ch);
        return $r!==false;
    }catch(Throwable $e){ return false; }
}

// --- WhatsApp Konuşma Geçmişi (2026-07-05, kullanıcı isteği) ---
// "Sender scope" allowlist: HANGİ modüllerin gönderdiği mesajların conversation history'ye
// yazılacağını tek yerden kontrol eder. Bugün sadece 'wa_send_now' (manuel/müşteri iletişimi
// ekranı, web+mobil) etkin — sifre_sifirla.php (OTP), users.php (giriş bilgisi),
// daily_reminder_lib.php (otomatik rapor) gibi sistem mesajları BİLİNÇLİ OLARAK dışarıda: bunlar
// hassas/tek kullanımlık içerik, kalıcı ve ekrandan görülebilir bir geçmişte durmamalı. İleride
// başka bir modülü dahil etmek için TEK satır: aşağıdaki diziye kaynak adını eklemek yeterli.
function wa_log_enabled_sources(){
    return ['wa_send_now'];
}

// Migration 041 ile aynı şema — activity_lib.php::activity_install() ile aynı desen (kod, tablo
// henüz migrate edilmemiş bir ortamda da kendi kendine iyileşsin diye tam şemayı burada da taşır;
// migration idempotent olduğu için ikisi çakışmaz).
function wa_install(){
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS wa_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(32) NOT NULL,
            contact_id INT NULL,
            last_message_at DATETIME NULL,
            last_message_preview VARCHAR(255) NULL,
            last_direction VARCHAR(10) NULL,
            unread_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_phone(phone),
            INDEX idx_contact(contact_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS wa_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            direction VARCHAR(10) NOT NULL,
            source VARCHAR(40) NULL,
            body TEXT NULL,
            media_url VARCHAR(500) NULL,
            media_type VARCHAR(30) NULL,
            provider_message_id VARCHAR(120) NULL,
            status VARCHAR(30) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation(conversation_id),
            INDEX idx_provider_msg(provider_message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }catch(Throwable $e){}
}

// Telefona göre var olan cari eşleşmesini bulur (contacts.phone/phone2, normalize edilmiş
// karşılaştırma — DB'de ham format tutarsız olabileceği için PHP tarafında normalize edilip
// kıyaslanıyor, küçük/orta ölçekli cari tablosu için performans sorunu yaratmaz).
function wa_match_contact_by_phone($normalizedPhone){
    if(!$normalizedPhone) return null;
    try{
        $rows=db()->query("SELECT id,phone,phone2 FROM contacts WHERE phone<>'' OR phone2<>''")->fetchAll();
        foreach($rows as $r){
            if(_wa_normalize_phone($r['phone']??'')===$normalizedPhone) return (int)$r['id'];
            if(_wa_normalize_phone($r['phone2']??'')===$normalizedPhone) return (int)$r['id'];
        }
    }catch(Throwable $e){}
    return null;
}

// Bir konuşmayı (yoksa oluşturarak) telefon numarasına göre bulur, son-mesaj özetini günceller.
// $direction='inbound' ise unread_count arttırılır (webhook'tan çağrılır); 'outbound' okunmuş sayılır.
function wa_conversation_touch($phone,$direction,$preview){
    $p=_wa_normalize_phone($phone);
    if(!$p) return null;
    $pdo=db();
    $contactId=wa_match_contact_by_phone($p);
    $preview=mb_substr((string)$preview,0,255);
    wa_install();
    try{
        $s=$pdo->prepare("SELECT id FROM wa_conversations WHERE phone=?"); $s->execute([$p]);
        $convId=$s->fetchColumn();
        if($convId){
            $inc = $direction==='inbound' ? ",unread_count=unread_count+1" : ",unread_count=0";
            $pdo->prepare("UPDATE wa_conversations SET contact_id=?, last_message_at=NOW(), last_message_preview=?, last_direction=?$inc WHERE id=?")
                ->execute([$contactId,$preview,$direction,$convId]);
            return (int)$convId;
        }
        $unread = $direction==='inbound' ? 1 : 0;
        $pdo->prepare("INSERT INTO wa_conversations(phone,contact_id,last_message_at,last_message_preview,last_direction,unread_count) VALUES(?,?,NOW(),?,?,?)")
            ->execute([$p,$contactId,$preview,$direction,$unread]);
        return (int)$pdo->lastInsertId();
    }catch(Throwable $e){ return null; }
}

// Tek bir mesajı wa_messages'a yazar + ilgili konuşmayı günceller. $source, wa_log_enabled_sources()
// allowlist'i ile eşleşmeli (çağıran zaten bu kontrolü wa_send_logged() üzerinden yapıyor).
function wa_message_log($phone,$direction,$body,$source=null,$mediaUrl=null,$mediaType=null,$providerMsgId=null,$status=null){
    $convId=wa_conversation_touch($phone,$direction,$body!==''?$body:($mediaUrl?'📎 Medya':''));
    if(!$convId) return false;
    try{
        db()->prepare("INSERT INTO wa_messages(conversation_id,direction,source,body,media_url,media_type,provider_message_id,status,is_read)
            VALUES(?,?,?,?,?,?,?,?,?)")
            ->execute([$convId,$direction,$source,$body,$mediaUrl,$mediaType,$providerMsgId,$status,$direction==='outbound'?1:0]);
        return true;
    }catch(Throwable $e){ return false; }
}

// wa_send()/wa_send_media() sarmalayıcısı — GÖNDERME davranışı birebir aynı, TEK fark $source
// wa_log_enabled_sources() allowlist'indeyse başarılı gönderim conversation history'ye de yazılır.
// Allowlist'te olmayan kaynaklar (OTP, sistem mesajları) öncekiyle birebir aynı şekilde LOGSUZ kalır.
function wa_send_logged($phone,$text,$source,$mediaUrl=null,$mediaType=null){
    $sent = $mediaUrl ? wa_send_media($phone,$mediaUrl,$mediaType,$text) : wa_send($phone,$text);
    if($sent && in_array($source, wa_log_enabled_sources(), true)){
        try{ wa_message_log($phone,'outbound',$text,$source,$mediaUrl,$mediaType); }catch(Throwable $e){}
    }
    return $sent;
}

// Yüklenen dosyayı uploads/wa_send/ altına taşır, DIŞARIDAN erişilebilir tam URL döner (veya hata
// mesajı). İç mesajlaşmadaki (messages.php) attach mantığıyla aynı tür/uzantı ayrımını kullanır.
function wa_upload_media($field,&$err){
    if(empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error']!==UPLOAD_ERR_OK) return null;
    $f=$_FILES[$field];
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $imgExt=['jpg','jpeg','png','gif','webp'];
    $vidExt=['mp4','mov','webm','m4v'];
    $audExt=['m4a','mp3','wav','ogg','oga','aac','opus'];
    $docExt=['pdf','doc','docx','xls','xlsx'];
    // Beyaz liste — uploads/.htaccess script çalıştırmayı zaten engelliyor ama uzantı denetimi
    // ek güvenlik katmanı (2026-07-03 güvenlik denetimi: eskiden HERHANGİ bir uzantı kabul ediliyordu).
    if(!in_array($ext, array_merge($imgExt,$vidExt,$audExt,$docExt), true)){
        $err='Desteklenmeyen dosya türü (.'.h($ext).'). İzin verilenler: resim, video, ses, PDF/Word/Excel.';
        return null;
    }
    $type='document';
    if(in_array($ext,$imgExt,true)) $type='image';
    elseif(in_array($ext,$vidExt,true)) $type='video';
    elseif(in_array($ext,$audExt,true)) $type='audio';
    $dir=__DIR__.'/uploads/wa_send';
    if(!is_dir($dir)) @mkdir($dir,0755,true);
    $stored=bin2hex(random_bytes(8)).'.'.$ext;
    $dest=$dir.'/'.$stored;
    if(!@move_uploaded_file($f['tmp_name'],$dest)){ $err='Dosya kaydedilemedi.'; return null; }
    return ['url'=>base_url().'uploads/wa_send/'.$stored,'type'=>$type,'name'=>$f['name']];
}

// Basit emoji seçici — WhatsApp gönderme ve iç mesajlaşma kutularında ortak kullanılır
// (2026-07-03, kullanıcı isteği). Harici kütüphane/CDN yok, sabit kısa liste + textarea/input'a
// imleç konumuna ekleme. $dark=true mobil koyu temaya uysun diye.
function emoji_picker_html($targetId, $dark=false){
    $emojis = ['😀','😂','😊','🙂','😍','👍','👎','🙏','🎉','✅','❌','⚠️','📌','📅','💰','🧾','📦','🚚','⏰','❤️','🔥','👏','🤝','📞'];
    $panelId = $targetId.'_emojiPanel';
    $bg = $dark ? '#0f1d33' : '#fff';
    $border = $dark ? '#1e3350' : '#e5e7eb';
    ob_start(); ?>
<div style="position:relative;display:inline-block;flex:0 0 auto">
  <button type="button" class="btn secondary small" style="white-space:nowrap;overflow:hidden;min-width:0" title="Emoji" onclick="var p=document.getElementById('<?=$panelId?>');p.style.display=p.style.display==='block'?'none':'block'">😀</button>
  <div id="<?=$panelId?>" style="display:none;position:absolute;z-index:1002;bottom:100%;left:0;margin-bottom:6px;max-height:160px;overflow-y:auto;background:<?=$bg?>;border:1px solid <?=$border?>;border-radius:12px;padding:8px;box-shadow:0 -8px 20px rgba(0,0,0,.25);width:230px">
    <?php foreach($emojis as $e): ?><button type="button" style="font-size:19px;border:none;background:none;cursor:pointer;padding:4px" onclick="var t=document.getElementById('<?=$targetId?>');var s=t.selectionStart||t.value.length;t.value=t.value.slice(0,s)+'<?=$e?>'+t.value.slice(s);t.focus();var np=s+'<?=$e?>'.length;t.selectionStart=t.selectionEnd=np;document.getElementById('<?=$panelId?>').style.display='none'"><?=$e?></button><?php endforeach; ?>
  </div>
</div>
<?php
    return ob_get_clean();
}

function mail_link($subject,$body){
    return 'mailto:?subject='.rawurlencode($subject).'&body='.rawurlencode($body);
}

// Teklif firmaları — logo + web (logo dosyaları kök ve mobile/ içinde mevcut)
function firm_list(){
    return [
        'ACANS'  => ['name'=>'ACANS Reklam','logo'=>'logo.png','mark'=>'logo_acans_a.png','web'=>'www.acansreklam.com','c'=>'#cf3030','c2'=>'#1b2431','letterhead'=>'letterhead_acans.png'],
        'PRIMAC' => ['name'=>'PRIMAC','logo'=>'logo_primac.png','web'=>'www.primac.com.tr','c'=>'#e23b2e','c2'=>'#222831','letterhead'=>'letterhead_primac.png'],
    ];
}
function firm_info($k){ $l=firm_list(); return $l[$k] ?? null; }

// Rastgele güvenli şifre üret (8-12 karakter, uppercase+lowercase+digit+special)
function generate_random_password($length=10){
    $chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    $result='';
    for($i=0;$i<$length;$i++){
        $result.=$chars[random_int(0,strlen($chars)-1)];
    }
    return $result;
}

// Logo/ikon yükleme (brand_settings.php + mobile/brand_settings.php ortak — parite).
function brand_upload_ext(){ return ['jpg','jpeg','png','webp','gif']; }
function _brand_upload($field, $dest_path, &$err){
    $allowed_ext = brand_upload_ext();
    if(empty($_FILES[$field]['tmp_name'])) return false;
    $f = $_FILES[$field];
    if($f['error'] !== UPLOAD_ERR_OK){
        $err = 'Dosya yükleme hatası (kod: '.$f['error'].')';
        return false;
    }
    $orig = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if(!in_array($orig, $allowed_ext, true)){
        $err = 'Geçersiz dosya türü. Sadece: '.implode(', ', $allowed_ext);
        return false;
    }
    // Gerçek MIME kontrolü (imagecreatefromstring ile)
    $raw = @file_get_contents($f['tmp_name'], false, null, 0, 12);
    $is_img = (
        (substr($raw,0,8) === "\x89PNG\r\n\x1a\n") ||  // PNG
        (substr($raw,0,3) === "\xff\xd8\xff") ||         // JPG
        (substr($raw,0,6) === 'GIF87a' || substr($raw,0,6) === 'GIF89a') || // GIF
        (substr($raw,0,4) === 'RIFF')                    // WEBP (RIFF...WEBP)
    );
    // webp: RIFF....WEBP
    if(substr($raw,0,4) === 'RIFF'){
        $chunk = @file_get_contents($f['tmp_name'], false, null, 8, 4);
        $is_img = ($chunk === 'WEBP');
    }
    if(!$is_img && function_exists('exif_imagetype')){
        $is_img = (exif_imagetype($f['tmp_name']) !== false);
    }
    if(!$is_img){
        $err = 'Yüklenen dosya geçerli bir resim değil.';
        return false;
    }
    if(!move_uploaded_file($f['tmp_name'], $dest_path)){
        $err = 'Dosya kaydedilemedi. uploads/ klasörü yazılabilir mi?';
        return false;
    }
    return true;
}

// Giriş bilgilerini WhatsApp ile gönder linki (şifre düz metin — sadece kurulum/sıfırlama anında).
function cred_wa($phone,$username,$password){
    $url=function_exists('base_url')?base_url():'';
    $appName=function_exists('app_config')?(app_config()['app_name']??'OTS'):'OTS';
    $txt="🔐 ".$appName." giriş bilgileriniz\nKullanıcı: ".$username."\nŞifre: ".$password.($url?"\nAdres: ".$url:'');
    return wa_link($txt,$phone);
}

// WhatsApp + Mail buton çifti. $phone boşsa WhatsApp kişi seçtirir.
function share_buttons($text,$phone='',$subject=null){
    if($subject===null) $subject=function_exists('app_config')?(app_config()['app_name']??'OTS'):'OTS';
    $wa=wa_link($text,$phone); $ml=mail_link($subject,$text);
    return '<div style="display:flex;gap:8px;margin-top:8px">'
        .'<a href="'.htmlspecialchars($wa).'" target="_blank" rel="noopener" class="btn" style="flex:1;text-align:center;background:#16a34a;color:#fff;padding:10px;text-decoration:none">📲 WhatsApp</a>'
        .'<a href="'.htmlspecialchars($ml).'" class="btn" style="flex:1;text-align:center;background:#2563eb;color:#fff;padding:10px;text-decoration:none">✉️ Mail</a>'
        .'</div>';
}
