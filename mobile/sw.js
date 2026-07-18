// ACANS OTS mobil SW — Offline çalışma + Web Push (uygulama kapalıyken bildirim)
// PX-002 BRAND AREA v1 (2026-07-17): v27→v28 — icon.php cache-first önbelleğe alınıyordu
// (STATIC_ASSETS), logo_primac.png içeriği değişince PWA eski (kırmızımsı/krem) logoyu sonsuza
// dek göstermeye devam ediyordu. Versiyon adı değişince activate() eski cache'i siler, install()
// icon.php'yi yeniden fetch eder — kullanıcı hiçbir şey yapmadan (uygulamayı kapat/aç yeter).
// P0 MOBİL SHELL 2. REGRESYON KÖK NEDENİ (2026-07-18): v28→v29 — assets/css/ds-foundation.css ve
// assets/js/ds-foundation.js "isTrulyStatic" olduğu için CACHE-FIRST önbelleğe alınıyor (fetch
// handler'ın aşağıdaki yorumuna bakın). Bu dosya (sw.js) 2026-07-17'den beri HİÇ değişmemişti —
// yani bu oturumda ds-foundation.css'e yapılan TÜM düzeltmeler (chat-mode/bottom-nav, tema
// token'ları, sekme taşması vb.) kullanıcının telefonunda ESKİ CACHE'TEN sunulmaya devam ediyordu,
// sunucudaki kod doğru olsa bile. Versiyon adı değişince activate() eski cache'i SİLER, yeni
// service worker self.skipWaiting()+self.clients.claim() ile HEMEN devreye girer — kullanıcının
// "Ana Ekrandaki uygulamayı kapatıp yeniden açması" yeterli olur, cache elle temizlenmesine gerek
// kalmaz. Bundan sonra bu dosyanın HER ds-foundation.css/js değişikliğiyle BİRLİKTE bump
// edilmesi gerekiyor — aksi halde aynı sınıf hata sessizce tekrarlanır.
// P0 MOBİL HOTFIX (2026-07-19): v29→v30 — ds-foundation.css'te .df-m-bottomnav min-height kilidi
// eklendi (composer/nav çakışması kök neden düzeltmesi) — AYNI KURAL, bump zorunlu.
const CACHE='acans-os-v30';
const STATIC_ASSETS=[
  './',
  './index.php',
  './manifest.php',
  './icon.php?size=192',
  './icon.php?size=180',
  './icon.php?size=96'
];

self.addEventListener('install',e=>{
  e.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(STATIC_ASSETS)).catch(()=>{}));
  self.skipWaiting();
});

self.addEventListener('activate',e=>{
  e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==CACHE).map(k=>caches.delete(k)))));
  self.clients.claim();
});

self.addEventListener('fetch',e=>{
  if(e.request.method!=='GET') return;

  var url=new URL(e.request.url);
  // Gerçekten statik (nadiren değişen) varlıklar: js/css/görsel + icon.php/manifest.php.
  // Bunlar cache-first olabilir. DİĞER TÜM .php sayfaları (kasa.php, notifications.php,
  // index.php gibi bildirim/sayı/bakiye içeren dinamik içerik) network-first OLMALI —
  // 2026-07-03 denetiminde bulundu: hepsi cache-first yapılmıştı, kullanıcı sinyali olsa
  // bile bir kez açılan sayfa hep ESKİ veriyi (eski mesaj sayısı, eski bakiye) gösteriyordu.
  var isTrulyStatic = url.pathname.match(/\.(js|css|png|jpg|jpeg|webp|svg|gif)$/)
    || url.pathname.endsWith('icon.php') || url.pathname.endsWith('manifest.php');

  if(isTrulyStatic){
    e.respondWith(
      caches.match(e.request).then(function(cached){
        if(cached) return cached;
        return fetch(e.request).then(function(response){
          if(response && response.status===200 && response.type!=='error'){
            var clone=response.clone();
            caches.open(CACHE).then(cache=>cache.put(e.request,clone)).catch(()=>{});
          }
          return response;
        });
      })
    );
    return;
  }

  // Dinamik .php sayfaları: ÖNCE network dene, sadece network gerçekten başarısızsa
  // (offline/sinyal yok) cache'deki son bilinen hale düş — "uygulamanın kabuğu offline'da
  // açılabilsin" ihtiyacı bununla karşılanıyor, veri tazeliğinden ödün verilmiyor.
  e.respondWith(
    fetch(e.request).then(function(response){
      if(response && response.status===200 && response.type!=='error' && url.pathname.endsWith('.php')){
        var clone=response.clone();
        caches.open(CACHE).then(cache=>cache.put(e.request,clone)).catch(()=>{});
      }
      return response;
    }).catch(function(){
      return caches.match(e.request).then(cached=>cached || new Response('Offline - İçerik yok',{status:503}));
    })
  );
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
