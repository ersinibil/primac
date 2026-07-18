<?php
/* İLETİŞİM MERKEZİ — Duyurular (mobil, 2026-07-17). Web duyurular.php ile aynı veri kaynağı
 * (internal_notifications, target_user_id IS NULL) — yeni iş mantığı yok. */
require_once 'common.php';
require_once __DIR__.'/../notifications_lib.php';
require_once __DIR__.'/../share_lib.php';
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del'])){ notif_dismiss($pdo,$ME,(int)$_POST['del']); header('Location: duyurular.php'); exit; }

// P0 DÜZELTME (2026-07-18, Product Owner): web duyurular.php ile aynı — admin şirket duyurusu
// yayınlayabilsin (önceden bu akış hiçbir yerde yoktu, bkz. web duyurular.php'deki not).
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['publish_announcement'])){
    if(is_admin()){
        $title=trim($_POST['title'] ?? '');
        $message=trim($_POST['message'] ?? '');
        if($title!=='' && $message!==''){
            try{
                $pdo->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,NULL,NULL,0)")
                    ->execute(['📢 '.$title,$message]);
                try{ if(function_exists('activity_log')) activity_log('Duyuru','Yayınlama',$title,'','announcement',null,'duyurular.php','📢'); }catch(Throwable $e){}
            }catch(Throwable $e){}
        }
    }
    header('Location: duyurular.php'); exit;
}

topx('İletişim Merkezi');
?>
<?php ic_tabs('duyurular'); ?>
<?php if($isAdmin): ?>
<details class="df-panel" style="margin-top:10px">
  <summary style="cursor:pointer;font-weight:700"><?=ds_icon('plus',16)?> Yeni Duyuru Yayınla</summary>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="publish_announcement" value="1">
    <label>Başlık</label><input name="title" required maxlength="160">
    <label>Mesaj</label><textarea name="message" rows="4" required></textarea>
    <button type="submit" class="df-btn df-btn--primary df-btn--lg" style="width:100%;margin-top:8px">📢 Yayınla</button>
  </form>
</details>
<?php endif; ?>
<?php
try{
  $rows=array_values(array_filter(notif_list_for_user($pdo,$ME,80), function($n){ return $n['target_user_id']===null; }));
  foreach($rows as $__n){ try{ notif_mark_read($pdo,$ME,(int)$__n['id']); }catch(Throwable $e){} }
  if(!$rows){ echo ds_empty_state('Henüz duyuru yok.', null, ds_icon('bell',32)); }
  foreach($rows as $n){
    $t=notif_type_info($n['title']);
    $msg=(string)($n['message']??'');
    $long=(mb_strlen($msg)>90 || substr_count($msg,"\n")>1);
    echo '<a class="df-panel" style="display:flex;gap:12px;align-items:flex-start;margin-top:10px" href="notification_view.php?id='.(int)$n['id'].'">';
    echo '<div style="flex:0 0 auto;width:42px;height:42px;border-radius:var(--df-radius-sm);display:flex;align-items:center;justify-content:center;font-size:20px;background:var(--df-surface-sunken)">'.$t['icon'].'</div>';
    echo '<div style="flex:1;min-width:0">';
    echo '<div class="df-list-row-meta" style="text-transform:uppercase;font-weight:800;font-size:11px">'.h($t['label']).'</div>';
    echo '<div class="df-list-row-title">'.h($t['title']).'</div>';
    if($msg!==''){
        echo '<div class="df-list-row-desc" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">'.h($msg).'</div>';
        if($long) echo '<span style="font-size:12px;color:var(--df-accent);font-weight:700;margin-top:4px;display:block">Devamını gör →</span>';
    }
    echo '<small class="df-text-caption" style="display:block;margin-top:4px">'.h($n['created_at']??'').'</small>';
    echo '</div></a>';
  }
}catch(Throwable $e){ echo ds_alert('danger',$e->getMessage()); }
botx();
