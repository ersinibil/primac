<?php
// PWA / uygulama ikonu — yönetimden yüklenen marka ikonu veya logo
error_reporting(0); @ini_set('display_errors','0');
while(function_exists('ob_get_level') && ob_get_level()>0) ob_end_clean();

$size=(int)($_GET['size'] ?? 192);
if($size<64) $size=64; if($size>1024) $size=1024;

// Marka ikonu: uploads/brand_icon.png → uploads/brand_logo.png → logo_a.png → logo.png
$_root = dirname(__DIR__);
$logo = '';
// Önce share_lib ile ayarı oku (boot yüklenmeden güvenli)
if(!function_exists('get_setting') && is_file($_root.'/share_lib.php')){
    // Minimal db bağlantısı için config gerekli
    if(!function_exists('db') && is_file($_root.'/boot.php')){
        // Sadece ikonun kaynak tespiti için; sessiz yükle
        @include_once $_root.'/boot.php';
    } else {
        @include_once $_root.'/share_lib.php';
    }
}
if(function_exists('brand_icon')){
    $__bi = brand_icon();
    if($__bi && is_file($_root.'/'.$__bi)) $logo = $_root.'/'.$__bi;
} else {
    // get_setting doğrudan
    if(function_exists('get_setting')){
        $__bi = get_setting('brand_icon','');
        if($__bi && is_file($_root.'/'.$__bi)) $logo = $_root.'/'.$__bi;
        if(!$logo){ $__bl = get_setting('brand_logo',''); if($__bl && is_file($_root.'/'.$__bl)) $logo = $_root.'/'.$__bl; }
    }
    // uploads/brand_icon.png doğrudan kontrol
    if(!$logo && is_file($_root.'/uploads/brand_icon.png')) $logo = $_root.'/uploads/brand_icon.png';
    if(!$logo && is_file($_root.'/uploads/brand_logo.png')) $logo = $_root.'/uploads/brand_logo.png';
}
// Fallback: logo_a.png → logo.png
if(!$logo) $logo = is_file(__DIR__.'/logo_a.png') ? __DIR__.'/logo_a.png' : __DIR__.'/logo.png';
// Kök dizindeki logo_a / logo
if(!is_file($logo)){
    $logo = is_file($_root.'/logo_a.png') ? $_root.'/logo_a.png' : $_root.'/logo.png';
}
if(is_file($logo) && function_exists('imagecreatefrompng')){
    $src=@imagecreatefrompng($logo);
    if($src){
        $sw=imagesx($src); $sh=imagesy($src);
        $dst=imagecreatetruecolor($size,$size);
        $white=imagecolorallocate($dst,255,255,255);
        imagefilledrectangle($dst,0,0,$size,$size,$white);
        $scale=min($size/$sw,$size/$sh); $nw=(int)($sw*$scale); $nh=(int)($sh*$scale);
        $ox=(int)(($size-$nw)/2); $oy=(int)(($size-$nh)/2);
        imagecopyresampled($dst,$src,$ox,$oy,0,0,$nw,$nh,$sw,$sh);
        header('Content-Type: image/png');
        imagepng($dst); imagedestroy($dst); imagedestroy($src); exit;
    }
}
// Yedek: logo yoksa kırmızı amblem
if(function_exists('imagecreatetruecolor')){
    $img=imagecreatetruecolor($size,$size);
    $bg=imagecolorallocate($img,255,255,255);
    $red=imagecolorallocate($img,225,30,42);
    imagefill($img,0,0,$bg);
    imagefilledellipse($img,(int)($size/2),(int)($size/2),(int)($size*0.78),(int)($size*0.78),$red);
    header('Content-Type: image/png'); imagepng($img); imagedestroy($img); exit;
}
header('Content-Type: image/svg+xml');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'"><rect width="100%" height="100%" fill="#fff"/><circle cx="'.($size/2).'" cy="'.($size/2).'" r="'.($size*0.39).'" fill="#e11e2a"/></svg>';
