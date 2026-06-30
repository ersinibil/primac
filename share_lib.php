<?php
// ACANS OTS — Ortak paylaşım (WhatsApp + Mail) — mobil+web
// wa.me sadece METİN taşır (PDF eki için rapor PDF paylaşımı kullanılır).

function wa_link($text,$phone=''){
    $p=preg_replace('/\D/','',(string)$phone);
    return 'https://wa.me/'.$p.'?text='.rawurlencode($text);
}
function mail_link($subject,$body){
    return 'mailto:?subject='.rawurlencode($subject).'&body='.rawurlencode($body);
}

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
