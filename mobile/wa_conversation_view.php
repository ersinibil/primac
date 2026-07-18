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

// P0 MOBİL SHELL KAPANIŞI (2026-07-18): WhatsApp Konuşma Detayı → WhatsApp listesine deterministik
// döner (bkz. common.php::topx() notu).
topx($conv['contact_name'] ?? 'Konuşma', 'wa_conversations.php');
if(!$conv){ echo ds_alert('danger','Konuşma bulunamadı.'); botx(); exit; }
$lastMsgId = $messages ? (int)end($messages)['id'] : 0;
?>
<style>
/* İLETİŞİM MERKEZİ — SON UI BİRLİĞİ (2026-07-18): mobile/messages.php'nin sohbet balonu + sabit
 * (fixed) klavye-farkında composer deseniyle BİREBİR AYNI (chat-mode, visualViewport pin/unpin) —
 * önceden sayfa-içi sabit-olmayan bir kompozisyon çubuğuydu, artık native sohbet hissi veriyor.
 * WhatsApp gönderim/polling JS'i (.bubble/#waThread seçicileri, ajax_send/poll uçları) HİÇ
 * değişmedi — sadece dış çerçeve ve kompozisyon çubuğunun konumlanması DS'e taşındı.
 */
.bubble{max-width:80%;padding:10px 13px;border-radius:18px;font-size:15px;line-height:1.35;word-wrap:break-word;white-space:pre-wrap}
.bubble small{display:block;font-size:10px;opacity:.6;margin-top:3px;text-align:right}
.bubble.mine{align-self:flex-end;background:#2563eb;color:#fff;border-bottom-right-radius:5px}
.bubble.theirs{align-self:flex-start;background:rgba(255,255,255,.12);border-bottom-left-radius:5px}
.thread{display:flex;flex-direction:column;gap:8px;padding-bottom:8px}
.composer{position:fixed;left:0;right:0;bottom:0;background:#071326;border-top:1px solid rgba(255,255,255,.12);padding:8px 8px calc(8px + env(safe-area-inset-bottom));z-index:1001}
.composer .wrap{max-width:520px;margin:auto;display:flex;gap:8px;align-items:flex-end}
.composer textarea{flex:1;margin:0;resize:none;max-height:100px}
.composer button.send{flex:0 0 auto;width:50px;height:46px;border-radius:14px;font-size:18px}
body.chat-mode{padding-bottom:0}
body.chat-mode .thread{padding-bottom:88px}
</style>

<div class="df-panel">
<div style="display:flex;align-items:center;gap:12px">
  <div style="width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto;background:<?=wa_avatar_color2((int)($conv['contact_real_id'] ?: crc32($conv['phone'])))?>"><?=h(mb_strtoupper(mb_substr($conv['contact_name'] ?: $conv['phone'],0,1)))?></div>
  <div style="min-width:0">
    <b><?=h($conv['contact_name'] ?: $conv['phone'])?></b><br>
    <span class="muted"><?=h($conv['phone'])?><?php if($conv['contact_type']): ?> · <?=h($conv['contact_type'])?><?php endif; ?></span>
    <?php if(!empty($conv['authorized_person'])): ?><br><span class="muted">Yetkili: <?=h($conv['authorized_person'])?></span><?php endif; ?>
  </div>
</div>
</div>

<div style="display:flex;gap:8px;margin-bottom:10px">
<?php if($conv['contact_real_id']): ?><?=ds_button(ds_icon('users',15).' Cari Kartı','contact_view.php?id='.(int)$conv['contact_real_id'],'secondary','','style="flex:1;justify-content:center"',true)?><?php endif; ?>
<?=ds_button(ds_icon('chat',15).' Tüm Konuşmalar','wa_conversations.php','secondary','','style="flex:1;justify-content:center"',true)?>
</div>

<div class="thread" id="waThread">
<?php if(!$messages): ?>
<p class="muted">Bu konuşmada henüz mesaj yok.</p>
<?php else: foreach($messages as $m): $mine=$m['direction']==='outbound'; ?>
<div class="bubble <?=$mine?'mine':'theirs'?>" data-id="<?=(int)$m['id']?>">
<?=h($m['body'])?><?php if($m['media_url']): ?><br>📎 <a href="<?=h($m['media_url'])?>" target="_blank" rel="noopener" style="color:inherit"><?=h($m['media_type']?:'Medya')?></a><?php endif; ?>
<small><?=h(date('d.m.Y H:i',strtotime($m['created_at'])))?></small>
</div>
<?php endforeach; endif; ?>
</div>

<div class="composer" id="waComposer">
  <div class="wrap">
    <?=emoji_picker_html('waComposeText', true)?>
    <textarea id="waComposeText" rows="1" placeholder="Mesajınızı yazın…" oninput="this.style.height='';this.style.height=this.scrollHeight+'px'"></textarea>
    <button type="button" class="df-btn df-btn--primary send" id="waSendBtn">➤</button>
  </div>
</div>

<?php function wa_avatar_color2($id){ $c=['#3b82f6','#22c55e','#f97316','#8b5cf6','#ef4444','#14b8a6','#eab308','#ec4899']; return $c[((int)$id) % count($c)]; } ?>

<script>
(function(){
  document.body.classList.add('chat-mode');
  var convId = <?=(int)($conv['id'] ?? 0)?>;
  var phone = <?=json_encode($conv['phone'])?>;
  var lastId = <?=(int)$lastMsgId?>;
  var thread = document.getElementById('waThread');
  var textEl = document.getElementById('waComposeText');
  var sendBtn = document.getElementById('waSendBtn');
  var composer = document.getElementById('waComposer');

  function scrollBottom(){ window.scrollTo(0, document.body.scrollHeight); }
  scrollBottom();

  // mobile/messages.php ile AYNI klavye-pin deseni (visualViewport) — kompozisyon çubuğu
  // klavye açıkken görünür alanın tam dibinde kalır.
  function pinComposer(){
    if(!composer||!window.visualViewport) return;
    var v=window.visualViewport;
    composer.style.top=(v.offsetTop+v.height-composer.offsetHeight)+'px';
    composer.style.bottom='auto'; composer.style.paddingBottom='8px';
  }
  function unpinComposer(){
    if(!composer) return;
    composer.style.top='auto'; composer.style.bottom='0'; composer.style.paddingBottom='';
  }
  if(window.visualViewport){
    window.visualViewport.addEventListener('resize', function(){ pinComposer(); scrollBottom(); });
    window.visualViewport.addEventListener('scroll', pinComposer);
  }
  textEl.addEventListener('focus', function(){ setTimeout(function(){ pinComposer(); scrollBottom(); },250); });
  textEl.addEventListener('blur', function(){ setTimeout(unpinComposer,100); });

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
