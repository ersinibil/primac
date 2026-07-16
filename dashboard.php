<?php
require_once __DIR__.'/layout_top.php';
if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/notes_lib.php';
require_once __DIR__.'/user_prefs_lib.php';
// PX-001 (2026-07-16) — Home Screen v1.1 compact modu task_my_stats()/task_my_personnel_id()
// kullanıyor; dashboard.php bu dosyayı daha önce hiç require etmiyordu (mobile/index.php zaten
// ediyordu, mytasks.php'den beri).
if(is_file(__DIR__.'/tasks_lib.php')) require_once __DIR__.'/tasks_lib.php';

$today=date('Y-m-d');
$pdo=db();
$__myNotes=personal_notes_list($pdo,(int)($_SESSION['user']['id']??0),'open');

// PX-001 (2026-07-16) — NAV-001B'nin nav_layout_mode pilot mekanizması: legacy dal ORİJİNAL
// Komuta Merkezi'ni birebir korur, compact dal yeni Home Screen v1.1'i (Hero+Sırada+Devam Et)
// gösterir. dashboard.php bu güne kadar hiçbir compact/legacy ayrımı yapmıyordu (sadece
// mobile/index.php'de vardı) — web tarafı için bu ayrım İLK KEZ burada ekleniyor.
$__navSavedMode = function_exists('user_pref_get') ? user_pref_get($pdo, (int)($_SESSION['user']['id']??0), 'nav_layout_mode', null) : null;
$__navMode = function_exists('nav_effective_mode') ? nav_effective_mode($__navSavedMode, is_admin(), nav_is_pilot_user((int)($_SESSION['user']['id']??0))) : 'legacy';

// Mevcut ay
$monthStart=date('Y-m-01');
$monthEnd=date('Y-m-t');

// Geçen ay
$prevMonthEnd=date('Y-m-01', strtotime('-1 day'));
$prevMonthStart=date('Y-m-01', strtotime($prevMonthEnd));

// KPI Karşılaştırması: Bu ay vs Geçen ay
function getKpiMetrics($pdo, $from, $to) {
    $metrics = [];

    try {
        // Tahsilat — SADECE gerçek kasa/banka hareketleri (2026-07-10 Finans Çekirdek düzeltmesi:
        // satış artık her zaman "Bekliyor" ve account_id=NULL ile oluşuyor, bu yüzden burada
        // sayılmaz — aksi halde bekleyen bir satış "tahsil edilmiş gelir" gibi görünürdü).
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) total FROM finance_movements WHERE direction='in' AND account_id IS NOT NULL AND DATE(movement_date) BETWEEN ? AND ?");
        $stmt->execute([$from, $to]);
        $metrics['revenue'] = (float)($stmt->fetch()['total'] ?? 0);
    } catch(Throwable $e) { $metrics['revenue'] = 0; }

    try {
        // Ödeme/Gider — hesaplar arası transfer gerçek bir gider değildir, hariç tutulur (2026-07-03
        // modül-zinciri denetiminde bulundu: kasadan bankaya transfer "gider" toplamını şişiriyordu).
        // 2026-07-10: aynı gerekçeyle SADECE gerçek kasa/banka hareketleri sayılır (alış artık
        // "Bekliyor" + account_id=NULL ile oluşuyor).
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) total FROM finance_movements WHERE direction='out' AND COALESCE(movement_type,'')<>'transfer' AND account_id IS NOT NULL AND DATE(movement_date) BETWEEN ? AND ?");
        $stmt->execute([$from, $to]);
        $metrics['expense'] = (float)($stmt->fetch()['total'] ?? 0);
    } catch(Throwable $e) { $metrics['expense'] = 0; }

    try {
        // Yeni açılan iş
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM jobs WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$from, $to]);
        $metrics['jobs_created'] = (int)($stmt->fetch()['c'] ?? 0);
    } catch(Throwable $e) { $metrics['jobs_created'] = 0; }

    try {
        // Tamamlanan iş
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM jobs WHERE status IN ('Tamamlandı','Teslim Edildi') AND DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$from, $to]);
        $metrics['jobs_completed'] = (int)($stmt->fetch()['c'] ?? 0);
    } catch(Throwable $e) { $metrics['jobs_completed'] = 0; }

    return $metrics;
}

function calculateChangePercent($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

function changeIndicator($percent) {
    if ($percent > 0) {
        $color = '#22c55e';
        $icon = '▲';
    } elseif ($percent < 0) {
        $color = '#ef4444';
        $icon = '▼';
    } else {
        $color = '#94a3b8';
        $icon = '→';
    }
    return '<span style="color:'.$color.';font-weight:700">'.$icon.' '.abs($percent).'%</span>';
}

$currentMetrics = getKpiMetrics($pdo, $monthStart, $monthEnd);
$prevMetrics = getKpiMetrics($pdo, $prevMonthStart, $prevMonthEnd);

// Trend: son 6 ay aylık tahsilat/ödeme
$trends = [];
try {
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime($date));

        // 2026-07-10: sadece gerçek kasa/banka hareketleri (account_id IS NOT NULL) — yukarıdaki
        // getKpiMetrics() ile aynı gerekçe.
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) rev, COALESCE(SUM(CASE WHEN direction='out' AND COALESCE(movement_type,'')<>'transfer' THEN amount END),0) exp FROM finance_movements WHERE account_id IS NOT NULL AND DATE(movement_date) BETWEEN ? AND ?");
        $stmt->execute([$date, $monthEnd]);
        $row = $stmt->fetch();

        $trends[date('M Y', strtotime($date))] = [
            'revenue' => (float)($row['rev'] ?? 0),
            'expense' => (float)($row['exp'] ?? 0)
        ];
    }
} catch(Throwable $e) {}

// Gecikme analizi
$overdue_count = safe_count("SELECT COUNT(*) c FROM jobs WHERE due_date IS NOT NULL AND due_date<CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$critical_stock = safe_count("SELECT COUNT(*) c FROM stock_items WHERE quantity <= critical_level");

// UX SPRINT 002 — PHASE B3: Nabız Satırı verisi — safe_count() hatayı sessizce 0'a çevirdiği için
// (yukarıdaki $overdue_count/$critical_stock TEKRAR kullanılmıyor), burada ayrı, hataya duyarlı
// bir okuma yapılıyor — aynı sorgular, sadece $__pulseOk ile hata durumu ayırt edilebiliyor.
$__pulseOk = true; $__pulseOverdue = 0; $__pulseCriticalStock = 0;
try {
    $__pulseOverdue = (int)($pdo->query("SELECT COUNT(*) c FROM jobs WHERE due_date IS NOT NULL AND due_date<CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')")->fetch()['c'] ?? 0);
    $__pulseCriticalStock = (int)($pdo->query("SELECT COUNT(*) c FROM stock_items WHERE quantity<=critical_level")->fetch()['c'] ?? 0);
} catch(Throwable $e) { $__pulseOk = false; }
$__pulse = dashboard_pulse_state($__pulseOk, $__pulseOverdue, is_admin()||user_can('jobs'), $__pulseCriticalStock, is_admin()||user_can('stock'));

// UX SPRINT 002 — PHASE B4: Hızlı İşlemler — tanım+ayrım listesi boot.php'de merkezi (mobil ile ortak).
$__qaSplit = dashboard_quick_actions_split(function($perm){ return is_admin()||user_can($perm); });
$__qaCategoryOrder = ['TİCARET','FİNANS','OPERASYON','İLETİŞİM'];
$__qaByCategory = [];
foreach($__qaSplit['primary'] as $__qa){ $__qaByCategory[$__qa['category']][] = $__qa; }

$open=safe_count("SELECT COUNT(*) c FROM jobs WHERE status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$todayDue=safe_count("SELECT COUNT(*) c FROM jobs WHERE due_date=CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$late=safe_count("SELECT COUNT(*) c FROM jobs WHERE due_date IS NOT NULL AND due_date<CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$approval=safe_count("SELECT COUNT(*) c FROM job_files WHERE approval_status='Müşteri Onayı Bekliyor'");
$external=safe_count("SELECT COUNT(*) c FROM jobs WHERE job_type IN ('dis_atolye','tedarikcide_uretim') AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$production=safe_count("SELECT COUNT(*) c FROM jobs WHERE job_type IN ('3d_imalat','uv_baski','lazer') AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi')");
$stock=safe_count("SELECT COUNT(*) c FROM stock_items WHERE quantity <= critical_level");
$tasks=safe_count("SELECT COUNT(*) c FROM tasks WHERE status!='Tamamlandı'");
$receivable=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='in' AND status='Bekliyor'");
$payable=safe_sum("SELECT COALESCE(SUM(amount),0) s FROM finance_movements WHERE direction='out' AND status='Bekliyor'");

function cmd_card($title,$value,$desc,$url,$tone='blue'){
    echo '<a class="command-card module-card '.$tone.'" href="'.h($url).'">';
    echo '<small>'.h($title).'</small>';
    echo '<strong>'.h($value).'</strong>';
    echo '<span>'.h($desc).'</span>';
    echo '</a>';
}
?>
<style>
/* ── Komuta kartları ── */
.command-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:14px 0 20px}
.command-card{display:block;text-decoration:none;background:#fff;border-radius:20px;padding:18px;box-shadow:0 8px 28px rgba(16,24,40,.06);border:1px solid #eef2f6;color:#101828;transition:transform .12s ease,box-shadow .12s ease}
.command-card:hover{transform:translateY(-2px);box-shadow:0 14px 36px rgba(16,24,40,.11)}
.command-card small{display:block;color:#667085;font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.command-card strong{display:block;font-size:30px;margin:8px 0;line-height:1}
.command-card span{color:#667085;font-size:13px}
.command-card.red{border-left:6px solid #ef4444}.command-card.orange{border-left:6px solid #f97316}.command-card.yellow{border-left:6px solid #eab308}.command-card.blue{border-left:6px solid #3b82f6}.command-card.purple{border-left:6px solid #8b5cf6}.command-card.teal{border-left:6px solid #14b8a6}.command-card.green{border-left:6px solid #22c55e}

/* ── İki sütunlu grid ── */
.mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}

/* ── Tablo geliştirmeleri ── */
.dash-table{width:100%;border-collapse:collapse}
.dash-table thead tr{background:#f7f9fc}
.dash-table th{font-size:11px;color:#667085;font-weight:900;text-transform:uppercase;letter-spacing:.06em;padding:10px 12px;border-bottom:2px solid #eef2f6}
.dash-table td{padding:11px 12px;border-bottom:1px solid #f2f4f7;vertical-align:middle;font-size:14px}
.dash-table tbody tr{transition:background .1s}
.dash-table tbody tr:hover{background:#f7f9fc}
.dash-table tbody tr:last-child td{border-bottom:0}
.dash-table .job-link{color:#101828;text-decoration:none;font-weight:700;display:block;line-height:1.3}
.dash-table .job-link:hover{color:#3b82f6}
.dash-table .job-sub{color:#667085;font-size:12px;margin-top:2px}
.dash-table td.date-cell{color:#667085;font-size:13px;white-space:nowrap}
.dash-table td.date-cell.overdue{color:#ef4444;font-weight:700}

/* ── Boş durum ── */
.empty-state{padding:32px 16px;text-align:center;color:#a0aec0}
.empty-state .empty-icon{font-size:36px;display:block;margin-bottom:8px;opacity:.5}
.empty-state p{margin:0;font-size:14px}

/* ── Bildirim kartları ── */
.notif-list{display:flex;flex-direction:column;gap:8px}
.notif-item{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border-radius:14px;border:1px solid #eef2f6;background:#fff;transition:background .1s}
.notif-item.unread{background:#fff7ed;border-color:#fed7aa}
.notif-item:hover{background:#f7f9fc}
.notif-dot{width:10px;height:10px;border-radius:50%;background:#d1d5db;flex:0 0 10px;margin-top:5px}
.notif-item.unread .notif-dot{background:#f97316}
.notif-body{flex:1;min-width:0}
.notif-title{font-weight:700;font-size:14px;color:#101828;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.notif-msg{font-size:13px;color:#667085;margin:3px 0 0;line-height:1.4}
.notif-time{font-size:11px;color:#a0aec0;margin-top:4px}
.notif-action{flex:0 0 auto;align-self:center}

/* ── Panel başlık ikonu ── */
.panel-head h2 .section-icon{margin-right:6px;opacity:.85}

/* ── Karşılaştırmalı KPI kartları ── */
.kpi-comparison-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin:14px 0}
.kpi-comparison-card{background:#fff;border-radius:14px;padding:16px;box-shadow:0 2px 8px rgba(16,24,40,.08);border:1px solid #eef2f6}
.kpi-comparison-card .metric-label{font-size:12px;color:#667085;font-weight:700;text-transform:uppercase;margin-bottom:4px}
.kpi-comparison-card .metric-current{font-size:22px;font-weight:900;color:#101828;margin-bottom:6px}
.kpi-comparison-card .metric-prev{font-size:12px;color:#a0aec0;margin-bottom:6px}
.kpi-comparison-card .metric-change{padding:6px 10px;background:#f2f4f7;border-radius:8px;font-size:12px;display:inline-block;font-weight:700}

/* ── Trend grafiği (bar chart) ── */
.trend-chart{background:#fff;border-radius:14px;padding:16px;box-shadow:0 2px 8px rgba(16,24,40,.08);border:1px solid #eef2f6;margin:14px 0}
.trend-chart h3{margin:0 0 12px;font-size:14px;color:#101828;display:flex;align-items:center;gap:6px}
.trend-bar{display:flex;align-items:flex-end;gap:8px;margin:8px 0}
.trend-bar .label{font-size:11px;color:#667085;font-weight:600;width:45px;text-align:right}
.trend-bar .chart-item{display:flex;flex-direction:column;gap:4px;flex:1;align-items:center}
.trend-bar .bar-container{width:100%;height:80px;background:#f2f4f7;border-radius:6px;position:relative;overflow:hidden;display:flex;align-items:flex-end}
.trend-bar .bar-fill{height:100%;width:100%;border-radius:4px;position:relative}
.trend-bar .bar-label{font-size:10px;color:#667085;margin-top:2px;text-align:center}
.trend-bar .bar-value{font-size:11px;font-weight:700;color:#101828;text-align:center}

/* ── Gecikme uyarı panosu ── */
.alert-panel{background:#fef3c7;border:1px solid #fcd34d;border-radius:14px;padding:16px;margin:14px 0}
.alert-panel.critical{background:#fee2e2;border-color:#fca5a5}
.alert-panel h3{margin:0 0 10px;font-size:14px;font-weight:700;color:#92400e}
.alert-panel.critical h3{color:#7f1d1d}
.alert-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:12px}
.alert-stat{background:#fff;border-radius:8px;padding:10px;text-align:center;border:1px solid rgba(0,0,0,.05)}
.alert-stat .stat-value{font-size:18px;font-weight:900;color:#101828}
.alert-stat .stat-label{font-size:11px;color:#667085;margin-top:2px}
a.alert-stat-link{display:block;text-decoration:none;cursor:pointer;transition:transform .12s ease,box-shadow .12s ease}
a.alert-stat-link:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(16,24,40,.1)}
.overdue-jobs-list{background:#fff;border-radius:8px;padding:8px 0}
.overdue-job-item{padding:10px 12px;border-bottom:1px solid #f2f4f7;display:flex;justify-content:space-between;align-items:center;font-size:12px}
.overdue-job-item:last-child{border-bottom:0}
.overdue-job-item .job-info{flex:1;color:#101828;font-weight:600}
.overdue-job-item .overdue-days{background:#ef4444;color:#fff;border-radius:6px;padding:3px 8px;font-weight:700;font-size:10px}

/* ── Dashboard bölüm sürükle-bırak (Seviye 1) — WEB UI ALIGNMENT & NAVIGATION SPRINT 001 ── */
.dash-section{position:relative}
.dash-section.dragging{opacity:.5}
.dash-section-handlebar{display:flex;justify-content:flex-end;margin:6px 2px 2px}
.dash-section-handlebar .section-drag{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#94a3b8;cursor:grab;padding:5px 11px;border-radius:999px;background:rgba(148,163,184,.14);user-select:none}
.dash-section-handlebar .section-drag:active{cursor:grabbing}
.dash-reset-bar{display:flex;gap:8px;justify-content:flex-end;margin:0 0 6px}

@media(max-width:960px){.command-grid,.mini-grid,.kpi-comparison-grid{grid-template-columns:1fr}}

/* ── Nabız Satırı — UX SPRINT 002 PHASE B3 — sabit üst özet, sürüklenemez, section değil ── */
.pulse-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;border-radius:14px;padding:12px 16px;margin:12px 0;border:1px solid transparent}
.pulse-row .pulse-icon{font-size:18px;line-height:1;flex:0 0 auto}
.pulse-row .pulse-text{flex:1 1 auto;min-width:0;font-size:13px;font-weight:700}
.pulse-row .pulse-link{flex:0 0 auto;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.55)}
.pulse-row.pulse-green{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.pulse-row.pulse-green .pulse-link{color:#166534}
.pulse-row.pulse-yellow{background:#fffbeb;border-color:#fde68a;color:#92400e}
.pulse-row.pulse-yellow .pulse-link{color:#92400e}
.pulse-row.pulse-red{background:#fef2f2;border-color:#fecaca;color:#991b1b}
.pulse-row.pulse-red .pulse-link{color:#991b1b}
.pulse-row.pulse-neutral{background:#f8fafc;border-color:#e2e8f0;color:#475569}
@media(max-width:640px){.pulse-row{padding:10px 12px}.pulse-row .pulse-text{font-size:12.5px}}

/* ── Hızlı İşlemler — UX SPRINT 002 PHASE B4 ── */
.qa-panel{margin:12px 0 18px}
.qa-panel .panel-head-sm{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.qa-panel .panel-head-sm h2{font-size:15px;margin:0}
.qa-categories{display:flex;flex-wrap:wrap;gap:18px 28px}
.qa-category{min-width:180px}
.qa-cat-label{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:8px}
.qa-cat-actions{display:flex;flex-wrap:wrap;gap:8px}
.qa-overflow{margin-top:14px;border-top:1px solid #eef2f6;padding-top:12px}
.qa-overflow summary{cursor:pointer;font-size:12px;font-weight:800;color:#475569;list-style:none}
.qa-overflow summary::-webkit-details-marker{display:none}
.qa-overflow summary::before{content:'▸ ';color:#94a3b8}
.qa-overflow[open] summary::before{content:'▾ '}
.qa-overflow .qa-cat-actions{margin-top:10px}
@media(max-width:640px){.qa-categories{gap:14px}.qa-category{min-width:140px}}
</style>

<?php if($__navMode === 'legacy'): ?>
<div class="panel-head page-header">
<h1>Komuta Merkezi</h1>
</div>

<div class="pulse-row pulse-<?=h($__pulse['level'])?>">
<span class="pulse-icon" aria-hidden="true"><?=$__pulse['icon']?></span>
<span class="pulse-text"><?=h($__pulse['message'])?></span>
<?php if($__pulse['hasDetail']): ?>
<a href="#" class="pulse-link" onclick="return dashboardPulseScrollToCritical();">İncele</a>
<?php endif; ?>
</div>

<section class="panel qa-panel">
<div class="panel-head-sm"><h2>⚡ Hızlı İşlemler</h2></div>
<div class="qa-categories">
<?php foreach($__qaCategoryOrder as $__qaCat): if(empty($__qaByCategory[$__qaCat])) continue; ?>
<div class="qa-category">
<div class="qa-cat-label"><?=h($__qaCat)?></div>
<div class="qa-cat-actions">
<?php foreach($__qaByCategory[$__qaCat] as $__qa): ?>
<a class="btn secondary quick-action" href="<?=h($__qa['url'])?>"><?=$__qa['icon']?> <?=h($__qa['label'])?></a>
<?php endforeach; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php if($__qaSplit['overflow']): ?>
<details class="qa-overflow">
<summary>Diğer İşlemler (<?=count($__qaSplit['overflow'])?>)</summary>
<div class="qa-cat-actions">
<?php foreach($__qaSplit['overflow'] as $__qa): ?>
<a class="btn secondary quick-action" href="<?=h($__qa['url'])?>"><?=$__qa['icon']?> <?=h($__qa['label'])?></a>
<?php endforeach; ?>
</div>
</details>
<?php endif; ?>
</section>

<div class="dash-reset-bar">
<button type="button" class="btn small secondary" onclick="resetDashboardOrder('tiles')">↺ Kart Sırası</button>
<button type="button" class="btn small secondary" onclick="resetDashboardOrder('sections')">↺ Sayfa Düzeni</button>
</div>

<?php
// WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Faz A: kart sırası artık statik değil, kullanıcı
// bazlı kaydedilmiş sıraya göre (user_preferences.dashboard_tile_order) render ediliyor.
// Sürükle-bırak sadece küçük bir "tile-drag" tutamaçla yapılır (kartın tamamı draggable DEĞİL),
// böylece kart içindeki link tıklaması bozulmaz.
$__tileDefs = [
    'jobs'      => ['perm'=>'jobs',      'color'=>'blue',   'icon'=>'📋', 'title'=>'İş Emirleri', 'desc'=>'Müşteri işleri ve operasyon takibi', 'url'=>'jobs.php'],
    'contacts'  => ['perm'=>'contacts',  'color'=>'teal',   'icon'=>'👥', 'title'=>'Cariler',   'desc'=>'Müşteri / tedarikçi',        'url'=>'contacts.php'],
    'teklif'    => ['perm'=>'teklif',    'color'=>'purple', 'icon'=>'📄', 'title'=>'Teklifler', 'desc'=>'Hazırla &amp; gönder',       'url'=>'teklif.php'],
    'finance'   => ['perm'=>'finance',   'color'=>'green',  'icon'=>'💰', 'title'=>'Finans',    'desc'=>'Kasa / banka / kart',        'url'=>'finance.php'],
    'stock'     => ['perm'=>'stock',     'color'=>'orange', 'icon'=>'📦', 'title'=>'Stok',      'desc'=>'Ürün &amp; depo',            'url'=>'stock.php'],
    'report'    => ['perm'=>'report',    'color'=>'yellow', 'icon'=>'📊', 'title'=>'Raporlar',  'desc'=>'Yekün &amp; modül',          'url'=>'report.php'],
    'personnel' => ['perm'=>'personnel', 'color'=>'red',    'icon'=>'👷', 'title'=>'Personel',  'desc'=>'Ekip &amp; görev',           'url'=>'personnel.php'],
    'messages'  => ['perm'=>null,        'color'=>'gray',   'icon'=>'💬', 'title'=>'Mesajlar',  'desc'=>'İç yazışma',                 'url'=>'messages.php'],
    'takvim'    => ['perm'=>null,        'color'=>'indigo', 'icon'=>'📅', 'title'=>'Takvim',    'desc'=>'Planlama &amp; hatırlatma',  'url'=>'takvim.php'],
];
$__visibleTileKeys = [];
foreach($__tileDefs as $__k=>$__t){ if($__t['perm']===null || user_can($__t['perm'])) $__visibleTileKeys[]=$__k; }
$__myId = (int)(current_user()['id'] ?? 0);
$__savedOrderRaw = user_pref_get($pdo, $__myId, 'dashboard_tile_order', '');
$__savedOrder = $__savedOrderRaw ? explode(',', $__savedOrderRaw) : [];
$__orderedTileKeys = array_values(array_intersect($__savedOrder, $__visibleTileKeys));
foreach($__visibleTileKeys as $__k){ if(!in_array($__k, $__orderedTileKeys, true)) $__orderedTileKeys[]=$__k; }

// WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Faz A devamı (2026-07-13): Seviye 1 kişiselleştirme
// — Komuta Merkezi'nin ANA BÖLÜMLERİNİN sayfa üzerindeki sırası. Seviye 2 (yukarıdaki
// $__tileDefs) Ana Modül Kartları bölümünün İÇİNDEKİ kart sırası — iki seviye tamamen bağımsız:
// ayrı tercih anahtarı (dashboard_section_order — JSON dizi), ayrı drag sistemi
// (dashboard_section_order — JSON dizi). İzolasyon ayrı DOM seçicileri (.ntile-wrap vs
// .dash-section) VE ayrı state değişkenleri (draggedWrap/draggedSection, her biri kendi tipi
// dışındaki sürüklemede null kalıp no-op yapar) üzerinden sağlanıyor — data-drag-type niteliği
// belgeleme/ileride ek kontrol için tutuluyor, event'ler bilerek stopPropagation edilMEZ (aşağıda
// bindWrap'teki not) ki bir SECTION, module_tiles'ın kart alanına bırakılınca event doğru
// yukarı kabarıp (bubble) o bölümün kendi drop handler'ına ulaşabilsin.
// Her bölüm önce output buffering ile yakalanır (SADECE gerçekten görünürse $__sections'a girer,
// böylece yetki/veri nedeniyle gizli bir bölüm sıralamayı bozmaz), sonra kayıtlı sıraya göre
// yeniden dizilip yazdırılır. Bölüm anahtarları dashboard_section_keys() (user_prefs_lib.php)
// içinde SUNUCU tarafında sabit — ajax_dashboard_order.php de aynı listeden okur.
$__sections = [];

ob_start();
?>
<div class="navtiles" id="navtiles">
<?php foreach($__orderedTileKeys as $__k): $__t=$__tileDefs[$__k]; ?>
<div class="ntile-wrap" data-key="<?=h($__k)?>">
    <span class="tile-drag" draggable="true" data-drag-type="tile" title="Sürükle, sırayı değiştir">⠿</span>
    <a class="ntile module-card <?=h($__t['color'])?>" href="<?=h($__t['url'])?>"><span class="ic"><?=$__t['icon']?></span><b><?=h($__t['title'])?></b><small><?=$__t['desc']?></small></a>
</div>
<?php endforeach; ?>
</div>
<script>
(function(){
  var container = document.getElementById('navtiles');
  if(!container) return;
  var draggedWrap = null;
  function bindHandle(handle){
    handle.addEventListener('dragstart', function(e){
      draggedWrap = handle.closest('.ntile-wrap');
      if(!draggedWrap) return;
      e.dataTransfer.setData('text/plain', draggedWrap.dataset.key);
      e.dataTransfer.effectAllowed = 'move';
      draggedWrap.classList.add('dragging');
      e.stopPropagation();
    });
    handle.addEventListener('dragend', function(e){
      if(draggedWrap) draggedWrap.classList.remove('dragging');
      draggedWrap = null;
      saveTileOrder();
      e.stopPropagation();
    });
  }
  function bindWrap(wrap){
    // stopPropagation KULLANILMIYOR (2026-07-13, Ece/code-review'da bulundu): module_tiles bölümü
    // kendi de bir section-drop hedefi olduğu için, bir SECTION tile alanının üzerine bırakılırsa
    // event'in dış .dash-section'a kabarması (bubble) gerekiyor. İzolasyon zaten draggedWrap/
    // draggedSection ayrı state değişkenleriyle (aşağıdaki null-guard) sağlanıyor, propagation'ı
    // durdurmaya gerek yok — durdurulursa section-drop bu alanda sessizce çalışmaz hale gelirdi.
    wrap.addEventListener('dragover', function(e){ e.preventDefault(); });
    wrap.addEventListener('drop', function(e){
      e.preventDefault();
      if(!draggedWrap || draggedWrap===wrap) return;
      var rect = wrap.getBoundingClientRect();
      var after = (e.clientX - rect.left) > rect.width/2;
      if(after){ wrap.parentNode.insertBefore(draggedWrap, wrap.nextSibling); }
      else{ wrap.parentNode.insertBefore(draggedWrap, wrap); }
    });
  }
  container.querySelectorAll('.tile-drag').forEach(bindHandle);
  container.querySelectorAll('.ntile-wrap').forEach(bindWrap);
  function saveTileOrder(){
    var keys = Array.prototype.map.call(container.querySelectorAll('.ntile-wrap'), function(w){ return w.dataset.key; });
    fetch('ajax_dashboard_order.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token': window.CSRF_TOKEN},
      body: 'order_type=tiles&order=' + encodeURIComponent(keys.join(','))
    }).catch(function(){});
  }
})();
</script>
<?php
$__sections['module_tiles'] = ob_get_clean();

ob_start();
?>
<!-- ── Karşılaştırmalı KPI Kartları ── -->
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">📊</span> Bu Ay vs Geçen Ay Karşılaştırması</h2>
</div>
<div class="kpi-comparison-grid">
    <div class="kpi-comparison-card">
        <div class="metric-label">💰 Tahsilat</div>
        <div class="metric-current"><?=money($currentMetrics['revenue'])?></div>
        <div class="metric-prev">Geçen ay: <?=money($prevMetrics['revenue'])?></div>
        <div class="metric-change">
            <?=changeIndicator(calculateChangePercent($currentMetrics['revenue'], $prevMetrics['revenue']))?>
        </div>
    </div>

    <div class="kpi-comparison-card">
        <div class="metric-label">💸 Ödeme / Gider</div>
        <div class="metric-current"><?=money($currentMetrics['expense'])?></div>
        <div class="metric-prev">Geçen ay: <?=money($prevMetrics['expense'])?></div>
        <div class="metric-change">
            <?=changeIndicator(calculateChangePercent($currentMetrics['expense'], $prevMetrics['expense']))?>
        </div>
    </div>

    <div class="kpi-comparison-card">
        <div class="metric-label">📋 Yeni Açılan İş</div>
        <div class="metric-current"><?=$currentMetrics['jobs_created']?></div>
        <div class="metric-prev">Geçen ay: <?=$prevMetrics['jobs_created']?></div>
        <div class="metric-change">
            <?=changeIndicator(calculateChangePercent($currentMetrics['jobs_created'], $prevMetrics['jobs_created']))?>
        </div>
    </div>

    <div class="kpi-comparison-card">
        <div class="metric-label">✓ Tamamlanan İş</div>
        <div class="metric-current"><?=$currentMetrics['jobs_completed']?></div>
        <div class="metric-prev">Geçen ay: <?=$prevMetrics['jobs_completed']?></div>
        <div class="metric-change">
            <?=changeIndicator(calculateChangePercent($currentMetrics['jobs_completed'], $prevMetrics['jobs_completed']))?>
        </div>
    </div>
</div>
</section>
<?php
$__sections['month_comparison'] = ob_get_clean();

if(!empty($trends)):
ob_start();
?>
<!-- ── 6 Ay Trend Grafiği ── -->
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">📈</span> Son 6 Ay Trend - Tahsilat vs Ödeme</h2>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div class="trend-chart">
        <h3>💰 Tahsilat</h3>
        <?php
        $maxRev = max(array_map(function($t){ return $t['revenue']; }, $trends)) ?: 1;
        foreach($trends as $month => $data):
            $height = $maxRev > 0 ? ($data['revenue'] / $maxRev) * 100 : 0;
        ?>
        <div class="trend-bar">
            <div class="label"><?=h($month)?></div>
            <div class="chart-item">
                <div class="bar-container">
                    <div class="bar-fill" style="background:linear-gradient(180deg,#22c55e 0%,#86efac 100%);height:<?=$height?>%"></div>
                </div>
                <div class="bar-value"><?=money($data['revenue'])?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="trend-chart">
        <h3>💸 Ödeme / Gider</h3>
        <?php
        $maxExp = max(array_map(function($t){ return $t['expense']; }, $trends)) ?: 1;
        foreach($trends as $month => $data):
            $height = $maxExp > 0 ? ($data['expense'] / $maxExp) * 100 : 0;
        ?>
        <div class="trend-bar">
            <div class="label"><?=h($month)?></div>
            <div class="chart-item">
                <div class="bar-container">
                    <div class="bar-fill" style="background:linear-gradient(180deg,#f87171 0%,#fca5a5 100%);height:<?=$height?>%"></div>
                </div>
                <div class="bar-value"><?=money($data['expense'])?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</section>
<?php
$__sections['six_month_trend'] = ob_get_clean();
endif;

ob_start();
?>
<!-- ── Gecikme Uyarı Panosu ── -->
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">⚠️</span> Dikkat - Geciken İşler & Kritik Stok</h2>
</div>
<?php if($overdue_count > 0 || $critical_stock > 0): ?>
<div class="alert-panel <?=$overdue_count > 0 ? 'critical' : ''?>">
    <h3>⚠️ Acil Dikkat Gerektiren Maddeler</h3>
    <div class="alert-summary">
        <?php if($overdue_count > 0): ?>
        <a class="alert-stat alert-stat-link" href="jobs.php?s=gec">
            <div class="stat-value" style="color:#ef4444"><?=$overdue_count?></div>
            <div class="stat-label">Geciken İş</div>
        </a>
        <?php else: ?>
        <div class="alert-stat">
            <div class="stat-value" style="color:#a0aec0"><?=$overdue_count?></div>
            <div class="stat-label">Geciken İş</div>
        </div>
        <?php endif; ?>
        <?php if($critical_stock > 0): ?>
        <a class="alert-stat alert-stat-link" href="stock.php?critical=1">
            <div class="stat-value" style="color:#f97316"><?=$critical_stock?></div>
            <div class="stat-label">Kritik Stok</div>
        </a>
        <?php else: ?>
        <div class="alert-stat">
            <div class="stat-value" style="color:#a0aec0"><?=$critical_stock?></div>
            <div class="stat-label">Kritik Stok</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if($overdue_count > 0): ?>
<div style="margin-top:12px">
    <h4 style="font-size:12px;color:#667085;font-weight:700;margin:8px 0">📋 En Kritik 5 Geciken İş:</h4>
    <div class="overdue-jobs-list">
    <?php
    try {
        $stmt = $pdo->prepare("
            SELECT j.id, j.job_no, j.title, j.due_date
            FROM jobs j
            WHERE j.due_date IS NOT NULL
            AND j.due_date < CURDATE()
            AND j.status NOT IN ('Tamamlandı','İptal','Teslim Edildi')
            ORDER BY j.due_date ASC
            LIMIT 5
        ");
        $stmt->execute();
        $overdueJobs = $stmt->fetchAll();

        if($overdueJobs):
            foreach($overdueJobs as $job):
                $daysDiff = (int)((strtotime(date('Y-m-d')) - strtotime($job['due_date'])) / 86400);
                echo '<div class="overdue-job-item">';
                echo '<a class="job-info" href="job_view.php?id='.h($job['id']).'" style="color:#2563eb;text-decoration:none">';
                echo h($job['job_no']).': '.h(substr($job['title'], 0, 40)).'…';
                echo '</a>';
                echo '<span class="overdue-days">'.$daysDiff.' gün gecikti</span>';
                echo '</div>';
            endforeach;
        endif;
    } catch(Throwable $e) {}
    ?>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state" style="padding:20px">
    <span class="empty-icon">✅</span>
    <p>Tüm işler zamanında, kritik stok yok.</p>
</div>
<?php endif; ?>
</section>
<?php
$__sections['critical_alerts'] = ob_get_clean();

ob_start();
?>
<section class="command-grid">
<?php
cmd_card('Bugün Teslim', $todayDue, 'Bugün terminli açık işler', 'jobs.php?s=bugun', 'red');
cmd_card('Geciken İş', $late, 'Termin tarihi geçmiş işler', 'jobs.php?s=gec', 'orange');
cmd_card('Müşteri Onayı', $approval, 'Onay bekleyen dosyalar', 'approval_waiting.php', 'yellow');
cmd_card('Dış Atölye', $external, 'Dışarıdaki açık işler', 'external.php', 'blue');
cmd_card('Üretimde', $production, '3D / UV / Lazer açık işler', 'production.php', 'purple');
cmd_card('Kritik Stok', $stock, 'Kritik seviyedeki stoklar', 'stock.php?critical=1', 'red');
cmd_card('Açık Görev', $tasks, 'Personel açık görevleri', 'tasks.php', 'teal');
cmd_card('Bekleyen İş', $open, 'Tüm açık işler', 'jobs.php?s=aktif', 'green');
?>
</section>
<?php
$__sections['operation_kpis'] = ob_get_clean();

if($__myNotes):
ob_start();
?>
<section class="panel" style="background:#fffbeb">
<div class="panel-head">
    <h2><span class="section-icon">📝</span> Notlarım <small class="muted" style="font-weight:400">(sadece sana özel)</small></h2>
    <a class="btn small secondary" href="notes.php">Tümünü Gör / Ekle</a>
</div>
<?php foreach(array_slice($__myNotes,0,4) as $__n): ?>
<div style="padding:8px 0;border-bottom:1px solid #fde68a">
    <b><?=h($__n['title'])?></b>
    <?php if($__n['due_date']): ?><span class="muted" style="margin-left:6px">📅 <?=h($__n['due_date'])?></span><?php endif; ?>
</div>
<?php endforeach; ?>
</section>
<?php
$__sections['notes'] = ob_get_clean();
endif;

ob_start();
?>
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">🕘</span> Son İşlemler</h2>
    <a class="btn small secondary" href="activity.php">Tümünü Gör</a>
</div>
<?php
try{
    activity_render_list(activity_recent(10));
}catch(Throwable $e){
    echo "<div class='empty-state'><span class='empty-icon'>📋</span><p>Son işlemler okunamadı.</p></div>";
}
?>
</section>
<?php
$__sections['recent_actions'] = ob_get_clean();

ob_start();
?>
<section class="panel">
<div class="panel-head">
<h2><span class="section-icon">🔔</span> Canlı Bildirimler</h2>
<a class="btn small secondary" href="notifications.php">Tüm Bildirimler</a>
</div>
<?php
try{
$__me=(int)(current_user()['id'] ?? 0);
$notifs=function_exists('notif_list_for_user') ? notif_list_for_user($pdo,$__me,6) : [];
if($notifs):
?>
<div class="notif-list">
<?php foreach($notifs as $n):
$go=$n['action_url'] ?: 'dashboard.php';
$readUrl='notifications.php?read='.$n['id'].'&go='.urlencode($go);
?>
<div class="notif-item <?=$n['effective_is_read']?'':'unread'?>">
    <div class="notif-dot"></div>
    <div class="notif-body">
        <div class="notif-title">
            <?=h($n['title'])?>
            <?=$n['effective_is_read']?badge('Okundu','gray'):badge('Yeni','orange')?>
        </div>
        <div class="notif-msg"><?=nl2br(h($n['message']))?></div>
        <div class="notif-time"><?=h($n['created_at'])?></div>
    </div>
    <div class="notif-action">
        <a class="btn small secondary" href="<?=h($readUrl)?>">Detay</a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
    <span class="empty-icon">🔔</span>
    <p>Henüz bildirim yok.</p>
</div>
<?php endif;
}catch(Throwable $e){ echo "<div class='alert'>".h($e->getMessage())."</div>";}
?>
</section>
<?php
$__sections['live_notifications'] = ob_get_clean();

ob_start();
?>
<div class="mini-grid">
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">🔴</span> Bugün Teslim</h2>
    <a class="btn small secondary" href="jobs.php?s=bugun">Tümü</a>
</div>
<?php
try{
$st=db()->query("SELECT * FROM jobs WHERE due_date=CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi') ORDER BY id DESC LIMIT 8");
$rows=$st->fetchAll();
if($rows):
?>
<table class="dash-table"><thead><tr><th>İş</th><th>Tip</th><th>Durum</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr>
    <td>
        <a class="job-link" href="job_view.php?id=<?=h($r['id'])?>"><?=h($r['job_no'])?></a>
        <div class="job-sub"><?=h($r['title'])?></div>
    </td>
    <td><?=h(job_type_label($r['job_type']))?></td>
    <td><?=badge($r['status'],status_tone($r['status']))?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?>
<div class="empty-state">
    <span class="empty-icon">✅</span>
    <p>Bugün teslim edilecek açık iş yok.</p>
</div>
<?php endif;
}catch(Throwable $e){ echo "<div class='alert'>".h($e->getMessage())."</div>";}
?>
</section>

<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">🟠</span> Geciken İşler</h2>
    <a class="btn small secondary" href="jobs.php?s=gec">Tümü</a>
</div>
<?php
try{
$st=db()->query("SELECT * FROM jobs WHERE due_date IS NOT NULL AND due_date<CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi') ORDER BY due_date ASC LIMIT 8");
$rows=$st->fetchAll();
if($rows):
?>
<table class="dash-table"><thead><tr><th>İş</th><th>Termin</th><th>Durum</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr>
    <td>
        <a class="job-link" href="job_view.php?id=<?=h($r['id'])?>"><?=h($r['job_no'])?></a>
        <div class="job-sub"><?=h($r['title'])?></div>
    </td>
    <td class="date-cell overdue"><?=h($r['due_date'])?></td>
    <td><?=badge($r['status'],status_tone($r['status']))?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?>
<div class="empty-state">
    <span class="empty-icon">🎉</span>
    <p>Geciken iş yok.</p>
</div>
<?php endif;
}catch(Throwable $e){ echo "<div class='alert'>".h($e->getMessage())."</div>";}
?>
</section>
</div>
<?php
$__sections['today_and_late_lists'] = ob_get_clean();

ob_start();
?>
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">📋</span> Son İşler</h2>
    <a href="jobs.php" class="btn small secondary">İş Emirlerine Git</a>
</div>
<?php
try{
$rows=db()->query("SELECT * FROM jobs ORDER BY id DESC LIMIT 10")->fetchAll();
if($rows):
?>
<table class="dash-table">
<thead><tr><th>İş No</th><th>Başlık</th><th>Tip</th><th>Termin</th><th>Durum</th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
    <td><a class="job-link" href="job_view.php?id=<?=h($r['id'])?>"><?=h($r['job_no'])?></a></td>
    <td style="color:#344054"><?=h($r['title'])?></td>
    <td style="color:#667085;font-size:13px"><?=h(job_type_label($r['job_type']))?></td>
    <td class="date-cell"><?=h($r['due_date'])?></td>
    <td><?=badge($r['status'], status_tone($r['status']))?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<div class="empty-state">
    <span class="empty-icon">📋</span>
    <p>Henüz iş kaydı yok.</p>
</div>
<?php endif;
}catch(Throwable $e){ echo "<div class='alert'>".h($e->getMessage())."</div>";}
?>
</section>
<?php
$__sections['recent_jobs'] = ob_get_clean();

// ---- Bölüm sırasını uygula ve yazdır ----
$__sectionDefaultOrder = dashboard_section_keys();
$__sectionLabels = [
    'module_tiles'         => 'Ana Modül Kartları',
    'month_comparison'     => 'Bu Ay vs Geçen Ay',
    'six_month_trend'      => 'Son 6 Ay Trend',
    'critical_alerts'      => 'Dikkat / Geciken & Kritik Stok',
    'operation_kpis'       => 'Operasyon KPI Kartları',
    'notes'                => 'Notlarım',
    'recent_actions'       => 'Son İşlemler',
    'live_notifications'   => 'Canlı Bildirimler',
    'today_and_late_lists' => 'Bugün Teslim & Geciken İşler',
    'recent_jobs'          => 'Son İşler',
];
$__visibleSectionKeys = array_keys($__sections);
$__savedSectionOrderRaw = user_pref_get($pdo, $__myId, 'dashboard_section_order', '');
$__savedSectionOrder = [];
if($__savedSectionOrderRaw){
    $__decoded = json_decode($__savedSectionOrderRaw, true);
    if(is_array($__decoded)) $__savedSectionOrder = $__decoded;
}
$__orderedSectionKeys = array_values(array_intersect($__savedSectionOrder, $__visibleSectionKeys));
foreach($__sectionDefaultOrder as $__sk){
    if(in_array($__sk, $__visibleSectionKeys, true) && !in_array($__sk, $__orderedSectionKeys, true)) $__orderedSectionKeys[]=$__sk;
}

foreach($__orderedSectionKeys as $__sk):
?>
<div class="dash-section" data-drag-type="section" data-key="<?=h($__sk)?>">
  <div class="dash-section-handlebar">
    <span class="section-drag" draggable="true" data-drag-type="section" title="Bölümü sürükle, sırayı değiştir">⋮⋮ <?=h($__sectionLabels[$__sk] ?? $__sk)?></span>
  </div>
  <?=$__sections[$__sk]?>
</div>
<?php endforeach; ?>

<script>
(function(){
  var draggedSection = null;
  function bindSectionHandle(handle){
    handle.addEventListener('dragstart', function(e){
      draggedSection = handle.closest('.dash-section');
      if(!draggedSection) return;
      e.dataTransfer.setData('text/plain', draggedSection.dataset.key);
      e.dataTransfer.effectAllowed = 'move';
      draggedSection.classList.add('dragging');
      e.stopPropagation();
    });
    handle.addEventListener('dragend', function(e){
      if(draggedSection) draggedSection.classList.remove('dragging');
      draggedSection = null;
      saveSectionOrder();
      e.stopPropagation();
    });
  }
  function bindSectionWrap(sec){
    sec.addEventListener('dragover', function(e){ e.preventDefault(); e.stopPropagation(); });
    sec.addEventListener('drop', function(e){
      e.preventDefault();
      e.stopPropagation();
      if(!draggedSection || draggedSection===sec) return;
      var rect = sec.getBoundingClientRect();
      var after = (e.clientY - rect.top) > rect.height/2;
      if(after){ sec.parentNode.insertBefore(draggedSection, sec.nextSibling); }
      else{ sec.parentNode.insertBefore(draggedSection, sec); }
    });
  }
  document.querySelectorAll('.dash-section > .dash-section-handlebar > .section-drag').forEach(bindSectionHandle);
  document.querySelectorAll('.dash-section').forEach(bindSectionWrap);
  function saveSectionOrder(){
    var keys = Array.prototype.map.call(document.querySelectorAll('.dash-section'), function(w){ return w.dataset.key; });
    fetch('ajax_dashboard_order.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token': window.CSRF_TOKEN},
      body: 'order_type=sections&order=' + encodeURIComponent(JSON.stringify(keys))
    }).catch(function(){});
  }
})();

function resetDashboardOrder(type){
  var label = type==='tiles' ? 'kart sırasını' : 'sayfa düzenini';
  if(!confirm('Varsayılan '+label+' geri yüklemek istiyor musunuz?')) return;
  fetch('ajax_dashboard_order.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token': window.CSRF_TOKEN},
    body: 'order_type=' + type + '&reset=1'
  }).then(function(){ location.reload(); }).catch(function(){ location.reload(); });
}

// Nabız Satırı "İncele" — yeni detay ekranı yok, mevcut kritik bölüme yumuşak scroll (UX SPRINT 002 PHASE B3)
function dashboardPulseScrollToCritical(){
  var target = document.querySelector('.dash-section[data-key="critical_alerts"]');
  if(target){ target.scrollIntoView({behavior:'smooth', block:'start'}); }
  return false;
}
</script>

<?php else: ?>
<?php
// PX-001 — Home Screen v1.1 (Hero + Sırada + Devam Et). Kod yok, sadece mockup denilen aşama
// kapandı (Product Owner kararı, 2026-07-16) — bu artık gerçek sorgulara bağlı pilot ekran.
// Not: arama (Spotlight) bu turda BİLİNÇLİ OLARAK eklenmedi — gerçek çapraz-modül arama altyapısı
// ayrı bir iş; mevcut Launcher ("Tüm Modüller") zaten bir arama/erişim yolu sağlıyor.
$__homeMe = (int)($_SESSION['user']['id'] ?? 0);
$__homePid = function_exists('task_my_personnel_id') ? task_my_personnel_id($pdo, $__homeMe) : null;
$__homeCanSee = function($perm){ return user_can($perm); };
$__homeQ = home_build_queue($pdo, is_admin(), $__homeCanSee, $__homePid, 'web');
$__homeC = home_build_continue($pdo, is_admin(), $__homeCanSee);
$__homeDay = home_today_label();
?>
<div class="df-home-daylabel"><span class="df-home-dow"><?=h($__homeDay['dow'])?></span><span class="df-home-date"><?=h($__homeDay['date'])?></span></div>

<?php if($__homeQ['hero']): $__h=$__homeQ['hero']; ?>
<a class="df-home-hero" href="<?=h($__h['url'])?>">
  <div class="df-home-hero-body">
    <div class="df-home-hero-title"><?=h($__h['title'])?></div>
    <div class="df-home-hero-meta"><?=h($__h['meta'])?></div>
    <div class="df-home-hero-pill"><span class="df-badge df-badge--<?=h(home_pill_badge_tone($__h['pill']['tone']))?>"><?=h($__h['pill']['label'])?></span></div>
  </div>
  <div class="df-home-chev"></div>
</a>
<?php else: ?>
<div class="df-panel" style="text-align:center;padding:28px 16px">
  <div style="font-size:14px;font-weight:700">Bugün her şey yolunda</div>
  <div style="font-size:12.5px;color:var(--df-ink-600);margin-top:4px">Sırada acil bir iş yok.</div>
</div>
<?php endif; ?>

<?php if($__homeQ['queue']): ?>
<div>
  <div class="df-home-lab">Sırada</div>
  <div class="df-home-qlist">
    <?php foreach($__homeQ['queue'] as $__qi): ?>
    <a class="df-home-qrow" href="<?=h($__qi['url'])?>">
      <div class="df-home-qrow-body"><div class="df-home-qrow-title"><?=h($__qi['title'])?></div><div class="df-home-qrow-meta"><?=h($__qi['meta'])?></div></div>
      <span class="df-badge df-badge--<?=h(home_pill_badge_tone($__qi['pill']['tone']))?>" style="font-size:10px;padding:3px 8px"><?=h($__qi['pill']['label'])?></span>
      <div class="df-home-chev df-home-chev--sm"></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if($__homeQ['more'] > 0): ?><a class="df-home-more" href="jobs.php">+<?=(int)$__homeQ['more']?> iş daha</a><?php endif; ?>
</div>
<?php endif; ?>

<?php if($__homeC): ?>
<div>
  <div class="df-home-lab">Devam Et</div>
  <div class="df-home-continue">
    <?php foreach($__homeC as $__ci): ?>
    <a class="df-home-cc" href="<?=h($__ci['url'])?>">
      <div class="df-home-cc-eyebrow"><?=h($__ci['eyebrow'])?></div>
      <div class="df-home-cc-row"><div class="df-home-cc-title"><?=h($__ci['title'])?></div><div class="df-home-chev df-home-chev--sm"></div></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
