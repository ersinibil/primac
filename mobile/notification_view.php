<?php
require_once 'common.php';
require_once __DIR__.'/../notifications_lib.php';
// UX SPRINT-001 (2026-07-04) — Bildirim Detayı ekranı. Liste ekranı sadece listeler, tekil
// aksiyonlar (Sil, İlgili Modüle Git) sadece burada sunulur (bkz. PROJECT_RULES.md UX standardı).
$pdo=db();
$id=(int)($_GET['id']??0);
$n=notif_get_for_user($pdo,$ME,$id);
if(!$n){ header('Location: notifications.php'); exit; }

$t=notif_type_info($n['title']);
$go=!empty($n['action_url']) ? $n['action_url'] : 'index.php';
// Bazı bildirimler (örn. daily_reminder_lib.php) sadece web kökünde var olan bir sayfaya link
// veriyor — mobile/ altında aynı isimde dosya yoksa web köküne çık (notifications.php'den taşındı).
$goFile=explode('?',$go,2)[0];
if($goFile!=='' && strpos($go,'../')!==0 && !file_exists(__DIR__.'/'.$goFile) && file_exists(__DIR__.'/../'.$goFile)){
    $go='../'.$go;
}

topx('Bildirim Detayı');
?>
<div class="panel">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px">
    <div class="notif-icon <?=$t['color']?>" style="width:48px;height:48px;font-size:24px"><?=$t['icon']?></div>
    <div style="min-width:0">
      <div class="notif-type"><?=htmlspecialchars($t['label'])?></div>
      <b style="font-size:17px"><?=htmlspecialchars($t['title'])?></b>
    </div>
  </div>
  <?php if(!empty($n['message'])): ?>
  <div style="margin-top:12px;line-height:1.55"><?=notif_linkify($n['message'])?></div>
  <?php endif; ?>
  <div class="muted" style="margin-top:14px;font-size:13px">🕒 <?=htmlspecialchars(date('d.m.Y H:i', strtotime($n['created_at']??'now')))?></div>
</div>
<div class="panel" style="display:flex;flex-direction:column;gap:8px">
  <a class="btn dark" style="text-align:center" href="<?=htmlspecialchars($go)?>">İlgili Modüle Git</a>
  <a class="btn" style="text-align:center;background:#7f1d1d;color:#fff" href="notifications.php?del=<?=(int)$n['id']?>" onclick="return confirm('Bu bildirim silinsin mi?')">Sil</a>
</div>
<?php botx(); ?>
