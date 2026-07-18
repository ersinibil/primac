<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
if(file_exists(__DIR__.'/../audit_lib.php')) require_once __DIR__.'/../audit_lib.php';

$pdo=db();
$tableFilter = trim($_GET['table'] ?? '');
$userFilter = trim($_GET['user'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$tables = ['finance_accounts', 'finance_movements'];

$users=[];
try{
    $stmt = $pdo->prepare("SELECT DISTINCT audit_log.user_id, app_users.full_name
        FROM audit_log LEFT JOIN app_users ON audit_log.user_id = app_users.id
        WHERE audit_log.user_id IS NOT NULL ORDER BY app_users.full_name");
    $stmt->execute();
    $users = $stmt->fetchAll();
}catch(Throwable $e){}

$logs=[];
if(function_exists('audit_log_list')) $logs = audit_log_list($pdo, 300, $tableFilter, $userFilter, $dateFrom, $dateTo);

$stats=[];
if(function_exists('audit_log_table_stats')) $stats = audit_log_table_stats($pdo);

$actionColor=['create'=>'#10b981','update'=>'#f59e0b','delete'=>'#ef4444'];

topx('Denetim Günlüğü');
?>
<p class="muted" style="font-size:13px">Kritik finansal işlemlerin (hesap/hareket güncelleme-silme) değişmez kaydı. Kim-ne zaman-ne değiştirdi.</p>

<?php if($stats): ?>
<div class="df-panel">
    <b><?=ds_icon('info',16)?> İstatistikler</b>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <?php foreach($stats as $table=>$count): ?>
        <span class="df-badge df-badge--info"><?=(int)$count?> · <?=htmlspecialchars($table)?></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<details class="df-panel" style="margin-top:10px">
  <summary style="font-weight:900;cursor:pointer"><?=ds_icon('filter',16)?> Filtrele</summary>
  <form method="get" style="margin-top:10px">
    <label>Tablo</label>
    <select name="table">
        <option value="">— Tüm Tablolar —</option>
        <?php foreach($tables as $t): ?>
        <option value="<?=htmlspecialchars($t)?>" <?=$tableFilter===$t?'selected':''?>><?=htmlspecialchars($t)?></option>
        <?php endforeach; ?>
    </select>
    <label>Kullanıcı</label>
    <select name="user">
        <option value="">— Tüm Kullanıcılar —</option>
        <?php foreach($users as $u): ?>
        <option value="<?=(int)$u['user_id']?>" <?=$userFilter==(int)$u['user_id']?'selected':''?>><?=htmlspecialchars($u['full_name'] ?? 'Bilinmiyor')?></option>
        <?php endforeach; ?>
    </select>
    <label>Başlangıç Tarihi</label>
    <input type="date" name="date_from" value="<?=htmlspecialchars($dateFrom)?>">
    <label>Bitiş Tarihi</label>
    <input type="date" name="date_to" value="<?=htmlspecialchars($dateTo)?>">
    <div style="display:flex;gap:8px;margin-top:10px">
        <button type="submit" class="df-btn df-btn--primary" style="flex:1;justify-content:center"><?=ds_icon('search',15)?> Ara</button>
        <a href="audit_log.php" class="df-btn df-btn--secondary" style="flex:1;justify-content:center">Sıfırla</a>
    </div>
  </form>
</details>

<?php if($logs): ?>
<div class="muted" style="font-size:12px;margin:14px 4px 6px;font-weight:700">Kayıtlar (son <?=count($logs)?>)</div>
<?php foreach($logs as $log): $ac=$actionColor[$log['action']] ?? '#94a3b8'; ?>
<div class="df-panel" style="margin-top:10px;border-left:3px solid <?=$ac?>">
    <span style="background:<?=$ac?>22;color:<?=$ac?>;border-radius:8px;padding:3px 8px;font-size:11px;font-weight:900"><?=htmlspecialchars(strtoupper($log['action']))?></span>
    <div class="df-list-row-title" style="margin-top:6px"><?=htmlspecialchars($log['table_name'])?>#<?=(int)$log['record_id']?></div>
    <div class="df-list-row-meta" style="margin-top:4px"><?=ds_icon('user',13)?> <?=htmlspecialchars($log['user_id'] ? 'Kullanıcı ID:'.$log['user_id'] : 'Sistem')?><span>🌐 <?=htmlspecialchars($log['ip_address'] ?? '—')?></span><span><?=ds_icon('calendar',13)?> <?=htmlspecialchars(date('d.m.Y H:i:s', strtotime($log['created_at'])))?></span></div>
    <?php if($log['old_value'] || $log['new_value']): ?>
    <details style="margin-top:8px">
        <summary style="font-size:12px;color:var(--df-accent);cursor:pointer">Değerler</summary>
        <?php if($log['old_value']): ?><div style="background:var(--df-surface-sunken);border-radius:8px;padding:8px;margin-top:6px;font-size:11px;font-family:monospace;word-break:break-all"><b>Eski:</b><br><?=htmlspecialchars($log['old_value'])?></div><?php endif; ?>
        <?php if($log['new_value']): ?><div style="background:var(--df-surface-sunken);border-radius:8px;padding:8px;margin-top:6px;font-size:11px;font-family:monospace;word-break:break-all"><b>Yeni:</b><br><?=htmlspecialchars($log['new_value'])?></div><?php endif; ?>
    </details>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<?php ds_empty_state('Denetim kaydı bulunamadı.', null, ds_icon('info',20)); ?>
<?php endif; ?>
<?php botx(); ?>
