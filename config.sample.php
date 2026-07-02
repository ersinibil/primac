<?php
// ÖRNEK yapılandırma. Kopyala → config.php yap, kendi bilgilerini gir.
// config.php sunucuda .htaccess ile korunur ve ASLA pakete/git'e konmaz.
return [
  'db_host' => 'localhost',
  'db_name' => 'VERITABANI_ADI',
  'db_user' => 'KULLANICI',
  'db_pass' => 'SIFRE',
  'app_name' => 'ACANS OTS',
  // 'logo' => 'logo.png',  // Sol menü + PDF logosu (uploads/brand/ veya kök dizin)
  // Sabah raporu e-postası app_users tablosundaki admin/yonetici e-postalarına gider (mail() ile).
];
