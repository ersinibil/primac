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

// --- AJAX: KENDİ gönderdiğimiz bir WhatsApp mesajını kayıttan kaldır ---
// P0 MOBİL HOTFIX (2026-07-19, Product Owner talebi — "WhatsApp mesaj aksiyonları eksik") ARAŞTIRMA
// SONUCU: WhatsApp'ın resmi Business Platform API'si (Meta Cloud API) işletmenin gönderdiği bir
// mesajı silme/geri çekme (recall/unsend) veya düzenleme (edit) için HİÇBİR uç nokta SUNMUYOR —
// bu, platformun genel/dokümante edilmiş bir kısıtı, bu projeye özel değil. Bu projedeki gönderim
// katmanı (share_lib.php::wa_send()/wa_send_media()) UltraMsg (WhatsApp Web otomasyonu tabanlı,
// gayri-resmi bir sağlayıcı) VEYA ayarlardan girilen genel/özel bir gateway URL'i kullanıyor — ikisi
// için de bu kod tabanında delete/edit çağrısı YAZILMAMIŞ, ve UltraMsg'in güncel API yüzeyinde böyle
// bir uç nokta olup olmadığı bu ortamdan (canlı internet/hesap erişimi yok) DOĞRULANAMADI — bu yüzden
// UYDURULMADI. Bunun yerine dürüst/gerçek bir aksiyon uygulandı: bu buton müşterinin telefonundaki
// mesajı SİLMEZ/DEĞİŞTİRMEZ, SADECE bizim wa_messages log'umuzdan kaldırır (mobile/messages.php'nin
// kendi internal_messages sil/düzenle akışıyla AYNI risk sınıfı DEĞİL — oradaki mesajlar hiç dış
// sisteme gitmediği için "silmek" gerçekten siliyor; burada UI metni bu farkı AÇIKÇA belirtiyor).
// "Düzenle" hiç eklenmedi: zaten karşı tarafın telefonunda görünen metni değiştirmenin YOLU yok —
// sahte bir "düzenle" ekranı personeli mesajın karşı tarafta da değiştiğine inandırıp yanıltırdı.
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['ajax_delete_msg'] ?? '')==='1'){
    header('Content-Type: application/json; charset=utf-8');
    csrf_verify();
    $mid=(int)($_POST['id'] ?? 0);
    $convId=(int)($conv['id'] ?? 0);
    if(!$mid || !$convId){ echo json_encode(['ok'=>false,'error'=>'Geçersiz istek.']); exit; }
    try{
        $chk=$pdo->prepare("SELECT direction FROM wa_messages WHERE id=? AND conversation_id=?");
        $chk->execute([$mid,$convId]); $row=$chk->fetch();
        if(!$row){ echo json_encode(['ok'=>false,'error'=>'Mesaj bulunamadı.']); exit; }
        if($row['direction']!=='outbound'){ echo json_encode(['ok'=>false,'error'=>'Sadece kendi gönderdiğiniz mesajlar kayıttan kaldırılabilir.']); exit; }
        $pdo->prepare("DELETE FROM wa_messages WHERE id=?")->execute([$mid]);
        echo json_encode(['ok'=>true]);
    }catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>'Silinemedi.']); }
    exit;
}

// --- AJAX: konuşmayı kayıttan tamamen temizle — mobile/messages.php::delConv() ile AYNI desen,
// sadece bizim log'umuzu temizler, WhatsApp tarafında hiçbir etkisi yok (yukarıdaki notla aynı gerekçe). ---
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['ajax_delete_conv'] ?? '')==='1'){
    header('Content-Type: application/json; charset=utf-8');
    csrf_verify();
    $convId=(int)($conv['id'] ?? 0);
    if(!$convId){ echo json_encode(['ok'=>false,'error'=>'Geçersiz istek.']); exit; }
    try{
        $pdo->prepare("DELETE FROM wa_messages WHERE conversation_id=?")->execute([$convId]);
        $pdo->prepare("DELETE FROM wa_conversations WHERE id=?")->execute([$convId]);
        echo json_encode(['ok'=>true]);
    }catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>'Silinemedi.']); }
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
topx($conv['contact_name'] ?? 'Konuşma', 'wa_conversations.php', 'WhatsApp');
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
.thread{display:flex;flex-direction:column;gap:8px}
.composer{position:static;flex:0 0 auto;background:#071326;border-top:1px solid rgba(255,255,255,.12);padding:8px 8px calc(8px + env(safe-area-inset-bottom));z-index:1001}
.composer .wrap{max-width:520px;margin:auto;display:flex;gap:8px;align-items:flex-end}
.composer textarea{flex:1;margin:0;resize:none;max-height:100px}
.composer button.send{flex:0 0 auto;width:50px;height:46px;border-radius:14px;font-size:18px}
/* P0 KONUŞMA EKRANI SCROLL/SHELL DÜZELTMESİ (2026-07-19, Product Owner FAIL raporu — bug SADECE
   konuşma detayında, global shell'e DOKUNULMADI — bkz. messages.php'deki AYNI not/mekanizma) —
   `.app` viewport'a kilitli flex-column kabuk, SADECE `.thread` scroll ediyor, composer static
   flex item olarak nav'ın üstünde normal akışta duruyor (nav'a binmesi geometrik olarak imkansız). */
body.chat-mode{height:100vh;height:100dvh;overflow:hidden}
body.chat-mode .app{display:flex;flex-direction:column;height:calc(100vh - var(--df-navh, 76px) - env(safe-area-inset-bottom));height:calc(100dvh - var(--df-navh, 76px) - env(safe-area-inset-bottom));overflow:hidden}
body.chat-mode.kb .app{height:100vh;height:100dvh}
body.chat-mode .df-m-topbar{flex:0 0 auto;position:static}
body.chat-mode .app>div:not(.thread){flex:0 0 auto}
body.chat-mode .thread{flex:1 1 auto;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;padding-bottom:8px}
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
<?php if((int)($conv['id'] ?? 0) > 0): ?><button type="button" id="waDelConvBtn" class="df-btn df-btn--secondary" style="flex:0 0 auto;color:var(--df-danger-ink,#f87171)" title="Konuşmayı kayıttan temizle (WhatsApp'ta silinmez)"><?=ds_icon('trash',15)?></button><?php endif; ?>
</div>
<p class="small" style="margin:-4px 0 10px;color:var(--df-ink-500,#94a3b8)">Sil aksiyonları sadece bu ekrandaki kaydı temizler — WhatsApp'ta karşı tarafın telefonundaki mesajı SİLMEZ (WhatsApp Business API bunu desteklemiyor).</p>

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

  function scrollBottom(){ if(thread) thread.scrollTop=thread.scrollHeight; }
  scrollBottom();

  // mobile/messages.php ile AYNI desen: composer normalde .app flex akışında static'tir (nav'a
  // asla binmez) — SADECE klavye açıkken geçici olarak fixed'e alınıp visualViewport'un dibine
  // pinlenir, kapanınca static'e (normal flex konumuna) döner.
  function pinComposer(){
    if(!composer||!window.visualViewport) return;
    var v=window.visualViewport;
    composer.style.position='fixed'; composer.style.left='0'; composer.style.right='0';
    composer.style.top=(v.offsetTop+v.height-composer.offsetHeight)+'px';
    composer.style.bottom='auto'; composer.style.paddingBottom='8px';
  }
  function unpinComposer(){
    if(!composer) return;
    composer.style.position='static'; composer.style.top='auto'; composer.style.left=''; composer.style.right='';
    composer.style.paddingBottom='';
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
    if(div.classList.contains('mine')) bindLongPress(div);
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

  // Mesaj yönetimi (P0 MOBİL HOTFIX 2026-07-19): "sürekli buton" yerine uzun-basma context action —
  // sadece KENDİ gönderdiğimiz mesajlarda (.bubble.mine), sadece kayıttan kaldırma (bkz. dosya başındaki
  // ajax_delete_msg notu — WhatsApp tarafında bir etkisi yoktur, "Düzenle" bilinçli olarak YOK).
  var pressTimer=null;
  function bindLongPress(el){
    var fire=function(){ pressTimer=null; if(!confirm('Bu mesaj kayıttan kaldırılsın mı?\n(WhatsApp\'ta karşı tarafta SİLİNMEZ, sadece bizim listemizden kalkar)')) return;
      var mid=el.getAttribute('data-id');
      var fd=new FormData(); fd.append('ajax_delete_msg','1'); fd.append('id',mid);
      fetch(window.location.pathname+'?id='+convId,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd})
        .then(function(r){return r.json();}).then(function(d){ if(d&&d.ok){ el.remove(); } else { alert((d&&d.error)||'Silinemedi.'); } })
        .catch(function(){ alert('Bağlantı hatası — tekrar deneyin.'); });
    };
    el.addEventListener('touchstart',function(){ pressTimer=setTimeout(fire,550); },{passive:true});
    el.addEventListener('touchend',function(){ if(pressTimer){clearTimeout(pressTimer);pressTimer=null;} });
    el.addEventListener('touchmove',function(){ if(pressTimer){clearTimeout(pressTimer);pressTimer=null;} });
    el.addEventListener('contextmenu',function(e){ e.preventDefault(); fire(); }); // masaüstü/sağ tık test kolaylığı
  }
  thread.querySelectorAll('.bubble.mine').forEach(bindLongPress);

  var delConvBtn=document.getElementById('waDelConvBtn');
  if(delConvBtn){
    delConvBtn.addEventListener('click', function(){
      if(!confirm('Bu konuşma kayıttan tamamen temizlensin mi?\n(WhatsApp\'ta SİLİNMEZ, sadece bu ekrandaki geçmiş kalkar)')) return;
      var fd=new FormData(); fd.append('ajax_delete_conv','1');
      fetch(window.location.pathname+'?id='+convId,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd})
        .then(function(r){return r.json();}).then(function(d){ if(d&&d.ok){ window.location.href='wa_conversations.php'; } else { alert((d&&d.error)||'Silinemedi.'); } })
        .catch(function(){ alert('Bağlantı hatası — tekrar deneyin.'); });
    });
  }

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
