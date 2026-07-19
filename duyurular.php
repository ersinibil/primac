<?php
/* İLETİŞİM MERKEZİ — Duyurular (2026-07-17, Product Owner kararı).
 * Yeni bir veri kaynağı/iş mantığı İCAT EDİLMEDİ: internal_notifications tablosunda zaten var olan
 * target_user_id IS NULL (genel/broadcast) bildirimler, notifications.php'nin (artık sadece
 * kişisel bildirimleri gösteren) listesinden ayrılıp burada gösteriliyor. Okundu/sil işlemleri
 * notifications_lib.php'nin AYNI, değişmemiş fonksiyonlarını kullanıyor. */
require_once __DIR__.'/boot.php';
require_login();
require_once __DIR__.'/notifications_lib.php';
require_once __DIR__.'/share_lib.php';

$pdo=db();
$ME=(int)(current_user()['id'] ?? 0);

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del'])){ notif_dismiss($pdo,$ME,(int)$_POST['del']); header('Location: duyurular.php'); exit; }

// OTONOM KAPANIŞ PASS (2026-07-19): notif_admin_delete_global() zaten yazılmıştı ama hiçbir UI'ye
// bağlanmamıştı — "Sil" (notif_dismiss) genel duyuruda SADECE o admin için gizliyor, satır DB'de
// kalıcı kalıyor. Yanlışlıkla yayınlanan bir duyuruyu herkes için geri almanın hiçbir yolu yoktu.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_global'])){
    if(is_admin()) notif_admin_delete_global($pdo,(int)$_POST['del_global']);
    header('Location: duyurular.php'); exit;
}

// P0 DÜZELTME (2026-07-18, Product Owner): "Duyurular = Yönetim tarafından yayınlanan şirket
// duyuruları" — önceden bu sekme SADECE var olan target_user_id=NULL bildirimleri filtreliyordu,
// ama hiçbir yerde gerçek bir "duyuru yayınla" akışı yoktu (tek kaynak public_file.php'nin müşteri
// onay/red bildirimiydi — bir "duyuru" değil). Bu, yeni bir tablo/veri modeli İCAT ETMEDEN aynı
// internal_notifications mekanizmasını (target_user_id=NULL = herkese) admin'e açan minimal bir
// yazma yolu ekler.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['publish_announcement'])){
    if(!is_admin()){ header('Location: duyurular.php'); exit; }
    $title=trim($_POST['title'] ?? '');
    $message=trim($_POST['message'] ?? '');
    if($title!=='' && $message!==''){
        try{
            $pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,NULL,NULL,0)")
                ->execute(['📢 '.$title,$message]);
            try{ if(function_exists('activity_log')) activity_log('Duyuru','Yayınlama',$title,'','announcement',null,'duyurular.php','📢'); }catch(Throwable $e){}
        }catch(Throwable $e){}
    }
    header('Location: duyurular.php'); exit;
}

if(isset($_GET['read'])){
    $id=(int)$_GET['read'];
    notif_mark_read($pdo,$ME,$id);
    header('Location: duyurular.php');
    exit;
}

require_once __DIR__.'/layout_top.php';

$rows=[];
try{
    $rows=array_values(array_filter(notif_list_for_user($pdo,$ME,100), function($n){ return $n['target_user_id']===null; }));
}catch(Throwable $e){
    echo "<div class='alert'>".h($e->getMessage())."</div>";
}
?>

<?php ds_page_header('İletişim Merkezi', ds_icon('bell',24), '', '', false, true); ?>
<?php ic_tabs('duyurular'); ?>

<?php if(is_admin()): ?>
<section class="df-card" style="margin-top:var(--df-space-4)">
<details>
<summary style="cursor:pointer;font-weight:700"><?=ds_icon('plus',16)?> Yeni Duyuru Yayınla</summary>
<form method="post" style="margin-top:var(--df-space-3);max-width:560px">
<input type="hidden" name="publish_announcement" value="1">
<?php ds_form_field('Başlık', '<input name="title" required maxlength="160">'); ?>
<?php ds_form_field('Mesaj', '<textarea name="message" rows="4" required></textarea>'); ?>
<button class="df-btn df-btn--primary" style="margin-top:var(--df-space-2)">📢 Yayınla</button>
</form>
</details>
</section>
<?php endif; ?>

<section class="df-card" style="margin-top:var(--df-space-4)">
<?php foreach($rows as $n): ?>
<div class="df-card" style="margin-bottom:var(--df-space-3);background:<?=$n['effective_is_read']?'transparent':'var(--df-accent-soft)'?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--df-space-3)">
        <div>
            <b><?=h($n['title'])?></b>
            <?=$n['effective_is_read']?ds_badge('Okundu','gray'):ds_badge('Yeni','warning')?>
            <br>
            <span class="df-muted" style="font-size:12px"><?=h($n['created_at'])?></span>
        </div>
        <div style="display:flex;gap:6px;flex:0 0 auto">
            <a class="df-btn df-btn--secondary df-btn--sm" href="duyurular.php?read=<?=(int)$n['id']?>">Aç</a>
            <form method="post" style="display:inline"><input type="hidden" name="del" value="<?=(int)$n['id']?>"><button type="submit" class="df-btn df-btn--secondary df-btn--sm" title="Benden gizle"><?=ds_icon('trash',14)?></button></form>
            <?php if(is_admin()): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Bu duyuru HERKES için kalıcı olarak silinecek. Emin misin?')"><input type="hidden" name="del_global" value="<?=(int)$n['id']?>"><button type="submit" class="df-btn df-btn--secondary df-btn--sm" title="Herkes için kalıcı sil"><?=ds_icon('trash',14)?> Tümü</button></form>
            <?php endif; ?>
        </div>
    </div>
    <p style="margin:var(--df-space-2) 0 0"><?=nl2br(h($n['message']))?></p>
</div>
<?php endforeach; ?>
<?php if(!$rows): ?><?=ds_empty_state('Henüz duyuru yok.', null, ds_icon('bell',32))?><?php endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
