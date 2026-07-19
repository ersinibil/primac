<?php
// ACANS OTS — Günlük İş Raporu (app-view + PDF belgesi)
require_once __DIR__.'/boot.php';
require_login();
if(!is_admin() && !user_can('report')){ echo '<div class="alert">Bu sayfaya erişim yetkiniz yok.</div>'; require __DIR__.'/layout_bottom.php'; exit; }
require_once __DIR__.'/report_lib.php';

$pdo = db();

// Tarih seçici — varsayılan bugün
$tarih = '';
if(!empty($_GET['d']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['d'])){
    $tarih = $_GET['d'];
} else {
    $tarih = date('Y-m-d');
}
$tarihGoster = date('d.m.Y', strtotime($tarih));

$appName = app_config()['app_name'] ?? 'OTS';

// Aktif personel listesi
$pers = [];
try{
    $pers = $pdo->query("SELECT id, name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
}catch(Throwable $e){}

// Her personel için açık iş + bekleyen görev sayısı
$jobStmt  = $pdo->prepare("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=? AND status NOT IN('Tamamlandı','Teslim Edildi','İptal')");
$taskStmt = $pdo->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status NOT IN('Tamamlandı','İptal')");

// KÖK NEDEN DÜZELTMESİ (2026-07-19, canlı bulgu): önceden burada "if($nj || $nt)" filtresi vardı —
// açık iş/görevi olmayan personel tabloya HİÇ eklenmiyordu (6 aktif personelden sadece hareketi
// olan 2'si görünüyordu, sessizce). "Personel Bazlı Durum" genel bir DURUM raporu — işi/görevi
// olmayan personel de 0/0 ile listede görünmeli, aksi halde "personel saklanıyor" izlenimi verir.
// Toplamlar (0 eklemek matematiği değiştirmez) ve filtre mantığı aynı kaldı, sadece görünürlük.
$personelSatirlar = [];
$toplamIs  = 0;
$toplamGov = 0;
foreach($pers as $p){
    $nj = 0; $nt = 0;
    try{ $jobStmt->execute([$p['id']]);  $nj = (int)$jobStmt->fetch()['c'];  }catch(Throwable $e){}
    try{ $taskStmt->execute([$p['id']]); $nt = (int)$taskStmt->fetch()['c']; }catch(Throwable $e){}
    $personelSatirlar[] = ['name'=>$p['name'], 'jobs'=>$nj, 'tasks'=>$nt];
    $toplamIs  += $nj;
    $toplamGov += $nt;
}

// Bugünün işleri (seçili rapor tarihinde termini olan, tamamlanmamış)
$bugunList = [];
try{
    $bs = $pdo->prepare("SELECT j.id, j.job_no, j.title, p.name AS sorumlu
        FROM jobs j LEFT JOIN personnel p ON p.id = j.responsible_personnel_id
        WHERE j.due_date=? AND j.status NOT IN('Tamamlandı','Teslim Edildi','İptal')
        ORDER BY j.id DESC LIMIT 50");
    $bs->execute([$tarih]);
    $bugunList = $bs->fetchAll();
}catch(Throwable $e){}

// Geciken işler (termin geçmiş, tamamlanmamış)
$gecikenList = [];
$toplamGeciken = 0;
try{
    $gs = $pdo->query("SELECT j.id, j.job_no, j.title, j.due_date, p.name AS sorumlu
        FROM jobs j
        LEFT JOIN personnel p ON p.id = j.responsible_personnel_id
        WHERE j.due_date < CURDATE()
          AND j.status NOT IN('Tamamlandı','Teslim Edildi','İptal')
        ORDER BY j.due_date ASC
        LIMIT 50");
    $gecikenList  = $gs->fetchAll();
    $toplamGeciken = count($gecikenList);
}catch(Throwable $e){}

// Bekleyen görevler (detay liste)
$gorevList = [];
try{
    $tls = $pdo->query("SELECT t.title, t.due_date, p.name AS sorumlu
        FROM tasks t LEFT JOIN personnel p ON p.id = t.personnel_id
        WHERE t.status NOT IN('Tamamlandı','İptal')
        ORDER BY (t.due_date IS NULL), t.due_date ASC LIMIT 50");
    $gorevList = $tls->fetchAll();
}catch(Throwable $e){}

// Bekleyen teklifler
$toplamTeklif = 0;
$teklifList = [];
try{
    $ts = $pdo->query("SELECT COUNT(*) c FROM quotes WHERE status NOT IN('Kabul','Red','İptal')");
    if($ts) $toplamTeklif = (int)$ts->fetch()['c'];
    $qls = $pdo->query("SELECT id, quote_no, customer_name, total, status FROM quotes WHERE status NOT IN('Kabul','Red','İptal') ORDER BY id DESC LIMIT 50");
    $teklifList = $qls->fetchAll();
}catch(Throwable $e){}

require __DIR__.'/layout_top.php';
?>
<style>
@media print{.noprint{display:none!important}}
/* GÜNLÜK RAPOR AİLESİ — TUR SON (2026-07-19): bu ekran önceden hem uygulama-içi görünüm hem PDF
   çıktısı için AYNI HTML'i (koyu lacivert header, .stat-card gradientsiz ama "eski rapor" dili,
   emoji başlıklar) kullanıyordu — rapor ailesinin geri kalanı (report.php/report_lib.php) DS'e
   geçtikten sonra bu sayfa geride kalmıştı. Artık report.php ile AYNI mimari: app-view (aşağıda,
   ds_page_header + report_kpi_grid + df-table) ekranda her zaman görünür; PDF belgesi
   (report_pdf_doc_css()/report_pdf_doc_header(), report_lib.php — report.php'nin Yekün PDF'iyle
   BİREBİR aynı kurumsal belge dili) #repArea içinde off-screen durur, sadece window.print()/
   "PDF İndir" onu görünür kılar. VERİ/sorgu mantığı (yukarıdaki PHP) hariç hiçbir şey ekran
   görüntüsü değil — html2canvas de zaten sadece #repArea'yı hedefliyor (report_share.js). */
.repdoc-offscreen{position:absolute;left:-99999px;top:0}
@media print{.repdoc-offscreen{position:static!important;left:auto!important}}
@media print{
  body *{visibility:hidden!important}
  #repArea,#repArea *{visibility:visible!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  #repArea{position:static!important;left:auto!important;width:100%!important}
  @page{size:A4;margin:14mm 12mm}
}
.gr-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.gr-badge-red{background:var(--df-danger-soft,#fee2e2);color:var(--df-danger-ink,#b91c1c);border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700}
</style>

<section style="max-width:1000px" class="noprint">
<?php
ob_start(); ?>
<form method="get" class="gr-toolbar" style="margin:0">
  <input type="date" name="d" value="<?=h($tarih)?>" style="margin:0;width:auto">
  <button type="submit" class="df-btn df-btn--secondary df-btn--sm">Göster</button>
</form>
<button onclick="shareReportPDF(this)" class="df-btn df-btn--primary df-btn--sm" type="button">PDF İndir / Paylaş</button>
<button onclick="window.print()" class="df-btn df-btn--secondary df-btn--sm" type="button">Yazdır / PDF</button>
<?php
$__grActions = ob_get_clean();
ds_page_header('Günlük İş Raporu', ds_icon('calendar',24), $tarihGoster.' — '.h(date('l', strtotime($tarih))), $__grActions, false, true);
?>
</section>

<section style="max-width:1000px">
<?=report_kpi_grid([
    ['','Açık İş',$toplamIs,'#2563eb'],
    ['','Geciken İş',$toplamGeciken,'#ef4444'],
    ['','Bekleyen Görev',$toplamGov,'#f59e0b'],
    ['','Bekleyen Teklif',$toplamTeklif,'#22c55e'],
])?>

<section class="df-card noprint" style="margin:var(--df-space-3) 0">
<h2 class="df-section-title" style="font-size:var(--df-type-subtitle-size);font-weight:var(--df-type-subtitle-weight);margin:0 0 var(--df-space-3)">Personel Bazlı Durum</h2>
<?php if($personelSatirlar): ?>
<div class="df-table-wrap"><table class="df-table">
  <thead><tr><th>Personel</th><th style="text-align:right">Açık İş</th><th style="text-align:right">Bekleyen Görev</th></tr></thead>
  <tbody>
  <?php foreach($personelSatirlar as $s): ?>
  <tr>
    <td><?=h($s['name'])?></td>
    <td style="text-align:right"><?=h($s['jobs'])?></td>
    <td style="text-align:right"><?=h($s['tasks'])?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php else: ?>
<p class="df-muted" style="text-align:center;padding:16px 0">Aktif personel bulunamadı.</p>
<?php endif; ?>
</section>

<?php if($bugunList): ?>
<section class="df-card noprint" style="margin:var(--df-space-3) 0">
<h2 class="df-section-title" style="font-size:var(--df-type-subtitle-size);font-weight:var(--df-type-subtitle-weight);margin:0 0 var(--df-space-3)">Bugünün İşleri</h2>
<div class="df-table-wrap"><table class="df-table">
  <thead><tr><th>İş No</th><th>Başlık</th><th>Sorumlu</th><th></th></tr></thead>
  <tbody>
  <?php foreach($bugunList as $g): ?>
  <tr><td><?=h($g['job_no']?:'—')?></td><td><?=h($g['title'])?></td><td><?=h($g['sorumlu']?:'—')?></td>
  <td><a class="df-btn df-btn--secondary df-btn--sm" href="job_view.php?id=<?=(int)$g['id']?>">Aç</a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
</section>
<?php endif; ?>

<?php if($gecikenList): ?>
<section class="df-card noprint" style="margin:var(--df-space-3) 0;border-color:var(--df-danger)">
<h2 class="df-section-title" style="font-size:var(--df-type-subtitle-size);font-weight:var(--df-type-subtitle-weight);margin:0 0 var(--df-space-3);color:var(--df-danger-ink)">Geciken İşler (Termini Geçmiş)</h2>
<div class="df-table-wrap"><table class="df-table">
  <thead><tr><th>İş No</th><th>Başlık</th><th>Sorumlu</th><th style="text-align:right">Termin</th><th></th></tr></thead>
  <tbody>
  <?php foreach($gecikenList as $g): ?>
  <tr><td><span class="gr-badge-red"><?=h($g['job_no']?:'—')?></span></td><td><?=h($g['title'])?></td><td><?=h($g['sorumlu']?:'—')?></td>
  <td style="text-align:right;color:var(--df-danger-ink);font-weight:600"><?=h($g['due_date'])?></td>
  <td><a class="df-btn df-btn--secondary df-btn--sm" href="job_view.php?id=<?=(int)$g['id']?>">Aç</a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
</section>
<?php endif; ?>

<?php if($gorevList): ?>
<section class="df-card noprint" style="margin:var(--df-space-3) 0">
<h2 class="df-section-title" style="font-size:var(--df-type-subtitle-size);font-weight:var(--df-type-subtitle-weight);margin:0 0 var(--df-space-3)">Bekleyen Görevler</h2>
<div class="df-table-wrap"><table class="df-table">
  <thead><tr><th>Görev</th><th>Sorumlu</th><th style="text-align:right">Termin</th></tr></thead>
  <tbody>
  <?php foreach($gorevList as $g): ?>
  <tr><td><?=h($g['title'])?></td><td><?=h($g['sorumlu']?:'—')?></td><td style="text-align:right"><?=h($g['due_date']?:'—')?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
</section>
<?php endif; ?>

<?php if($teklifList): ?>
<section class="df-card noprint" style="margin:var(--df-space-3) 0">
<h2 class="df-section-title" style="font-size:var(--df-type-subtitle-size);font-weight:var(--df-type-subtitle-weight);margin:0 0 var(--df-space-3)">Bekleyen Teklifler</h2>
<div class="df-table-wrap"><table class="df-table">
  <thead><tr><th>Teklif No</th><th>Müşteri</th><th style="text-align:right">Tutar</th><th>Durum</th><th></th></tr></thead>
  <tbody>
  <?php foreach($teklifList as $t): ?>
  <tr><td><?=h($t['quote_no'])?></td><td><?=h($t['customer_name']?:'—')?></td><td style="text-align:right"><?=money($t['total'])?></td><td><?=h($t['status'])?></td>
  <td><a class="df-btn df-btn--secondary df-btn--sm" href="teklif.php?id=<?=(int)$t['id']?>">Aç</a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
</section>
<?php endif; ?>

<!-- PDF BELGESİ — ekranda görünmez (off-screen), window.print() + "PDF İndir/Paylaş" (html2canvas,
     report_share.js) İKİSİ DE bu düğümü hedefler. Yukarıdaki app-view HİÇ yazdırılmaz/screenshot
     alınmaz. report.php'nin Yekün PDF'iyle AYNI kurumsal belge component'i (report_pdf_doc_css/
     report_pdf_doc_header, report_lib.php) — iki ayrı PDF tasarımı yok. -->
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
</section>

<script>
window.ACANS_REPORT_NAME = '<?=report_pdf_filename($appName,'Gunluk_Is_Raporu',$tarih,$tarih)?>';
window.ACANS_PDF_BG  = '#ffffff';
window.ACANS_PDF_FG  = '#0f172a';
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="report_share.js"></script>

<?php require __DIR__.'/layout_bottom.php'; ?>
