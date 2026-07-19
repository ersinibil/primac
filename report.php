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
<section style="max-width:1000px" class="noprint">
<?php ds_page_header('Raporlar', ds_icon('box',24), 'Modül bazlı özet + detay, PDF/CSV dışa aktarım', '', false, true); ?>
</section>
<section style="max-width:1000px">
<style>
@media print{.noprint{display:none!important}}
/* RAPOR AİLESİ — SON POLISH (2026-07-19, tur 3): toolbar masaüstünde TEK satıra kilitli
   (flex-wrap:nowrap, sadece <640px'de alt alta) — önceki tur "wrap" bırakmıştı, geniş ekranda
   bile gereksiz sarabiliyordu. Modül sekmeleri: body.nav-compact .df-tabs GLOBAL kuralı
   (assets/css/ds-foundation.css) web'de bilinçli olarak flex-wrap:wrap kullanıyor (başka
   sayfalarda az sekme var, sorun değil) — burada 12 modül var, wrap "Muhasebe tek başına 2.
   satırda" gibi dengesiz kırılmaya yol açıyordu. Global kuralı DEĞİŞTİRMİYORUZ (başka sayfaları
   bozmamak için) — sadece BU sayfaya özel .rep-modtabs sarmalayıcısıyla, global kuraldan daha
   yüksek özgüllükte (body.nav-compact .rep-modtabs .df-tabs) yatay kaydırmaya geri döndürüyoruz. */
.rtoolbar{display:flex;flex-wrap:nowrap;gap:10px;align-items:center;justify-content:space-between;padding:10px 14px}
.rtoolbar-dates{display:flex;flex-wrap:nowrap;gap:8px;align-items:center;flex:0 1 auto}
.rtoolbar-dates input[type=date]{margin:0;padding:8px 10px;width:auto}
.rtoolbar-actions{display:flex;flex-wrap:nowrap;gap:8px;align-items:center;flex:0 0 auto}
@media(max-width:760px){.rtoolbar{flex-wrap:wrap}}
@media(max-width:640px){.rtoolbar{flex-direction:column;align-items:stretch}.rtoolbar-dates,.rtoolbar-actions{flex-wrap:wrap;justify-content:stretch}.rtoolbar-dates input[type=date]{flex:1;min-width:0}}
body.nav-compact .rep-modtabs .df-tabs{flex-wrap:nowrap;overflow-x:auto;max-width:100%}
.rview-tabs{display:inline-flex;background:var(--df-surface-sunken,#f2f4f7);border-radius:var(--df-radius-md,10px);padding:3px;gap:2px;margin-bottom:var(--df-space-3)}
.rview-tabs a{padding:7px 16px;border-radius:calc(var(--df-radius-md,10px) - 3px);font-size:var(--df-type-caption-size,12.5px);font-weight:600;color:var(--df-ink-500,#667085);text-decoration:none}
.rview-tabs a.is-active{background:var(--df-surface,#fff);color:var(--df-ink-900,#101828);box-shadow:var(--df-shadow-sm,0 1px 3px rgba(16,24,40,.1))}
</style>

<div class="noprint rep-modtabs">
<?php
ds_tabs(array_map(function($k,$v) use ($modul,$from,$to){
    return ['label'=>$v,'url'=>'report.php?modul='.$k.'&from='.$from.'&to='.$to,'active'=>$modul===$k];
}, array_keys($MODULES), $MODULES));
?>
</div>

<section class="df-card noprint" style="margin:var(--df-space-3) 0;padding:0">
  <form method="get" class="rtoolbar">
    <input type="hidden" name="modul" value="<?=htmlspecialchars($modul)?>"><input type="hidden" name="ref" value="<?=$ref?>">
    <input type="hidden" name="mode" value="<?=htmlspecialchars($bmode)?>"><input type="hidden" name="type" value="<?=htmlspecialchars($btype)?>">
    <div class="rtoolbar-dates">
      <input type="date" name="from" value="<?=$from?>">
      <span style="color:var(--df-ink-500)">—</span>
      <input type="date" name="to" value="<?=$to?>">
      <button class="df-btn df-btn--primary df-btn--sm" type="submit">Uygula</button>
    </div>
    <div class="rtoolbar-actions">
      <?=ds_button('CSV','report.php?modul='.$modul.'&export=csv&from='.$from.'&to='.$to.'&ref='.$ref.'&mode='.urlencode($bmode).'&type='.urlencode($btype),'secondary','df-btn--sm','',true)?>
      <button class="df-btn df-btn--secondary df-btn--sm" type="button" onclick="shareReportPDF(this)">PDF Paylaş</button>
      <button class="df-btn df-btn--secondary df-btn--sm" type="button" onclick="window.print()">Yazdır / PDF</button>
    </div>
  </form>
</section>
<script>window.ACANS_REPORT_NAME='rapor_<?=$modul?>_<?=$from?>';</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="report_share.js"></script>

<?php if(!empty($R['error'])): ?><?=ds_alert('danger',$R['error'])?><?php endif; ?>

<nav class="rview-tabs noprint">
  <a href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>" class="<?=!$detail?'is-active':''?>">Özet</a>
  <a href="report.php?modul=<?=$modul?>&from=<?=$from?>&to=<?=$to?>&ref=<?=$ref?>&detay=1&mode=<?=urlencode($bmode)?>&type=<?=urlencode($btype)?>" class="<?=$detail?'is-active':''?>">Detay</a>
</nav>
<div id="repArea"><?= $isAll ? report_render_all($pdo,$appName,$from,$to,$detail) : ($isCariToplu ? report_render_cari_toplu($pdo,$appName,$from,$to,$bmode,$btype,$detail) : report_render($R,$appName,$from,$to,$detail)) ?></div>
</section>
<?php require_once __DIR__.'/layout_bottom.php'; ?>
