<?php
require_once 'common.php';
require_once __DIR__.'/../notifications_lib.php';
require_once __DIR__.'/../share_lib.php';
$pdo=db();
// Silme / temizleme işlemleri (çıktıdan önce) — SADECE oturum sahibinin kendi görünümünü etkiler:
// kişisel bildirim fiziksel silinir (sahiplik kontrollü), genel (target_user_id=NULL) bildirim
// hiçbir zaman fiziksel silinmez, sadece BU kullanıcı için gizlenir (bkz. notifications_lib.php,
// Sprint-001 — eskiden 'clear'/'clearall' TÜM kullanıcıların bildirimlerini siliyordu).
// SECURITY SPRINT-004 FAZ-3B (2026-07-05): GET ile veri değiştirme kapatıldı, POST+CSRF'e taşındı.
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del'])){ notif_dismiss($pdo,$ME,(int)$_POST['del']); header('Location: notifications.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clear'])){ notif_dismiss_all_read($pdo,$ME); header('Location: notifications.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clearall'])){ notif_dismiss_all($pdo,$ME); header('Location: notifications.php'); exit; }

topx('İletişim Merkezi');
?>
<?php ic_tabs('bildirimler'); ?>
<div style="display:flex;gap:8px;margin-top:10px">
  <form method="post" style="flex:1"><input type="hidden" name="clear" value="1"><button type="submit" class="df-btn df-btn--secondary" style="width:100%">Okunanları Sil</button></form>
  <form method="post" style="flex:1" onsubmit="return confirm('Tüm bildirimler silinsin mi?')"><input type="hidden" name="clearall" value="1"><button type="submit" class="df-btn df-btn--danger" style="width:100%">Tümünü Sil</button></form>
</div>
<?php
try{
  // İLETİŞİM MERKEZİ (2026-07-17): SADECE kişisel bildirimler (target_user_id=$ME) — genel/
  // broadcast bildirimler artık duyurular.php'de. Görüntülenince okundu yap — SADECE bu sayfada
  // gösterilen (kişisel) alt küme için (notif_mark_all_read() kullanılmadı, o genel bildirimleri
  // de işaretlerdi — Duyurular sekmesindeki "Yeni" rozetinin sessizce sıfırlanmasını önler).
  $rows=array_values(array_filter(notif_list_for_user($pdo,$ME,80), function($n){ return $n['target_user_id']!==null; }));
  foreach($rows as $__n){ try{ notif_mark_read($pdo,$ME,(int)$__n['id']); }catch(Throwable $e){} }
  if(!$rows){ echo ds_empty_state('Bildirim yok.', null, ds_icon('bell',32)); }
  // UX SPRINT-001 (2026-07-04): kart artık sadece özet gösterir ve tamamı tıklanabilir —
  // tekil aksiyonlar (Sil, İlgili Modüle Git) sadece notification_view.php'de. Liste ekranı
  // sadece listeleme amacı taşır (bkz. PROJECT_RULES.md yeni UX standardı).
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
