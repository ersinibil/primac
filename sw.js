// OTS web — Web Push service worker (masaüstü/tarayıcı; offline cache YOK, mobildeki
// mobile/sw.js'in aksine — web tarafı için sadece bildirim gerekiyordu, bkz. görev notu).
self.addEventListener('install', e => { self.skipWaiting(); });
self.addEventListener('activate', e => { self.clients.claim(); });

self.addEventListener('push', e => {
  let d = {title:'OTS', body:'Yeni bildirim', url:'dashboard.php'};
  try{ if(e.data) d = Object.assign(d, e.data.json()); }catch(err){ try{ d.body = e.data.text(); }catch(x){} }
  e.waitUntil(
    self.clients.matchAll({type:'window', includeUncontrolled:true}).then(function(cs){
      var open = cs.some(function(c){ return c.focused === true; });
      if(open) return; // sekme zaten önde/aktifse ayrıca sistem bildirimi gösterme
      return self.registration.showNotification(d.title, {
        body: d.body, tag: 'ots-msg', renotify: true,
        data: {url: d.url || 'dashboard.php'}
      });
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || 'dashboard.php';
  e.waitUntil(clients.matchAll({type:'window', includeUncontrolled:true}).then(list => {
    for(const c of list){ if('focus' in c){ c.navigate(url); return c.focus(); } }
    if(clients.openWindow) return clients.openWindow(url);
  }));
});
