<?php
// WEB Rapor — mobil ile AYNI report_lib.php mantığını kullanır (parite)
require_once __DIR__.'/boot.php'; require_login();
require_once __DIR__.'/report_lib.php';
$pdo=db(); $me=(int)($_SESSION['user']['id']??0);
$appName = 'ACANS OTS';

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['from']??'')?$_GET['from']:date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['to']??'')?$_GET['to']:date('Y-m-t');
$modul= $_GET['modul'] ?? 'genel';
$ref  = (int)($_GET['ref'] ?? 0);
$detail = !empty($_GET['detay']);
$MODULES = report_modules();
$isAll = ($modul==='tumu');
$R = $isAll ? ['title'=>'Tüm Modüller','cards'=>[],'chart'=>null,'table'=>['head'=>[],'rows'=>[]]] : rpt($pdo,$modul,$from,$to,$ref,$detail);

// CSV indir (layout'tan ÖNCE)
if(($_GET['export']??'')==='csv'){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="acans_'.$modul.'_'.$from.'_'.$to.'.csv"');
  echo $isAll ? build_csv_all($pdo,$appName,$from,$to) : build_csv($R,$appName,$from,$to); exit;
}

require_once __DIR__.'/layout_top.php';
?>
<section style="max-width:1000px">
<style>
.rh{background:linear-gradient(135deg,#1e3a8a,#2563eb);border-radius:16px;padding:18px;display:flex;align-items:center;gap:14px;margin-bottom:14px;color:#fff}
.rh .lg{width:54px;height:54px;border-radius:14px;background:#fff;color:#1e3a8a;display:flex;align-items:center;justify-content:center;font-weight:1000;font-size:26px}
.rtabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.rtabs a{padding:8px 14px;border-radius:10px;background:#13233b;color:#cbd5e1;text-decoration:none;font-weight:700;font-size:13px}
.rtabs a.on{background:#2563eb;color:#fff}
.rkpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:14px}
.rkpi .b{background:#0f1d33;border:1px solid #1e3350;border-radius:14px;padding:14px}
.rkpi .b small{color:#7f95b2}.rkpi .b .v{font-size:22px;font-weight:900;margin-top:4px}
.rbar{display:flex;align-items:center;gap:10px;margin:6px 0;font-size:13px}
.rbar .l{width:30%;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rbar .t{flex:1;height:20px;background:#0f1d33;border-radius:6px;overflow:hidden}
.rbar .f{height:100%;background:linear-gradient(90deg,#3b82f6,#22d3ee)}
.rbar .v{width:22%;text-align:right;font-weight:700}
table.rt{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
table.rt th{text-align:left;color:#7f95b2;border-bottom:1px solid #1e3350;padding:7px}
table.rt td{padding:7px;border-bottom:1px solid #13233b}
.card{background:#0c1830;border:1px solid #1e3350;border-radius:14px;padding:16px;margin-bottom:14px}
@media print{.rtabs,.noprint{display:none!important}}
.rfilter{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.rfilter>div{display:flex;flex-direction:column}
.rfilter input[type=date]{min-width:150px;border:1px solid #1e3350;border-radius:10px;padding:9px 11px;background:#0f1d33;color:#e5edf7}
.rfilter .btn,.rfilter a.btn{flex:0 0 auto}
@media(max-width:520px){.rfilter input[type=date]{width:100%;min-width:0}.rfilter>div{flex:1 1 100%}}
</style>

<div class="rtabs noprint">
  <?php foreach($MODULES as $k=>$v): ?><a href="report.php?modul=<?=$k?>&from=<?=$from?>&to=<?=$to?>" class="<?=$modul===$k?'on':''?>"><?=$v?></a><?php endforeach; ?>
</div>

<div class="card noprint">
  <form method="get" class="rfilter">
    <input type="hidden" name="modul" value="<?=htmlspecialchars($modul)?>"><input type="hidden" name="ref" value="<?=$ref?>">
    <div><label style="display:block;color:#7f95b2;font-size:12px">Başlangıç</label><input type="date" name="from" value="<?=$from?>"></div>
    <div><label style="display:block;color:#7f95b2;font-size:12px">Bitiş</label><input type="date" name="to" value="<?=$to?>"></div>
    <button class="btn" type="submit">Getir</button>
    <button class="btn" type="button" onclick="shareReportPDF(this)" style="background:#16a34a;color:#fff">📲 PDF Paylaş</button>
    <button class="btn" type="button" onclick="shareReportPDF(this)" style="background:#2563eb;color:#fff">📄 PDF</button>
    <a class="btn ghost" href="report.php?modul=<?=$modul?>&export=csv&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>">Ham Veri (CSV)</a>
  </form>
</div>
<script>window.ACANS_REPORT_NAME='rapor_<?=$modul?>_<?=$from?>';</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="report_share.js"></script>

<?php if(!empty($R['error'])): ?><div class="card" style="color:#fca5a5"><?=htmlspecialchars($R['error'])?></div><?php endif; ?>

<div class="noprint" style="display:flex;gap:8px;margin-bottom:12px;max-width:360px">
  <a class="btn" href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>" style="flex:1;text-align:center;background:<?=!$detail?'#2563eb':'#13233b'?>;color:#fff">📄 Özet</a>
  <a class="btn" href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&detay=1" style="flex:1;text-align:center;background:<?=$detail?'#2563eb':'#13233b'?>;color:#fff">🔍 Detaylı</a>
</div>
<div id="repArea"><?= $isAll ? report_render_all($pdo,$appName,$from,$to,$detail) : report_render($R,$appName,$from,$to,$detail) ?></div>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
