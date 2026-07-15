<?php
require_once 'common.php';
require_once __DIR__.'/../share_lib.php';
$me = (int)($_SESSION['user']['id'] ?? 0);
$pdo = db();

/* --- Tablo + kolon güvencesi (eski 'tek akış' yapısını kişiselleştir) --- */
try { $pdo->exec("CREATE TABLE IF NOT EXISTS internal_messages(
    id INT AUTO_INCREMENT PRIMARY KEY, sender_user_id INT NULL, receiver_user_id INT NULL,
    message TEXT, is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e){}
foreach (['receiver_user_id'=>'INT NULL','is_read'=>'TINYINT(1) DEFAULT 0','attachment'=>'VARCHAR(255) NULL','attach_type'=>'VARCHAR(20) NULL'] as $col=>$def){
    try { if(!$pdo->query("SHOW COLUMNS FROM internal_messages LIKE '$col'")->fetch()) $pdo->exec("ALTER TABLE internal_messages ADD COLUMN $col $def"); } catch(Throwable $e){}
}

$with = (int)($_GET['with'] ?? 0);
$thread = (int)($_GET['thread'] ?? 0);

/* --- Mesaj düzenleme (sadece kendi metin mesajları) --- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_msg'])) {
    $eid=(int)$_POST['edit_msg'];
    $newText=trim($_POST['edit_text']??'');
    $resp=['ok'=>false,'error'=>''];
    if($eid>0 && $newText!==''){
        try{
            $st=$pdo->prepare("UPDATE internal_messages SET message=? WHERE id=? AND sender_user_id=? AND (attachment IS NULL OR attachment='')");
            $st->execute([$newText,$eid,$me]);
            $resp['ok']=($st->rowCount()>0);
            if(!$resp['ok']) $resp['error']='Düzenlenemedi (sahip değilsiniz ya da ekli mesaj).';
        }catch(Throwable $e){ $resp['error']='Hata: '.$e->getMessage(); }
    } else { $resp['error']='Geçersiz istek.'; }
    if(!empty($_POST['ajax'])){ header('Content-Type: application/json'); echo json_encode($resp); exit; }
    header('Location: messages.php?with='.(int)($_POST['with']??0).'&thread='.(int)($_POST['thread']??0)); exit;
}

/* --- Mesaj / sohbet silme --- */
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['del_msg'])||isset($_POST['del_conv']))) {
    if(isset($_POST['del_msg'])){
        $dmid=(int)$_POST['del_msg'];
        // Önce eki kontrol et (sadece kendi gönderdiği mesaj)
        $attRow=null;
        try{ $attRow=$pdo->prepare("SELECT attachment FROM internal_messages WHERE id=? AND sender_user_id=?")->execute([$dmid,$me]) ? null : null;
             $st2=$pdo->prepare("SELECT attachment FROM internal_messages WHERE id=? AND sender_user_id=?"); $st2->execute([$dmid,$me]); $attRow=$st2->fetch(); }catch(Throwable $e){}
        if($attRow && !empty($attRow['attachment'])){
            $fpath=__DIR__.'/../'.$attRow['attachment'];
            if(is_file($fpath)) @unlink($fpath);
        }
        try{ $pdo->prepare("DELETE FROM internal_messages WHERE id=? AND sender_user_id=?")->execute([$dmid,$me]); }catch(Throwable $e){}
        if(!empty($_POST['ajax'])){ header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
        header('Location: messages.php?with='.(int)($_POST['with']??0)); exit;
    }
    if(isset($_POST['del_conv'])){
        $o=(int)$_POST['del_conv'];
        try{ $pdo->prepare("DELETE FROM internal_messages WHERE (sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?)")->execute([$me,$o,$o,$me]); }catch(Throwable $e){}
        if(!empty($_POST['ajax'])){ header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
        header('Location: messages.php'); exit;
    }
}

/* --- Mesaj gönder (çıktıdan ÖNCE, topx çağrılmadan) --- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $isAjax = !empty($_POST['ajax']);
    if($isAjax){ @ini_set('display_errors','0'); error_reporting(0); if(!ob_get_level()) ob_start(); } // temiz JSON garantisi
    $resp = ['ok'=>false,'error'=>''];
    $to = (int)($_POST['to'] ?? 0);
    $threadId = (int)($_POST['thread'] ?? 0);
    $body = trim($_POST['message'] ?? '');
    // Dosya/foto eki
    $att=null; $attType=null;
    if(isset($_FILES['attach'])){
        $af=$_FILES['attach'];
        if($af['error']!==0){ $resp['error']='Yükleme hatası kodu: '.$af['error'].($af['error']==1||$af['error']==2?' (dosya çok büyük / limit)':''); }
        else {
            $ext=strtolower(pathinfo($af['name'],PATHINFO_EXTENSION));
            $allowed=['jpg','jpeg','png','gif','webp','heic','pdf','mp4','mov','m4a','mp3','wav','webm','ogg','oga','aac','doc','docx','xls','xlsx'];
            if(!in_array($ext,$allowed)){ $resp['error']='Desteklenmeyen dosya türü: .'.$ext; }
            elseif($af['size']>25*1024*1024){ $resp['error']='Dosya 25MB üstü.'; }
            else {
                // Çalışan web sürümüyle AYNI klasör: uploads/job_files (PHP oluşturur → yazılabilir)
                $dir=__DIR__.'/../uploads/job_files'; if(!is_dir($dir)) @mkdir($dir,0755,true);
                if(!is_writable($dir)){ @chmod($dir,0777); }
                if(!is_writable($dir)){ $resp['error']='uploads/job_files yazılabilir değil (cPanel izin 755/777).'; }
                else {
                    $stored=bin2hex(random_bytes(8)).'.'.$ext;
                    $dest=$dir.'/'.$stored; $saved=false;
                    if(@move_uploaded_file($af['tmp_name'],$dest)) $saved=true;
                    elseif(@copy($af['tmp_name'],$dest)) $saved=true;
                    else { $data=@file_get_contents($af['tmp_name']); if($data!==false && @file_put_contents($dest,$data)!==false) $saved=true; }
                    if($saved){
                        @chmod($dest,0644);
                        $att='uploads/job_files/'.$stored;
                        if(in_array($ext,['jpg','jpeg','png','gif','webp','heic'])) $attType='image';
                        elseif(in_array($ext,['m4a','mp3','wav','ogg','oga','aac','webm','opus'])) $attType='audio';
                        elseif(in_array($ext,['mp4','mov','m4v'])) $attType='video';
                        else $attType='file';
                    } else { $resp['error']='Kaydedilemedi (move/copy/put). dir_yazılır='.(is_writable($dir)?'1':'0'); }
                }
            }
        }
    }
    $sname = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'Kullanıcı';
    $pext = $att ? strtolower(pathinfo($att,PATHINFO_EXTENSION)) : '';
    $preview = $body!=='' ? mb_substr($body,0,90) : ($attType==='image' ? '📷 Fotoğraf'
        : (in_array($pext,['m4a','mp3','wav','ogg','oga','aac']) ? '🎤 Sesli mesaj'
        : (in_array($pext,['mp4','mov','webm','m4v']) ? '🎬 Video' : '📎 Dosya')));
    if ($threadId && ($body!=='' || $att)) {
        // GRUP / İŞ / CARİ sohbeti — üye kontrolü
        try{
            $chk=$pdo->prepare("SELECT 1 FROM chat_thread_members WHERE thread_id=? AND user_id=?"); $chk->execute([$threadId,$me]);
            if(!$chk->fetch()){ $resp['error']='Bu sohbetin üyesi değilsin.'; }
            else {
                $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,thread_id,message,attachment,attach_type,is_read) VALUES(?,?,?,?,?,?,0)")
                    ->execute([$me,null,$threadId,$body,$att,$attType]);
                $tt=$pdo->prepare("SELECT title FROM chat_threads WHERE id=?"); $tt->execute([$threadId]); $tname=$tt->fetch()['title']??'Grup';
                if(file_exists(__DIR__.'/../push_lib.php')){ require_once __DIR__.'/../push_lib.php';
                    $mem=$pdo->prepare("SELECT user_id FROM chat_thread_members WHERE thread_id=? AND user_id<>?"); $mem->execute([$threadId,$me]);
                    foreach($mem->fetchAll() as $mm){ try{ push_to_user((int)$mm['user_id'],'👥 '.$tname,$sname.': '.$preview,'messages.php?thread='.$threadId); }catch(Throwable $e){} }
                }
                $resp['ok']=true;
            }
        } catch(Throwable $e){ $resp['error']='Kayıt hatası: '.$e->getMessage(); }
        if($isAjax){ if(ob_get_level()){@ob_end_clean();} header('Content-Type: application/json; charset=utf-8'); echo json_encode($resp); exit; }
        header('Location: messages.php?thread='.$threadId); exit;
    }
    if ($to && ($body!=='' || $att)) {
        try {
            $pdo->prepare("INSERT INTO internal_messages(sender_user_id,receiver_user_id,message,attachment,attach_type,is_read) VALUES(?,?,?,?,?,0)")
                ->execute([$me,$to,$body,$att,$attType]);
            // Mesaj için SADECE push (kapalıyken) — zil bildirimi YOK (mesaj zaten Mesajlar ekranında + 💬 rozetinde)
            if(file_exists(__DIR__.'/../push_lib.php')){ require_once __DIR__.'/../push_lib.php'; try{ push_to_user((int)$to,'💬 '.$sname,$preview,'messages.php?with='.$me); }catch(Throwable $e){} }
            $resp['ok']=true;
        } catch(Throwable $e){ $resp['error']='Kayıt hatası: '.$e->getMessage(); }
    } elseif(!$resp['error']) { $resp['error']='Mesaj boş.'; }

    if($isAjax){ if(ob_get_level()){@ob_end_clean();} header('Content-Type: application/json; charset=utf-8'); echo json_encode($resp); exit; }
    header('Location: messages.php?with='.$to); exit;
}

/* --- Sohbet açıldıysa o kişinin mesajlarını okundu yap --- */
if ($with) {
    try { $pdo->prepare("UPDATE internal_messages SET is_read=1 WHERE receiver_user_id=? AND sender_user_id=?")->execute([$me,$with]); } catch(Throwable $e){}
}

topx($thread ? 'Grup' : ($with ? 'Sohbet' : 'Mesajlar'));
?>
<style>
.chat-list{display:flex;flex-direction:column;gap:8px;min-width:0}
.chat-row-wrap{display:flex;align-items:center;gap:6px;min-width:0}
.chat-row{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:12px;text-decoration:none;color:#fff;min-width:0}
.av{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;flex:0 0 auto;color:#fff}
.chat-row .meta{flex:1;min-width:0}
.chat-row .meta b{display:block;overflow-wrap:anywhere}
.chat-row .meta small{color:#94a3b8;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-del-btn{flex:0 0 auto;background:rgba(248,113,113,.12);color:#f87171;border:0;border-radius:14px;width:46px;height:46px;font-size:18px}
.unread-badge{background:#22c55e;color:#06281a;border-radius:999px;min-width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:12px;padding:0 6px}
.thread{display:flex;flex-direction:column;gap:8px;padding-bottom:90px}
.bubble{max-width:80%;padding:10px 13px;border-radius:18px;font-size:15px;line-height:1.35;word-wrap:break-word}
.bubble small{display:block;font-size:10px;opacity:.6;margin-top:3px;text-align:right}
.mine{align-self:flex-end;background:#2563eb;border-bottom-right-radius:5px}
.theirs{align-self:flex-start;background:rgba(255,255,255,.12);border-bottom-left-radius:5px}
.composer{position:fixed;left:0;right:0;bottom:0;background:#071326;border-top:1px solid rgba(255,255,255,.12);padding:8px 8px calc(8px + env(safe-area-inset-bottom));z-index:1001}
.composer .wrap{max-width:520px;margin:auto;display:flex;gap:8px;align-items:flex-end}
.composer textarea{flex:1;margin:0;resize:none;max-height:120px}
.composer button{flex:0 0 auto;width:50px;height:46px;border-radius:14px;font-size:18px}
.peer-head{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:10px 12px;margin-bottom:10px}
/* CHAT MODU: composer fixed kalır, JS ile klavyenin tam üstüne pinlenir */
body.chat-mode{padding-bottom:0}
body.chat-mode .thread{padding-bottom:88px}
</style>
<?php
function avatar_color($id){ $c=['#3b82f6','#22c55e','#f97316','#8b5cf6','#ef4444','#14b8a6','#eab308','#ec4899']; return $c[$id % count($c)]; }

if ($thread):
    /* ---------- GRUP / İŞ / CARİ SOHBET EKRANI ---------- */
    $tr=$pdo->prepare("SELECT * FROM chat_threads WHERE id=?"); $tr->execute([$thread]); $trow=$tr->fetch();
    $mchk=$pdo->prepare("SELECT 1 FROM chat_thread_members WHERE thread_id=? AND user_id=?"); $mchk->execute([$thread,$me]); $isMember=(bool)$mchk->fetch();
    if(!$trow || !$isMember){ echo '<div class="err">Grup bulunamadı ya da üyesi değilsin.</div>'; botx(); exit; }
    $memCount=(int)($pdo->query("SELECT COUNT(*) c FROM chat_thread_members WHERE thread_id=$thread")->fetch()['c'] ?? 0);
    $tm=$pdo->prepare("SELECT m.*, u.full_name, u.username FROM internal_messages m LEFT JOIN app_users u ON u.id=m.sender_user_id WHERE m.thread_id=? ORDER BY m.id ASC LIMIT 400");
    $tm->execute([$thread]); $tmsgs=$tm->fetchAll();
    // okundu işaretle
    try{ $mx=(int)($pdo->query("SELECT COALESCE(MAX(id),0) m FROM internal_messages WHERE thread_id=$thread")->fetch()['m']??0);
         $pdo->prepare("UPDATE chat_thread_members SET last_read_id=? WHERE thread_id=? AND user_id=?")->execute([$mx,$thread,$me]); }catch(Throwable $e){}
    $ticon=$trow['type']==='job'?'📋':($trow['type']==='cari'?'🏢':'👥');
?>
<div class="peer-head">
    <div class="av" style="background:<?=avatar_color((int)$thread+99)?>"><?=$ticon?></div>
    <div style="flex:1"><b><?=htmlspecialchars($trow['title'])?></b><br><small style="color:#94a3b8"><?=$memCount?> üye · <?=$trow['type']==='job'?'İş sohbeti':($trow['type']==='cari'?'Cari sohbeti':'Grup')?></small></div>
    <a href="messages.php" style="background:rgba(255,255,255,.12);color:#fff;border-radius:12px;padding:8px 12px;text-decoration:none">‹</a>
</div>

<div class="thread" id="thread">
<?php if(!$tmsgs): ?><div id="emptymsg" style="text-align:center;color:#94a3b8;padding:24px">Henüz mesaj yok. İlk mesajı sen yaz 👇</div><?php endif; ?>
<?php foreach($tmsgs as $m): $mine=((int)$m['sender_user_id']===$me); $snm=$m['full_name']?:$m['username']?:'?'; ?>
    <div class="bubble <?=$mine?'mine':'theirs'?>" id="msg<?=$m['id']?>" <?=$mine?'ondblclick="editMsgT('.$m['id'].')"':''?>>
        <?php if(!$mine): ?><small style="color:#60a5fa;font-weight:700;opacity:1;text-align:left;margin:0 0 2px"><?=htmlspecialchars($snm)?></small><?php endif; ?>
        <?php if(!empty($m['attachment'])):
          $apath='../'.htmlspecialchars($m['attachment']);
          $aext=strtolower(pathinfo($m['attachment'],PATHINFO_EXTENSION));
          $attT=$m['attach_type']??'file';
          $isVid=($attT==='video'||in_array($aext,['mp4','mov','m4v']));
          $isAud=($attT==='audio'||in_array($aext,['m4a','mp3','wav','ogg','oga','aac','webm','opus']));
          if($m['attach_type']==='image'): ?><img src="<?=$apath?>" onclick="ACANS_VIEW('<?=$apath?>','image')" style="max-width:200px;width:100%;border-radius:12px;display:block;margin-bottom:4px;cursor:pointer">
        <?php elseif($isAud): ?><audio controls preload="none" src="<?=$apath?>" style="max-width:230px;width:100%;margin-bottom:4px"></audio>
        <?php elseif($isVid): ?><video controls preload="none" src="<?=$apath?>" style="max-width:220px;width:100%;border-radius:12px;display:block;margin-bottom:4px" playsinline></video>
        <?php else: ?><a href="javascript:void(0)" onclick="ACANS_VIEW('<?=$apath?>','file')" style="color:#fff;text-decoration:underline">📎 Dosya</a><br><?php endif; endif; ?>
        <span id="msgtxt<?=$m['id']?>"><?=nl2br(htmlspecialchars($m['message']))?></span>
        <small>
          <?=htmlspecialchars(date('d.m H:i',strtotime($m['created_at'])))?>
          <?php if($mine): ?>
            <?php if(empty($m['attachment'])): ?><span onclick="event.stopPropagation();editMsgT(<?=$m['id']?>)" style="cursor:pointer;opacity:.55;margin-left:6px" title="Düzenle">✏️</span><?php endif; ?>
            <span onclick="event.stopPropagation();delMsgT(<?=$m['id']?>)" style="cursor:pointer;opacity:.55;margin-left:4px" title="Sil">🗑</span>
          <?php endif; ?>
        </small>
    </div>
<?php endforeach; ?>
</div>

<!-- Grup düzenleme modalı -->
<div id="editModalT" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.55);align-items:center;justify-content:center">
  <div style="background:#0f2035;border-radius:18px;padding:18px;width:90%;max-width:400px">
    <div style="font-weight:700;margin-bottom:10px">Mesajı Düzenle</div>
    <textarea id="editTextT" rows="4" style="width:100%;border-radius:10px;padding:9px;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);resize:vertical;font-size:15px"></textarea>
    <div style="display:flex;gap:8px;margin-top:10px">
      <button onclick="saveEditT()" class="btn dark" style="flex:1">Kaydet</button>
      <button onclick="document.getElementById('editModalT').style.display='none'" style="flex:1;background:rgba(255,255,255,.1);color:#fff;border:0;border-radius:12px;padding:10px;font-size:14px">İptal</button>
    </div>
  </div>
</div>

<form method="post" class="composer" enctype="multipart/form-data" id="msgform">
    <div class="wrap">
        <input type="hidden" name="thread" value="<?=$thread?>">
        <input type="file" name="attach" id="attach" accept="image/*,application/pdf,video/*,audio/*" multiple style="display:none">
        <label for="attach" style="flex:0 0 auto;width:44px;height:46px;background:rgba(255,255,255,.12);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer">📎</label>
        <button type="button" class="vbtn" onclick="vrec(this)" style="flex:0 0 auto;width:44px;height:46px;background:rgba(255,255,255,.12);border:0;border-radius:14px;font-size:19px;color:#fff">🎤</button>
        <?=emoji_picker_html('msgTextT', true)?>
        <textarea id="msgTextT" name="message" rows="1" placeholder="Gruba yaz…" oninput="this.style.height='';this.style.height=this.scrollHeight+'px'"></textarea>
        <button class="btn dark" type="submit" id="sendbtn" style="flex:0 0 auto">➤</button>
    </div>
    <div id="attlbl" style="max-width:520px;margin:4px auto 0;color:#94a3b8;font-size:12px"></div>
</form>
<script>
document.body.classList.add('chat-mode');
function scrollBottom(){ window.scrollTo(0,document.body.scrollHeight); } scrollBottom();
var THREADID=<?=$thread?>;
<?php $tmax=0; foreach($tmsgs as $mm)$tmax=max($tmax,(int)$mm['id']); ?>
/* Canlı grup akışı: common.php poll'u conv_thread için yeni mesajları buraya getirir */
window.ACANS_CONV_THREAD=<?=$thread?>;
window.ACANS_CONV_SINCE=<?=$tmax?>;
window.ACANS_ON_CONV=function(list){
  var th=document.getElementById('thread'); var em=document.getElementById('emptymsg'); if(em)em.remove();
  list.forEach(function(m){
    if(m.id<=window.ACANS_CONV_SINCE) return; window.ACANS_CONV_SINCE=m.id;
    var d=document.createElement('div'); d.className='bubble '+(m.mine?'mine':'theirs');
    var esc=function(s){return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
    var name=(!m.mine&&m.from)?'<small style="color:#60a5fa;font-weight:700;opacity:1;text-align:left;margin:0 0 2px">'+esc(m.from)+'</small>':'';
    d.innerHTML=name+esc(m.body).replace(/\n/g,'<br>')+'<small>'+m.at+'</small>';
    th.appendChild(d);
  });
  window.scrollTo(0,document.body.scrollHeight);
};
function acansCompress(file,cb){ if(!file||!file.type||file.type.indexOf('image/')!==0){cb(file,file?file.name:'dosya');return;}
  var img=new Image(),u=URL.createObjectURL(file);
  img.onload=function(){var max=1600,w=img.width,h=img.height;if(w>max||h>max){if(w>h){h=Math.round(h*max/w);w=max;}else{w=Math.round(w*max/h);h=max;}}
    var c=document.createElement('canvas');c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);URL.revokeObjectURL(u);
    c.toBlob(function(b){cb(b||file,(file.name||'foto').replace(/\.[^.]+$/,'')+'.jpg');},'image/jpeg',0.82);};
  img.onerror=function(){cb(file,file.name||'dosya');};img.src=u;}
var attEl=document.getElementById('attach'),lbl=document.getElementById('attlbl'),pendItems=[];
attEl.addEventListener('change',function(){ var fs=this.files; pendItems=[]; if(!fs||!fs.length){lbl.textContent='';return;}
  lbl.textContent='⏳ '+fs.length+' dosya hazırlanıyor...'; var done=0,total=fs.length;
  for(var i=0;i<total;i++){ acansCompress(fs[i],function(b,n){ pendItems.push({blob:b,name:n}); done++; if(done>=total){ lbl.textContent='📎 '+pendItems.length+' dosya hazır'; } }); }});
function vrec(btn){
  if(window._mr&&_mr.state==='recording'){ _mr.stop(); return; }
  if(!navigator.mediaDevices||!window.MediaRecorder){ alert('Cihaz ses kaydını desteklemiyor'); return; }
  navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){ var ch=[]; window._mr=new MediaRecorder(stream);
    _mr.ondataavailable=function(e){ if(e.data&&e.data.size) ch.push(e.data); };
    _mr.onstop=function(){ var mime=_mr.mimeType||'audio/mp4'; var ext=mime.indexOf('webm')>=0?'webm':(mime.indexOf('ogg')>=0?'ogg':'m4a');
      var blob=new Blob(ch,{type:mime}); stream.getTracks().forEach(function(t){t.stop();});
      btn.textContent='🎤'; btn.style.background='rgba(255,255,255,.12)';
      pendItems.push({blob:blob,name:'ses_'+Date.now()+'.'+ext}); lbl.textContent='🎤 ses gönderiliyor...';
      var f=document.getElementById('msgform'); if(f.requestSubmit)f.requestSubmit(); else f.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true})); };
    _mr.start(); btn.textContent='⏹'; btn.style.background='#ef4444';
  }).catch(function(){ alert('Mikrofon izni gerekli'); });
}
if(!window.MediaRecorder||!navigator.mediaDevices){ var vb=document.querySelectorAll('.vbtn'); for(var i=0;i<vb.length;i++)vb[i].style.display='none'; }
var _editIdT=0;
function editMsgT(id){
  var span=document.getElementById('msgtxt'+id); if(!span)return;
  _editIdT=id; document.getElementById('editTextT').value=span.innerText||span.textContent;
  document.getElementById('editModalT').style.display='flex';
}
function saveEditT(){
  var txt=document.getElementById('editTextT').value.trim(); if(!txt)return;
  var fd=new FormData(); fd.append('edit_msg',_editIdT); fd.append('edit_text',txt); fd.append('thread',THREADID); fd.append('ajax','1');
  fetch('messages.php?thread='+THREADID,{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){
      if(d&&d.ok){ var sp=document.getElementById('msgtxt'+_editIdT); if(sp)sp.innerHTML=txt.replace(/\n/g,'<br>'); document.getElementById('editModalT').style.display='none'; }
      else alert(d&&d.error?d.error:'Düzenlenemedi.');
    }).catch(function(){ alert('Bağlantı hatası.'); });
}
function delMsgT(id){ if(!confirm('Bu mesaj silinsin mi?'))return; var fd=new FormData();fd.append('del_msg',id);fd.append('ajax','1');
  fetch('messages.php?thread='+THREADID,{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){ if(d&&d.ok){ var b=document.getElementById('msg'+id); if(b)b.remove(); } else alert(d&&d.error?d.error:'Silinemedi.'); }); }
document.getElementById('msgform').addEventListener('submit',function(e){ e.preventDefault();
  var ta=document.querySelector('.composer textarea'); var msg=ta?ta.value.trim():''; if(!msg&&!pendItems.length)return;
  var b=document.getElementById('sendbtn'); b.disabled=true; b.textContent='…';
  var queue=[]; if(pendItems.length){ pendItems.forEach(function(it,idx){ queue.push({msg:(idx===0?msg:''),file:it}); }); } else { queue.push({msg:msg,file:null}); }
  var failed=0, failMsgs=[];
  function one(q,tries,cb){ var fd=new FormData(); fd.append('thread',THREADID); fd.append('message',q.msg); fd.append('ajax','1'); if(q.file)fd.append('attach',q.file.blob,q.file.name||'foto.jpg');
    fetch('messages.php?thread='+THREADID,{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'}).then(function(r){return r.json();})
      .then(function(d){ if(d&&d.ok)cb(true); else if(tries>1)setTimeout(function(){one(q,tries-1,cb);},600); else cb(false,(d&&d.error)||'bağlantı hatası'); })
      .catch(function(){ if(tries>1)setTimeout(function(){one(q,tries-1,cb);},600); else cb(false,'bağlantı/yükleme hatası'); }); }
  function run(i){ if(i>=queue.length){ if(failed>0){ b.disabled=false; b.textContent='Gönder'; lbl.textContent='⚠️ '+failed+'/'+queue.length+' gitmedi: '+failMsgs.join(' · '); } else { window.location.href='messages.php?thread='+THREADID; } return; }
    if(queue.length>1)lbl.textContent='⬆ '+(i+1)+'/'+queue.length; one(queue[i],3,function(ok,err){ if(!ok){failed++; if(err)failMsgs.push(err);} setTimeout(function(){run(i+1);},150); }); }
  run(0);
});
// klavye: composer'ı görünür alanın dibine pinle
var composer=document.querySelector('.composer');
function pinC(){ if(!composer||!window.visualViewport)return; var v=window.visualViewport; composer.style.top=(v.offsetTop+v.height-composer.offsetHeight)+'px'; composer.style.bottom='auto'; composer.style.paddingBottom='8px'; }
function unpinC(){ if(!composer)return; composer.style.top='auto'; composer.style.bottom='0'; composer.style.paddingBottom=''; }
if(window.visualViewport){ window.visualViewport.addEventListener('resize',function(){pinC();scrollBottom();}); window.visualViewport.addEventListener('scroll',pinC); }
var ta2=document.querySelector('.composer textarea');
if(ta2){ ta2.addEventListener('focus',function(){ setTimeout(function(){pinC();scrollBottom();},250); }); ta2.addEventListener('blur',function(){ setTimeout(unpinC,100); }); }
</script>
<?php botx(); exit; endif;

if ($with):
    /* ---------- SOHBET EKRANI ---------- */
    $p=$pdo->prepare("SELECT id,full_name,username,role FROM app_users WHERE id=?"); $p->execute([$with]); $peer=$p->fetch();
    if(!$peer){ echo '<div class="err">Kullanıcı bulunamadı.</div>'; botx(); exit; }
    $pname=$peer['full_name'] ?: $peer['username'];
    $ms=$pdo->prepare("SELECT * FROM internal_messages WHERE (sender_user_id=? AND receiver_user_id=?) OR (sender_user_id=? AND receiver_user_id=?) ORDER BY id ASC LIMIT 400");
    $ms->execute([$me,$with,$with,$me]); $msgs=$ms->fetchAll();
?>
<div class="peer-head">
    <div class="av" style="background:<?=avatar_color((int)$peer['id'])?>"><?=htmlspecialchars(mb_strtoupper(mb_substr($pname,0,1)))?></div>
    <div style="flex:1"><b><?=htmlspecialchars($pname)?></b><br><small id="peerStatus" style="color:#94a3b8"><?=htmlspecialchars($peer['role'] ?: 'Kullanıcı')?></small></div>
    <button onclick="delConv(<?=$with?>)" style="background:rgba(248,113,113,.15);color:#f87171;border:0;border-radius:12px;padding:8px 12px;font-weight:700;font-size:13px">🗑 Temizle</button>
</div>

<div class="thread" id="thread">
<?php if(!$msgs): ?><div id="emptymsg" style="text-align:center;color:#94a3b8;padding:24px">Henüz mesaj yok. İlk mesajı sen yaz 👇</div><?php endif; ?>
<?php $maxId=0; foreach($msgs as $m): $mine=((int)$m['sender_user_id']===$me); $maxId=max($maxId,(int)$m['id']); ?>
    <div class="bubble <?=$mine?'mine':'theirs'?>" id="msg<?=$m['id']?>" <?=$mine?'ondblclick="editMsg('.$m['id'].')"':''?>>
        <?php if(!empty($m['attachment'])):
          $apath='../'.htmlspecialchars($m['attachment']);
          $aext=strtolower(pathinfo($m['attachment'],PATHINFO_EXTENSION));
          $attT=$m['attach_type']??'file';
          $isVid=($attT==='video'||in_array($aext,['mp4','mov','m4v']));
          $isAud=($attT==='audio'||in_array($aext,['m4a','mp3','wav','ogg','oga','aac','webm','opus']));
          if($m['attach_type']==='image'): ?>
          <img src="<?=$apath?>" onclick="ACANS_VIEW('<?=$apath?>','image')" style="max-width:200px;width:100%;border-radius:12px;display:block;margin-bottom:4px;cursor:pointer">
        <?php elseif($isAud): ?>
          <audio controls preload="none" src="<?=$apath?>" style="max-width:230px;width:100%;margin-bottom:4px"></audio>
        <?php elseif($isVid): ?>
          <video controls preload="none" src="<?=$apath?>" style="max-width:220px;width:100%;border-radius:12px;display:block;margin-bottom:4px" playsinline></video>
        <?php else: ?>
          <a href="javascript:void(0)" onclick="ACANS_VIEW('<?=$apath?>','file')" style="color:#fff;text-decoration:underline">📎 Dosya</a><br>
        <?php endif; endif; ?>
        <span id="msgtxt<?=$m['id']?>"><?=nl2br(htmlspecialchars($m['message']))?></span>
        <small>
          <?=htmlspecialchars(date('d.m H:i',strtotime($m['created_at'])))?><?=$mine?($m['is_read']?' ✓✓':' ✓'):''?>
          <?php if($mine): ?>
            <?php if(empty($m['attachment'])): ?><span onclick="event.stopPropagation();editMsg(<?=$m['id']?>)" style="cursor:pointer;opacity:.55;margin-left:6px" title="Düzenle">✏️</span><?php endif; ?>
            <span onclick="event.stopPropagation();delMsg(<?=$m['id']?>)" style="cursor:pointer;opacity:.55;margin-left:4px" title="Sil">🗑</span>
          <?php endif; ?>
        </small>
    </div>
<?php endforeach; ?>
</div>

<!-- Düzenleme modalı -->
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.55);align-items:center;justify-content:center">
  <div style="background:#0f2035;border-radius:18px;padding:18px;width:90%;max-width:400px">
    <div style="font-weight:700;margin-bottom:10px">Mesajı Düzenle</div>
    <textarea id="editText" rows="4" style="width:100%;border-radius:10px;padding:9px;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);resize:vertical;font-size:15px"></textarea>
    <div style="display:flex;gap:8px;margin-top:10px">
      <button onclick="saveEdit()" class="btn dark" style="flex:1">Kaydet</button>
      <button onclick="document.getElementById('editModal').style.display='none'" style="flex:1;background:rgba(255,255,255,.1);color:#fff;border:0;border-radius:12px;padding:10px;font-size:14px">İptal</button>
    </div>
  </div>
</div>

<script>
/* Mesaj / sohbet silme */
var WITHID=<?=$with?>;
var _editId=0;
function editMsg(id){
  var span=document.getElementById('msgtxt'+id); if(!span)return;
  var txt=span.innerText||span.textContent;
  _editId=id;
  document.getElementById('editText').value=txt;
  document.getElementById('editModal').style.display='flex';
}
function saveEdit(){
  var txt=document.getElementById('editText').value.trim(); if(!txt)return;
  var fd=new FormData(); fd.append('edit_msg',_editId); fd.append('edit_text',txt); fd.append('with',WITHID); fd.append('ajax','1');
  fetch('messages.php?with='+WITHID,{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){
      if(d&&d.ok){ var sp=document.getElementById('msgtxt'+_editId); if(sp)sp.innerHTML=txt.replace(/\n/g,'<br>'); document.getElementById('editModal').style.display='none'; }
      else alert(d&&d.error?d.error:'Düzenlenemedi.');
    }).catch(function(){ alert('Bağlantı hatası.'); });
}
function delMsg(id){
  if(!confirm('Bu mesaj silinsin mi?')) return;
  var fd=new FormData(); fd.append('del_msg',id); fd.append('with',WITHID); fd.append('ajax','1');
  fetch('messages.php?with='+WITHID,{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'})
    .then(function(r){return r.json();}).then(function(d){ if(d&&d.ok){ var b=document.getElementById('msg'+id); if(b)b.remove(); } else alert(d&&d.error?d.error:'Silinemedi.'); });
}
function delConv(uid){
  if(!confirm('Bu kişiyle tüm sohbet silinsin mi? Geri alınamaz.')) return;
  var fd=new FormData(); fd.append('del_conv',uid); fd.append('ajax','1');
  fetch('messages.php',{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'})
    .then(function(){ location.href='messages.php'; });
}
/* Durum: yazıyor… > çevrimiçi > son görülme (poll'dan gelir) */
window.ACANS_ON_STATUS=function(d){
  var el=document.getElementById('peerStatus'); if(!el) return;
  if(d.conv_typing){ el.textContent='yazıyor…'; el.style.color='#22c55e'; }
  else if(d.conv_online){ el.textContent='çevrimiçi'; el.style.color='#22c55e'; }
  else if(d.conv_last_seen){ el.textContent='son görülme '+d.conv_last_seen; el.style.color='#94a3b8'; }
};
/* "yazıyor" sinyali: en fazla 3sn'de bir gönder */
var _lastTyping=0;
document.addEventListener('input',function(e){
  if(!(e.target && e.target.matches && e.target.matches('.composer textarea'))) return;
  var now=Date.now(); if(now-_lastTyping<3000) return; _lastTyping=now;
  fetch('poll.php?typing=1&to='+WITHID,{credentials:'same-origin'}).catch(function(){});
});
/* Canlı sohbet: common.php notifier'ı bu sohbetin yeni mesajlarını buraya akıtır */
window.ACANS_CONV=<?=$with?>;
window.ACANS_CONV_SINCE=<?=$maxId?>;
window.ACANS_ON_CONV=function(list){
  var th=document.getElementById('thread'); var em=document.getElementById('emptymsg'); if(em)em.remove();
  list.forEach(function(m){
    if(m.id<=window.ACANS_CONV_SINCE) return;
    window.ACANS_CONV_SINCE=m.id;
    var d=document.createElement('div');
    d.className='bubble '+(m.mine?'mine':'theirs');
    d.innerHTML=(m.body||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')+'<small>'+m.at+'</small>';
    th.appendChild(d);
  });
  window.scrollTo(0,document.body.scrollHeight);
};
</script>

<form method="post" class="composer" enctype="multipart/form-data" id="msgform">
    <div class="wrap">
        <input type="hidden" name="to" value="<?=$with?>">
        <input type="file" name="attach" id="attach" accept="image/*,application/pdf,video/*,audio/*" multiple style="display:none">
        <label for="attach" style="flex:0 0 auto;width:44px;height:46px;background:rgba(255,255,255,.12);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer">📎</label>
        <button type="button" class="vbtn" onclick="vrec(this)" style="flex:0 0 auto;width:44px;height:46px;background:rgba(255,255,255,.12);border:0;border-radius:14px;font-size:19px;color:#fff">🎤</button>
        <?=emoji_picker_html('msgText', true)?>
        <textarea id="msgText" name="message" rows="1" placeholder="Mesaj yaz…" oninput="this.style.height='';this.style.height=this.scrollHeight+'px'"></textarea>
        <button class="btn dark" type="submit" id="sendbtn" style="flex:0 0 auto">➤</button>
    </div>
    <div id="attlbl" style="max-width:520px;margin:4px auto 0;color:#94a3b8;font-size:12px"></div>
</form>
<script>
document.body.classList.add('chat-mode');
function scrollBottom(){ window.scrollTo(0,document.body.scrollHeight); }

// Belirgin GERİ butonu (logo kalır, başına eklenir)
(function(){
  var top=document.querySelector('.top');
  if(top && !document.getElementById('backbtn')){
    var back=document.createElement('a');
    back.href='index.php'; back.id='backbtn'; back.innerHTML='‹';
    back.style.cssText='flex:0 0 auto;width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.14);color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:900;text-decoration:none;margin-right:6px';
    top.insertBefore(back, top.firstChild);
  }
})();

// ÇÖZÜM: composer fixed; JS ile GÖRÜNÜR ALANIN tam dibine (klavyenin üstüne) "top" ile pinle.
// top kullanımı, iOS Safari'nin innerHeight tutarsızlığından etkilenmez.
var composer=document.querySelector('.composer');
function pinComposer(){
  if(!composer||!window.visualViewport) return;
  var v=window.visualViewport;
  var topPos=v.offsetTop + v.height - composer.offsetHeight;
  composer.style.top=topPos+'px';
  composer.style.bottom='auto';
  composer.style.paddingBottom='8px';
}
function unpinComposer(){
  if(!composer) return;
  composer.style.top='auto'; composer.style.bottom='0';
  composer.style.paddingBottom='';
}
if(window.visualViewport){
  window.visualViewport.addEventListener('resize',function(){ pinComposer(); scrollBottom(); });
  window.visualViewport.addEventListener('scroll',pinComposer);
}
var ta=document.querySelector('.composer textarea');
if(ta){
  ta.addEventListener('focus',function(){ setTimeout(function(){ pinComposer(); scrollBottom(); },250); });
  ta.addEventListener('blur',function(){ setTimeout(unpinComposer,100); });
}
scrollBottom();

// Fotoğrafı tarayıcıda küçült → Blob döndür (input'a YAZMA, iOS desteklemiyor)
function acansCompress(file,cb){
  if(!file || !file.type || file.type.indexOf('image/')!==0){ cb(file,file?file.name:'dosya'); return; }
  var img=new Image(), url=URL.createObjectURL(file);
  img.onload=function(){
    var max=1600,w=img.width,h=img.height;
    if(w>max||h>max){ if(w>h){h=Math.round(h*max/w);w=max;}else{w=Math.round(w*max/h);h=max;} }
    var c=document.createElement('canvas');c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);
    URL.revokeObjectURL(url);
    c.toBlob(function(blob){ cb(blob||file,(file.name||'foto').replace(/\.[^.]+$/,'')+'.jpg'); },'image/jpeg',0.82);
  };
  img.onerror=function(){ cb(file,file.name||'dosya'); };
  img.src=url;
}
var attEl=document.getElementById('attach'), lbl=document.getElementById('attlbl');
var pendItems=[]; // {blob,name} dizisi (çoklu seçim)
function vrec(btn){
  if(window._mr&&_mr.state==='recording'){ _mr.stop(); return; }
  if(!navigator.mediaDevices||!window.MediaRecorder){ alert('Cihaz ses kaydını desteklemiyor'); return; }
  navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){ var ch=[]; window._mr=new MediaRecorder(stream);
    _mr.ondataavailable=function(e){ if(e.data&&e.data.size) ch.push(e.data); };
    _mr.onstop=function(){ var mime=_mr.mimeType||'audio/mp4'; var ext=mime.indexOf('webm')>=0?'webm':(mime.indexOf('ogg')>=0?'ogg':'m4a');
      var blob=new Blob(ch,{type:mime}); stream.getTracks().forEach(function(t){t.stop();});
      btn.textContent='🎤'; btn.style.background='rgba(255,255,255,.12)';
      pendItems.push({blob:blob,name:'ses_'+Date.now()+'.'+ext}); lbl.textContent='🎤 ses gönderiliyor...';
      var f=document.getElementById('msgform'); if(f.requestSubmit)f.requestSubmit(); else f.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true})); };
    _mr.start(); btn.textContent='⏹'; btn.style.background='#ef4444';
  }).catch(function(){ alert('Mikrofon izni gerekli'); });
}
if(!window.MediaRecorder||!navigator.mediaDevices){ var vb=document.querySelectorAll('.vbtn'); for(var i=0;i<vb.length;i++)vb[i].style.display='none'; }
attEl.addEventListener('change',function(){
  var files=this.files; pendItems=[];
  if(!files || !files.length){ lbl.textContent=''; return; }
  lbl.textContent='⏳ '+files.length+' dosya hazırlanıyor...';
  var done=0, total=files.length;
  for(var i=0;i<total;i++){
    acansCompress(files[i],function(blob,name){
      pendItems.push({blob:blob,name:name}); done++;
      if(done>=total){
        var kb=0; pendItems.forEach(function(it){kb+=(it.blob.size||0);});
        lbl.textContent='📎 '+pendItems.length+' dosya ('+Math.round(kb/1024)+' KB) hazır';
      }
    });
  }
});

// fetch + FormData ile gönder (input.files'a dokunmadan → iOS uyumlu). Çoklu = sıralı gönderim.
var TOID=<?=$with?>;
document.getElementById('msgform').addEventListener('submit',function(e){
  e.preventDefault();
  var msg=ta?ta.value.trim():'';
  if(!msg && !pendItems.length){ return; }
  var b=document.getElementById('sendbtn'); b.disabled=true; b.textContent='…';
  // Kuyruk: her dosya kendi mesajı; metin ilk öğeye eklenir (yoksa tek metin mesajı)
  var queue=[];
  if(pendItems.length){ pendItems.forEach(function(it,idx){ queue.push({msg:(idx===0?msg:''),file:it}); }); }
  else { queue.push({msg:msg,file:null}); }
  var failed=0, failMsgs=[];
  // Her öğeyi 3 kez dene (600ms backoff), kalıcı hatada durma → sıradakine geç
  function sendWithRetry(q,tries,cb){
    var fd=new FormData(); fd.append('to',TOID); fd.append('message',q.msg); fd.append('ajax','1');
    if(q.file) fd.append('attach',q.file.blob,q.file.name||'foto.jpg');
    fetch('messages.php?with='+TOID,{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){ if(d&&d.ok){ cb(true); } else if(tries>1){ setTimeout(function(){sendWithRetry(q,tries-1,cb);},600); } else cb(false,(d&&d.error)||'bağlantı hatası'); })
      .catch(function(){ if(tries>1){ setTimeout(function(){sendWithRetry(q,tries-1,cb);},600); } else cb(false,'bağlantı/yükleme hatası'); });
  }
  function run(i){
    if(i>=queue.length){
      if(failed>0){ b.disabled=false; b.textContent='➤'; lbl.textContent='⚠️ '+failed+'/'+queue.length+' gitmedi: '+failMsgs.join(' · '); }
      else { window.location.href='messages.php?with='+TOID; }
      return;
    }
    if(queue.length>1) lbl.textContent='⬆ Gönderiliyor '+(i+1)+'/'+queue.length;
    sendWithRetry(queue[i],3,function(ok,err){ if(!ok){ failed++; if(err)failMsgs.push(err); } setTimeout(function(){ run(i+1); },150); });
  }
  run(0);
});
</script>

<?php else:
    /* ---------- SOHBET LİSTESİ ---------- */
    // Her kullanıcı için son mesaj + okunmamış sayısı
    $users=$pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.role,
          (SELECT message FROM internal_messages m WHERE (m.sender_user_id=u.id AND m.receiver_user_id=?) OR (m.sender_user_id=? AND m.receiver_user_id=u.id) ORDER BY m.id DESC LIMIT 1) last_msg,
          (SELECT created_at FROM internal_messages m WHERE (m.sender_user_id=u.id AND m.receiver_user_id=?) OR (m.sender_user_id=? AND m.receiver_user_id=u.id) ORDER BY m.id DESC LIMIT 1) last_at,
          (SELECT COUNT(*) FROM internal_messages m WHERE m.sender_user_id=u.id AND m.receiver_user_id=? AND m.is_read=0) unread
        FROM app_users u WHERE u.id<>? AND (u.active=1 OR EXISTS(
          SELECT 1 FROM internal_messages m2 WHERE (m2.sender_user_id=u.id AND m2.receiver_user_id=?) OR (m2.sender_user_id=? AND m2.receiver_user_id=u.id)
        ))
        ORDER BY (last_at IS NULL), last_at DESC, u.full_name");
    $users->execute([$me,$me,$me,$me,$me,$me,$me,$me]);
    $rows=$users->fetchAll();
    // Üyesi olduğum gruplar/iş/cari sohbetleri (migration yoksa boş)
    $threads=[];
    try{
        $thr=$pdo->prepare("SELECT t.id,t.title,t.type,
            (SELECT message FROM internal_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_msg,
            (SELECT MAX(id) FROM internal_messages m WHERE m.thread_id=t.id) last_id, cm.last_read_id
          FROM chat_threads t JOIN chat_thread_members cm ON cm.thread_id=t.id AND cm.user_id=?
          ORDER BY COALESCE((SELECT MAX(id) FROM internal_messages m WHERE m.thread_id=t.id),0) DESC, t.id DESC");
        $thr->execute([$me]); $threads=$thr->fetchAll();
    }catch(Throwable $e){}
?>
<div class="panel" style="padding:10px"><a class="btn dark" href="group_new.php" style="width:100%;text-align:center">👥 Yeni Grup</a></div>
<?php if($threads): ?>
<div style="font-weight:900;margin:6px 4px">Gruplar & İş Sohbetleri</div>
<div class="chat-list">
<?php foreach($threads as $t): $ic=$t['type']==='job'?'📋':($t['type']==='cari'?'🏢':'👥'); $un=((int)$t['last_id']>(int)$t['last_read_id']); ?>
    <a class="chat-row" href="messages.php?thread=<?=$t['id']?>">
      <div class="av" style="background:<?=avatar_color((int)$t['id']+99)?>"><?=$ic?></div>
      <div class="meta"><b><?=htmlspecialchars($t['title'])?></b>
        <small><?=$t['last_msg']?htmlspecialchars(mb_substr($t['last_msg'],0,42)):'<span style="opacity:.6">Yeni grup</span>'?></small></div>
      <?php if($un): ?><span class="unread-badge">●</span><?php endif; ?>
    </a>
<?php endforeach; ?>
</div>
<div style="font-weight:900;margin:14px 4px 6px">Kişiler</div>
<?php endif; ?>
<div class="chat-list">
<?php foreach($rows as $r): $nm=$r['full_name'] ?: $r['username']; ?>
    <div class="chat-row-wrap">
      <a class="chat-row" href="messages.php?with=<?=$r['id']?>" style="flex:1">
        <div class="av" style="background:<?=avatar_color((int)$r['id'])?>"><?=htmlspecialchars(mb_strtoupper(mb_substr($nm,0,1)))?></div>
        <div class="meta">
            <b><?=htmlspecialchars($nm)?></b>
            <small><?=$r['last_msg'] ? htmlspecialchars(mb_substr($r['last_msg'],0,42)) : '<span style="opacity:.6">'.htmlspecialchars($r['role'] ?: 'Yeni sohbet').'</span>'?></small>
        </div>
        <?php if($r['unread']>0): ?><span class="unread-badge"><?=$r['unread']?></span><?php endif; ?>
      </a>
      <?php if($r['last_msg']): ?><button onclick="delConvList(<?=$r['id']?>)" class="chat-del-btn">🗑</button><?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div style="text-align:center;color:#94a3b8;padding:30px">Mesajlaşılacak başka kullanıcı yok.</div><?php endif; ?>
</div>
<script>
function delConvList(uid){
  if(!confirm('Bu kişiyle tüm sohbet silinsin mi? Geri alınamaz.')) return;
  var fd=new FormData(); fd.append('del_conv',uid); fd.append('ajax','1');
  fetch('messages.php',{method:'POST',headers:{'X-CSRF-Token':window.CSRF_TOKEN},body:fd,credentials:'same-origin'}).then(function(){ location.reload(); });
}
</script>
<?php endif; ?>

<?php botx(); ?>
