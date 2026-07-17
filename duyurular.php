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

<div class="panel-head">
<h1>İletişim Merkezi</h1>
</div>

<?php ic_tabs('duyurular'); ?>

<section class="panel" style="margin-top:16px">
<?php foreach($rows as $n): ?>
<div class="notice-card <?=$n['effective_is_read']?'':'unread'?>">
    <div class="panel-head">
        <div>
            <b><?=h($n['title'])?></b>
            <?=$n['effective_is_read']?badge('Okundu','gray'):badge('Yeni','orange')?>
            <br>
            <span class="muted"><?=h($n['created_at'])?></span>
        </div>
        <div style="display:flex;gap:6px">
            <a class="btn small" href="duyurular.php?read=<?=(int)$n['id']?>">Aç</a>
            <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="del" value="<?=(int)$n['id']?>"><button type="submit" class="btn small secondary" title="Sil">🗑️</button></form>
        </div>
    </div>
    <p><?=nl2br(h($n['message']))?></p>
</div>
<?php endforeach; ?>
<?php if(!$rows): ?><p class="muted">Henüz duyuru yok.</p><?php endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
