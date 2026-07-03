<?php
require_once 'common.php';
require_once __DIR__.'/../report_lib.php'; // paylaşımlı rapor mantığı (mobil+web)
block_personel('report'); // 2026-07-03: kullanıcı onayı verildi — 'report' yetkisi olan da girebilir
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
$appName = app_config()['app_name'] ?? 'OTS';

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['from']??'')?$_GET['from']:date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['to']??'')?$_GET['to']:date('Y-m-t');
$modul= $_GET['modul'] ?? 'genel';
$ref  = (int)($_GET['ref'] ?? 0);
$bmode= $_GET['mode'] ?? '';   // cari_toplu: '', receivable, payable, zero
$btype= $_GET['type'] ?? '';   // cari_toplu: '', Müşteri, Tedarikçi, Her İkisi
$detail = !empty($_GET['detay']);

$MODULES = report_modules();

$isAll = ($modul==='tumu');
$isCariToplu = ($modul==='cari_toplu');
$R = ($isAll || $isCariToplu) ? ($isCariToplu ? cari_toplu_summary($pdo,$bmode,$btype) : ['title'=>'Tüm Modüller','cards'=>[],'chart'=>null,'table'=>['head'=>[],'rows'=>[]]]) : rpt($pdo,$modul,$from,$to,$ref,$detail);

/* CSV indir */
if(($_GET['export']??'')==='csv'){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="acans_'.$modul.'_'.$from.'_'.$to.'.csv"');
  echo $isAll ? build_csv_all($pdo,$appName,$from,$to) : ($isCariToplu ? build_csv_cari_toplu($pdo,$appName,$from,$to,$bmode,$btype) : build_csv($R,$appName,$from,$to)); exit;
}
/* İç mesaj (CSV ekli) */
$sent='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_msg'])){
  $toUser=(int)$_POST['to_user'];
  if($toUser){
    $csv = $isAll ? build_csv_all($pdo,$appName,$from,$to) : ($isCariToplu ? build_csv_cari_toplu($pdo,$appName,$from,$to,$bmode,$btype) : build_csv($R,$appName,$from,$to));
    $dir=__DIR__.'/../uploads/job_files'; if(!is_dir($dir)) @mkdir($dir,0777,true);
    $fn='rapor_'.$modul.'_'.bin2hex(random_bytes(4)).'.csv';
    if(@file_put_contents($dir.'/'.$fn,$csv)!==false){
      $body="📊 ".$R['title']." Raporu ($from – $to)\n".implode(' · ',array_map(function($c){return $c[1].': '.$c[2];},array_slice($R['cards'],0,4)));
      $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,attachment,attach_type,is_read) VALUES(?,?,?,?,'file',0)")->execute([$me,$toUser,$body,'uploads/job_files/'.$fn]);
      if(file_exists(__DIR__.'/../push_lib.php')){ require_once __DIR__.'/../push_lib.php'; try{ push_to_user($toUser,'📊 Rapor',$R['title'].' '.$from,'messages.php?with='.$me); }catch(Throwable $e){} }
      $sent='Rapor iç mesaj olarak gönderildi (CSV ekli).';
    }
  }
}

$users=$pdo->query("SELECT id,full_name,username FROM app_users WHERE id<>$me AND active=1 ORDER BY full_name")->fetchAll();
topx('Rapor');
?>
<style>
.rpt-tabs{display:flex;gap:6px;overflow:auto;padding-bottom:4px;margin-bottom:10px}
.rpt-tabs a{white-space:nowrap;padding:8px 12px;border-radius:12px;background:#1e293b;color:#cbd5e1;text-decoration:none;font-weight:700;font-size:13px}
.rpt-tabs a.on{background:#2563eb;color:#fff}
</style>
<div class="rpt-tabs noprint">
  <?php foreach($MODULES as $k=>$v): ?><a href="report.php?modul=<?=$k?>&from=<?=$from?>&to=<?=$to?>" class="<?=$modul===$k?'on':''?>"><?=$v?></a><?php endforeach; ?>
</div>

<div class="panel noprint" style="padding:10px">
  <form method="get" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
    <input type="hidden" name="modul" value="<?=htmlspecialchars($modul)?>"><input type="hidden" name="ref" value="<?=$ref?>">
    <input type="hidden" name="mode" value="<?=htmlspecialchars($bmode)?>"><input type="hidden" name="type" value="<?=htmlspecialchars($btype)?>">
    <div style="flex:1;min-width:110px"><label>Başlangıç</label><input type="date" name="from" value="<?=$from?>" style="margin:0"></div>
    <div style="flex:1;min-width:110px"><label>Bitiş</label><input type="date" name="to" value="<?=$to?>" style="margin:0"></div>
    <button class="btn dark" style="padding:12px 16px">Getir</button>
  </form>
</div>

<?php if($sent): ?><div class="notice"><?=htmlspecialchars($sent)?></div><?php endif; ?>
<?php if(!empty($R['error'])): ?><div class="err"><?=htmlspecialchars($R['error'])?></div><?php endif; ?>

<div class="noprint" style="display:flex;gap:8px;margin-bottom:10px">
  <a class="btn" href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>" style="flex:1;text-align:center;background:<?=!$detail?'#2563eb':'#334155'?>;color:#fff">📄 Özet</a>
  <a class="btn" href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&detay=1&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>" style="flex:1;text-align:center;background:<?=$detail?'#2563eb':'#334155'?>;color:#fff">🔍 Detaylı</a>
</div>

<div id="repArea"><?= $isAll ? report_render_all($pdo,$appName,$from,$to,$detail) : ($isCariToplu ? report_render_cari_toplu($pdo,$appName,$from,$to,$bmode,$btype,$detail) : report_render($R,$appName,$from,$to,$detail)) ?></div>

<div class="panel noprint">
  <b>📤 Bu raporu paylaş</b>
  <button onclick="shareReportPDF(this)" class="btn" style="display:block;width:100%;text-align:center;margin-top:10px;background:#16a34a;color:#fff;padding:14px;font-size:15px">📲 PDF Olarak Paylaş (WhatsApp/Mail)</button>
  <small class="muted" style="display:block;margin-top:6px">Tüm raporu çok-sayfalı PDF yapıp paylaşım sayfasına yollar.</small>
  <div style="display:flex;gap:8px;margin-top:10px">
    <button onclick="shareReportPDF(this)" class="btn" style="flex:1;background:#2563eb;color:#fff">📄 PDF</button>
    <a class="btn" href="report.php?modul=<?=$modul?>&export=csv&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>" style="flex:1;text-align:center;background:#334155;color:#fff">Ham Veri</a>
  </div>
  <form method="post" style="margin-top:8px;display:flex;gap:8px">
    <input type="hidden" name="modul" value="<?=$modul?>"><input type="hidden" name="ref" value="<?=$ref?>"><input type="hidden" name="from" value="<?=$from?>"><input type="hidden" name="to" value="<?=$to?>"><input type="hidden" name="mode" value="<?=htmlspecialchars($bmode)?>"><input type="hidden" name="type" value="<?=htmlspecialchars($btype)?>">
    <select name="to_user" required style="flex:1;margin:0"><option value="">İç mesaj — kişi seç</option>
      <?php foreach($users as $u): ?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['full_name']?:$u['username'])?></option><?php endforeach; ?></select>
    <button class="btn dark" name="send_msg" value="1" style="padding:12px 14px">💬</button>
  </form>
</div>
<script>window.ACANS_REPORT_NAME='rapor_<?=$modul?>_<?=$from?>';</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="../report_share.js"></script>
<?php botx(); ?>
