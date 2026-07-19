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
      $body=$R['title']." Raporu ($from – $to)\n".implode(' · ',array_map(function($c){return $c[1].': '.$c[2];},array_slice($R['cards'],0,4)));
      $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,attachment,attach_type,is_read) VALUES(?,?,?,?,'file',0)")->execute([$me,$toUser,$body,'uploads/job_files/'.$fn]);
      if(file_exists(__DIR__.'/../push_lib.php')){ require_once __DIR__.'/../push_lib.php'; try{ push_to_user($toUser,'Rapor',$R['title'].' '.$from,'messages.php?with='.$me); }catch(Throwable $e){} }
      $sent='Rapor iç mesaj olarak gönderildi (CSV ekli).';
    }
  }
}

$users=$pdo->query("SELECT id,full_name,username FROM app_users WHERE id<>$me AND active=1 ORDER BY full_name")->fetchAll();
topx('Rapor');
?>
<div class="df-tabs noprint" style="overflow:auto;max-width:100%;-webkit-overflow-scrolling:touch;margin-bottom:12px">
  <?php foreach($MODULES as $k=>$v): ?><a href="report.php?modul=<?=$k?>&from=<?=$from?>&to=<?=$to?>" class="df-tab<?=$modul===$k?' df-tab--active':''?>"><?=$v?></a><?php endforeach; ?>
</div>

<style>
/* RAPOR MOBİL P0 (2026-07-19, tur 4): önceki flex+min-width:110px iki tarih alanı+buton aynı
   satıra sıkıştırıyordu — dar viewport'ta (375-430px) native iOS tarih input'u kendi minimum
   rahat genişliğini isteyip taşma/çakışmaya yol açıyordu. Varsayılan: dikey (label üstte, input
   tam genişlik) — sadece gerçekten yer olan genişlikte (mobil shell .app max-width:520px olduğu
   için pratikte nadiren tetiklenir ama kasıtlı bırakıldı) yatay kompakt satıra döner. */
.rfilter-m{display:flex;flex-direction:column;gap:10px}
.rfilter-m-field{display:flex;flex-direction:column;gap:4px}
.rfilter-m-field label{font-size:12px;font-weight:600;color:var(--c-muted,#94a3b8)}
.rfilter-m-field input{width:100%;margin:0}
.rfilter-m button{width:100%}
@media(min-width:560px){.rfilter-m{flex-direction:row;align-items:end;flex-wrap:nowrap}.rfilter-m-field{flex:1;min-width:0}.rfilter-m button{width:auto;flex:0 0 auto}}
</style>
<div class="df-panel noprint">
  <form method="get" class="rfilter-m">
    <input type="hidden" name="modul" value="<?=h($modul)?>"><input type="hidden" name="ref" value="<?=$ref?>">
    <input type="hidden" name="mode" value="<?=h($bmode)?>"><input type="hidden" name="type" value="<?=h($btype)?>">
    <div class="rfilter-m-field"><label>Başlangıç</label><input type="date" name="from" value="<?=$from?>"></div>
    <div class="rfilter-m-field"><label>Bitiş</label><input type="date" name="to" value="<?=$to?>"></div>
    <button class="df-btn df-btn--primary">Uygula</button>
  </form>
</div>

<?php if($sent): ?><?=ds_alert('success',$sent)?><?php endif; ?>
<?php if(!empty($R['error'])): ?><?=ds_alert('danger',$R['error'])?><?php endif; ?>

<style>.rview-tabs{display:inline-flex;background:var(--df-surface-sunken,rgba(255,255,255,.08));border-radius:14px;padding:3px;gap:2px;margin:10px 0}.rview-tabs a{padding:7px 16px;border-radius:11px;font-size:12.5px;font-weight:600;color:var(--c-muted,#94a3b8);text-decoration:none}.rview-tabs a.is-active{background:var(--df-surface,#1e293b);color:var(--df-ink-900,#fff)}</style>
<nav class="rview-tabs noprint">
  <a class="<?=!$detail?'is-active':''?>" href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>">Özet</a>
  <a class="<?=$detail?'is-active':''?>" href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&detay=1&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>">Detay</a>
</nav>

<div id="repArea"><?= $isAll ? report_render_all($pdo,$appName,$from,$to,$detail) : ($isCariToplu ? report_render_cari_toplu($pdo,$appName,$from,$to,$bmode,$btype,$detail) : report_render($R,$appName,$from,$to,$detail)) ?></div>

<div class="df-panel noprint" style="margin-top:14px">
  <b><?=ds_icon('send',16)?> Bu raporu paylaş</b>
  <button onclick="shareReportPDF(this)" class="df-btn df-btn--primary df-btn--lg" style="display:flex;width:100%;margin-top:10px;justify-content:center"><?=ds_icon('box',14)?> PDF Olarak Paylaş (WhatsApp/Mail)</button>
  <small class="muted" style="display:block;margin-top:6px">Tüm raporu çok-sayfalı PDF yapıp paylaşım sayfasına yollar.</small>
  <div style="display:flex;gap:8px;margin-top:10px">
    <button onclick="window.print()" class="df-btn df-btn--secondary" style="flex:1;justify-content:center">Yazdır / PDF</button>
    <a class="df-btn df-btn--secondary" href="report.php?modul=<?=$modul?>&export=csv&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>" style="flex:1;justify-content:center">Ham Veri</a>
  </div>
  <form method="post" style="margin-top:8px;display:flex;gap:8px">
    <input type="hidden" name="modul" value="<?=$modul?>"><input type="hidden" name="ref" value="<?=$ref?>"><input type="hidden" name="from" value="<?=$from?>"><input type="hidden" name="to" value="<?=$to?>"><input type="hidden" name="mode" value="<?=h($bmode)?>"><input type="hidden" name="type" value="<?=h($btype)?>">
    <select name="to_user" required style="flex:1;margin:0"><option value="">İç mesaj — kişi seç</option>
      <?php foreach($users as $u): ?><option value="<?=$u['id']?>"><?=h($u['full_name']?:$u['username'])?></option><?php endforeach; ?></select>
    <button class="df-btn df-btn--primary" name="send_msg" value="1"><?=ds_icon('chat',16)?></button>
  </form>
</div>
<script>window.ACANS_REPORT_NAME='rapor_<?=$modul?>_<?=$from?>';</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="../report_share.js"></script>
<?php botx(); ?>
