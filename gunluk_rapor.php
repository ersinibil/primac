<?php
// ACANS OTS — Günlük İş Raporu (görsel + PDF)
require_once __DIR__.'/boot.php';
require_login();
if(!is_admin() && !user_can('report')){ echo '<div class="alert">Bu sayfaya erişim yetkiniz yok.</div>'; require __DIR__.'/layout_bottom.php'; exit; }

$pdo = db();

// Tarih seçici — varsayılan bugün
$tarih = '';
if(!empty($_GET['d']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['d'])){
    $tarih = $_GET['d'];
} else {
    $tarih = date('Y-m-d');
}
$tarihGoster = date('d.m.Y', strtotime($tarih));

// Firma bilgisi
$appName = app_config()['app_name'] ?? 'OTS';
$logo    = brand_logo();

// Aktif personel listesi
$pers = [];
try{
    $pers = $pdo->query("SELECT id, name FROM personnel WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
}catch(Throwable $e){}

// Her personel için bekleyen iş + görev sayısı
$jobStmt  = $pdo->prepare("SELECT COUNT(*) c FROM jobs WHERE responsible_personnel_id=? AND status NOT IN('Tamamlandı','Teslim Edildi','İptal')");
$taskStmt = $pdo->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status NOT IN('Tamamlandı','İptal')");

$personelSatirlar = [];
$toplamIs  = 0;
$toplamGov = 0;
foreach($pers as $p){
    $nj = 0; $nt = 0;
    try{ $jobStmt->execute([$p['id']]);  $nj = (int)$jobStmt->fetch()['c'];  }catch(Throwable $e){}
    try{ $taskStmt->execute([$p['id']]); $nt = (int)$taskStmt->fetch()['c']; }catch(Throwable $e){}
    if($nj || $nt){
        $personelSatirlar[] = ['name'=>$p['name'], 'jobs'=>$nj, 'tasks'=>$nt];
        $toplamIs  += $nj;
        $toplamGov += $nt;
    }
}

// Geciken işler (termin geçmiş, tamamlanmamış)
$gecikenList = [];
$toplamGeciken = 0;
try{
    $gs = $pdo->query("SELECT j.job_no, j.title, j.due_date, p.name AS sorumlu
        FROM jobs j
        LEFT JOIN personnel p ON p.id = j.responsible_personnel_id
        WHERE j.due_date < CURDATE()
          AND j.status NOT IN('Tamamlandı','Teslim Edildi','İptal')
        ORDER BY j.due_date ASC
        LIMIT 50");
    $gecikenList  = $gs->fetchAll();
    $toplamGeciken = count($gecikenList);
}catch(Throwable $e){}

// Bekleyen teklifler
$toplamTeklif = 0;
try{
    $ts = $pdo->query("SELECT COUNT(*) c FROM quotes WHERE status NOT IN('Kabul','Red','İptal') LIMIT 1");
    if($ts) $toplamTeklif = (int)$ts->fetch()['c'];
}catch(Throwable $e){}

require __DIR__.'/layout_top.php';
?>
<style>
@media print{
    body *{visibility:hidden!important}
    #repArea,#repArea *{visibility:visible!important}
    #repArea{position:absolute;left:0;top:0;width:100%}
    .noprint{display:none!important}
    @page{size:A4;margin:0}
}
.stat-card{display:inline-block;min-width:140px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 20px;text-align:center;margin:0 6px 10px}
.stat-card .num{font-size:36px;font-weight:900;line-height:1}
.stat-card .lbl{font-size:12px;color:#6b7280;margin-top:4px}
.stat-red   .num{color:#dc2626}
.stat-blue  .num{color:#2563eb}
.stat-orange .num{color:#d97706}
.stat-green .num{color:#16a34a}
.rep-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:12px}
.rep-table th{background:#f1f3f5;padding:9px 10px;text-align:left;border-bottom:2px solid #e5e7eb}
.rep-table td{padding:8px 10px;border-bottom:1px solid #f1f3f5;vertical-align:middle}
.rep-table tr:last-child td{border-bottom:none}
.rep-table tr:nth-child(even) td{background:#fafafa}
.badge-red{background:#fee2e2;color:#b91c1c;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700}
</style>

<div class="noprint">
<?php
ob_start();
?>
<form method="get" style="display:inline-flex;gap:8px;align-items:center">
<input type="date" name="d" value="<?=h($tarih)?>" style="max-width:160px">
<button type="submit" class="df-btn df-btn--secondary">Göster</button>
</form>
<button onclick="shareReportPDF(this)" class="df-btn df-btn--primary" style="background:var(--df-success)">📄 PDF İndir / Paylaş</button>
<?php
$__grActions = ob_get_clean();
ds_page_header('📅 Günlük İş Raporu', ds_icon('calendar',24), '', $__grActions, false, true);
?>
</div>

<div id="repArea" style="max-width:860px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
    <!-- Başlık -->
    <div style="background:#1b2431;color:#fff;padding:20px 28px;display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;align-items:center;gap:14px">
            <img src="<?=h($logo)?>" alt="logo" style="height:44px;object-fit:contain;background:#fff;border-radius:6px;padding:4px">
            <div>
                <div style="font-size:18px;font-weight:800"><?=h($appName)?></div>
                <div style="font-size:11px;opacity:.7;letter-spacing:.05em">GÜNLÜK İŞ RAPORU</div>
            </div>
        </div>
        <div style="text-align:right">
            <div style="font-size:26px;font-weight:900"><?=h($tarihGoster)?></div>
            <div style="font-size:11px;opacity:.6"><?=h(date('l', strtotime($tarih)))?></div>
        </div>
    </div>

    <!-- Özet kartlar -->
    <div style="padding:22px 24px 4px;text-align:center">
        <div class="stat-card stat-blue">
            <div class="num"><?=h($toplamIs)?></div>
            <div class="lbl">Açık İş</div>
        </div>
        <div class="stat-card stat-red">
            <div class="num"><?=h($toplamGeciken)?></div>
            <div class="lbl">Geciken İş</div>
        </div>
        <div class="stat-card stat-orange">
            <div class="num"><?=h($toplamGov)?></div>
            <div class="lbl">Bekleyen Görev</div>
        </div>
        <div class="stat-card stat-green">
            <div class="num"><?=h($toplamTeklif)?></div>
            <div class="lbl">Bekleyen Teklif</div>
        </div>
    </div>

    <!-- Personel tablosu -->
    <div style="padding:16px 24px">
        <div style="font-weight:700;font-size:14px;color:#1b2431;margin-bottom:4px">👷 Personel Bazlı Durum</div>
        <?php if($personelSatirlar): ?>
        <table class="rep-table">
            <thead>
                <tr>
                    <th>Personel</th>
                    <th style="text-align:right">Açık İş</th>
                    <th style="text-align:right">Bekleyen Görev</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($personelSatirlar as $s): ?>
                <tr>
                    <td><?=h($s['name'])?></td>
                    <td style="text-align:right;font-weight:700;color:<?=$s['jobs']>0?'#2563eb':'#6b7280'?>"><?=h($s['jobs'])?></td>
                    <td style="text-align:right;font-weight:700;color:<?=$s['tasks']>0?'#d97706':'#6b7280'?>"><?=h($s['tasks'])?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align:center;color:#6b7280;padding:20px">Açık iş/görev yok 🎉</div>
        <?php endif; ?>
    </div>

    <!-- Geciken işler -->
    <?php if($gecikenList): ?>
    <div style="padding:0 24px 22px">
        <div style="font-weight:700;font-size:14px;color:#b91c1c;margin-bottom:4px">⚠️ Geciken İşler (Termini Geçmiş)</div>
        <table class="rep-table">
            <thead>
                <tr>
                    <th>İş No</th>
                    <th>Başlık</th>
                    <th>Sorumlu</th>
                    <th style="text-align:right">Termin</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($gecikenList as $g): ?>
                <tr>
                    <td><span class="badge-red"><?=h($g['job_no'] ?: '—')?></span></td>
                    <td><?=h($g['title'])?></td>
                    <td><?=h($g['sorumlu'] ?: '—')?></td>
                    <td style="text-align:right;color:#b91c1c;font-weight:600"><?=h($g['due_date'])?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Alt bilgi -->
    <div style="border-top:2px solid #e5e7eb;background:#f8f9fa;color:#6b7280;padding:10px 24px;text-align:center;font-size:11px">
        <?=h($appName)?> &nbsp;·&nbsp; Rapor tarihi: <?=h($tarihGoster)?> &nbsp;·&nbsp; Oluşturulma: <?=date('H:i')?>
    </div>
</div>

<script>
window.ACANS_REPORT_NAME = 'gunluk_rapor_<?=h($tarih)?>';
window.ACANS_PDF_BG  = '#ffffff';
window.ACANS_PDF_FG  = '#111111';
window.ACANS_PDF_FIT = true;
window.ACANS_PDF_PAD = 0;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="report_share.js"></script>

<?php require __DIR__.'/layout_bottom.php';
