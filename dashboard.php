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
.command-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin:14px 0 20px}.command-card{display:block;text-decoration:none;background:#fff;border-radius:20px;padding:18px;box-shadow:0 8px 28px rgba(16,24,40,.06);border:1px solid #eef2f6;color:#101828}.command-card small{display:block;color:#667085;font-weight:800}.command-card strong{display:block;font-size:30px;margin:8px 0}.command-card span{color:#667085;font-size:13px}.command-card.red{border-left:6px solid #ef4444}.command-card.orange{border-left:6px solid #f97316}.command-card.yellow{border-left:6px solid #eab308}.command-card.blue{border-left:6px solid #3b82f6}.command-card.purple{border-left:6px solid #8b5cf6}.command-card.teal{border-left:6px solid #14b8a6}.command-card.green{border-left:6px solid #22c55e}.mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media(max-width:960px){.command-grid,.mini-grid{grid-template-columns:1fr}}
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
    <h2>🕘 Son İşlemler</h2>
    <a class="btn small secondary" href="activity.php">Tümünü Gör</a>
</div>
<?php
try{
    activity_render_list(activity_recent(10));
}catch(Throwable $e){
    echo "<p class='muted'>Son işlemler okunamadı.</p>";
}
?>
</section>


<section class="panel">
<div class="panel-head">
<h2>🔔 Canlı Bildirimler</h2>
<a class="btn small secondary" href="notifications.php">Tüm Bildirimler</a>
</div>
<?php
try{
$notifs=db()->query("SELECT * FROM notifications ORDER BY is_read ASC, id DESC LIMIT 6")->fetchAll();
foreach($notifs as $n):
$go=$n['action_url'] ?: 'dashboard.php';
$readUrl='notifications.php?read='.$n['id'].'&go='.urlencode($go);
?>
<div class="notice-card <?=$n['is_read']?'':'unread'?>">
    <div class="panel-head">
        <div>
            <b><?=h($n['title'])?></b>
            <?=$n['is_read']?badge('Okundu','gray'):badge('Yeni','orange')?>
            <br><span class="muted"><?=h($n['created_at'])?></span>
        </div>
        <a class="btn small" href="<?=h($readUrl)?>">Detaya Git</a>
    </div>
    <p><?=nl2br(h($n['message']))?></p>
</div>
<?php endforeach; if(!$notifs) echo "<p class='muted'>Henüz bildirim yok.</p>";
}catch(Throwable $e){ echo "<div class='alert'>".h($e->getMessage())."</div>";}
?>
</section>

<div class="mini-grid">
<section class="panel">
<div class="panel-head"><h2>🔴 Bugün Teslim Edilecekler</h2><a class="btn small secondary" href="jobs.php?filter=today">Tümü</a></div>
<table><thead><tr><th>İş</th><th>Tip</th><th>Durum</th></tr></thead><tbody>
<?php
try{
$st=db()->query("SELECT * FROM jobs WHERE due_date=CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi') ORDER BY id DESC LIMIT 8");
$rows=$st->fetchAll();
foreach($rows as $r){
echo "<tr><td><a href='job_view.php?id=".h($r['id'])."'>".h($r['job_no'])."<br>".h($r['title'])."</a></td><td>".h(job_type_label($r['job_type']))."</td><td>".badge($r['status'],status_tone($r['status']))."</td></tr>";
}
if(!$rows) echo "<tr><td colspan='3' class='muted'>Bugün teslim edilecek açık iş yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='3'><div class='alert'>".h($e->getMessage())."</div></td></tr>";}
?>
</tbody></table>
</section>

<section class="panel">
<div class="panel-head"><h2>🟠 Geciken İşler</h2><a class="btn small secondary" href="jobs.php?filter=late">Tümü</a></div>
<table><thead><tr><th>İş</th><th>Termin</th><th>Durum</th></tr></thead><tbody>
<?php
try{
$st=db()->query("SELECT * FROM jobs WHERE due_date IS NOT NULL AND due_date<CURDATE() AND status NOT IN ('Tamamlandı','İptal','Teslim Edildi') ORDER BY due_date ASC LIMIT 8");
$rows=$st->fetchAll();
foreach($rows as $r){
echo "<tr class='danger-row'><td><a href='job_view.php?id=".h($r['id'])."'>".h($r['job_no'])."<br>".h($r['title'])."</a></td><td>".h($r['due_date'])."</td><td>".badge($r['status'],status_tone($r['status']))."</td></tr>";
}
if(!$rows) echo "<tr><td colspan='3' class='muted'>Geciken iş yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='3'><div class='alert'>".h($e->getMessage())."</div></td></tr>";}
?>
</tbody></table>
</section>
</div>

<section class="panel">
<div class="panel-head"><h2>Son İşler</h2><a href="jobs.php" class="btn small secondary">İş Merkezine Git</a></div>
<table><thead><tr><th>İş No</th><th>Başlık</th><th>Tip</th><th>Termin</th><th>Durum</th></tr></thead><tbody>
<?php
try{
$rows=db()->query("SELECT * FROM jobs ORDER BY id DESC LIMIT 10")->fetchAll();
foreach($rows as $r){
 echo "<tr><td><a href='job_view.php?id=".h($r['id'])."'>".h($r['job_no'])."</a></td><td>".h($r['title'])."</td><td>".h(job_type_label($r['job_type']))."</td><td>".h($r['due_date'])."</td><td>".badge($r['status'], status_tone($r['status']))."</td></tr>";
}
if(!$rows) echo "<tr><td colspan='5' class='muted'>Henüz iş kaydı yok.</td></tr>";
}catch(Throwable $e){ echo "<tr><td colspan='5'><div class='alert'>".h($e->getMessage())."</div></td></tr>";}
?>
</tbody></table>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
