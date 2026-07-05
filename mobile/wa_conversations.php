<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
wa_install();

$pdo=db();
$rows=$pdo->query("SELECT c.*, ct.name contact_name FROM wa_conversations c
    LEFT JOIN contacts ct ON ct.id=c.contact_id
    ORDER BY c.last_message_at DESC")->fetchAll();

topx('WhatsApp Konuşmaları');
?>
<?php if(!$rows): ?>
<div class="panel muted">Henüz WhatsApp konuşması yok.</div>
<?php else: foreach($rows as $r): $preview=mb_substr((string)($r['last_message_preview']??''),0,60); ?>
<a class="item" href="wa_conversation_view.php?id=<?=(int)$r['id']?>" style="display:flex;justify-content:space-between;align-items:center;gap:8px">
<div style="min-width:0">
<b><?=htmlspecialchars($r['contact_name'] ?: $r['phone'])?></b><br>
<span class="muted" style="font-size:13px"><?=($r['last_direction']==='outbound'?'Siz: ':'')?><?=htmlspecialchars($preview)?></span>
</div>
<div style="text-align:right;white-space:nowrap">
<?php if((int)$r['unread_count']>0): ?><span style="background:#dc2626;color:#fff;border-radius:10px;padding:2px 7px;font-size:11px;font-weight:800"><?=(int)$r['unread_count']?></span><br><?php endif; ?>
<small class="muted"><?=htmlspecialchars($r['last_message_at']?date('d.m H:i',strtotime($r['last_message_at'])):'')?></small>
</div>
</a>
<?php endforeach; endif; ?>
<?php botx(); ?>
