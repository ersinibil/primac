<?php
require_once __DIR__.'/../boot.php'; require_login();
if(is_file(__DIR__.'/../activity_lib.php')) require_once __DIR__.'/../activity_lib.php'; // mobil olayları da loglansın
if(is_file(__DIR__.'/../notifications_lib.php')) require_once __DIR__.'/../notifications_lib.php';
$u=$_SESSION['user']??[]; $name=$u['name']??$u['username']??'Kullanıcı'; $role=$u['role']??''; $isAdmin=in_array($role,['admin','yonetici','yönetici'],true);
$ME=(int)($u['id']??0);
function mc($sql){try{return (int)(db()->query($sql)->fetch()['c']??0);}catch(Throwable $e){return 0;}}
function mm($v){return function_exists('money')?money($v):number_format((float)$v,2,',','.').' ₺';}
// Bildirimleri kişiselleştir: target_user_id kolonu (oturum başına bir kez migrate)
if(empty($_SESSION['_mig_notif'])){
    try{ if(!db()->query("SHOW COLUMNS FROM internal_notifications LIKE 'target_user_id'")->fetch()) db()->exec("ALTER TABLE internal_notifications ADD COLUMN target_user_id INT NULL"); }catch(Throwable $e){}
    $_SESSION['_mig_notif']=1;
}
// Bildirim tıklanınca doğru sayfaya gitsin: action_url kolonu (oturum başına bir kez migrate)
if(empty($_SESSION['_mig_notif_url'])){
    try{ if(!db()->query("SHOW COLUMNS FROM internal_notifications LIKE 'action_url'")->fetch()) db()->exec("ALTER TABLE internal_notifications ADD COLUMN action_url VARCHAR(255) DEFAULT NULL"); }catch(Throwable $e){}
    $_SESSION['_mig_notif_url']=1;
}
// Bir kullanıcıya bildirim (uygulama içi + Web Push). $uid null ise genel.
function notify_user($uid,$title,$msg='',$url='index.php'){
    try{ db()->prepare("INSERT INTO internal_notifications(title,message,target_user_id,action_url,is_read) VALUES(?,?,?,?,0)")->execute([$title,$msg,$uid?:null,$url]); }catch(Throwable $e){}
    if($uid && file_exists(__DIR__.'/../push_lib.php')){ require_once __DIR__.'/../push_lib.php'; try{ push_to_user((int)$uid,$title,$msg,$url); }catch(Throwable $e){} }
}
// TOPBAR MESSAGE BADGE GHOST COUNT düzeltmesi (2026-07-14): sender=receiver (kendine-atama kenar
// durumu) Mesajlar ekranında hiç görünmez (u.id<>$me ile hariç tutulur), sayaçta da sayılmamalı.
function unread_msg(){ static $v=null; global $ME; if($v===null) $v=mc("SELECT COUNT(*) c FROM internal_messages WHERE receiver_user_id=$ME AND is_read=0 AND sender_user_id IS NOT NULL AND sender_user_id<>receiver_user_id"); return $v; }
function unread_notif(){ static $v=null; global $ME; if($v===null) $v=function_exists('notif_unread_count')?notif_unread_count(db(),$ME):0; return $v; }
// Personel finansal/cari ekranlarına giremez — topx'ten ÖNCE çağrılır
// $module verilirse ve kullanıcıya o modül için yetki verilmişse (user_can()) admin olmasa da
// içeri alınır — 2026-07-02 denetiminde bulunan çakışma: eskiden SADECE rol (admin/yönetici)
// kontrol ediliyordu, users.php'den verilen granüler modül yetkisi (örn. 'personnel', 'report')
// mobilde tamamen görmezden geliniyordu (web'de aynı yetki page_module_map() ile çalışıyordu —
// kullanıcı şikayeti: "yetki verdiğim personel verdiğim yetki alanlarını görmüyor").
function block_personel($module=null){
    global $isAdmin;
    if($isAdmin) return;
    if($module && function_exists('user_can') && user_can($module)) return;
    header('Location: index.php'); exit;
}
function topx($t){global $name,$isAdmin; $um=unread_msg(); $un=unread_notif(); ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=htmlspecialchars($t)?></title><link rel="manifest" href="manifest.php"><meta name="theme-color" content="#071326"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black"><meta name="apple-mobile-web-app-title" content="<?=htmlspecialchars(app_config()['app_name'] ?? 'OTS')?>"><link rel="apple-touch-icon" href="icon.php?size=180"><link rel="icon" href="icon.php?size=192"><meta name="csrf-token" content="<?=h(csrf_token())?>"><?php /* DESIGN SYSTEM SPRINT 001 / PHASE A (2026-07-15) — yeni "ds-" foundation stylesheet, sadece
yeni sınıflar tanımlar, mevcut hiçbir class'a dokunmaz. */ if(function_exists('ds_styles')) ds_styles(); ?><script>
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('form').forEach(function(f){
    if(f.method.toLowerCase()!=='post') return;
    if(f.querySelector('input[name="csrf_token"]')) return;
    var i=document.createElement('input');
    i.type='hidden'; i.name='csrf_token'; i.value=window.CSRF_TOKEN;
    f.appendChild(i);
  });
});
</script><style>
:root{--radius-sm:12px;--radius-md:16px;--radius-lg:20px;--c-accent:#2563eb;--c-danger:#ef4444;--c-danger-bg:#fee2e2;--c-danger-text:#991b1b;--c-success:#16a34a;--c-success-bg:#dcfce7;--c-success-text:#166534;--c-warn:#f59e0b;--c-warn-bg:#fef3c7;--c-muted:#94a3b8}*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}body{margin:0;background:#071326;color:white;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;padding-top:env(safe-area-inset-top);padding-bottom:calc(118px + env(safe-area-inset-bottom));touch-action:manipulation;-webkit-tap-highlight-color:transparent}a,button,select,.card,.item,.nav a{touch-action:manipulation}.app{max-width:520px;margin:auto;padding:14px 16px}.top{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;position:sticky;top:0;z-index:50;background:#071326;padding:8px 0 10px}.top-row{display:flex;justify-content:space-between;align-items:center;gap:10px}.toolbar-search{display:flex;align-items:center;gap:8px;height:50px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);border-radius:var(--radius-md);padding:0 14px}.toolbar-search .ts-icon{font-size:18px;flex:0 0 auto;opacity:.65}.toolbar-search input{background:transparent;border:0;padding:0;margin:0;color:#fff;font-size:15px;height:100%}.toolbar-search input::placeholder{color:var(--c-muted)}body.chat-mode .toolbar-search{display:none}.brand{display:flex;gap:12px;align-items:center;color:white;text-decoration:none;min-width:0}.logo{width:46px;height:46px;border-radius:var(--radius-md);background:white;color:#071326;display:flex;align-items:center;justify-content:center;font-weight:1000;flex:0 0 auto}.small{color:var(--c-muted);font-size:13px}h1{font-size:22px;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.acts{display:flex;gap:8px;flex:0 0 auto}.pill{position:relative;background:rgba(255,255,255,.12);color:white;border-radius:var(--radius-sm);width:42px;height:42px;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:17px}.dot{position:absolute;top:-5px;right:-5px;background:var(--c-danger);color:#fff;font-size:10px;font-weight:900;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px}.panel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:var(--radius-lg);padding:15px;margin:12px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.card{min-height:104px;border-radius:var(--radius-lg);padding:14px;color:#0f172a;text-decoration:none;display:flex;flex-direction:column;justify-content:space-between}.card span{font-size:29px}.blue{background:#dbeafe}.green{background:#dcfce7}.orange{background:#ffedd5}.purple{background:#ede9fe}.red{background:#fee2e2}.yellow{background:#fef3c7}.teal{background:#ccfbf1}.gray{background:#f1f5f9}.item{display:block;background:rgba(255,255,255,.1);border-radius:var(--radius-md);padding:13px;margin:10px 0;color:white;text-decoration:none}.item small{color:var(--c-muted)}.activity-list{display:flex;flex-direction:column;gap:10px}.activity-item{display:flex;gap:12px;background:rgba(255,255,255,.1);border-radius:var(--radius-md);padding:13px;color:#fff;text-decoration:none}.activity-item:active{background:rgba(255,255,255,.16)}.activity-icon{width:42px;height:42px;border-radius:var(--radius-sm);background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto}.activity-body{flex:1;min-width:0}.activity-body p{margin:4px 0;color:var(--c-muted)}.activity-body small{color:var(--c-muted);font-size:12px}.notif-card{display:flex;gap:12px;align-items:flex-start}.notif-icon{flex:0 0 auto;width:42px;height:42px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:20px}.notif-body{flex:1;min-width:0}.notif-type{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.03em;color:var(--c-muted)}.notif-summary{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;color:var(--c-muted);margin-top:2px}.notif-more{font-size:12px;color:var(--c-accent);font-weight:700;margin-top:4px;display:block}.btn{display:inline-block;background:white;color:#071326;border:0;border-radius:var(--radius-sm);padding:12px 14px;text-decoration:none;font-weight:1000}.btn.dark{background:var(--c-accent);color:#fff}input,select,textarea{width:100%;border:0;border-radius:var(--radius-sm);padding:12px;font-size:16px;margin:6px 0 12px}.ok,.notice{background:var(--c-success-bg);color:var(--c-success-text);padding:12px;border-radius:var(--radius-sm)}.err{background:var(--c-danger-bg);color:var(--c-danger-text);padding:12px;border-radius:var(--radius-sm)}.muted{color:var(--c-muted)}.bottom{position:fixed;left:0;right:0;bottom:0;background:#071326;border-top:1px solid rgba(255,255,255,.12);padding:6px 8px calc(10px + env(safe-area-inset-bottom));z-index:1000;transform:translateZ(0);-webkit-transform:translateZ(0);will-change:transform;backface-visibility:hidden}.nav{max-width:520px;margin:auto;display:grid;grid-template-columns:repeat(5,1fr);gap:4px}.nav a{position:relative;display:block;text-align:center;color:#cbd5e1;text-decoration:none;font-weight:900;font-size:12.5px;padding:9px 2px;border-radius:var(--radius-sm)}.nav a:active{background:rgba(59,130,246,.28)}.nav span{display:block;font-size:26px;margin-bottom:2px;line-height:1}.nav .nd{position:absolute;top:-2px;right:14px;background:var(--c-danger);color:#fff;font-size:10px;font-weight:900;min-width:17px;height:17px;border-radius:9px;display:flex;align-items:center;justify-content:center}body.chat-mode .bottom{display:none}body.chat-mode{padding-bottom:0}body.kb .bottom{display:none!important}body.kb{padding-bottom:env(safe-area-inset-bottom)}body.chat-mode.kb{padding-bottom:0}.offline-banner{background:#b91c1c;border:1px solid #dc2626;border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:12px;display:none;align-items:center;gap:10px;color:#fecaca}.offline-banner span{font-size:18px;flex:0 0 auto}.offline-banner b{display:block;font-weight:900;font-size:14px;color:#fff}.fab{position:fixed;right:18px;bottom:calc(126px + env(safe-area-inset-bottom));width:56px;height:56px;border-radius:50%;background:var(--c-accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;text-decoration:none;box-shadow:0 8px 20px rgba(0,0,0,.35);z-index:900}body.chat-mode .fab,body.kb .fab{display:none}
</style></head><body class="mobile-shell"><div class="offline-banner" id="offline-banner"><span>📴</span><div><b>Offline</b><small style="color:#fecaca">Sinyal bulunamadı — önbellek kullanılıyor</small></div></div><div class="app"><div class="top"><div class="top-row"><a href="javascript:void(0)" id="backbtn" onclick="if(history.length>1){history.back();}else{location.href='index.php';}" style="flex:0 0 auto;width:40px;height:42px;background:rgba(255,255,255,.12);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;color:#fff;text-decoration:none;margin-right:2px">‹</a><a class="brand" href="index.php"><div class="logo"><img src="icon.php?size=96" alt="ACANS" style="width:100%;height:100%;object-fit:contain;border-radius:inherit"></div><div style="min-width:0"><h1><?=htmlspecialchars($t)?></h1><div class="small"><?=htmlspecialchars($name)?> · <?=$isAdmin?'Yönetici':'Personel'?></div></div></a><div class="acts"><a class="pill" href="messages.php">💬<i class="dot" data-badge="msg" style="<?=$um?'':'display:none'?>"><?=$um?($um>9?'9+':$um):''?></i></a><a class="pill" href="notifications.php">🔔<i class="dot" data-badge="notif" style="<?=$un?'':'display:none'?>"><?=$un?($un>9?'9+':$un):''?></i></a></div></div><form method="get" action="search.php" class="toolbar-search"><span class="ts-icon">🔍</span><input type="text" name="q" id="globalSearchInput" placeholder="İş, cari, personel, müşteri, stok, ürün, sipariş, mesaj, not ara..." autocomplete="off"></form><div id="searchSuggest" style="display:none"></div></div><?php }
function botx(){ $um=unread_msg(); $mb=$um?'<i class="nd">'.($um>9?'9+':$um).'</i>':''; ?></div><div class="bottom"><div class="nav"><a href="index.php"><span>🏠</span>Ana</a><a href="jobs.php"><span>📋</span>İş</a><a href="contacts.php"><span>👥</span>Cari</a><a href="messages.php"><span>💬</span>Mesaj<?=$mb?></a><a href="more.php"><span>☰</span>Menü</a></div></div>
<script>
/* Ana sayfada geri butonunu gizle */
(function(){var b=document.getElementById('backbtn');if(b&&(/(^|\/)index\.php$/.test(location.pathname)||/\/mobile\/?$/.test(location.pathname)))b.style.display='none';})();

/* Offline banner — sinyal durumunu göster */
(function(){
  var banner=document.getElementById('offline-banner');
  if(!banner) return;

  function updateOfflineStatus(){
    if(navigator.onLine){
      banner.style.display='none';
    }else{
      banner.style.display='flex';
    }
  }

  window.addEventListener('online',updateOfflineStatus);
  window.addEventListener('offline',updateOfflineStatus);
  updateOfflineStatus();
})();

/* Klavye açıkken alt menüyü gizle → sayfa zıplamasın, uygulama içinde yazılsın */
(function(){function f(e){return e&&(e.tagName==='INPUT'||e.tagName==='TEXTAREA');}document.addEventListener('focusin',function(e){if(f(e.target)){document.body.classList.add('kb');setTimeout(function(){try{e.target.scrollIntoView({block:'center',behavior:'smooth'});}catch(_){}}, 280);}});document.addEventListener('focusout',function(e){if(f(e.target))setTimeout(function(){var a=document.activeElement;if(!f(a))document.body.classList.remove('kb');},150);});})();
</script>
<div id="acans-toast" style="position:fixed;top:-160px;left:50%;transform:translateX(-50%);width:92%;max-width:480px;background:#1e293b;border:1px solid rgba(255,255,255,.18);border-radius:16px;padding:12px 14px;box-shadow:0 16px 40px rgba(0,0,0,.5);z-index:9999;transition:top .35s ease;display:flex;gap:10px;align-items:center;cursor:pointer;pointer-events:none">
  <div style="font-size:26px">💬</div>
  <div style="flex:1;min-width:0"><b id="acans-toast-t" style="display:block;color:#fff">Yeni mesaj</b><span id="acans-toast-b" style="color:#cbd5e1;font-size:13px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></span></div>
</div>
<!-- Uygulama içi medya görüntüleyici (WhatsApp/Telegram gibi) -->
<div id="acans-lb" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.96);z-index:100000;flex-direction:column">
  <div style="display:flex;justify-content:space-between;align-items:center;padding:calc(10px + env(safe-area-inset-top)) 14px 10px">
    <button id="lb-save" style="background:#2563eb;color:#fff;border:0;border-radius:12px;padding:11px 16px;font-weight:900">⬇ Kaydet</button>
    <button onclick="ACANS_LB_CLOSE()" style="background:rgba(255,255,255,.18);color:#fff;border:0;border-radius:12px;width:44px;height:44px;font-size:22px">✕</button>
  </div>
  <div id="lb-body" style="flex:1;display:flex;align-items:center;justify-content:center;overflow:auto;padding:10px" onclick="if(event.target.id==='lb-body')ACANS_LB_CLOSE()"></div>
  <div id="lb-hint" style="text-align:center;color:#94a3b8;font-size:12px;padding:0 14px calc(10px + env(safe-area-inset-bottom))">İpucu: Fotoğrafa basılı tutarak da “Fotoğraflara Ekle” diyebilirsin.</div>
</div>
<script>
var _lbUrl=null,_lbType=null;
window.ACANS_VIEW=function(url,type){
  var lb=document.getElementById('acans-lb'),body=document.getElementById('lb-body');
  _lbUrl=url; _lbType=type;
  if(type==='video'){ body.innerHTML='<video src="'+url+'" controls autoplay playsinline style="max-width:100%;max-height:100%"></video>'; }
  else if(type==='file'){ body.innerHTML='<div style="text-align:center;color:#fff"><div style="font-size:64px">📄</div><a href="'+url+'" target="_blank" style="color:#60a5fa;font-weight:900">Dosyayı aç</a></div>'; }
  else { body.innerHTML='<img src="'+url+'" style="max-width:100%;max-height:100%;object-fit:contain">'; }
  document.getElementById('lb-hint').style.display=(type==='image')?'block':'none';
  lb.style.display='flex';
};
window.ACANS_LB_CLOSE=function(){ var lb=document.getElementById('acans-lb'); if(lb){lb.style.display='none';document.getElementById('lb-body').innerHTML='';} };
// Kaydet → iOS Paylaş Sayfası ("Görüntüyü Kaydet" → Fotoğraflar). Desteklenmezse indir.
document.getElementById('lb-save').addEventListener('click',function(){
  if(!_lbUrl) return;
  var btn=this; btn.textContent='⏳';
  fetch(_lbUrl).then(function(r){return r.blob();}).then(function(blob){
    var ext=(_lbUrl.split('?')[0].split('.').pop()||'jpg');
    var fname='ACANS_'+Date.now()+'.'+ext;
    var file=new File([blob],fname,{type:blob.type||'image/jpeg'});
    if(navigator.canShare && navigator.canShare({files:[file]})){
      navigator.share({files:[file]}).then(function(){btn.textContent='⬇ Kaydet';}).catch(function(){btn.textContent='⬇ Kaydet';});
    }else{
      var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=fname; document.body.appendChild(a); a.click(); a.remove(); btn.textContent='⬇ Kaydet';
    }
  }).catch(function(){ btn.textContent='⬇ Kaydet'; });
});
</script>
<script>
/* ACANS OTS — canlı bildirim: yeni mesajda UYGULAMA İÇİ BANNER + ses + titreşim + rozet (+ native bildirim) */
(function(){
  var lastMsg=parseInt(localStorage.getItem('acans_lastMsg')||'0',10);
  var lastNotif=parseInt(localStorage.getItem('acans_lastNotif')||'0',10);
  var first=true, ac=null, toastTimer=null;
  var toast=document.getElementById('acans-toast');

  function beep(){
    try{
      if(!ac){ac=new (window.AudioContext||window.webkitAudioContext)();}
      if(ac.state==='suspended')ac.resume();
      var t=ac.currentTime;
      [880,1320,880].forEach(function(f,i){var o=ac.createOscillator(),g=ac.createGain();o.type='sine';o.frequency.value=f;o.connect(g);g.connect(ac.destination);var s=t+i*0.14;g.gain.setValueAtTime(0.0001,s);g.gain.exponentialRampToValueAtTime(0.4,s+0.02);g.gain.exponentialRampToValueAtTime(0.0001,s+0.16);o.start(s);o.stop(s+0.18);});
    }catch(e){}
  }
  var permAsked=false;
  function unlock(){ try{ if(!ac){ac=new (window.AudioContext||window.webkitAudioContext)();} if(ac.state==='suspended')ac.resume(); }catch(e){} }
  // İzin isteği SADECE ilk dokunuşta + izin verilince HEMEN abone ol (push'un asıl düzeltmesi)
  function subAfterGrant(){ if('serviceWorker'in navigator){ navigator.serviceWorker.ready.then(function(r){ setupPush(r); }); } }
  function askPerm(){ if(permAsked) return; permAsked=true;
    try{
      if('Notification'in window && Notification.permission==='default'){
        Notification.requestPermission().then(function(p){ if(p==='granted') subAfterGrant(); });
      } else if('Notification'in window && Notification.permission==='granted'){ subAfterGrant(); }
    }catch(e){}
  }
  window.addEventListener('pointerdown',unlock,{passive:true});
  window.addEventListener('touchstart',unlock,{passive:true});
  window.addEventListener('pointerdown',askPerm,{passive:true,once:true});

  function showToast(title,body,url){
    if(!toast) return;
    document.getElementById('acans-toast-t').textContent=title;
    document.getElementById('acans-toast-b').textContent=body;
    toast.onclick=function(){location.href=url;};
    toast.style.pointerEvents='auto';
    toast.style.top='calc(12px + env(safe-area-inset-top))';
    clearTimeout(toastTimer);
    toastTimer=setTimeout(function(){toast.style.top='-160px';toast.style.pointerEvents='none';},4500);
  }
  function nativeNotify(title,body,url){
    if('Notification'in window&&Notification.permission==='granted'){
      try{var x=new Notification(title,{body:body,icon:'icon.php?size=192',tag:'acans-'+Date.now()});x.onclick=function(){window.focus();location.href=url;};}catch(e){}
    }
  }
  function setBadges(m,n){
    document.querySelectorAll('[data-badge="msg"]').forEach(function(e){e.textContent=m>0?(m>9?'9+':m):'';e.style.display=m>0?'':'none';});
    document.querySelectorAll('[data-badge="notif"]').forEach(function(e){e.textContent=n>0?(n>9?'9+':n):'';e.style.display=n>0?'':'none';});
  }
  // Test/Etkinleştir butonu için global (kullanıcı dokunuşuyla çağrılır)
  window.ACANS_TEST=function(){
    try{ if(!ac){ac=new (window.AudioContext||window.webkitAudioContext)();} if(ac.state==='suspended')ac.resume(); }catch(e){}
    var msg='Ses + banner çalışıyor.';
    if('Notification'in window){
      if(Notification.permission==='default'){ try{ Notification.requestPermission().then(function(p){ if(p==='granted'){ nativeNotify('🔔 <?=htmlspecialchars(app_config()['app_name'] ?? 'OTS')?>','Bildirim açıldı','#'); if('serviceWorker'in navigator)navigator.serviceWorker.ready.then(function(r){setupPush(r);}); } }); }catch(e){} }
      else if(Notification.permission==='granted'){ nativeNotify('🔔 <?=htmlspecialchars(app_config()['app_name'] ?? 'OTS')?>','Test bildirimi','#'); if('serviceWorker'in navigator)navigator.serviceWorker.ready.then(function(r){setupPush(r);}); }
      else { msg='Bildirim izni REDDEDİLMİŞ — telefon ayarlarından izin ver.'; }
    }
    beep();
    showToast('🔔 Test',msg,'#');
    if(navigator.vibrate)navigator.vibrate([140,70,140]);
    return msg;
  };
  function poll(){
    var conv=window.ACANS_CONV||0, convSince=window.ACANS_CONV_SINCE||0;
    var ct=window.ACANS_CONV_THREAD||0;
    var u='poll.php?since_msg='+lastMsg+'&since_notif='+lastNotif+(conv?'&conv='+conv+'&conv_since='+convSince:'')+(ct?'&conv_thread='+ct+'&conv_since='+convSince:'');
    fetch(u,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
      if(!d||!d.auth)return;
      setBadges(d.msg_unread||0,d.notif_unread||0);
      if(!first && d.new && d.new.length){
        var fired=false;
        d.new.forEach(function(it){
          if(parseInt(it.with,10)===parseInt(conv,10)) return; // o sohbeti zaten izliyorsa atla
          showToast('💬 '+it.from,it.body,'messages.php?with='+it.with);
          fired=true;
        });
        if(fired){ beep(); if(navigator.vibrate)navigator.vibrate([140,70,140]); }
      }
      if(window.ACANS_ON_CONV && d.conv_new && d.conv_new.length){ window.ACANS_ON_CONV(d.conv_new); }
      if(window.ACANS_ON_STATUS){ window.ACANS_ON_STATUS(d); }
      if(typeof d.last_msg_id==='number'){lastMsg=d.last_msg_id;localStorage.setItem('acans_lastMsg',lastMsg);}
      if(typeof d.last_notif_id==='number'){lastNotif=d.last_notif_id;localStorage.setItem('acans_lastNotif',lastNotif);}
      first=false;
    }).catch(function(){});
  }
  setTimeout(poll,1500); setInterval(poll,7000);

  // Anında geri bildirim: link/buton dokununca üst yükleme çubuğu → "tıklandı" hissi (tekrar tıklamaya gerek yok)
  var bar=document.createElement('div');
  bar.style.cssText='position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#3b82f6,#22d3ee);z-index:99999;transition:width .25s ease;box-shadow:0 0 8px #3b82f6';
  document.body.appendChild(bar);
  function goBar(){ bar.style.width='85%'; }
  document.addEventListener('click',function(e){
    var a=e.target.closest('a');
    if(a && a.getAttribute('href') && a.getAttribute('target')!=='_blank' && a.getAttribute('href').charAt(0)!=='#'){ goBar(); }
  },true);
  document.addEventListener('submit',function(){ goBar(); },true);
  window.addEventListener('pageshow',function(){ bar.style.width='0'; });

  /* Web Push: uygulama kapalıyken bildirim — SW kaydı + abonelik */
  function b64ToU8(b){var p='='.repeat((4-b.length%4)%4);var s=(b+p).replace(/-/g,'+').replace(/_/g,'/');var r=atob(s),a=new Uint8Array(r.length);for(var i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a;}
  function setupPush(reg){
    if(!('PushManager' in window)) return;
    fetch('../push_subscribe.php?key=1',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(cfg){
      if(!cfg.key||!cfg.available) return;
      if(Notification.permission!=='granted') return; // izin ilk dokunuşta isteniyor
      reg.pushManager.getSubscription().then(function(sub){
        if(sub) return sub;
        return reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64ToU8(cfg.key)});
      }).then(function(sub){
        if(!sub) return;
        fetch('../push_subscribe.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.CSRF_TOKEN},body:JSON.stringify(sub)})
          .then(function(r){return r.json();})
          .then(function(res){ if(res&&res.ok&&!localStorage.getItem('acans_pushDone')){ localStorage.setItem('acans_pushDone','1'); try{ showToast('🔔 Bildirimler','Bu cihaz için AÇILDI ✅','#'); }catch(e){} } });
      }).catch(function(){});
    }).catch(function(){});
  }
  if('serviceWorker' in navigator){
    navigator.serviceWorker.register('sw.js').then(function(reg){
      // izin verildiyse hemen, yoksa ilk dokunuşta tekrar dene
      setupPush(reg);
      window.addEventListener('pointerdown',function(){ setTimeout(function(){ setupPush(reg); },500); },{once:true});
    }).catch(function(){});
  }

  /* Kurulum: Android'de gerçek "Yükle" düğmesi, iOS'ta talimat */
  var deferredPrompt=null;
  window.addEventListener('beforeinstallprompt',function(e){ e.preventDefault(); deferredPrompt=e; });
  try{
    var standalone = (window.navigator.standalone===true) || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    if(!standalone && !localStorage.getItem('acans_hideInstall')){
      var isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
      var b=document.createElement('div');
      b.style.cssText='position:fixed;left:10px;right:10px;bottom:78px;background:#111827;border:1px solid rgba(255,255,255,.18);border-radius:16px;padding:12px 14px;z-index:9998;display:flex;gap:10px;align-items:center;box-shadow:0 14px 40px rgba(0,0,0,.5)';
      var label=isIOS?'Alttaki boşluk/çubuk <b>Safari yüzünden</b>. <b>Paylaş ⬆️ → “Ana Ekrana Ekle”</b>, sonra uygulamayı <b>ana ekrandaki ikondan</b> aç — boşluk kaybolur, tam ekran olur.':'“Yükle”ye dokun, uygulama olarak kurulsun.';
      b.innerHTML='<div style="font-size:24px">📲</div><div style="flex:1;color:#fff;font-size:13px"><b>Tam ekran için kur</b><br><span style="color:#cbd5e1">'+label+'</span></div><button id="ai-act" style="background:#2563eb;color:#fff;border:0;border-radius:10px;padding:8px 12px;font-weight:800">'+(isIOS?'Anladım':'Yükle')+'</button>';
      b.querySelector('#ai-act').onclick=function(){
        if(!isIOS && deferredPrompt){ deferredPrompt.prompt(); deferredPrompt.userChoice.then(function(){deferredPrompt=null;}); }
        localStorage.setItem('acans_hideInstall','1'); b.remove();
      };
      document.body.appendChild(b);
    }
  }catch(e){}
})();
</script>
</body></html><?php }
function card($t,$d,$i,$u,$c){echo '<a class="card '.$c.'" href="'.$u.'"><span>'.$i.'</span><b>'.htmlspecialchars($t).'</b><small>'.htmlspecialchars($d).'</small></a>';}
?>
