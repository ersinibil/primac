<?php
require_once __DIR__.'/../boot.php';
$appName = app_config()['app_name'] ?? 'OTS';
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
  'id'=>'ots-mobile',
  'name'=>$appName.' — İşletim Paneli',
  'short_name'=>$appName,
  'description'=>$appName.' mobil işletim paneli: iş, cari, stok, tahsilat ve mesajlaşma.',
  'start_url'=>'./index.php',
  'scope'=>'./',
  'display'=>'standalone',
  'orientation'=>'portrait',
  'background_color'=>'#071326',
  'theme_color'=>'#071326',
  'lang'=>'tr',
  'icons'=>[
    ['src'=>'icon.php?size=192','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
    ['src'=>'icon.php?size=512','sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable'],
  ],
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
