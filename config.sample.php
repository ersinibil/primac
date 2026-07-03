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
  // Web Push (push_lib.php) VAPID anahtar çifti — 2026-07-03'ten önce push_lib.php'ye gömülüydü,
  // artık buradan okunuyor. Her sitenin (ACANS/PRIMAC) KENDİ anahtar çiftini üretmesi önerilir.
  // 'vapid_public' => '...',
  // 'vapid_private' => '...',
  // 'vapid_subject' => 'mailto:admin@acanstr.com',
];
