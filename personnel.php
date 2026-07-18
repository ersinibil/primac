<?php
require_once __DIR__.'/personnel_lib.php';
require_once __DIR__.'/layout_top.php';

// RELEASE 0.9 — Personel Ekranları DS Migration (2026-07-17): sayfa artık ds_lib.php'nin
// PROVEN (search.php/mytasks.php/job_view.php'de zaten canlıda kullanılan) bileşenlerine
// dayanıyor — df-table/df-form-group gibi bugüne kadar hiç canlı kullanılmamış sınıflar
// BİLEREK tercih edilmedi. Kart ızgarası kendi başına kalıyor (bu ekranın kendi deseni), ama
// artık hardcoded renk/spacing yerine DS token'larını (--df-*) kullanıyor.
ds_page_header('Personel', ds_icon('users',24), '', ds_button('Yeni Personel','personnel_new.php','primary','','',true), false, true);
?>

<div class="df-personnel-grid">
<?php
try{
    // app_users LEFT JOIN — kart üzerindeki "Mesaj Gönder" butonu için bağlı kullanıcı hesabı var mı bakılıyor.
    $rows=db()->query("SELECT p.*,
                        (SELECT u.id FROM app_users u WHERE u.personnel_id=p.id ORDER BY u.id LIMIT 1) AS linked_user_id
                        FROM personnel p
                        ORDER BY p.active DESC, p.name ASC")->fetchAll();
    foreach($rows as $r){
        $pid=(int)$r['id'];
        $openTasks=0;
        try{
            $s=db()->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND status!='Tamamlandı' AND deleted_at IS NULL");
            $s->execute([$pid]);
            $openTasks=(int)($s->fetch()['c'] ?? 0);
        }catch(Throwable $e){}
        $todayTasks=0;
        try{
            $s=db()->prepare("SELECT COUNT(*) c FROM tasks WHERE personnel_id=? AND due_date=CURDATE() AND deleted_at IS NULL");
            $s->execute([$pid]);
            $todayTasks=(int)($s->fetch()['c'] ?? 0);
        }catch(Throwable $e){}
        ?>
        <div class="df-personnel-card">
            <div class="df-personnel-card-head">
                <div class="df-personnel-avatar"><?=h(personnel_initials($r['name']))?></div>
                <div class="df-personnel-id">
                    <strong><?=h($r['name'])?></strong>
                    <span class="df-personnel-role"><?=h($r['role'] ?: 'Personel')?></span>
                </div>
                <?=$r['active']?ds_badge('Aktif','green'):ds_badge('Pasif','red')?>
            </div>
            <div class="df-personnel-info">
                <div><?=ds_icon('phone',14)?> <?=h($r['phone'] ?: '-')?></div>
                <div>✉️ <?=h($r['email'] ?? '' ?: '-')?></div>
            </div>
            <div class="df-personnel-stats">
                <div><strong><?=$todayTasks?></strong><small>Bugünkü Görev</small></div>
                <div><strong><?=$openTasks?></strong><small>Açık Görev</small></div>
            </div>
            <div class="df-personnel-actions">
                <?=ds_button('Detay','personnel_edit.php?id='.$pid,'secondary','df-btn--sm','',true)?>
                <?=ds_button('Görevler','personnel_edit.php?id='.$pid.'&tab=gorevler','secondary','df-btn--sm','',true)?>
                <?php if(!empty($r['linked_user_id'])): ?>
                <?=ds_button('Mesaj Gönder','messages.php?u='.(int)$r['linked_user_id'],'secondary','df-btn--sm','',true)?>
                <?php endif; ?>
                <?=ds_button('Performans','kpi.php','secondary','df-btn--sm','',true)?>
            </div>
        </div>
        <?php
    }
    if(!$rows) ds_empty_state('Henüz personel yok.', 'Yeni Personel butonuyla ilk personeli ekleyebilirsiniz.', ds_icon('users',32));
}catch(Throwable $e){
    echo ds_alert('danger', $e->getMessage());
}
?>
</div>

<style>
body.nav-compact .df-personnel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:var(--df-space-4);margin:var(--df-space-4) 0 var(--df-space-5)}
body.nav-compact .df-personnel-card{background:var(--df-surface);border:1px solid var(--df-hairline);border-radius:var(--df-radius-lg);box-shadow:var(--df-elevation-raised);padding:var(--df-space-4);display:flex;flex-direction:column;gap:var(--df-space-3)}
body.nav-compact .df-personnel-card-head{display:flex;align-items:center;gap:var(--df-space-3)}
body.nav-compact .df-personnel-avatar{width:46px;height:46px;border-radius:var(--df-radius-md);background:var(--df-ink-900);color:var(--df-surface);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px;flex:0 0 auto}
body.nav-compact .df-personnel-id{display:flex;flex-direction:column;flex:1;min-width:0}
body.nav-compact .df-personnel-id strong{font-size:var(--df-type-subtitle-size);color:var(--df-ink-900)}
body.nav-compact .df-personnel-role{color:var(--df-ink-500);font-size:var(--df-type-caption-size)}
body.nav-compact .df-personnel-info{display:flex;flex-direction:column;gap:4px;font-size:var(--df-type-caption-size);color:var(--df-ink-600)}
body.nav-compact .df-personnel-info .df-icon{vertical-align:-2px;margin-right:2px}
body.nav-compact .df-personnel-stats{display:flex;gap:var(--df-space-2)}
body.nav-compact .df-personnel-stats>div{flex:1;background:var(--df-surface-sunken);border-radius:var(--df-radius-md);padding:var(--df-space-2) var(--df-space-3);text-align:center}
body.nav-compact .df-personnel-stats strong{display:block;font-size:18px;color:var(--df-ink-900)}
body.nav-compact .df-personnel-stats small{color:var(--df-ink-500);font-size:11px}
body.nav-compact .df-personnel-actions{display:flex;flex-wrap:wrap;gap:var(--df-space-2)}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
