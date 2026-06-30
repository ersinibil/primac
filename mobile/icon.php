<?php
// PWA / uygulama ikonu — gerçek ACANS logosu (logo.png) yeniden boyutlanır.
error_reporting(0); @ini_set('display_errors','0');
while(function_exists('ob_get_level') && ob_get_level()>0) ob_end_clean();

$size=(int)($_GET['size'] ?? 192);
if($size<64) $size=64; if($size>1024) $size=1024;

// İkon = sadece "A" amblemi (logo_a.png); yoksa tam logo
$logo=is_file(__DIR__.'/logo_a.png')?__DIR__.'/logo_a.png':__DIR__.'/logo.png';
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
