<?php
// İşi cihaz takvimine ekle (.ics) — telefon/masaüstü takvimine işlenir
require_once __DIR__.'/boot.php';
require_login();
$pdo=db();
$id=(int)($_GET['job']??0);
function ical_esc($s){ return preg_replace('/([,;\\\\])/','\\\\$1', str_replace(["\r\n","\r","\n"],' ', (string)$s)); }
try{
    $st=$pdo->prepare("SELECT title,job_no,due_date,description FROM jobs WHERE id=?"); $st->execute([$id]); $j=$st->fetch();
}catch(Throwable $e){ $j=null; }
if(!$j || empty($j['due_date'])){ http_response_code(404); exit('Bu işin termini yok.'); }
$dt=date('Ymd', strtotime($j['due_date']));
$now=gmdate('Ymd\THis\Z');
$sum='İş: '.$j['title'].($j['job_no']?' ('.$j['job_no'].')':'');
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="is_'.$id.'.ics"');
$icsAppName=app_config()['app_name'] ?? 'OTS';
echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//".$icsAppName."//TR\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\n";
echo "UID:job-".$id."@ots\r\nDTSTAMP:".$now."\r\nDTSTART;VALUE=DATE:".$dt."\r\n";
echo "SUMMARY:".ical_esc($sum)."\r\nDESCRIPTION:".ical_esc($j['description']??'')."\r\n";
echo "BEGIN:VALARM\r\nTRIGGER:-P1D\r\nACTION:DISPLAY\r\nDESCRIPTION:".ical_esc($sum)."\r\nEND:VALARM\r\n";
echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
