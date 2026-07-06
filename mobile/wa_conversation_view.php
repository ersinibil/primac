<?php
require_once 'common.php';
if(!user_can('users')){ header('Location: index.php'); exit; }
require_once __DIR__.'/../share_lib.php';
wa_install();

$pdo=db();
$id=(int)($_GET['id'] ?? 0);
$phoneParam=_wa_normalize_phone($_GET['phone'] ?? '');

$convSql="SELECT c.*, ct.name contact_name, ct.id contact_real_id, ct.authorized_person, ct.type contact_type
    FROM wa_conversations c LEFT JOIN contacts ct ON ct.id=c.contact_id WHERE ";
$conv=null;
if($id){
    $stc=$pdo->prepare($convSql."c.id=?"); $stc->execute([$id]);
    $conv=$stc->fetch();
} elseif($phoneParam){
    $stc=$pdo->prepare($convSql."c.phone=?"); $stc->execute([$phoneParam]);
    $conv=$stc->fetch();
    if(!$conv){
        $contactId=wa_match_contact_by_phone($phoneParam);
        $cRow=null;
        if($contactId){
            $cq=$pdo->prepare("SELECT name,authorized_person,type FROM contacts WHERE id=?");
            $cq->execute([$contactId]); $cRow=$cq->fetch();
        }
        $conv=[
            'id'=>0,'phone'=>$phoneParam,'contact_id'=>$contactId,'contact_real_id'=>$contactId,
            'contact_name'=>$cRow['name']??null,'authorized_person'=>$cRow['authorized_person']??null,
            'contact_type'=>$cRow['type']??null,'unread_count'=>0,
        ];
    }
}

// --- AJAX: mesaj gönder ---
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['ajax_send'] ?? '')==='1'){
    header('Content-Type: application/json; charset=utf-8');
    $sendPhone=_wa_normalize_phone($_POST['phone'] ?? ($conv['phone'] ?? ''));
    $text=trim($_POST['message'] ?? '');
    if(!$sendPhone || $text===''){
        echo json_encode(['ok'=>false,'error'=>'Telefon veya mesaj eksik.']); exit;
    }
    $sent=wa_send_logged($sendPhone,$text,'wa_conversation');
    if(!$sent){
        echo json_encode(['ok'=>false,'error'=>'Gönderilemedi (WhatsApp Ayarları\'nda gateway kurulu/etkin olmayabilir).']); exit;
    }
    $cid=$pdo->prepare("SELECT id FROM wa_conversations WHERE phone=?"); $cid->execute([$sendPhone]);
    $convId2=(int)$cid->fetchColumn();
    $mr=$pdo->prepare("SELECT * FROM wa_messages WHERE conversation_id=? ORDER BY id DESC LIMIT 1");
    $mr->execute([$convId2]); $row=$mr->fetch();
    echo json_encode(['ok'=>true,'conversation_id'=>$convId2,'message'=>[
        'id'=>(int)$row['id'],'direction'=>$row['direction'],'body'=>$row['body'],
        'media_url'=>$row['media_url'],'media_type'=>$row['media_type'],'created_at'=>$row['created_at'],
    ]]);
    exit;
}

// --- AJAX: poll ---
if(($_GET['poll'] ?? '')==='1'){
    header('Content-Type: application/json; charset=utf-8');
    $after=(int)($_GET['after'] ?? 0);
    $convId=(int)($conv['id'] ?? 0);
    if(!$convId){ echo json_encode(['ok'=>true,'messages'=>[]]); exit; }
    $st=$pdo->prepare("SELECT * FROM wa_messages WHERE conversation_id=? AND id>? ORDER BY id ASC");
    $st->execute([$convId,$after]);
    $rows=$st->fetchAll();
    if($rows){
        try{
            $pdo->prepare("UPDATE wa_messages SET is_read=1 WHERE conversation_id=? AND id>?")->execute([$convId,$after]);
            $pdo->prepare("UPDATE wa_conversations SET unread_count=0 WHERE id=?")->execute([$convId]);
        }catch(Throwable $e){}
    }
    echo json_encode(['ok'=>true,'messages'=>array_map(function($r){
        return ['id'=>(int)$r['id'],'direction'=>$r['direction'],'body'=>$r['body'],
            'media_url'=>$r['media_url'],'media_type'=>$r['media_type'],'created_at'=>$r['created_at']];
    },$rows)]);
    exit;
}

// --- Normal sayfa render ---
if($conv && (int)$conv['id']>0 && (int)$conv['unread_count']>0){
    try{ $pdo->prepare("UPDATE wa_conversations SET unread_count=0 WHERE id=?")->execute([$conv['id']]); }catch(Throwable $e){}
    try{ $pdo->prepare("UPDATE wa_messages SET is_read=1 WHERE conversation_id=?")->execute([$conv['id']]); }catch(Throwable $e){}
}

$messages=[];
if($conv && (int)$conv['id']>0){
    $stm=$pdo->prepare("SELECT * FROM wa_messages WHERE conversation_id=? ORDER BY id ASC");
    $stm->execute([$conv['id']]);
    $messages=$stm->fetchAll();
}

topx($conv['contact_name'] ?? 'Konuşma');
if(!$conv){ echo '<div class="panel err">Konuşma bulunamadı.</div>'; botx(); exit; }
$lastMsgId = $messages ? (int)end($messages)['id'] : 0;
?>
<style>
.wa-thread{display:flex;flex-direction:column;gap:8px;padding:4px 2px;max-height:52vh;overflow-y:auto}
.bubble{max-width:80%;padding:10px 13px;border-radius:18px;font-size:15px;line-height:1.35;word-wrap:break-word;white-space:pre-wrap}
.bubble small{display:block;font-size:10px;opacity:.6;margin-top:3px;text-align:right}
.bubble.mine{align-self:flex-end;background:#2563eb;color:#fff;border-bottom-right-radius:5px}
.bubble.theirs{align-self:flex-start;background:rgba(255,255,255,.12);border-bottom-left-radius:5px}
.wa-compose{display:flex;gap:8px;align-items:flex-end;padding:10px 0 0}
.wa-compose textarea{flex:1;resize:none;margin:0;max-height:100px}
.wa-compose button.icon{border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.08);color:#fff;border-radius:10px;width:40px;height:40px;font-size:16px;cursor:not-allowed;opacity:.5;flex:0 0 auto}
</style>

<div class="panel">
<b><?=htmlspecialchars($conv['contact_name'] ?: $conv['phone'])?></b><br>
<span class="muted"><?=htmlspecialchars($conv['phone'])?><?php if($conv['contact_type']): ?> · <?=htmlspecialchars($conv['contact_type'])?><?php endif; ?></span>
<?php if(!empty($conv['authorized_person'])): ?><br><span class="muted">Yetkili: <?=htmlspecialchars($conv['authorized_person'])?></span><?php endif; ?>
</div>

<div class="grid">
<?php if($conv['contact_real_id']): ?><a class="card blue" href="contact_view.php?id=<?=(int)$conv['contact_real_id']?>"><span>👥</span><b>Cari Kartı</b></a><?php endif; ?>
<a class="card teal" href="wa_conversations.php"><span>💬</span><b>Tüm Konuşmalar</b></a>
</div>

<div class="panel">
<div class="wa-thread" id="waThread">
<?php if(!$messages): ?>
<p class="muted">Bu konuşmada henüz mesaj yok.</p>
<?php else: foreach($messages as $m): $mine=$m['direction']==='outbound'; ?>
<div class="bubble <?=$mine?'mine':'theirs'?>" data-id="<?=(int)$m['id']?>">
<?=htmlspecialchars($m['body'])?><?php if($m['media_url']): ?><br>📎 <a href="<?=htmlspecialchars($m['media_url'])?>" target="_blank" rel="noopener" style="color:inherit"><?=htmlspecialchars($m['media_type']?:'Medya')?></a><?php endif; ?>
<small><?=htmlspecialchars(date('d.m.Y H:i',strtotime($m['created_at'])))?></small>
</div>
<?php endforeach; endif; ?>
</div>
<div class="wa-compose">
  <button type="button" class="icon" title="Yakında" disabled>😀</button>
  <button type="button" class="icon" title="Yakında" disabled>📎</button>
  <textarea id="waComposeText" rows="1" placeholder="Mesajınızı yazın…"></textarea>
  <button type="button" class="btn dark" id="waSendBtn">Gönder</button>
</div>
</div>

<script>
(function(){
  var convId = <?=(int)($conv['id'] ?? 0)?>;
  var phone = <?=json_encode($conv['phone'])?>;
  var lastId = <?=(int)$lastMsgId?>;
  var thread = document.getElementById('waThread');
  var textEl = document.getElementById('waComposeText');
  var sendBtn = document.getElementById('waSendBtn');

  function scrollBottom(){ thread.scrollTop = thread.scrollHeight; }
  scrollBottom();

  function escHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  function appendMessage(m){
    if(thread.querySelector('[data-id="'+m.id+'"]')) return;
    var placeholder = thread.querySelector('p.muted');
    if(placeholder) placeholder.remove();
    var div = document.createElement('div');
    div.className = 'bubble ' + (m.direction==='outbound' ? 'mine' : 'theirs');
    div.setAttribute('data-id', m.id);
    var html = escHtml(m.body||'');
    if(m.media_url) html += '<br>📎 <a href="'+escHtml(m.media_url)+'" target="_blank" rel="noopener" style="color:inherit">'+escHtml(m.media_type||'Medya')+'</a>';
    var d = new Date(m.created_at.replace(' ','T'));
    var dstr = isNaN(d.getTime()) ? '' : d.toLocaleString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    html += '<small>'+dstr+'</small>';
    div.innerHTML = html;
    thread.appendChild(div);
    lastId = Math.max(lastId, m.id);
    scrollBottom();
  }

  function doSend(){
    var text = textEl.value.trim();
    if(!text || !phone) return;
    sendBtn.disabled = true;
    var fd = new FormData();
    fd.append('ajax_send','1');
    fd.append('phone', phone);
    fd.append('message', text);
    fetch(window.location.pathname + '?id=' + convId, {
        method:'POST', credentials:'same-origin',
        headers:{'X-CSRF-Token': window.CSRF_TOKEN},
        body: fd
    }).then(function(r){ return r.json(); }).then(function(data){
        sendBtn.disabled = false;
        if(data.ok){
            appendMessage(data.message);
            textEl.value = '';
        } else {
            alert(data.error || 'Gönderilemedi.');
        }
    }).catch(function(){
        sendBtn.disabled = false;
        alert('Bağlantı hatası — sayfayı yenileyip tekrar deneyin.');
    });
  }
  sendBtn.addEventListener('click', doSend);
  textEl.addEventListener('keydown', function(e){
    if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); doSend(); }
  });

  if(convId){
    setInterval(function(){
        fetch(window.location.pathname + '?id=' + convId + '&poll=1&after=' + lastId, {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
                if(data.ok && data.messages && data.messages.length){
                    data.messages.forEach(appendMessage);
                }
            }).catch(function(){});
    }, 3000);
  }
})();
</script>
<?php botx(); ?>
