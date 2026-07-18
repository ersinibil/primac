<?php require_once 'common.php';
$pdo=db();
// P0 MOBİL SHELL KAPANIŞI (2026-07-18): kategori seviyesindeyken başlık + geri hedefi kategoriye
// göre değişir (Menü'ye deterministik döner) — topx() bu yüzden $__openCat çözüldükten SONRA
// çağrılıyor (nav_lib.php zaten common.php→boot.php zinciriyle yüklü).
// P0 MOBİL UX (2026-07-18, Product Owner kararı 2. madde) — "Ayarlar" (Görünüm/Bildirim/Profil)
// gerçek bir kategori DEĞİL (nav_category_keys() 5 iş kategorisiyle karışmasın diye) — açık
// bir sözde-kategori olarak ayrı tutuluyor, aynı ?open= parametresini paylaşıyor (ikinci bir
// URL şeması İCAT EDİLMEDİ).
$__openCatTitle = $_GET['open'] ?? '';
$__isSettings = ($__openCatTitle === 'ayarlar');
if(!$__isSettings && !in_array($__openCatTitle, nav_category_keys(), true)) $__openCatTitle='';
$__pageTitle = $__isSettings ? 'Ayarlar' : ($__openCatTitle!=='' ? nav_category_label($__openCatTitle) : 'Menü');
topx($__pageTitle, ($__isSettings || $__openCatTitle!=='') ? 'more.php' : null, 'Menü');
/* NAV-001B (2026-07-16) — Product Owner kararı: "Diğer kullanıcıların mevcut web VE MOBİL
 * deneyimi birebir korunacaktır" — bu dosya da $__navMode'a göre iki yola ayrılıyor.
 * legacy: ORİJİNAL renkli-kart menü listesi birebir korunur (aşağıda, değişmedi).
 * compact: nav_lib.php tabanlı gruplu Module Launcher (Product Owner'ın istediği yeni deneyim).
 */
if($__navMode === 'legacy'):
?>
<div style="font-weight:900;margin:16px 4px 8px">🧭 İş / Üretim</div>
<div class="grid">
  <?php
  card('Görevlerim','Bana atanan görevler ve hatırlatmalar','✅','mytasks.php','green');
  card('Kendime İş Ekle','Kendine iş kaydı oluştur','➕','mytask_new.php','green');
  if(user_can('jobs')) {
    card('İş Emirleri','Müşteri işleri ve operasyon takibi','📋','jobs.php','green');
    card('Üretim','Aşama panosu','🏭','uretim.php','red');
    card('Takvim','Termin takvimi','📅','calendar.php','blue');
    card('Müşteri Onayı','Onay bekleyen dosyalar','⏳','approval_waiting.php','yellow');
    card('Dış İşler','Dış atölye/tedarik','🏭','external.php','blue');
  }
  if(user_can('tasks')) {
    card('Tüm Görevler','Görev yönetimi (herkes)','✅','tasks.php','teal');
  }
  if($isAdmin || user_can('tasks')) {
    card('İş Ekle','Personele iş ata','🎯','task_new.php','teal');
  }
  if($isAdmin) {
    card('Talepler','Talep onay merkezi','📨','requests.php','orange');
  }
  if($isAdmin || user_can('personnel')) {
    card('Personel','Ekip listesi','👷','personnel.php','purple');
    card('Performans','Personel KPI','🏆','kpi.php','orange');
  }
  if($isAdmin || user_can('report')) {
    card('Günlük Rapor','Bugünkü iş özeti','📅','gunluk_rapor.php','blue');
    card('İş Emirleri Raporu','Açılan·tamamlanan·geciken','📋','report.php?modul=is','purple');
    card('Personel Raporu','Performans/KPI','👷','report.php?modul=personel','orange');
  }
  ?>
</div>

<?php if(user_can('contacts') || user_can('stock') || user_can('teklif')): ?>
<div style="font-weight:900;margin:16px 4px 8px">🤝 Ticaret</div>
<div class="grid">
  <?php
  if(user_can('contacts')) {
    card('Cariler','Müşteri/tedarikçi','👥','contacts.php','purple');
    card('Yeni Cari','Cari ekle','➕','contact_new.php','blue');
  }
  if(user_can('stock')) {
    card('Stok','Ürün listesi','📦','stock.php','red');
    card('Yeni Ürün','Ürün ekle','➕','product_new.php','green');
    card('Satış','Satış yap','🧾','sales.php','orange');
    card('Satın Alma','Alış + stok kartı','🛒','purchase.php','green');
    card('Stok Giriş/Çıkış','Manuel stok hareketi','↕️','stock_movement_new.php','yellow');
    card('Ürün Kategorileri','Kategori yönetimi','🏷️','product_categories.php','gray');
    card('Marka / Birim','Taksonomi yönetimi','🔖','product_taxonomy.php','gray');
  }
  if(user_can('teklif')) {
    card('Teklif','Teklif hazırla/gönder','📄','teklif.php','blue');
  }
  ?>
</div>
<?php endif; ?>

<?php if(user_can('finance') || user_can('muhasebe')): ?>
<div style="font-weight:900;margin:16px 4px 8px">💰 Finans</div>
<div class="grid">
  <?php
  if(user_can('finance')) {
    card('Kasa Durumu','Banka/kasa/kart','🏦','kasa.php','teal');
    card('Tahsilat','Tahsilat gir','💰','collection.php','yellow');
    card('Ödeme / Gider','Gider/ödeme gir','💸','payment.php','red');
    card('Transfer','Hesaplar arası / karta ödeme','↔️','transfer.php','blue');
    card('Çek / Senet','Vade takibi','🧾','checks_notes.php','purple');
  }
  if(user_can('muhasebe')) {
    card('Muhasebe','Gider · Gelir · Personel','📒','accounting.php','purple');
    card('Yeni Kayıt','Hızlı gider/gelir gir','➕','accounting.php#yeni','orange');
    if($isAdmin) card('Muhasebe Kategorileri','Kategori yönetimi','⚙️','accounting_categories.php','gray');
  }
  ?>
</div>
<?php endif; ?>

<div style="font-weight:900;margin:16px 4px 8px">💬 İletişim Merkezi</div>
<div class="grid">
  <?php
  card('Sohbetler','İç yazışma','💬','messages.php','teal');
  card('Bildirimler','Kişisel bildirimler','🔔','notifications.php','yellow');
  card('Taleplerim','Gönderdiğim talepler','📨','taleplerim.php','purple');
  card('Duyurular','Genel duyurular','📢','duyurular.php','orange');
  if(user_can('users')) {
    card('WhatsApp Konuşmaları','Gelen/giden geçmiş, 1:1 yazış','💬','wa_conversations.php','teal');
    card('Toplu WhatsApp Gönderimi','Birden fazla kişiye mesaj','📤','wa_send_now.php','green');
  }
  ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">📊 Raporlama</div>
<div class="grid">
  <?php
  if($isAdmin) card('Son İşlemler','Aktivite akışı','🕘','activity.php','gray');
  if($isAdmin || user_can('report')) {
    card('Genel Özet Rapor','Yekün özet','📊','report.php?modul=genel','blue');
    card('Tüm Modüller','Hepsi tek sunum','🗂️','report.php?modul=tumu','blue');
  }
  if(user_can('contacts')) {
    card('Cari Raporlar','Alacaklı/borçlu · toplu ekstre','📊','contacts_report.php','teal');
  }
  if($isAdmin || user_can('report')) {
    card('Finans/Tahsilat','Tahsilat·ödeme·net','💰','report.php?modul=tahsilat','green');
    card('Muhasebe Raporu','Kategori gelir/gider','📒','report.php?modul=muhasebe','purple');
    card('Cari','Bakiye·hareket','👥','report.php?modul=cari','teal');
    card('Satış Raporu','Satış dökümü','🧾','report.php?modul=satis','yellow');
    card('Satın Alma Raporu','Alış dökümü','📥','report.php?modul=satinalma','green');
    card('Teklif Raporu','Teklif dökümü','📄','report.php?modul=teklif','blue');
    card('Stok Raporu','Kritik·stok değeri','📦','report.php?modul=stok','red');
  }
  ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">🕘 Genel Sistem Yönetimi</div>
<div class="grid">
  <?php
  card('Talep Aç','Yönetime talep gönder','📨','request_new.php','orange');
  card('Profil','Şifre & hesap','👤','profile.php','blue');
  card('Web Sürümü','Masaüstü Sürüm','🖥','../dashboard.php?web=1','gray');
  if(user_can('users')) {
    card('Kullanıcılar','Yetki yönetimi','👥','users.php','purple');
    card('Denetim Günlüğü','Kim ne değiştirdi','🔍','audit_log.php','gray');
    card('WhatsApp Ayarları','Gateway kurulumu','📱','wa_settings.php','gray');
    card('Logo / Marka','Marka ayarları','🎨','brand_settings.php','gray');
    card('Veri Temizleme','Canlıya hazırlık','🧹','temizle_veri.php','red');
    card('Bildirim Kur','Push bildirim kurulum & teşhis','🔔','push_enable.php','yellow');
  }
  card('Çıkış Yap','Oturumu kapat','🚪','../logout.php','red');
  ?>
</div>
<?php if(user_can('users')): ?>
<div class="df-panel" style="text-align:center">
  <b>🔔 Bildirim & Ses (Test)</b>
  <p class="small" style="margin-top:4px">Sadece yöneticiler görür — geliştirme/teşhis amaçlıdır.</p>
  <button class="df-btn df-btn--primary" style="width:100%;margin-top:8px" onclick="var m=window.ACANS_TEST?ACANS_TEST():'hazır değil';document.getElementById('tres').textContent=m;">Bildirimleri Aç / Test Et</button>
  <p id="tres" class="small" style="margin-top:8px;color:#4ade80"></p>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── COMPACT — KATEGORİ MENÜSÜ (P0 MOBİL SHELL KAPANIŞI, 2026-07-18, Product Owner kararı) ──
 Eski "Launcher" (Sabitlenenler + her gruptan uzun düz liste + her satırda pin ➕/✕ + ikinci
 "Modül ara" kutusu) kaldırıldı — web Rail'de 2026-07-17'de yapılan AYNI karar (Flag 1 —
 Launcher/Pin Retirement, bkz. layout_top.php) burada da uygulandı: TEK ana arama (topx()'ün
 üstteki arama kutusu) yeterli, iki büyük kategori-drill-down modeli (nav_category_keys() + AYNI
 nav_items_for_category()/nav_category_label() — web ile TEK kaynak, ikinci bir taksonomi İCAT
 EDİLMEDİ). nav_pinned_modules()/nav_grouped_for_launcher()/ajax_nav_prefs.php SİLİNMEDİ (Legacy
 Mode dalı hâlâ kullanıyor), sadece buradan ÇAĞRILMIYOR. İletişim Merkezi ve Raporlar, messages.php/
 report.php'nin kendi iç sekme/modül anahtarları olduğu için (aynı iş iki yerde tekrarlanmasın diye)
 ayrı bir drill-down YAZILMADI — doğrudan o hub'a giden tek satırlık bağlantı. -->
<?php
$__canSee = function($perm){ return user_can($perm); };
$__catIconMapM = ['isler'=>'briefcase','ticaret'=>'tag','uretim_stok'=>'box','finans'=>'wallet','yonetim'=>'settings'];
$__openCat = $__openCatTitle;
$__icBadgeM = unread_msg() + unread_notif();
?>

<?php if($__isSettings): ?>
<!-- AYARLAR (P0 MOBİL UX, 2026-07-18, Product Owner kararı 2-3. madde) — Görünüm/Bildirim/Profil
     kişisel uygulama ayarları artık İş kategorileriyle karışmıyor, uzun listenin dibinde
     kaybolmuyor: Menü'nün en üstünde TEK satırlık kompakt bir giriş noktasından buraya geliniyor. -->
<div class="df-panel">
  <b style="display:block;margin-bottom:10px">Görünüm</b>
  <div class="df-tabs" id="themePicker">
    <button type="button" class="df-tab<?=$__mobTheme==='system'?' df-tab--active':''?>" data-theme="system" onclick="setMobileTheme(this,'system')">Sistem</button>
    <button type="button" class="df-tab<?=$__mobTheme==='light'?' df-tab--active':''?>" data-theme="light" onclick="setMobileTheme(this,'light')">Açık</button>
    <button type="button" class="df-tab<?=$__mobTheme==='dark'?' df-tab--active':''?>" data-theme="dark" onclick="setMobileTheme(this,'dark')">Koyu</button>
  </div>
  <p class="small" style="margin-top:8px" id="themeStatus">Sistem: telefonun açık/koyu ayarını takip eder.</p>
</div>
<script>
// P0 MOBİL TEMA KALICILIĞI REGRESYONU (2026-07-18, Product Owner kararı) — ÖNCEDEN fetch()'in
// sonucu hiç kontrol edilmiyordu: kayıt sessizce başarısız olsa bile kullanıcı sadece o an
// client-side boyanan ekranı görüyordu, bir sonraki sayfada her zaman eski değere dönüyordu.
// Artık başarı/hata AÇIKÇA gösteriliyor — "kaydedildi" sessizce YALANLANMIYOR. Sunucu (data-theme
// attribute'u topx()'ün HER render'ında $__mobTheme'den TAZE okunur) TEK doğruluk kaynağı;
// buradaki document.documentElement ataması sadece bu SAYFADA anlık geri bildirim için.
function setMobileTheme(btn,theme){
  var status=document.getElementById('themeStatus');
  if(theme==='system') document.documentElement.removeAttribute('data-theme');
  else document.documentElement.setAttribute('data-theme',theme);
  document.querySelectorAll('#themePicker .df-tab').forEach(function(b){ b.classList.toggle('df-tab--active', b===btn); });
  status.textContent='Kaydediliyor…'; status.style.color='';
  fetch('../ajax_nav_prefs.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':window.CSRF_TOKEN},
    body:'action=set_theme&theme='+encodeURIComponent(theme)+'&csrf_token='+encodeURIComponent(window.CSRF_TOKEN)})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d && d.ok){ status.textContent='Kaydedildi — tüm ekranlarda kalıcı.'; status.style.color='var(--df-success-ink,#4ade80)'; }
      else { status.textContent='⚠️ '+((d&&d.message)||'Kaydedilemedi.'); status.style.color='var(--df-danger-ink,#f87171)'; }
    })
    .catch(function(){ status.textContent='⚠️ Bağlantı hatası — kaydedilemedi, tekrar deneyin.'; status.style.color='var(--df-danger-ink,#f87171)'; });
}
</script>

<div class="df-panel" style="margin-top:12px;padding:6px">
  <a class="df-nav-row" href="profile.php">Profil / Şifre</a>
  <a class="df-nav-row" href="push_enable.php">Bildirim Ayarları (Push Kur/Test)</a>
  <a class="df-nav-row" href="../dashboard.php?web=1">Web Sürümü (Masaüstü)</a>
  <a class="df-nav-row" href="../logout.php">Çıkış Yap</a>
</div>

<?php if(user_can('users')): ?>
<div class="df-panel" style="text-align:center;margin-top:12px">
  <b>🔔 Bildirim & Ses (Test)</b>
  <p class="small" style="margin-top:4px">Sadece yöneticiler görür — geliştirme/teşhis amaçlıdır.</p>
  <button class="df-btn df-btn--primary" style="width:100%;margin-top:8px" onclick="var m=window.ACANS_TEST?ACANS_TEST():'hazır değil';document.getElementById('tres').textContent=m;">Bildirimleri Aç / Test Et</button>
  <p id="tres" class="small" style="margin-top:8px;color:#4ade80"></p>
</div>
<?php endif; ?>

<?php elseif($__openCat===''): ?>
<!-- SEVİYE 1: kompakt Ayarlar girişi (en üstte, aramanın hemen altında) + büyük kategori kutuları -->
<a class="df-panel" href="more.php?open=ayarlar" style="display:flex;align-items:center;gap:12px;margin-bottom:14px;text-decoration:none;color:inherit">
  <span style="flex:0 0 auto;width:44px;height:44px;border-radius:var(--df-radius-md);background:var(--df-surface-sunken);display:flex;align-items:center;justify-content:center"><?=ds_icon('settings',22)?></span>
  <div style="flex:1;min-width:0"><b>Ayarlar</b><div class="small">Görünüm · Bildirim · Profil</div></div>
  <?=ds_icon('chevron-right',18)?>
</a>

<a class="df-panel" href="messages.php" style="display:flex;align-items:center;gap:12px;margin-bottom:10px;text-decoration:none;color:inherit">
  <span style="flex:0 0 auto;width:44px;height:44px;border-radius:var(--df-radius-md);background:var(--df-surface-sunken);display:flex;align-items:center;justify-content:center"><?=ds_icon('chat',22)?></span>
  <div style="flex:1;min-width:0"><b>İletişim Merkezi</b><div class="small">Sohbetler · WhatsApp · Bildirimler · Talepler · Duyurular</div></div>
  <?php if($__icBadgeM): ?><span class="df-count-badge" style="position:static"><?=$__icBadgeM>9?'9+':$__icBadgeM?></span><?php endif; ?>
</a>
<?php if($isAdmin || user_can('report')): ?>
<a class="df-panel" href="report.php" style="display:flex;align-items:center;gap:12px;margin-bottom:10px;text-decoration:none;color:inherit">
  <span style="flex:0 0 auto;width:44px;height:44px;border-radius:var(--df-radius-md);background:var(--df-surface-sunken);display:flex;align-items:center;justify-content:center;font-size:20px">📊</span>
  <div style="flex:1;min-width:0"><b>Raporlar</b><div class="small">Genel özet · satış · alış · stok · finans</div></div>
  <?=ds_icon('chevron-right',18)?>
</a>
<?php endif; ?>

<?php foreach(nav_category_keys() as $__cat):
    $__catItems = nav_items_for_category($__canSee, $isAdmin, $__cat, 'mobile');
    if(!$__catItems) continue;
?>
<a class="df-panel" href="more.php?open=<?=h($__cat)?>" style="display:flex;align-items:center;gap:12px;margin-bottom:10px;text-decoration:none;color:inherit">
  <span style="flex:0 0 auto;width:44px;height:44px;border-radius:var(--df-radius-md);background:var(--df-surface-sunken);display:flex;align-items:center;justify-content:center"><?=ds_icon($__catIconMapM[$__cat] ?? 'menu', 22)?></span>
  <div style="flex:1;min-width:0"><b><?=h(nav_category_label($__cat))?></b><div class="small"><?=count($__catItems)?> aksiyon</div></div>
  <?=ds_icon('chevron-right',18)?>
</a>
<?php endforeach; ?>

<?php else:
    $__catItems = nav_items_for_category($__canSee, $isAdmin, $__openCat, 'mobile');
?>
<!-- SEVİYE 2: kategori içi aksiyonlar — geri dönüş TEK yerden: topbar'ın ‹ Menü butonu
     ($backUrl='more.php', bkz. dosya başı) — burada İKİNCİ bir "‹ Menü" linki YAZILMADI
     (P0 MOBİL NAVİGASYON, 2026-07-18: "Menü içinde gereksiz ikinci navigasyon/geri karmaşası
     oluşturma"). -->
<h2 style="margin:0 0 12px;display:flex;align-items:center;gap:10px"><?=ds_icon($__catIconMapM[$__openCat] ?? 'menu', 22)?> <?=h(nav_category_label($__openCat))?></h2>
<?php if(!$__catItems): ?>
<?php ds_empty_state('Bu kategoride yetkili aksiyon yok.'); ?>
<?php else: ?>
<div class="df-panel" style="padding:6px">
<?php foreach($__catItems as $__item): ?>
<a class="df-nav-row" href="<?=h(nav_url_for_platform($__item,'mobile'))?>"><?=h($__item['actionLabel'] ?? $__item['label'])?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php botx(); ?>
