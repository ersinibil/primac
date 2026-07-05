</main>
</div>
<script>
// Web Push kaydı — kullanıcı bildirimi: "web'de mesaj bildirimi gelmiyor" (mobilde vardı, web'de
// service worker/abonelik hiç yoktu). İlk kullanıcı etkileşiminde (tarayıcı politikası gereği izin
// isteği bir kullanıcı jestine bağlı olmalı) izin istenir, verilirse abone olunur.
(function(){
  function b64ToU8(b){var p='='.repeat((4-b.length%4)%4);var s=(b+p).replace(/-/g,'+').replace(/_/g,'/');var r=atob(s),a=new Uint8Array(r.length);for(var i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a;}
  function setupPush(reg){
    if(!('PushManager' in window)) return;
    fetch('push_subscribe.php?key=1',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(cfg){
      if(!cfg.key||!cfg.available) return;
      if(Notification.permission!=='granted') return;
      reg.pushManager.getSubscription().then(function(sub){
        if(sub) return sub;
        return reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64ToU8(cfg.key)});
      }).then(function(sub){
        if(!sub) return;
        fetch('push_subscribe.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.CSRF_TOKEN},body:JSON.stringify(sub)});
      }).catch(function(){});
    }).catch(function(){});
  }
  if(!('serviceWorker' in navigator)) return;
  navigator.serviceWorker.register('sw.js').then(function(){
    // pushManager.subscribe() aktif (activated) bir SW gerektirir — register()'ın döndürdüğü
    // registration henüz "installing" durumunda olabilir, .ready ile aktif olana kadar beklenir.
    return navigator.serviceWorker.ready;
  }).then(function(reg){
    if('Notification' in window && Notification.permission==='granted'){ setupPush(reg); }
    var asked=false;
    window.addEventListener('pointerdown',function(){
      if(asked || !('Notification' in window)) return; asked=true;
      if(Notification.permission==='default'){
        Notification.requestPermission().then(function(p){ if(p==='granted') setupPush(reg); });
      }else if(Notification.permission==='granted'){ setupPush(reg); }
    },{passive:true,once:true});
  }).catch(function(){});
})();
</script>
</body>
</html>
