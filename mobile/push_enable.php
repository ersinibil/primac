<?php require_once 'common.php'; $isDev=user_can('users'); topx('Bildirim Kur'); ?>
<div class="panel">
  <b>🔔 Bildirimler</b>
  <p class="small">Bu sayfayı <b>ana ekrandaki ikondan</b> açtığından emin ol, sonra aşağıdaki butona bas.</p>
</div>
<?php if($isDev): ?>
<div class="panel" id="env" style="font-size:13px;line-height:1.8"></div>
<?php endif; ?>
<button class="btn dark" style="width:100%;padding:15px;font-size:16px" onclick="enablePush()">🔔 Bildirimleri Aç</button>
<div class="panel" style="margin-top:12px"><p id="simpleStatus" class="small" style="margin:0">Hazır. Butona bas.</p></div>
<?php if($isDev): ?>
<div class="panel" style="margin-top:12px"><pre id="log" style="white-space:pre-wrap;word-break:break-word;color:#cbd5e1;font-size:12px;margin:0">Hazır. Butona bas.</pre></div>
<?php endif; ?>
<script>
var IS_DEV = <?=$isDev?'true':'false'?>;
function L(s){ if(!IS_DEV) return; var el=document.getElementById('log'); if(el) el.textContent += '\n'+s; }
function setSimple(s){ var el=document.getElementById('simpleStatus'); if(el) el.textContent=s; }
function b64ToU8(b){var p='='.repeat((4-b.length%4)%4);var s=(b+p).replace(/-/g,'+').replace(/_/g,'/');var r=atob(s),a=new Uint8Array(r.length);for(var i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a;}
<?php if($isDev): ?>
(function(){
  var sa=(window.navigator.standalone===true)||(window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches);
  var perm=('Notification'in window)?Notification.permission:'YOK';
  document.getElementById('env').innerHTML=
    '• Standalone (ikondan açık): <b style="color:'+(sa?'#4ade80':'#f87171')+'">'+(sa?'EVET ✅':'HAYIR ❌ (Safari sekmesi!)')+'</b><br>'+
    '• Notification API: <b>'+(('Notification'in window)?'var':'YOK ❌')+'</b><br>'+
    '• Bildirim izni: <b>'+perm+'</b><br>'+
    '• PushManager: <b>'+(('PushManager'in window)?'var ✅':'YOK ❌')+'</b><br>'+
    '• ServiceWorker: <b>'+(('serviceWorker'in navigator)?'var ✅':'YOK ❌')+'</b><br>'+
    '• iOS sürümü: <b>'+(navigator.userAgent.match(/OS (\d+_\d+)/)?navigator.userAgent.match(/OS (\d+_\d+)/)[1].replace('_','.'):'?')+'</b>';
}());
<?php endif; ?>
function enablePush(){
  if(document.getElementById('log')) document.getElementById('log').textContent='Başlıyor...';
  setSimple('Kontrol ediliyor...');
  if(!('serviceWorker'in navigator)){ L('❌ ServiceWorker yok — bu tarayıcı desteklemiyor.'); setSimple('Bu cihaz/tarayıcı bildirim özelliğini desteklemiyor.'); return; }
  if(!('PushManager'in window)){ L('❌ PushManager yok — iOS ise uygulamayı İKONDAN aç (Safari değil).'); setSimple('Bu cihaz/tarayıcı bildirim özelliğini desteklemiyor.'); return; }
  L('1) İzin isteniyor...');
  Notification.requestPermission().then(function(p){
    L('   izin sonucu: '+p);
    if(p!=='granted'){ L('❌ İzin verilmedi. Telefon Ayarlar→Bildirimler\'den de açabilirsin.'); setSimple('Bildirim izni verilmedi. Telefon ayarlarından bildirimleri açabilirsiniz.'); return; }
    L('2) ServiceWorker kaydı...');
    return navigator.serviceWorker.register('sw.js').then(function(){ return navigator.serviceWorker.ready; });
  }).then(function(reg){
    if(!reg) return;
    L('   SW hazır ✅');
    L('3) VAPID anahtarı alınıyor...');
    return fetch('../push_subscribe.php?key=1',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(cfg){
      L('   key: '+(cfg.key?cfg.key.substring(0,16)+'...':'YOK')+' · available: '+cfg.available);
      if(!cfg.key){ L('❌ VAPID public key gelmedi.'); setSimple('Bildirimler şu anda etkinleştirilemedi. Lütfen tekrar deneyin.'); return; }
      L('4) Abonelik (subscribe) deneniyor...');
      return reg.pushManager.getSubscription().then(function(s){
        if(s){ L('   zaten abone, güncelleniyor.'); return s; }
        return reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64ToU8(cfg.key)});
      });
    });
  }).then(function(sub){
    if(!sub) return;
    L('   subscribe OK ✅ endpoint: '+sub.endpoint.substring(0,40)+'...');
    L('5) Sunucuya kaydediliyor...');
    return fetch('../push_subscribe.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.CSRF_TOKEN},body:JSON.stringify(sub)}).then(function(r){return r.json();});
  }).then(function(res){
    if(res&&res.ok){ L('✅✅ TAMAM! Abonelik kaydedildi. Artık push gelir.'); setSimple('Bildirimler aktif. Bu cihaz bildirim almaya hazır.'); if(navigator.vibrate)navigator.vibrate([120,60,120]); }
    else if(res){ L('❌ Kayıt hatası: '+(res.error||JSON.stringify(res))); setSimple('Bildirimler şu anda etkinleştirilemedi. Lütfen tekrar deneyin.'); }
  }).catch(function(e){ L('❌ HATA: '+(e&&e.message?e.message:e)); L('(iOS\'ta en sık sebep: uygulama İKONDAN açılmadı.)'); setSimple('Bildirimler şu anda etkinleştirilemedi. Lütfen tekrar deneyin.'); });
}
</script>
<?php botx(); ?>
