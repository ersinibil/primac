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

// P0 MOBİL SHELL KAPANIŞI (2026-07-18): Bildirim Detayı → Bildirimler listesine deterministik
// döner (bkz. common.php::topx() notu).
topx('Bildirim Detayı', 'notifications.php', 'Bildirimler');
?>
<div class="df-panel">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px">
    <div class="notif-icon <?=$t['color']?>" style="width:48px;height:48px;font-size:24px"><?=$t['icon']?></div>
    <div style="min-width:0">
      <div class="notif-type"><?=h($t['label'])?></div>
      <b style="font-size:17px"><?=h($t['title'])?></b>
    </div>
  </div>
  <?php if(!empty($n['message'])): ?>
  <div style="margin-top:12px;line-height:1.55"><?=notif_linkify($n['message'])?></div>
  <?php endif; ?>
  <div class="muted" style="margin-top:14px;font-size:13px"><?=ds_icon('calendar',13)?> <?=h(date('d.m.Y H:i', strtotime($n['created_at']??'now')))?></div>
</div>
<div class="df-panel" style="display:flex;flex-direction:column;gap:8px">
  <a class="df-btn df-btn--primary df-btn--lg" style="width:100%" href="<?=h($go)?>">İlgili Modüle Git</a>
  <form method="post" action="notifications.php" onsubmit="return confirm('Bu bildirim silinsin mi?')"><?=csrf_field()?><input type="hidden" name="del" value="<?=(int)$n['id']?>"><button type="submit" class="df-btn df-btn--danger" style="width:100%"><?=ds_icon('trash',16)?> Sil</button></form>
</div>
<?php botx(); ?>
