<?php
require_once __DIR__.'/boot.php';
require_login();
require_permission('users');
require_once __DIR__.'/share_lib.php';
wa_install();

$pdo=db();
$rows=$pdo->query("SELECT c.*, ct.name contact_name FROM wa_conversations c
    LEFT JOIN contacts ct ON ct.id=c.contact_id
    ORDER BY c.last_message_at DESC")->fetchAll();

require_once __DIR__.'/layout_top.php';
?>
<div class="panel-head">
<h1>💬 WhatsApp Konuşmaları</h1>
<a class="btn secondary" href="wa_send_now.php">📤 Yeni Mesaj</a>
</div>

<section class="panel">
<?php if(!$rows): ?>
<p class="muted">Henüz WhatsApp konuşması yok.</p>
<?php else: foreach($rows as $r): $preview=mb_substr((string)($r['last_message_preview']??''),0,70); ?>
<a href="wa_conversation_view.php?id=<?=(int)$r['id']?>" style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 4px;border-bottom:1px solid #eef2f6;text-decoration:none;color:inherit">
<div style="min-width:0">
<b><?=h($r['contact_name'] ?: $r['phone'])?></b><br>
<span class="muted" style="font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:340px">
<?=($r['last_direction']==='outbound'?'Siz: ':'')?><?=h($preview)?>
</span>
</div>
<div style="text-align:right;white-space:nowrap">
<?php if((int)$r['unread_count']>0): ?><span style="background:#dc2626;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:800"><?=(int)$r['unread_count']?></span><br><?php endif; ?>
<small class="muted"><?=h($r['last_message_at']?date('d.m H:i',strtotime($r['last_message_at'])):'')?></small>
</div>
</a>
<?php endforeach; endif; ?>
</section>

<?php require_once __DIR__.'/layout_bottom.php'; ?>
