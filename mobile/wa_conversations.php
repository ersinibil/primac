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

topx('WhatsApp');
ic_tabs('whatsapp');
?>
<style>
/* İLETİŞİM MERKEZİ — SON UI BİRLİĞİ (2026-07-18): mobile/messages.php'nin .chat-row/.av/.meta/
   .unread-badge DS diliyle BİREBİR AYNI — önceden genel-amaçlı ds_list_item() (görev/iş/stok
   listeleriyle aynı bileşen) kullanıyordu, sohbet yüzeyi kendi diline sahip değildi. */
.chat-row{display:flex;align-items:center;gap:12px;background:var(--df-surface-sunken,rgba(255,255,255,.08));border:1px solid var(--df-hairline,rgba(255,255,255,.12));border-radius:18px;padding:12px;text-decoration:none;color:var(--df-ink-900,#fff);min-width:0;margin-top:10px}
.chat-row .av{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto;font-size:17px}
.chat-row .meta{flex:1;min-width:0}
.chat-row .meta b{display:block;font-size:15px}
.chat-row .meta small{display:block;color:var(--c-muted,#94a3b8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.unread-badge{background:#16a34a;color:#fff;border-radius:10px;padding:3px 9px;font-size:12px;font-weight:800;flex:0 0 auto}
</style>
<?php function wa_avatar_color($id){ $c=['#3b82f6','#22c55e','#f97316','#8b5cf6','#ef4444','#14b8a6','#eab308','#ec4899']; return $c[((int)$id) % count($c)]; } ?>

<div class="df-panel" style="padding:10px">
  <a class="df-btn df-btn--primary" href="javascript:void(0)" style="width:100%;justify-content:center" onclick="var b=document.getElementById('waNewConvBox');b.style.display=b.style.display==='none'?'block':'none'">➕ Yeni Konuşma</a>
  <div id="waNewConvBox" style="display:none;margin-top:10px">
    <select onchange="if(this.value) window.location='wa_conversation_view.php?phone='+encodeURIComponent(this.value)">
        <option value="">Kayıtlı kişi seç…</option>
        <?php if($personnel): ?><optgroup label="Personel"><?php foreach($personnel as $p): ?><option value="<?=h($p['phone'])?>"><?=h($p['name'])?> — <?=h($p['phone'])?></option><?php endforeach; ?></optgroup><?php endif; ?>
        <?php if($contactsAll): ?><optgroup label="Cari"><?php foreach($contactsAll as $c): ?><option value="<?=h($c['phone'])?>"><?=h($c['name'])?> — <?=h($c['phone'])?></option><?php endforeach; ?></optgroup><?php endif; ?>
    </select>
    <form method="get" action="wa_conversation_view.php" style="display:flex;gap:6px">
    <input type="text" name="phone" placeholder="veya telefon numarası yazın…" style="flex:1;margin:0">
    <button type="submit" class="df-btn df-btn--secondary">Git</button>
    </form>
  </div>
</div>
<form method="get" style="margin-bottom:4px">
<input type="text" name="q" placeholder="İsim veya telefon ara…" value="<?=h($q)?>" onchange="this.form.submit()">
</form>
<?php if(!$rows): ?>
<?php ds_empty_state('Henüz WhatsApp konuşması yok.', null, ds_icon('chat',32)); ?>
<?php else: foreach($rows as $r):
  $preview=mb_substr((string)($r['last_message_preview']??''),0,42);
  $nm=$r['contact_name'] ?: $r['phone'];
?>
<a class="chat-row" href="wa_conversation_view.php?id=<?=(int)$r['id']?>">
  <div class="av" style="background:<?=wa_avatar_color((int)$r['id'])?>"><?=h(mb_strtoupper(mb_substr($nm,0,1)))?></div>
  <div class="meta">
    <b><?=h($nm)?></b>
    <small><?=($r['last_direction']==='outbound'?'Siz: ':'').($preview!==''?h($preview):'<span style="opacity:.6">'.h($r['phone']).'</span>')?></small>
  </div>
  <?php if((int)$r['unread_count']>0): ?><span class="unread-badge"><?=(int)$r['unread_count']?></span><?php endif; ?>
</a>
<?php endforeach; endif; ?>
<?php botx(); ?>
