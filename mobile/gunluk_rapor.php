<?php
// OTS Mobil — Günlük İş Raporu — web'deki gunluk_rapor.php ile AYNI veri/mantık + AYNI PDF belgesi
require_once 'common.php';
if(!$isAdmin && !user_can('report')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../report_lib.php';

$pdo = db();

$tarih = '';
if(!empty($_GET['d']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['d'])){
    $tarih = $_GET['d'];
} else {
    $tarih = date('Y-m-d');
}
$tarihGoster = date('d.m.Y', strtotime($tarih));

$appName = app_config()['app_name'] ?? 'OTS';

$pers = [];
try{ $pers = $pdo->query("SELECT id, name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll(); }catch(Throwable $e){}

$jobStmt  = $pdo->prepare("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=? AND status NOT IN('Tamamlandı','Teslim Edildi','İptal')");
$taskStmt = $pdo->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status NOT IN('Tamamlandı','İptal')");

// KÖK NEDEN DÜZELTMESİ (2026-07-19) — web gunluk_rapor.php ile AYNI: "if($nj||$nt)" filtresi
// açık iş/görevi olmayan personeli sessizce saklıyordu. Artık tüm aktif personel 0/0 dahil listede.
$personelSatirlar = []; $toplamIs = 0; $toplamGov = 0;
foreach($pers as $p){
    $nj = 0; $nt = 0;
    try{ $jobStmt->execute([$p['id']]);  $nj = (int)$jobStmt->fetch()['c'];  }catch(Throwable $e){}
    try{ $taskStmt->execute([$p['id']]); $nt = (int)$taskStmt->fetch()['c']; }catch(Throwable $e){}
    $personelSatirlar[] = ['name'=>$p['name'], 'jobs'=>$nj, 'tasks'=>$nt];
    $toplamIs += $nj; $toplamGov += $nt;
}

$bugunList = [];
try{
    $bs = $pdo->prepare("SELECT j.id, j.job_no, j.title, p.name AS sorumlu
        FROM jobs j LEFT JOIN personnel p ON p.id = j.responsible_personnel_id
        WHERE j.due_date=? AND j.status NOT IN('Tamamlandı','Teslim Edildi','İptal')
        ORDER BY j.id DESC LIMIT 50");
    $bs->execute([$tarih]);
    $bugunList = $bs->fetchAll();
}catch(Throwable $e){}

$gecikenList = []; $toplamGeciken = 0;
try{
    $gs = $pdo->query("SELECT j.id, j.job_no, j.title, j.due_date, p.name AS sorumlu
        FROM jobs j LEFT JOIN personnel p ON p.id = j.responsible_personnel_id
        WHERE j.due_date < CURDATE() AND j.status NOT IN('Tamamlandı','Teslim Edildi','İptal')
        ORDER BY j.due_date ASC LIMIT 50");
    $gecikenList  = $gs->fetchAll();
    $toplamGeciken = count($gecikenList);
}catch(Throwable $e){}

$gorevList = [];
try{
    $tls = $pdo->query("SELECT t.title, t.due_date, p.name AS sorumlu
        FROM tasks t LEFT JOIN personnel p ON p.id = t.personnel_id
        WHERE t.status NOT IN('Tamamlandı','İptal')
        ORDER BY (t.due_date IS NULL), t.due_date ASC LIMIT 50");
    $gorevList = $tls->fetchAll();
}catch(Throwable $e){}

$toplamTeklif = 0; $teklifList = [];
try{
    $ts = $pdo->query("SELECT COUNT(*) c FROM quotes WHERE status NOT IN('Kabul','Red','İptal')");
    if($ts) $toplamTeklif = (int)$ts->fetch()['c'];
    $qls = $pdo->query("SELECT id, quote_no, customer_name, total, status FROM quotes WHERE status NOT IN('Kabul','Red','İptal') ORDER BY id DESC LIMIT 50");
    $teklifList = $qls->fetchAll();
}catch(Throwable $e){}

topx('Günlük İş Raporu');
?>
<style>
/* GÜNLÜK RAPOR AİLESİ — TUR SON (2026-07-19): web gunluk_rapor.php ile AYNI mimari — app-view
   (aşağıda, df-panel + report_kpi_grid, DS) ekranda her zaman görünür; PDF belgesi #repArea
   içinde off-screen durur (aynı report_pdf_doc_css()/report_pdf_doc_header(), report_lib.php —
   web'deki Yekün ve Günlük Rapor PDF'leriyle BİREBİR aynı kurumsal belge dili). */
.repdoc-offscreen{position:absolute;left:-99999px;top:0}
@media print{.repdoc-offscreen{position:static!important;left:auto!important}}
@media print{
  body *{visibility:hidden!important}
  #repArea,#repArea *{visibility:visible!important}
  #repArea{position:static!important;left:auto!important;width:100%!important}
  .noprint{display:none!important}
  @page{size:A4;margin:14mm 12mm}
}
.gr-badge-red{background:var(--df-danger-soft,rgba(248,113,113,.18));color:var(--df-danger-ink,#f87171);border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700}
</style>

<div class="noprint" style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
    <form method="get" style="display:flex;gap:8px;flex:1">
        <input type="date" name="d" value="<?=h($tarih)?>" style="flex:1;margin:0">
        <button type="submit" class="df-btn df-btn--secondary"><?=ds_icon('search',15)?> Göster</button>
    </form>
</div>
<div class="noprint" style="display:flex;gap:8px;margin-bottom:12px">
  <button class="df-btn df-btn--primary" onclick="shareReportPDF(this)" style="flex:1;justify-content:center">PDF İndir / Paylaş</button>
  <button class="df-btn df-btn--secondary" onclick="window.print()" style="flex:1;justify-content:center">Yazdır / PDF</button>
</div>

<?=report_kpi_grid([
    ['','Açık İş',$toplamIs,'#2563eb'],
    ['','Geciken İş',$toplamGeciken,'#ef4444'],
    ['','Bekleyen Görev',$toplamGov,'#f59e0b'],
    ['','Bekleyen Teklif',$toplamTeklif,'#22c55e'],
])?>

<div class="df-panel noprint" style="margin-bottom:10px">
<b style="display:block;margin-bottom:8px">Personel Bazlı Durum</b>
<?php if($personelSatirlar): ?>
<?php foreach($personelSatirlar as $s): ?>
<div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--df-hairline,rgba(255,255,255,.08));font-size:13px">
  <span><?=h($s['name'])?></span>
  <span class="muted">İş: <b style="color:var(--df-ink-900,#fff)"><?=h($s['jobs'])?></b> · Görev: <b style="color:var(--df-ink-900,#fff)"><?=h($s['tasks'])?></b></span>
</div>
<?php endforeach; ?>
<?php else: ?>
<p class="muted" style="text-align:center;padding:10px 0">Aktif personel bulunamadı.</p>
<?php endif; ?>
</div>

<?php if($gecikenList): ?>
<div class="df-panel noprint" style="margin-bottom:10px">
<b style="display:block;margin-bottom:8px;color:var(--df-danger-ink,#f87171)">Geciken İşler</b>
<?php foreach($gecikenList as $g): ?>
<div style="padding:7px 0;border-bottom:1px solid var(--df-hairline,rgba(255,255,255,.08));font-size:13px">
  <span class="gr-badge-red"><?=h($g['job_no']?:'—')?></span> <?=h($g['title'])?><br>
  <span class="muted" style="font-size:12px"><?=h($g['sorumlu']?:'—')?> · Termin: <?=h($g['due_date'])?></span>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($bugunList): ?>
<div class="df-panel noprint" style="margin-bottom:10px">
<b style="display:block;margin-bottom:8px">Bugünün İşleri</b>
<?php foreach($bugunList as $g): ?>
<div style="padding:7px 0;border-bottom:1px solid var(--df-hairline,rgba(255,255,255,.08));font-size:13px"><?=h($g['job_no']?:'—')?> — <?=h($g['title'])?><br><span class="muted" style="font-size:12px"><?=h($g['sorumlu']?:'—')?></span></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($gorevList): ?>
<div class="df-panel noprint" style="margin-bottom:10px">
<b style="display:block;margin-bottom:8px">Bekleyen Görevler</b>
<?php foreach($gorevList as $g): ?>
<div style="padding:7px 0;border-bottom:1px solid var(--df-hairline,rgba(255,255,255,.08));font-size:13px"><?=h($g['title'])?><br><span class="muted" style="font-size:12px"><?=h($g['sorumlu']?:'—')?> · Termin: <?=h($g['due_date']?:'—')?></span></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($teklifList): ?>
<div class="df-panel noprint" style="margin-bottom:10px">
<b style="display:block;margin-bottom:8px">Bekleyen Teklifler</b>
<?php foreach($teklifList as $t): ?>
<div style="padding:7px 0;border-bottom:1px solid var(--df-hairline,rgba(255,255,255,.08));font-size:13px"><?=h($t['quote_no'])?> — <?=h($t['customer_name']?:'—')?><br><span class="muted" style="font-size:12px"><?=mm($t['total'])?> · <?=h($t['status'])?></span></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- PDF BELGESİ — ekranda görünmez (off-screen), window.print() + "PDF İndir/Paylaş" İKİSİ DE bu
     düğümü hedefler (report_share.js DEĞİŞMEDİ). Web ile BİREBİR aynı belge markup'ı. -->
<div class="repdoc-offscreen"><div id="repArea">
<style><?=report_pdf_doc_css()?></style>
<div class="pdf-doc">
  <?=report_pdf_doc_header($appName, 'Günlük İş Raporu', [['Rapor Tarihi',$tarihGoster.' ('.date('l', strtotime($tarih)).')'],['Oluşturma',date('d.m.Y H:i')]])?>

  <div class="pdf-doc-h">Özet</div>
  <table class="pdf-doc-tbl"><tbody>
    <tr><td style="font-weight:600;color:#475467">Açık İş</td><td style="text-align:right;font-weight:800"><?=h($toplamIs)?></td></tr>
    <tr><td style="font-weight:600;color:#475467">Geciken İş</td><td style="text-align:right;font-weight:800<?=$toplamGeciken?';color:#b91c1c':''?>"><?=h($toplamGeciken)?></td></tr>
    <tr><td style="font-weight:600;color:#475467">Bekleyen Görev</td><td style="text-align:right;font-weight:800"><?=h($toplamGov)?></td></tr>
    <tr><td style="font-weight:600;color:#475467">Bekleyen Teklif</td><td style="text-align:right;font-weight:800"><?=h($toplamTeklif)?></td></tr>
  </tbody></table>

  <div class="pdf-doc-h">Personel Bazlı Durum</div>
  <?php if($personelSatirlar): ?>
  <table class="pdf-doc-tbl">
    <thead><tr><th>Personel</th><th style="text-align:right">Açık İş</th><th style="text-align:right">Bekleyen Görev</th></tr></thead>
    <tbody><?php foreach($personelSatirlar as $s): ?>
    <tr><td><?=h($s['name'])?></td><td style="text-align:right"><?=h($s['jobs'])?></td><td style="text-align:right"><?=h($s['tasks'])?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php else: ?><div class="pdf-doc-empty">Aktif personel bulunamadı.</div><?php endif; ?>

  <?php if($bugunList): ?>
  <div class="pdf-doc-h">Bugünün İşleri</div>
  <table class="pdf-doc-tbl">
    <thead><tr><th>İş No</th><th>Başlık</th><th>Sorumlu</th></tr></thead>
    <tbody><?php foreach($bugunList as $g): ?>
    <tr><td><?=h($g['job_no']?:'—')?></td><td><?=h($g['title'])?></td><td><?=h($g['sorumlu']?:'—')?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>

  <?php if($gecikenList): ?>
  <div class="pdf-doc-h">Geciken İşler (Termini Geçmiş)</div>
  <table class="pdf-doc-tbl">
    <thead><tr><th>İş No</th><th>Başlık</th><th>Sorumlu</th><th style="text-align:right">Termin</th></tr></thead>
    <tbody><?php foreach($gecikenList as $g): ?>
    <tr><td><?=h($g['job_no']?:'—')?></td><td><?=h($g['title'])?></td><td><?=h($g['sorumlu']?:'—')?></td><td style="text-align:right" class="pdf-doc-neg"><?=h($g['due_date'])?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>

  <?php if($gorevList): ?>
  <div class="pdf-doc-h">Bekleyen Görevler</div>
  <table class="pdf-doc-tbl">
    <thead><tr><th>Görev</th><th>Sorumlu</th><th style="text-align:right">Termin</th></tr></thead>
    <tbody><?php foreach($gorevList as $g): ?>
    <tr><td><?=h($g['title'])?></td><td><?=h($g['sorumlu']?:'—')?></td><td style="text-align:right"><?=h($g['due_date']?:'—')?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>

  <?php if($teklifList): ?>
  <div class="pdf-doc-h">Bekleyen Teklifler</div>
  <table class="pdf-doc-tbl">
    <thead><tr><th>Teklif No</th><th>Müşteri</th><th style="text-align:right">Tutar</th><th>Durum</th></tr></thead>
    <tbody><?php foreach($teklifList as $t): ?>
    <tr><td><?=h($t['quote_no'])?></td><td><?=h($t['customer_name']?:'—')?></td><td style="text-align:right"><?=money($t['total'])?></td><td><?=h($t['status'])?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>

  <div class="pdf-doc-foot">
    <span>Oluşturma: <?=date('d.m.Y H:i')?></span>
    <span><?=h($appName)?> OTS — Online Takip ve Yönetim Sistemi</span>
  </div>
</div>
</div></div>

<script>
window.ACANS_REPORT_NAME = '<?=report_pdf_filename($appName,'Gunluk_Is_Raporu',$tarih,$tarih)?>';
window.ACANS_PDF_BG  = '#ffffff';
window.ACANS_PDF_FG  = '#0f172a';
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="../report_share.js"></script>
<?php botx(); ?>
