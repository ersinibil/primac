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

// Giriş bilgilerini WhatsApp ile gönder linki (şifre düz metin — sadece kurulum/sıfırlama anında).
function cred_wa($phone,$username,$password){
    $url=function_exists('base_url')?base_url():'';
    $txt="🔐 ACANS OTS giriş bilgileriniz\nKullanıcı: ".$username."\nŞifre: ".$password.($url?"\nAdres: ".$url:'');
    return wa_link($txt,$phone);
}

// WhatsApp + Mail buton çifti. $phone boşsa WhatsApp kişi seçtirir.
function share_buttons($text,$phone='',$subject='ACANS OTS'){
    $wa=wa_link($text,$phone); $ml=mail_link($subject,$text);
    return '<div style="display:flex;gap:8px;margin-top:8px">'
        .'<a href="'.htmlspecialchars($wa).'" target="_blank" rel="noopener" class="btn" style="flex:1;text-align:center;background:#16a34a;color:#fff;padding:10px;text-decoration:none">📲 WhatsApp</a>'
        .'<a href="'.htmlspecialchars($ml).'" class="btn" style="flex:1;text-align:center;background:#2563eb;color:#fff;padding:10px;text-decoration:none">✉️ Mail</a>'
        .'</div>';
}
