<?php
// WEB Rapor — mobil ile AYNI report_lib.php mantığını kullanır (parite)
require_once __DIR__.'/boot.php'; require_login();
require_once __DIR__.'/report_lib.php';
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
$R = ($isAll || $isCariToplu) ? ['title'=>'Tüm Modüller','cards'=>[],'chart'=>null,'table'=>['head'=>[],'rows'=>[]]] : rpt($pdo,$modul,$from,$to,$ref,$detail);

// CSV indir (layout'tan ÖNCE)
if(($_GET['export']??'')==='csv'){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="acans_'.$modul.'_'.$from.'_'.$to.'.csv"');
  echo $isAll ? build_csv_all($pdo,$appName,$from,$to) : ($isCariToplu ? build_csv_cari_toplu($pdo,$appName,$from,$to,$bmode,$btype) : build_csv($R,$appName,$from,$to)); exit;
}

require_once __DIR__.'/layout_top.php';
?>
<section style="max-width:1000px">
<style>
@media print{.noprint{display:none!important}}
.rfilter{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.rfilter>div{display:flex;flex-direction:column}
@media(max-width:520px){.rfilter input[type=date]{width:100%;min-width:0}.rfilter>div{flex:1 1 100%}}
</style>

<div class="noprint">
<?php
ds_tabs(array_map(function($k,$v) use ($modul,$from,$to){
    return ['label'=>$v,'url'=>'report.php?modul='.$k.'&from='.$from.'&to='.$to,'active'=>$modul===$k];
}, array_keys($MODULES), $MODULES));
?>
</div>

<section class="df-card noprint" style="margin:var(--df-space-4) 0">
  <form method="get" class="rfilter">
    <input type="hidden" name="modul" value="<?=htmlspecialchars($modul)?>"><input type="hidden" name="ref" value="<?=$ref?>">
    <input type="hidden" name="mode" value="<?=htmlspecialchars($bmode)?>"><input type="hidden" name="type" value="<?=htmlspecialchars($btype)?>">
    <?php ds_form_field('Başlangıç', '<input type="date" name="from" value="'.$from.'">'); ?>
    <?php ds_form_field('Bitiş', '<input type="date" name="to" value="'.$to.'">'); ?>
    <button class="df-btn df-btn--primary" type="submit">Getir</button>
    <button class="df-btn" type="button" onclick="shareReportPDF(this)" style="background:var(--df-success);color:#fff">📲 PDF Paylaş</button>
    <button class="df-btn df-btn--secondary" type="button" onclick="shareReportPDF(this)">📄 PDF</button>
    <?=ds_button('Ham Veri (CSV)','report.php?modul='.$modul.'&export=csv&from='.$from.'&to='.$to.'&ref='.$ref.'&mode='.urlencode($bmode).'&type='.urlencode($btype),'ghost','','',true)?>
  </form>
</section>
<script>window.ACANS_REPORT_NAME='rapor_<?=$modul?>_<?=$from?>';</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="report_share.js"></script>

<?php if(!empty($R['error'])): ?><?=ds_alert('danger',$R['error'])?><?php endif; ?>

<div class="noprint" style="display:flex;gap:8px;margin-bottom:var(--df-space-3);max-width:360px">
  <?=ds_button('📄 Özet','report.php?modul='.$modul.'&from='.$from.'&to='.$to.'&ref='.$ref.'&mode='.urlencode($bmode).'&type='.urlencode($btype), !$detail?'primary':'secondary','','style="flex:1;text-align:center"',true)?>
  <?=ds_button('🔍 Detaylı','report.php?modul='.$modul.'&from='.$from.'&to='.$to.'&ref='.$ref.'&detay=1&mode='.urlencode($bmode).'&type='.urlencode($btype), $detail?'primary':'secondary','','style="flex:1;text-align:center"',true)?>
</div>
<div id="repArea"><?= $isAll ? report_render_all($pdo,$appName,$from,$to,$detail) : ($isCariToplu ? report_render_cari_toplu($pdo,$appName,$from,$to,$bmode,$btype,$detail) : report_render($R,$appName,$from,$to,$detail)) ?></div>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
