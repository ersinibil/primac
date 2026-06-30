<?php
// ACANS OTS — Zamanlanmış görev tetikleyici (cPanel Cron Jobs).
// Kesin 09:30 sabah hatırlatma için cPanel'de cron tanımla:
//   30 9 * * *  curl -s "https://acanstr.com/ots/cron.php?key=acans-cron-2026" >/dev/null
// (boot.php zaten 09:30 sonrası ilk girişte de tetikler; cron kimse girmese bile garanti eder.)
require_once __DIR__.'/boot.php';
$KEY='acans-cron-2026';
if(php_sapi_name()!=='cli' && (($_GET['key'] ?? '')!==$KEY)){ http_response_code(403); exit('Yetki yok'); }
if(is_file(__DIR__.'/daily_reminder_lib.php')){
    require_once __DIR__.'/daily_reminder_lib.php';
    try{ check_daily_reminders(db(), true); }catch(Throwable $e){}   // force: cron tam saatte çağırır
}
echo 'ok '.date('Y-m-d H:i');
