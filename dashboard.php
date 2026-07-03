<?php
require_once __DIR__.'/layout_top.php';
if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';
require_once __DIR__.'/notes_lib.php';

$today=date('Y-m-d');
$pdo=db();
$__myNotes=personal_notes_list($pdo,(int)($_SESSION['user']['id']??0),'open');

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
        // Tahsilat
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) total FROM finance_movements WHERE direction='in' AND DATE(movement_date) BETWEEN ? AND ?");
        $stmt->execute([$from, $to]);
        $metrics['revenue'] = (float)($stmt->fetch()['total'] ?? 0);
    } catch(Throwable $e) { $metrics['revenue'] = 0; }

    try {
        // Ödeme/Gider — hesaplar arası transfer gerçek bir gider değildir, hariç tutulur (2026-07-03
        // modül-zinciri denetiminde bulundu: kasadan bankaya transfer "gider" toplamını şişiriyordu).
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) total FROM finance_movements WHERE direction='out' AND COALESCE(movement_type,'')<>'transfer' AND DATE(movement_date) BETWEEN ? AND ?");
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

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount END),0) rev, COALESCE(SUM(CASE WHEN direction='out' AND COALESCE(movement_type,'')<>'transfer' THEN amount END),0) exp FROM finance_movements WHERE DATE(movement_date) BETWEEN ? AND ?");
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
    echo '<a class="command-card '.$tone.'" href="'.h($url).'">';
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
.overdue-jobs-list{background:#fff;border-radius:8px;padding:8px 0}
.overdue-job-item{padding:10px 12px;border-bottom:1px solid #f2f4f7;display:flex;justify-content:space-between;align-items:center;font-size:12px}
.overdue-job-item:last-child{border-bottom:0}
.overdue-job-item .job-info{flex:1;color:#101828;font-weight:600}
.overdue-job-item .overdue-days{background:#ef4444;color:#fff;border-radius:6px;padding:3px 8px;font-weight:700;font-size:10px}

@media(max-width:960px){.command-grid,.mini-grid,.kpi-comparison-grid{grid-template-columns:1fr}}
</style>

<div class="panel-head">
<h1>Komuta Merkezi</h1>
<div class="actions">
<a class="btn" href="job_new.php">+ Yeni İş</a>
<a class="btn secondary" href="request_new.php">+ Talep</a>
</div>
</div>

<div class="navtiles">
<?php if(user_can('jobs')): ?><a class="ntile blue" href="jobs.php"><span class="ic">📋</span><b>İşler</b><small>İş merkezi &amp; takip</small></a><?php endif; ?>
<?php if(user_can('contacts')): ?><a class="ntile teal" href="contacts.php"><span class="ic">👥</span><b>Cariler</b><small>Müşteri / tedarikçi</small></a><?php endif; ?>
<?php if(user_can('teklif')): ?><a class="ntile purple" href="teklif.php"><span class="ic">📄</span><b>Teklifler</b><small>Hazırla &amp; gönder</small></a><?php endif; ?>
<?php if(user_can('finance')): ?><a class="ntile green" href="finance.php"><span class="ic">💰</span><b>Finans</b><small>Kasa / banka / kart</small></a><?php endif; ?>
<?php if(user_can('stock')): ?><a class="ntile orange" href="stock.php"><span class="ic">📦</span><b>Stok</b><small>Ürün &amp; depo</small></a><?php endif; ?>
<?php if(user_can('report')): ?><a class="ntile yellow" href="report.php"><span class="ic">📊</span><b>Raporlar</b><small>Yekün &amp; modül</small></a><?php endif; ?>
<?php if(user_can('personnel')): ?><a class="ntile red" href="personnel.php"><span class="ic">👷</span><b>Personel</b><small>Ekip &amp; görev</small></a><?php endif; ?>
<a class="ntile gray" href="messages.php"><span class="ic">💬</span><b>Mesajlar</b><small>İç yazışma</small></a>
</div>

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

<!-- ── 6 Ay Trend Grafiği ── -->
<?php if(!empty($trends)): ?>
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
<?php endif; ?>

<!-- ── Gecikme Uyarı Panosu ── -->
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">⚠️</span> Dikkat - Geciken İşler & Kritik Stok</h2>
</div>
<?php if($overdue_count > 0 || $critical_stock > 0): ?>
<div class="alert-panel <?=$overdue_count > 0 ? 'critical' : ''?>">
    <h3>⚠️ Acil Dikkat Gerektiren Maddeler</h3>
    <div class="alert-summary">
        <div class="alert-stat">
            <div class="stat-value" style="color:<?=$overdue_count > 0 ? '#ef4444' : '#a0aec0'?>"><?=$overdue_count?></div>
            <div class="stat-label">Geciken İş</div>
        </div>
        <div class="alert-stat">
            <div class="stat-value" style="color:<?=$critical_stock > 0 ? '#f97316' : '#a0aec0'?>"><?=$critical_stock?></div>
            <div class="stat-label">Kritik Stok</div>
        </div>
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


<section class="command-grid">
<?php
cmd_card('Bugün Teslim', $todayDue, 'Bugün terminli açık işler', 'jobs.php?filter=today', 'red');
cmd_card('Geciken İş', $late, 'Termin tarihi geçmiş işler', 'jobs.php?filter=late', 'orange');
cmd_card('Müşteri Onayı', $approval, 'Onay bekleyen dosyalar', 'approval_waiting.php', 'yellow');
cmd_card('Dış Atölye', $external, 'Dışarıdaki açık işler', 'external.php', 'blue');
cmd_card('Üretimde', $production, '3D / UV / Lazer açık işler', 'production.php', 'purple');
cmd_card('Kritik Stok', $stock, 'Kritik seviyedeki stoklar', 'stock.php?critical=1', 'red');
cmd_card('Açık Görev', $tasks, 'Personel açık görevleri', 'tasks.php', 'teal');
cmd_card('Bekleyen İş', $open, 'Tüm açık işler', 'jobs.php?filter=open', 'green');
?>
</section>

<?php if($__myNotes): ?>
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
<?php endif; ?>

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


<section class="panel">
<div class="panel-head">
<h2><span class="section-icon">🔔</span> Canlı Bildirimler</h2>
<a class="btn small secondary" href="notifications.php">Tüm Bildirimler</a>
</div>
<?php
try{
$__me=(int)(current_user()['id'] ?? 0);
$notifs=$pdo->prepare("SELECT * FROM internal_notifications WHERE (target_user_id IS NULL OR target_user_id=?) ORDER BY is_read ASC, id DESC LIMIT 6");
$notifs->execute([$__me]);
$notifs=$notifs->fetchAll();
if($notifs):
?>
<div class="notif-list">
<?php foreach($notifs as $n):
$go=$n['action_url'] ?: 'dashboard.php';
$readUrl='notifications.php?read='.$n['id'].'&go='.urlencode($go);
?>
<div class="notif-item <?=$n['is_read']?'':'unread'?>">
    <div class="notif-dot"></div>
    <div class="notif-body">
        <div class="notif-title">
            <?=h($n['title'])?>
            <?=$n['is_read']?badge('Okundu','gray'):badge('Yeni','orange')?>
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

<div class="mini-grid">
<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">🔴</span> Bugün Teslim</h2>
    <a class="btn small secondary" href="jobs.php?filter=today">Tümü</a>
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
    <a class="btn small secondary" href="jobs.php?filter=late">Tümü</a>
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

<section class="panel">
<div class="panel-head">
    <h2><span class="section-icon">📋</span> Son İşler</h2>
    <a href="jobs.php" class="btn small secondary">İş Merkezine Git</a>
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

<?php require_once __DIR__.'/layout_bottom.php'; ?>
