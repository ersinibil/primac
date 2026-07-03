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
<p class="small">Kritik finansal işlemlerin (hesap/hareket güncelleme-silme) değişmez kaydı. Kim-ne zaman-ne değiştirdi.</p>

<?php if($stats): ?>
<div class="panel">
    <b>İstatistikler</b>
    <div class="grid" style="margin-top:10px">
        <?php foreach($stats as $table=>$count): ?>
        <div class="card gray"><span>📊</span><b><?=(int)$count?></b><small><?=htmlspecialchars($table)?></small></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<details class="panel">
  <summary style="font-weight:900;cursor:pointer">🔎 Filtrele</summary>
  <form method="get" style="margin-top:10px">
    <label style="color:#94a3b8;font-size:12px">Tablo</label>
    <select name="table">
        <option value="">— Tüm Tablolar —</option>
        <?php foreach($tables as $t): ?>
        <option value="<?=htmlspecialchars($t)?>" <?=$tableFilter===$t?'selected':''?>><?=htmlspecialchars($t)?></option>
        <?php endforeach; ?>
    </select>
    <label style="color:#94a3b8;font-size:12px">Kullanıcı</label>
    <select name="user">
        <option value="">— Tüm Kullanıcılar —</option>
        <?php foreach($users as $u): ?>
        <option value="<?=(int)$u['user_id']?>" <?=$userFilter==(int)$u['user_id']?'selected':''?>><?=htmlspecialchars($u['full_name'] ?? 'Bilinmiyor')?></option>
        <?php endforeach; ?>
    </select>
    <label style="color:#94a3b8;font-size:12px">Başlangıç Tarihi</label>
    <input type="date" name="date_from" value="<?=htmlspecialchars($dateFrom)?>">
    <label style="color:#94a3b8;font-size:12px">Bitiş Tarihi</label>
    <input type="date" name="date_to" value="<?=htmlspecialchars($dateTo)?>">
    <div style="display:flex;gap:8px;margin-top:10px">
        <button type="submit" class="btn dark" style="flex:1">Ara</button>
        <a href="audit_log.php" class="btn" style="flex:1;text-align:center;background:rgba(255,255,255,.12);color:#fff">Sıfırla</a>
    </div>
  </form>
</details>

<?php if($logs): ?>
<div style="font-size:12px;color:#94a3b8;margin:10px 4px">Kayıtlar (son <?=count($logs)?>)</div>
<?php foreach($logs as $log): $ac=$actionColor[$log['action']] ?? '#94a3b8'; ?>
<div class="item" style="border-left:3px solid <?=$ac?>">
    <span style="background:<?=$ac?>22;color:<?=$ac?>;border-radius:8px;padding:3px 8px;font-size:11px;font-weight:900"><?=htmlspecialchars(strtoupper($log['action']))?></span>
    <b><?=htmlspecialchars($log['table_name'])?>#<?=(int)$log['record_id']?></b>
    <br><small>👤 <?=htmlspecialchars($log['user_id'] ? 'Kullanıcı ID:'.$log['user_id'] : 'Sistem')?> · 🌐 <?=htmlspecialchars($log['ip_address'] ?? '—')?> · 📅 <?=htmlspecialchars(date('d.m.Y H:i:s', strtotime($log['created_at'])))?></small>
    <?php if($log['old_value'] || $log['new_value']): ?>
    <details style="margin-top:8px">
        <summary style="font-size:12px;color:#60a5fa;cursor:pointer">Değerler</summary>
        <?php if($log['old_value']): ?><div style="background:rgba(255,255,255,.06);border-radius:8px;padding:8px;margin-top:6px;font-size:11px;font-family:monospace;word-break:break-all"><b>Eski:</b><br><?=htmlspecialchars($log['old_value'])?></div><?php endif; ?>
        <?php if($log['new_value']): ?><div style="background:rgba(255,255,255,.06);border-radius:8px;padding:8px;margin-top:6px;font-size:11px;font-family:monospace;word-break:break-all"><b>Yeni:</b><br><?=htmlspecialchars($log['new_value'])?></div><?php endif; ?>
    </details>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="panel muted" style="text-align:center">Denetim kaydı bulunamadı.</div>
<?php endif; ?>
<?php botx(); ?>
