<?php
require_once 'common.php';
require_once __DIR__.'/../notifications_lib.php';
$pdo=db();
// Silme / temizleme işlemleri (çıktıdan önce) — SADECE oturum sahibinin kendi görünümünü etkiler:
// kişisel bildirim fiziksel silinir (sahiplik kontrollü), genel (target_user_id=NULL) bildirim
// hiçbir zaman fiziksel silinmez, sadece BU kullanıcı için gizlenir (bkz. notifications_lib.php,
// Sprint-001 — eskiden 'clear'/'clearall' TÜM kullanıcıların bildirimlerini siliyordu).
if(isset($_GET['del'])){ notif_dismiss($pdo,$ME,(int)$_GET['del']); header('Location: notifications.php'); exit; }
if(isset($_GET['clear'])){ notif_dismiss_all_read($pdo,$ME); header('Location: notifications.php'); exit; }
if(isset($_GET['clearall'])){ notif_dismiss_all($pdo,$ME); header('Location: notifications.php'); exit; }
// Görüntülenince okundu yap (sadece bana ait + genel — genel olan artık SADECE benim için okundu sayılır)
notif_mark_all_read($pdo,$ME);

topx('Bildirimler');
?>
<div class="panel" style="display:flex;gap:8px;padding:10px">
  <a class="btn" style="flex:1;background:#334155;color:#fff" href="notifications.php?clear=1">Okunanları Sil</a>
  <a class="btn" style="flex:1;background:#7f1d1d;color:#fff" href="notifications.php?clearall=1" onclick="return confirm('Tüm bildirimler silinsin mi?')">Tümünü Sil</a>
</div>
<?php
try{
  $rows=notif_list_for_user($pdo,$ME,80);
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
