<?php
require_once __DIR__.'/boot.php';
require_login();
require_permission('users');
require_once __DIR__.'/share_lib.php';
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
        // Henüz gerçek bir konuşma satırı yok — ilk mesaj gönderilene kadar sanal/boş bir
        // konuşma nesnesi kullanılır, DB'ye önceden boş satır yazılmaz.
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

// --- AJAX: poll (yeni mesaj var mı) ---
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

$q=trim($_GET['listq'] ?? '');
$lsql="SELECT c.*, ct.name contact_name FROM wa_conversations c LEFT JOIN contacts ct ON ct.id=c.contact_id";
$lparams=[];
if($q!==''){ $lsql.=" WHERE ct.name LIKE ? OR c.phone LIKE ?"; $lparams=['%'.$q.'%','%'.$q.'%']; }
$lsql.=" ORDER BY c.last_message_at DESC";
$lst=$pdo->prepare($lsql); $lst->execute($lparams);
$convList=$lst->fetchAll();

require_once __DIR__.'/layout_top.php';

if(!$conv){
    echo "<h1>Konuşma bulunamadı</h1>";
    require __DIR__.'/layout_bottom.php';
    exit;
}
$lastMsgId = $messages ? (int)end($messages)['id'] : 0;
?>
<style>
/* İLETİŞİM MERKEZİ — SON UI BİRLİĞİ (2026-07-18): messages.php ile BİREBİR AYNI blok — bkz.
   wa_conversations.php'deki aynı notu. Canlı polling JS'i (.bubble/#waThread yerine artık
   .bubble/#waThread AYNI kalıyor, sadece dış çerçeve sınıfları isim değiştirdi) aşağıda
   DOKUNULMADAN duruyor — WhatsApp gönderim altyapısı değişmedi. */
.msg-wrap{display:grid;grid-template-columns:320px 1fr;gap:16px;align-items:start}
.msg-list{background:var(--df-surface);border-radius:var(--df-radius-lg);box-shadow:var(--df-elevation-raised);padding:12px;max-height:74vh;overflow:auto}
.msg-list .lbl{font-size:11px;color:var(--df-ink-500);letter-spacing:.06em;font-weight:900;margin:6px 8px;text-transform:uppercase}
.msg-row{display:flex;align-items:center;gap:11px;padding:11px;border-radius:var(--df-radius-md);text-decoration:none;color:var(--df-ink-900)}
.msg-row:hover{background:var(--df-surface-sunken)}
.msg-row.active{background:var(--df-accent-soft)}
.msg-row .av{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto}
.msg-row .meta{flex:1;min-width:0}
.msg-row .meta b{display:block;font-size:14px}
.msg-row .meta small{display:block;color:var(--df-ink-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-row .badge.green{background:var(--df-success);color:#06281a;min-width:22px;justify-content:center}
.chat-panel{background:var(--df-surface);border-radius:var(--df-radius-lg);box-shadow:var(--df-elevation-raised);display:flex;flex-direction:column;min-height:74vh;max-height:74vh}
.chat-head{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--df-hairline)}
.chat-head .av{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;flex:0 0 auto}
.chat-body{flex:1;overflow:auto;padding:18px;display:flex;flex-direction:column;gap:8px;background:var(--df-surface-sunken)}
.bubble{max-width:72%;padding:10px 13px;border-radius:16px;font-size:14px;line-height:1.4;word-wrap:break-word;white-space:pre-wrap}
.bubble small{display:block;font-size:10px;opacity:.65;margin-top:4px;text-align:right}
.bubble.mine{align-self:flex-end;background:var(--df-accent);color:var(--df-accent-ink);border-bottom-right-radius:5px}
.bubble.theirs{align-self:flex-start;background:var(--df-surface);border:1px solid var(--df-hairline);color:var(--df-ink-900);border-bottom-left-radius:5px}
.composer{display:flex;gap:10px;padding:14px;border-top:1px solid var(--df-hairline)}
.composer textarea{flex:1;border:1px solid var(--df-hairline);border-radius:var(--df-radius-md);padding:11px;resize:none;font-family:inherit;font-size:14px;min-height:46px;max-height:140px;background:var(--df-surface);color:var(--df-ink-900)}
.composer button{flex:0 0 auto}
@media(max-width:960px){ .msg-wrap{grid-template-columns:1fr} .chat-panel,.msg-list{max-height:none} }
</style>

<?php
ds_page_header('WhatsApp', ds_icon('chat',24), 'İletişim Merkezi',
    ($conv['contact_real_id']?ds_button('👥 Cari Kartı','contact_view.php?id='.(int)$conv['contact_real_id'],'secondary','','',true):'').
    ds_button('Tüm Konuşmalar','wa_conversations.php','secondary','','',true), false, true);
ic_tabs('whatsapp');
?>

<div class="msg-wrap" style="margin-top:16px">
  <div class="msg-list">
    <?=wa_new_conversation_picker_html($pdo)?>
    <form method="get" style="margin:0 6px 10px">
      <input type="hidden" name="id" value="<?=(int)($conv['id'] ?? 0)?>">
      <input type="text" name="listq" placeholder="İsim veya telefon ara…" value="<?=h($q)?>" onchange="this.form.submit()">
    </form>
    <div class="lbl">Konuşmalar</div>
    <?=wa_conversation_list_html($convList, $conv['id'] ?? 0)?>
  </div>

  <div class="chat-panel">
    <div class="chat-head">
      <div class="av" style="background:<?=ic_avatar_color((int)($conv['contact_real_id'] ?: crc32($conv['phone'])))?>"><?=h(mb_strtoupper(mb_substr($conv['contact_name'] ?: $conv['phone'],0,1)))?></div>
      <div style="flex:1">
        <b style="font-size:16px"><?=h($conv['contact_name'] ?: $conv['phone'])?></b>
        <div class="df-muted" style="font-size:13px"><?=h($conv['phone'])?><?php if($conv['contact_type']): ?> · <?=h($conv['contact_type'])?><?php endif; ?><?php if(!empty($conv['authorized_person'])): ?> · Yetkili: <?=h($conv['authorized_person'])?><?php endif; ?></div>
      </div>
    </div>

    <div class="chat-body" id="waThread">
    <?php if(!$messages): ?>
      <div class="chat-empty">Henüz mesaj yok.<br>İlk mesajı siz yazın 👇</div>
    <?php else: foreach($messages as $m): $mine=$m['direction']==='outbound'; ?>
      <div class="bubble <?=$mine?'mine':'theirs'?>" data-id="<?=(int)$m['id']?>">
        <?=h($m['body'])?><?php if($m['media_url']): ?><br>📎 <a href="<?=h($m['media_url'])?>" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline"><?=h($m['media_type']?:'Medya')?></a><?php endif; ?>
        <small><?=h(date('d.m.Y H:i',strtotime($m['created_at'])))?></small>
      </div>
    <?php endforeach; endif; ?>
    </div>

    <div class="composer">
      <?=emoji_picker_html('waComposeText')?>
      <textarea id="waComposeText" rows="1" placeholder="Mesajınızı yazın…"></textarea>
      <button type="button" class="df-btn df-btn--primary" id="waSendBtn">➤ Gönder</button>
    </div>
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
    if(m.media_url) html += '<br>📎 <a href="'+escHtml(m.media_url)+'" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">'+escHtml(m.media_type||'Medya')+'</a>';
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

<?php require_once __DIR__.'/layout_bottom.php'; ?>
