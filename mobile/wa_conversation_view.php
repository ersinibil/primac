<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
wa_install();

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$stc=$pdo->prepare("SELECT c.*, ct.name contact_name, ct.id contact_real_id FROM wa_conversations c
    LEFT JOIN contacts ct ON ct.id=c.contact_id WHERE c.id=?");
$stc->execute([$id]);
$conv=$stc->fetch();

if($conv && (int)$conv['unread_count']>0){
    try{ $pdo->prepare("UPDATE wa_conversations SET unread_count=0 WHERE id=?")->execute([$id]); }catch(Throwable $e){}
    try{ $pdo->prepare("UPDATE wa_messages SET is_read=1 WHERE conversation_id=?")->execute([$id]); }catch(Throwable $e){}
}

$messages=[];
if($conv){
    $stm=$pdo->prepare("SELECT * FROM wa_messages WHERE conversation_id=? ORDER BY id ASC");
    $stm->execute([$id]);
    $messages=$stm->fetchAll();
}

topx($conv['contact_name'] ?? 'Konuşma');
if(!$conv){ echo '<div class="panel err">Konuşma bulunamadı.</div>'; botx(); exit; }
?>
<style>
.wa-thread{display:flex;flex-direction:column;gap:8px;padding:4px 2px}
.bubble{max-width:80%;padding:10px 13px;border-radius:18px;font-size:15px;line-height:1.35;word-wrap:break-word;white-space:pre-wrap}
.bubble small{display:block;font-size:10px;opacity:.6;margin-top:3px;text-align:right}
.bubble.mine{align-self:flex-end;background:#2563eb;color:#fff;border-bottom-right-radius:5px}
.bubble.theirs{align-self:flex-start;background:rgba(255,255,255,.12);border-bottom-left-radius:5px}
</style>

<div class="panel">
<b><?=htmlspecialchars($conv['contact_name'] ?: $conv['phone'])?></b><br>
<span class="muted"><?=htmlspecialchars($conv['phone'])?></span>
</div>

<div class="grid">
<?php if($conv['contact_real_id']): ?><a class="card blue" href="contact_view.php?id=<?=(int)$conv['contact_real_id']?>"><span>👥</span><b>Cari Kartı</b></a><?php endif; ?>
<a class="card green" href="wa_send_now.php?phone=<?=urlencode($conv['phone'])?>"><span>📤</span><b>Mesaj Gönder</b></a>
</div>

<?php if(!$messages): ?>
<div class="panel muted">Bu konuşmada henüz mesaj yok.</div>
<?php else: ?>
<div class="wa-thread">
<?php foreach($messages as $m): $mine=$m['direction']==='outbound'; ?>
<div class="bubble <?=$mine?'mine':'theirs'?>">
<?=htmlspecialchars($m['body'])?><?php if($m['media_url']): ?><br>📎 <a href="<?=htmlspecialchars($m['media_url'])?>" target="_blank" rel="noopener" style="color:inherit"><?=htmlspecialchars($m['media_type']?:'Medya')?></a><?php endif; ?>
<small><?=htmlspecialchars(date('d.m.Y H:i',strtotime($m['created_at'])))?></small>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php botx(); ?>
