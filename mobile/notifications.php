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
<div class="panel" style="display:flex;gap:8px;padding:10px;margin-top:10px">
  <form method="post" style="flex:1"><?=csrf_field()?><input type="hidden" name="clear" value="1"><button type="submit" class="btn" style="width:100%;background:#334155;color:#fff">Okunanları Sil</button></form>
  <form method="post" style="flex:1" onsubmit="return confirm('Tüm bildirimler silinsin mi?')"><?=csrf_field()?><input type="hidden" name="clearall" value="1"><button type="submit" class="btn" style="width:100%;background:#7f1d1d;color:#fff">Tümünü Sil</button></form>
</div>
<?php
try{
  // İLETİŞİM MERKEZİ (2026-07-17): SADECE kişisel bildirimler (target_user_id=$ME) — genel/
  // broadcast bildirimler artık duyurular.php'de. Görüntülenince okundu yap — SADECE bu sayfada
  // gösterilen (kişisel) alt küme için (notif_mark_all_read() kullanılmadı, o genel bildirimleri
  // de işaretlerdi — Duyurular sekmesindeki "Yeni" rozetinin sessizce sıfırlanmasını önler).
  $rows=array_values(array_filter(notif_list_for_user($pdo,$ME,80), function($n){ return $n['target_user_id']!==null; }));
  foreach($rows as $__n){ try{ notif_mark_read($pdo,$ME,(int)$__n['id']); }catch(Throwable $e){} }
  if(!$rows){ echo '<div class="panel muted" style="text-align:center">Bildirim yok 🔕</div>'; }
  // UX SPRINT-001 (2026-07-04): kart artık sadece özet gösterir ve tamamı tıklanabilir —
  // tekil aksiyonlar (Sil, İlgili Modüle Git) sadece notification_view.php'de. Liste ekranı
  // sadece listeleme amacı taşır (bkz. PROJECT_RULES.md yeni UX standardı).
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
