<?php
require_once __DIR__.'/layout_top.php';
if(file_exists(__DIR__.'/activity_lib.php')) require_once __DIR__.'/activity_lib.php';

$today=date('Y-m-d');

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

@media(max-width:960px){.command-grid,.mini-grid{grid-template-columns:1fr}}
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
$notifs=db()->query("SELECT * FROM internal_notifications ORDER BY is_read ASC, id DESC LIMIT 6")->fetchAll();
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
