<?php
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/notifications_lib.php';
require_once __DIR__.'/share_lib.php';

$pdo=db();
$ME=(int)(current_user()['id'] ?? 0);

// Sil/temizle işlemleri (parite, 2026-07-03 Sprint-001'de mobil ile eşitlendi) — SADECE oturum
// sahibinin kendi görünümünü etkiler: kişisel bildirim sahiplik kontrollü fiziksel silinir, genel
// (target_user_id=NULL) bildirim asla fiziksel silinmez, sadece BU kullanıcı için gizlenir
// (bkz. notifications_lib.php).
// SECURITY SPRINT-004 FAZ-3B (2026-07-05): GET ile veri değiştirme kapatıldı, POST+CSRF'e taşındı.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del'])){ notif_dismiss($pdo,$ME,(int)$_POST['del']); header('Location: notifications.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clear'])){ notif_dismiss_all_read($pdo,$ME); header('Location: notifications.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clearall'])){ notif_dismiss_all($pdo,$ME); header('Location: notifications.php'); exit; }

if(isset($_GET['read'])){
    $id=(int)$_GET['read'];
    notif_mark_read($pdo,$ME,$id);
    if(!empty($_GET['go'])){
        $go=$_GET['go'];
        if(preg_match('#^(https?:)?//#i',$go)) $go='dashboard.php'; // open redirect koruması: sadece site-içi göreli path
        // Bazı bildirimler mobil-sadece bir sayfaya link verebilir (mytasks.php artık web'de de
        // var, ama gelecekte benzer mobil-özel sayfalar olabilir) — web kökünde böyle bir dosya
        // yoksa 404 veriyordu. Bu, TEK giriş noktası
        // (herkes buradan geçiyor: dashboard.php'nin "Detay" butonu dahil) olduğu için düzeltme
        // burada merkezi olarak yapılıyor (2026-07-03: dashboard.php'nin kendi kopyası bu kontrolü
        // hiç yapmıyordu, aynı 404 orada da tekrarlanıyordu).
        $goFile=explode('?',$go,2)[0];
        if($goFile!=='' && strpos($go,'mobile/')!==0 && !file_exists(__DIR__.'/'.$goFile) && file_exists(__DIR__.'/mobile/'.$goFile)){
            $go='mobile/'.$go;
        }
        header("Location: ".$go);
        exit;
    }
}

if(isset($_GET['all_read'])){
    notif_mark_all_read($pdo,$ME);
    header("Location: notifications.php");
    exit;
}

require_once __DIR__.'/layout_top.php';

$rows=[];
try{
    // İLETİŞİM MERKEZİ (2026-07-17): Bildirimler artık SADECE kişisel bildirimleri gösterir
    // (target_user_id=$ME) — genel/broadcast bildirimler (target_user_id IS NULL) "Duyurular"
    // sekmesine ayrıldı (duyurular.php). notif_list_for_user() DEĞİŞMEDİ (hâlâ ikisini birlikte
    // döndürüyor, unread_notif() rozeti hâlâ ikisini birden sayıyor) — sadece BU sayfanın
    // render ettiği alt küme daraltıldı, veri katmanına dokunulmadı.
    $rows=array_values(array_filter(notif_list_for_user($pdo,$ME,100), function($n){ return $n['target_user_id']!==null; }));
}catch(Throwable $e){
    echo "<div class='alert'>".h($e->getMessage())."</div>";
}
?>

<?php
$__notifActions = ds_button('Tümünü Okundu Yap','notifications.php?all_read=1','secondary','','',true);
ob_start();
?>
<form method="post" style="display:inline"><input type="hidden" name="clear" value="1"><button type="submit" class="df-btn df-btn--secondary">Okunanları Sil</button></form>
<form method="post" style="display:inline" onsubmit="return confirm('Tüm bildirimlerin görünümden kaldırılmasını istediğine emin misin?')"><input type="hidden" name="clearall" value="1"><button type="submit" class="df-btn df-btn--secondary">Tümünü Sil</button></form>
<?php $__notifActions .= ob_get_clean(); ?>
<?php ds_page_header('İletişim Merkezi', ds_icon('bell',24), '', $__notifActions, false, true); ?>
<?php ic_tabs('bildirimler'); ?>

<section style="margin-top:var(--df-space-4)">
<?php foreach($rows as $n):
$go=$n['action_url'] ?: 'dashboard.php';
// Bazı bildirimler mobil-sadece bir sayfaya link verebilir (mytasks.php artık web'de de var, ama
// gelecekte benzer mobil-özel sayfalar olabilir) — web kökünde böyle bir dosya yoksa 404 veriyordu
// (2026-07-03 kullanıcı bildirimi). Hedef dosya web kökünde yoksa ama mobile/ altında varsa oraya yönlendir.
$goFile=explode('?',$go,2)[0];
if($goFile!=='' && strpos($go,'mobile/')!==0 && !file_exists(__DIR__.'/'.$goFile) && file_exists(__DIR__.'/mobile/'.$goFile)){
    $go='mobile/'.$go;
}
$readUrl='notifications.php?read='.$n['id'].'&go='.urlencode($go);
?>
<div class="df-card" style="margin-bottom:var(--df-space-3);background:<?=$n['effective_is_read']?'transparent':'var(--df-accent-soft)'?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--df-space-3)">
        <div>
            <b><?=h($n['title'])?></b>
            <?=$n['effective_is_read']?ds_badge('Okundu','gray'):ds_badge('Yeni','warning')?>
            <br>
            <span class="df-muted" style="font-size:12px"><?=h($n['created_at'])?></span>
        </div>
        <div style="display:flex;gap:6px;flex:0 0 auto">
            <a class="df-btn df-btn--secondary df-btn--sm" href="<?=h($readUrl)?>">Aç</a>
            <form method="post" style="display:inline"><input type="hidden" name="del" value="<?=(int)$n['id']?>"><button type="submit" class="df-btn df-btn--secondary df-btn--sm" title="Sil"><?=ds_icon('trash',14)?></button></form>
        </div>
    </div>
    <p style="margin:var(--df-space-2) 0 0"><?=nl2br(h($n['message']))?></p>
</div>
<?php endforeach; ?>
<?php if(!$rows): ?><?=ds_empty_state('Henüz bildirim yok.', null, ds_icon('bell',32))?><?php endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
