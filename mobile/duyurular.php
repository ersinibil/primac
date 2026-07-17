<?php
/* İLETİŞİM MERKEZİ — Duyurular (mobil, 2026-07-17). Web duyurular.php ile aynı veri kaynağı
 * (internal_notifications, target_user_id IS NULL) — yeni iş mantığı yok. */
require_once 'common.php';
require_once __DIR__.'/../notifications_lib.php';
require_once __DIR__.'/../share_lib.php';
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del'])){ notif_dismiss($pdo,$ME,(int)$_POST['del']); header('Location: duyurular.php'); exit; }

topx('İletişim Merkezi');
?>
<?php ic_tabs('duyurular'); ?>
<?php
try{
  $rows=array_values(array_filter(notif_list_for_user($pdo,$ME,80), function($n){ return $n['target_user_id']===null; }));
  foreach($rows as $__n){ try{ notif_mark_read($pdo,$ME,(int)$__n['id']); }catch(Throwable $e){} }
  if(!$rows){ echo '<div class="panel muted" style="text-align:center;margin-top:10px">Henüz duyuru yok 📢</div>'; }
  foreach($rows as $n){
    $t=notif_type_info($n['title']);
    $msg=(string)($n['message']??'');
    $long=(mb_strlen($msg)>90 || substr_count($msg,"\n")>1);
    echo '<a class="item notif-card" href="notification_view.php?id='.(int)$n['id'].'">';
    echo '<div class="notif-icon '.$t['color'].'">'.$t['icon'].'</div>';
    echo '<div class="notif-body">';
    echo '<div class="notif-type">'.htmlspecialchars($t['label']).'</div>';
    echo '<b>'.htmlspecialchars($t['title']).'</b>';
    if($msg!==''){
        echo '<div class="notif-summary">'.htmlspecialchars($msg).'</div>';
        if($long) echo '<span class="notif-more">Devamını gör →</span>';
    }
    echo '<small class="muted" style="display:block;margin-top:4px">'.htmlspecialchars($n['created_at']??'').'</small>';
    echo '</div></a>';
  }
}catch(Throwable $e){ echo '<div class="err">'.htmlspecialchars($e->getMessage()).'</div>'; }
botx();
