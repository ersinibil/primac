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
?>
<div class="panel">
<select onchange="if(this.value) window.location='wa_conversation_view.php?phone='+encodeURIComponent(this.value)">
    <option value="">➕ Yeni Konuşma — kişi seç…</option>
    <?php if($personnel): ?><optgroup label="Personel"><?php foreach($personnel as $p): ?><option value="<?=htmlspecialchars($p['phone'])?>"><?=htmlspecialchars($p['name'])?> — <?=htmlspecialchars($p['phone'])?></option><?php endforeach; ?></optgroup><?php endif; ?>
    <?php if($contactsAll): ?><optgroup label="Cari"><?php foreach($contactsAll as $c): ?><option value="<?=htmlspecialchars($c['phone'])?>"><?=htmlspecialchars($c['name'])?> — <?=htmlspecialchars($c['phone'])?></option><?php endforeach; ?></optgroup><?php endif; ?>
</select>
<form method="get" action="wa_conversation_view.php" style="display:flex;gap:6px;margin-top:8px">
<input type="text" name="phone" placeholder="veya telefon yazın…" style="flex:1;margin:0">
<button type="submit" class="btn" style="width:auto;padding:10px 14px">Git</button>
</form>
</div>
<form method="get" style="margin-bottom:10px">
<input type="text" name="q" placeholder="İsim veya telefon ara…" value="<?=htmlspecialchars($q)?>" onchange="this.form.submit()">
</form>
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
