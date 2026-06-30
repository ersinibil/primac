<?php
// ACANS OTS — Ortak paylaşım (WhatsApp + Mail) — mobil+web
// wa.me sadece METİN taşır (PDF eki için rapor PDF paylaşımı kullanılır).

function wa_link($text,$phone=''){
    $p=preg_replace('/\D/','',(string)$phone);
    return 'https://wa.me/'.$p.'?text='.rawurlencode($text);
}

// Otomatik WhatsApp gönderimi. config.php'de WA_GATEWAY_URL tanımlıysa o servise POST atar
// (ultramsg / callmebot / kendi gateway'in). Tanımsızsa sessizce false döner (sadece iç mesaj gider).
// Beklenen POST alanları: to=<telefon>, body=<metin>, (varsa) token=<WA_GATEWAY_TOKEN>.
function wa_send($phone,$text){
    if(!defined('WA_GATEWAY_URL') || !WA_GATEWAY_URL || !function_exists('curl_init')) return false;
    $p=preg_replace('/\D/','',(string)$phone);
    if(strlen($p)<10) return false;
    if(substr($p,0,1)==='0') $p='90'.substr($p,1);
    elseif(strlen($p)===10) $p='90'.$p;
    $payload=['to'=>$p,'body'=>$text];
    if(defined('WA_GATEWAY_TOKEN') && WA_GATEWAY_TOKEN) $payload['token']=WA_GATEWAY_TOKEN;
    try{
        $ch=curl_init(WA_GATEWAY_URL);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>false]);
        $r=curl_exec($ch); curl_close($ch);
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
