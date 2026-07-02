<?php
require_once __DIR__.'/boot.php';
require_permission('users'); // Admin/yetki kontrolü
if(file_exists(__DIR__.'/audit_lib.php')) require_once __DIR__.'/audit_lib.php';

$pdo = db();
$tableFilter = trim($_GET['table'] ?? '');
$userFilter = trim($_GET['user'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

// Tablo seçenekleri
$tables = ['finance_accounts', 'finance_movements'];

// Kullanıcı seçenekleri (sistem kaydı yapan aktif kullanıcılar)
$users = [];
try{
    $stmt = $pdo->prepare("SELECT DISTINCT audit_log.user_id, app_users.full_name
        FROM audit_log
        LEFT JOIN app_users ON audit_log.user_id = app_users.id
        WHERE audit_log.user_id IS NOT NULL
        ORDER BY app_users.full_name");
    $stmt->execute();
    $users = $stmt->fetchAll();
}catch(Throwable $e){}

// Denetim günlüğünü listele
$logs = [];
if(function_exists('audit_log_list')){
    $logs = audit_log_list($pdo, 300, $tableFilter, $userFilter, $dateFrom, $dateTo);
}

// Tablo istatistikleri
$stats = [];
if(function_exists('audit_log_table_stats')){
    $stats = audit_log_table_stats($pdo);
}
?>
<?php require_once __DIR__.'/layout_top.php'; ?>

<style>
.audit-filters{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 20px}
.audit-filters label{display:flex;flex-direction:column;gap:4px}
.audit-filters input,.audit-filters select{min-width:140px}
.audit-record{background:#fff;border-radius:12px;border-left:4px solid #2563eb;padding:14px;margin:10px 0;box-shadow:0 2px 8px rgba(16,24,40,.06)}
.audit-record.update{border-left-color:#f59e0b}
.audit-record.delete{border-left-color:#ef4444}
.audit-record.create{border-left-color:#10b981}
.audit-action{display:inline-block;padding:4px 8px;border-radius:8px;font-size:12px;font-weight:700;margin-right:8px}
.audit-action.create{background:#dcfce7;color:#166534}
.audit-action.update{background:#fef3c7;color:#92400e}
.audit-action.delete{background:#fee2e2;color:#991b1b}
.audit-details{background:#f5f7fb;border-radius:8px;padding:10px;margin:10px 0;font-size:13px;color:#544;font-family:monospace}
.audit-details summary{cursor:pointer;font-weight:700;color:#2563eb}
.audit-timestamp{color:#667085;font-size:12px;margin-top:8px}
</style>

<h1>🔍 Denetim Günlüğü</h1>
<p class="muted">Kritik finansal işlemlerin (hesap/hareket güncelleme-silme) değişmez kaydı. Kim-ne zaman-ne değiştirdi.</p>

<?php if($stats): ?>
<div class="panel">
    <h3 style="margin-top:0">İstatistikler</h3>
    <div class="cards">
        <?php foreach($stats as $table=>$count): ?>
        <div class="card" style="padding:14px">
            <small><?=htmlspecialchars($table)?></small>
            <strong><?=(int)$count?></strong>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <h3 style="margin-top:0">Filtreler</h3>
    <form method="get" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;align-items:flex-end">
        <label>
            <strong style="font-size:12px;color:#667085">Tablo</strong>
            <select name="table">
                <option value="">— Tüm Tablolar —</option>
                <?php foreach($tables as $t): ?>
                <option value="<?=htmlspecialchars($t)?>" <?=$tableFilter===$t?'selected':''?>>
                    <?=htmlspecialchars($t)?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <strong style="font-size:12px;color:#667085">Kullanıcı</strong>
            <select name="user">
                <option value="">— Tüm Kullanıcılar —</option>
                <?php foreach($users as $u): ?>
                <option value="<?=(int)$u['user_id']?>" <?=$userFilter==(int)$u['user_id']?'selected':''?>>
                    <?=htmlspecialchars($u['full_name'] ?? 'Bilinmiyor')?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <strong style="font-size:12px;color:#667085">Başlangıç Tarihi</strong>
            <input type="date" name="date_from" value="<?=htmlspecialchars($dateFrom)?>" />
        </label>
        <label>
            <strong style="font-size:12px;color:#667085">Bitiş Tarihi</strong>
            <input type="date" name="date_to" value="<?=htmlspecialchars($dateTo)?>" />
        </label>
        <button type="submit" class="btn" style="grid-column:5">Ara</button>
        <a href="audit_log.php" class="btn secondary" style="grid-column:5">Sıfırla</a>
    </form>
</div>

<?php if($logs): ?>
<div class="panel">
    <h3 style="margin-top:0">Kayıtlar (son <?=count($logs)?>)</h3>
    <?php foreach($logs as $log): ?>
        <div class="audit-record <?=htmlspecialchars($log['action'])?>" data-id="<?=(int)$log['id']?>">
            <div>
                <span class="audit-action <?=htmlspecialchars($log['action'])?>">
                    <?=htmlspecialchars(strtoupper($log['action']))?>
                </span>
                <strong><?=htmlspecialchars($log['table_name'])?>#<?=(int)$log['record_id']?></strong>
            </div>
            <div class="audit-timestamp">
                👤 <?=htmlspecialchars($log['user_id'] ? 'Kullanıcı ID:'.$log['user_id'] : 'Sistem')?>
                · 🌐 <?=htmlspecialchars($log['ip_address'] ?? '—')?>
                · 📅 <?=htmlspecialchars(date('d.m.Y H:i:s', strtotime($log['created_at'])))?>
            </div>

            <?php if($log['old_value'] || $log['new_value']): ?>
            <details style="margin-top:10px">
                <summary>Değerler</summary>
                <?php if($log['old_value']): ?>
                <div class="audit-details">
                    <strong>Eski Değer:</strong><br>
                    <code><?=htmlspecialchars($log['old_value'])?></code>
                </div>
                <?php endif; ?>
                <?php if($log['new_value']): ?>
                <div class="audit-details">
                    <strong>Yeni Değer:</strong><br>
                    <code><?=htmlspecialchars($log['new_value'])?></code>
                </div>
                <?php endif; ?>
            </details>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert">Denetim kaydı bulunamadı.</div>
<?php endif; ?>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
