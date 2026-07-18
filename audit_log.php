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
.audit-record{background:var(--df-surface);border-radius:var(--df-radius-md);border-left:4px solid var(--df-info);padding:14px;margin:10px 0;box-shadow:var(--df-elevation-raised)}
.audit-record.update{border-left-color:var(--df-warning)}
.audit-record.delete{border-left-color:var(--df-danger)}
.audit-record.create{border-left-color:var(--df-success)}
.audit-action{display:inline-block;padding:4px 8px;border-radius:8px;font-size:12px;font-weight:700;margin-right:8px}
.audit-action.create{background:var(--df-success-soft);color:var(--df-success-ink)}
.audit-action.update{background:var(--df-warning-soft);color:var(--df-warning-ink)}
.audit-action.delete{background:var(--df-danger-soft);color:var(--df-danger-ink)}
.audit-details{background:var(--df-surface-sunken);border-radius:8px;padding:10px;margin:10px 0;font-size:13px;color:var(--df-ink-900);font-family:monospace}
.audit-details summary{cursor:pointer;font-weight:700;color:var(--df-accent)}
.audit-timestamp{color:var(--df-ink-500);font-size:12px;margin-top:8px}
</style>

<?php ds_page_header('Denetim Günlüğü', ds_icon('search',24), 'Kritik finansal işlemlerin (hesap/hareket güncelleme-silme) değişmez kaydı. Kim-ne zaman-ne değiştirdi.', '', false, true); ?>

<?php if($stats): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
    <h3 class="df-section-title">İstatistikler</h3>
    <div class="df-audit-stats">
        <?php foreach($stats as $table=>$count): ?>
        <div class="df-card" style="padding:14px">
            <small><?=htmlspecialchars($table)?></small>
            <strong><?=(int)$count?></strong>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
    <h3 class="df-section-title">Filtreler</h3>
    <form method="get" class="df-form-grid-4">
        <?php
        $__tableOpts='<option value="">— Tüm Tablolar —</option>';
        foreach($tables as $t){ $__tableOpts.='<option value="'.htmlspecialchars($t).'" '.($tableFilter===$t?'selected':'').'>'.htmlspecialchars($t).'</option>'; }
        ds_form_field('Tablo', '<select name="table">'.$__tableOpts.'</select>');

        $__userOpts='<option value="">— Tüm Kullanıcılar —</option>';
        foreach($users as $u){ $__userOpts.='<option value="'.(int)$u['user_id'].'" '.($userFilter==(int)$u['user_id']?'selected':'').'>'.htmlspecialchars($u['full_name'] ?? 'Bilinmiyor').'</option>'; }
        ds_form_field('Kullanıcı', '<select name="user">'.$__userOpts.'</select>');

        ds_form_field('Başlangıç Tarihi', '<input type="date" name="date_from" value="'.htmlspecialchars($dateFrom).'">');
        ds_form_field('Bitiş Tarihi', '<input type="date" name="date_to" value="'.htmlspecialchars($dateTo).'">');
        ?>
        <div class="df-form-span-4" style="display:flex;gap:8px">
        <button type="submit" class="df-btn df-btn--primary">Ara</button>
        <?=ds_button('Sıfırla','audit_log.php','secondary','','',true)?>
        </div>
    </form>
</section>

<?php if($logs): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
    <h3 class="df-section-title">Kayıtlar (son <?=count($logs)?>)</h3>
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
</section>
<?php else: ?>
<div style="margin-top:var(--df-space-4)"><?=ds_alert('danger','Denetim kaydı bulunamadı.')?></div>
<?php endif; ?>

<style>
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
body.nav-compact .df-audit-stats{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:var(--df-space-4)}
body.nav-compact .df-form-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0 var(--df-space-4);align-items:end}
body.nav-compact .df-form-span-4{grid-column:1 / -1}
@media(max-width:960px){body.nav-compact .df-audit-stats,body.nav-compact .df-form-grid-4{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){body.nav-compact .df-audit-stats,body.nav-compact .df-form-grid-4{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
