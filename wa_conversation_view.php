<?php
require_once __DIR__.'/boot.php';
require_login();
require_permission('users');
require_once __DIR__.'/share_lib.php';
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

require_once __DIR__.'/layout_top.php';

if(!$conv){
    echo "<h1>Konuşma bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}
?>
<style>
.wa-thread{display:flex;flex-direction:column;gap:8px;padding:14px;max-height:60vh;overflow-y:auto;background:#f8fafc;border-radius:14px}
.bubble{max-width:72%;padding:10px 13px;border-radius:16px;font-size:14px;line-height:1.4;word-wrap:break-word;white-space:pre-wrap}
.bubble small{display:block;font-size:10px;opacity:.65;margin-top:4px;text-align:right}
.bubble.mine{align-self:flex-end;background:#2563eb;color:#fff;border-bottom-right-radius:5px}
.bubble.theirs{align-self:flex-start;background:#fff;border:1px solid #eef2f6;color:#101828;border-bottom-left-radius:5px}
</style>

<div class="panel-head">
<h1><?=h($conv['contact_name'] ?: $conv['phone'])?></h1>
<div class="actions">
<?php if($conv['contact_real_id']): ?><a class="btn secondary" href="contact_view.php?id=<?=(int)$conv['contact_real_id']?>">👥 Cari Kartı</a><?php endif; ?>
<a class="btn" href="wa_send_now.php?phone=<?=h($conv['phone'])?>">📤 Mesaj Gönder</a>
<a class="btn secondary" href="wa_conversations.php">Konuşmalar</a>
</div>
</div>

<section class="panel">
<p class="muted" style="margin-top:0"><?=h($conv['phone'])?></p>
<?php if(!$messages): ?>
<p class="muted">Bu konuşmada henüz mesaj yok.</p>
<?php else: ?>
<div class="wa-thread">
<?php foreach($messages as $m): $mine=$m['direction']==='outbound'; ?>
<div class="bubble <?=$mine?'mine':'theirs'?>">
<?=h($m['body'])?><?php if($m['media_url']): ?><br>📎 <a href="<?=h($m['media_url'])?>" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline"><?=h($m['media_type']?:'Medya')?></a><?php endif; ?>
<small><?=h(date('d.m.Y H:i',strtotime($m['created_at'])))?></small>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
