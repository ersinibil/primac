// ACANS OS mobil SW — ağ öncelikli + Web Push (uygulama kapalıyken bildirim)
const CACHE='acans-os-v25';
self.addEventListener('install',e=>{ self.skipWaiting(); });
self.addEventListener('activate',e=>{
  e.waitUntil(caches.keys().then(ks=>Promise.all(ks.map(k=>caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch',e=>{
  if(e.request.method!=='GET') return;
  e.respondWith(fetch(e.request).catch(()=>caches.match(e.request)));
});

// Gelen push → bildirim göster (uygulama kapalı/arka planda olsa da)
self.addEventListener('push',e=>{
  let d={title:'ACANS OS',body:'Yeni bildirim',url:'index.php'};
  try{ if(e.data) d=Object.assign(d,e.data.json()); }catch(err){ try{ d.body=e.data.text(); }catch(x){} }
  e.waitUntil(
    self.clients.matchAll({type:'window',includeUncontrolled:true}).then(function(cs){
      // Uygulama açık/önde ise push banner GÖSTERME → uygulama içi toast hallediyor (tek bildirim)
      var open=cs.some(function(c){ return c.focused===true; }); // sadece AKTİF/önde pencerede bastır; arka plan/kapalıda push göster
      if(open) return;
      return self.registration.showNotification(d.title,{
        body:d.body, icon:'icon.php?size=192', badge:'icon.php?size=192',
        data:{url:d.url||'index.php'}, vibrate:[140,70,140], tag:'acans-msg', renotify:true
      });
    })
  );
});

// Bildirime tıkla → uygulamayı aç/öne getir, ilgili sayfaya git
self.addEventListener('notificationclick',e=>{
  e.notification.close();
  const url=(e.notification.data&&e.notification.data.url)||'index.php';
  e.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(list=>{
    for(const c of list){ if('focus'in c){ c.navigate(url); return c.focus(); } }
    if(clients.openWindow) return clients.openWindow(url);
  }));
});
