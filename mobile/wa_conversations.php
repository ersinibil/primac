<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
wa_install();

$pdo=db();
$q=trim($_GET['q'] ?? '');
$sql="SELECT c.*, ct.name contact_name FROM wa_conversations c LEFT JOIN contacts ct ON ct.id=c.contact_id";
$params=[];
if($q!==''){ $sql.=" WHERE ct.name LIKE ? OR c.phone LIKE ?"; $params=['%'.$q.'%','%'.$q.'%']; }
$sql.=" ORDER BY c.last_message_at DESC";
$st=$pdo->prepare($sql); $st->execute($params);
$rows=$st->fetchAll();

$personnel=$pdo->query("SELECT name,phone FROM personnel WHERE COALESCE(active,1)=1 AND phone<>'' ORDER BY name")->fetchAll();
$contactsAll=$pdo->query("SELECT name,phone FROM contacts WHERE phone<>'' ORDER BY name")->fetchAll();

topx('WhatsApp Konuşmaları');
ic_tabs('whatsapp');
?>
<div class="df-panel">
<select onchange="if(this.value) window.location='wa_conversation_view.php?phone='+encodeURIComponent(this.value)">
    <option value="">➕ Yeni Konuşma — kişi seç…</option>
    <?php if($personnel): ?><optgroup label="Personel"><?php foreach($personnel as $p): ?><option value="<?=h($p['phone'])?>"><?=h($p['name'])?> — <?=h($p['phone'])?></option><?php endforeach; ?></optgroup><?php endif; ?>
    <?php if($contactsAll): ?><optgroup label="Cari"><?php foreach($contactsAll as $c): ?><option value="<?=h($c['phone'])?>"><?=h($c['name'])?> — <?=h($c['phone'])?></option><?php endforeach; ?></optgroup><?php endif; ?>
</select>
<form method="get" action="wa_conversation_view.php" style="display:flex;gap:6px;margin-top:8px">
<input type="text" name="phone" placeholder="veya telefon yazın…" style="flex:1;margin:0">
<button type="submit" class="df-btn df-btn--secondary">Git</button>
</form>
</div>
<form method="get" style="margin-bottom:10px">
<input type="text" name="q" placeholder="İsim veya telefon ara…" value="<?=h($q)?>" onchange="this.form.submit()">
</form>
<?php if(!$rows): ?>
<?php ds_empty_state('Henüz WhatsApp konuşması yok.', null, ds_icon('chat',32)); ?>
<?php else: ?>
<div class="df-list">
<?php foreach($rows as $r):
  $preview=mb_substr((string)($r['last_message_preview']??''),0,60);
  $__title=h($r['contact_name'] ?: $r['phone']);
  $__desc=h(($r['last_direction']==='outbound'?'Siz: ':'').$preview);
  $__meta='';
  if((int)$r['unread_count']>0) $__meta.='<span class="df-badge df-badge--danger">'.(int)$r['unread_count'].'</span>';
  $__meta.='<span class="df-list-row-due">'.h($r['last_message_at']?date('d.m H:i',strtotime($r['last_message_at'])):'').'</span>';
  ds_list_item($__title, 'wa_conversation_view.php?id='.(int)$r['id'], $__desc, $__meta);
endforeach; ?>
</div>
<?php endif; ?>
<?php botx(); ?>
