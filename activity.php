<?php
require_once __DIR__.'/boot.php';
require_login();
// Güvenlik: mobile/activity.php ile parite — tüm modüllerin (Finans dahil) aktivite akışını
// gösteriyor, modül bazlı filtre yok — sadece yönetici (2026-07-03 denetimi).
if(!is_admin()){ http_response_code(403); exit('Bu sayfa için yetkiniz yok.'); }

require_once __DIR__.'/layout_top.php';
require_once __DIR__.'/activity_lib.php';

$module=$_GET['module'] ?? '';
ds_page_header('Son İşlemler', ds_icon('menu-dots',24), '', ds_button('Ana Sayfa','dashboard.php','secondary','','',true), false, true);
?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<h2 class="df-section-title">İşlem Akışı</h2>
<?php
ds_tabs([
    ['label'=>'Tümü','url'=>'activity.php','active'=>$module===''],
    ['label'=>'İşler','url'=>'activity.php?module=İşler','active'=>$module==='İşler'],
    ['label'=>'Cari','url'=>'activity.php?module=Cari','active'=>$module==='Cari'],
    ['label'=>'Finans','url'=>'activity.php?module=Finans','active'=>$module==='Finans'],
    ['label'=>'Stok','url'=>'activity.php?module=Stok','active'=>$module==='Stok'],
    ['label'=>'Personel','url'=>'activity.php?module=Personel','active'=>$module==='Personel'],
    ['label'=>'Telegram','url'=>'activity.php?module=Telegram','active'=>$module==='Telegram'],
]);
?>

<div style="margin-top:var(--df-space-4)">
<?php
try{
    activity_install();
    if($module){
        $s=db()->prepare("SELECT * FROM activity_logs WHERE module=? ORDER BY id DESC LIMIT 150");
        $s->execute([$module]);
        $rows=$s->fetchAll();
    }else{
        $rows=activity_recent(150);
    }
    activity_render_list($rows);
}catch(Throwable $e){
    echo ds_alert('danger', $e->getMessage());
}
?>
</div>
</section>

<style>
body.nav-compact .df-section-title{font-size:var(--df-type-section-size);font-weight:var(--df-type-section-weight);color:var(--df-ink-900);margin:0 0 var(--df-space-3)}
</style>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
