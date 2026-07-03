<?php require_once 'common.php'; topx('Menü'); ?>
<div class="grid">
  <?php card('Ara','İş, cari, banka/kart, işlem, stok, personel…','🔍','search.php','blue'); ?>
</div>

<div class="panel" style="text-align:center">
  <b>🔔 Bildirim & Ses</b>
  <button class="btn dark" style="width:100%;padding:13px;margin-top:8px" onclick="var m=window.ACANS_TEST?ACANS_TEST():'hazır değil';document.getElementById('tres').textContent=m;">Bildirimleri Aç / Test Et</button>
  <p id="tres" class="small" style="margin-top:8px;color:#4ade80"></p>
</div>

<?php
/* Yeni taksonomi — web sol menüyle (layout_top.php) hizalı 6 grup: İş Takip, Ticaret,
   Finans & Muhasebe, Ekip, Rapor, Sistem (2026-07-03). Kart içerikleri/yetki kontrolleri
   aynı, sadece başlıklar ve gruplama web'le tutarlı hale getirildi. */
?>

<div style="font-weight:900;margin:16px 4px 8px">🧭 İş Takip</div>
<div class="grid">
  <?php
  card('İşlerim','Bana atanan görevler','✅','mytasks.php','green');
  card('Talep Aç','Yönetime talep gönder','📨','request_new.php','orange');
  if(user_can('jobs')) {
    card('İşler','İş takibi','📋','jobs.php','green');
    card('Üretim','Aşama panosu','🏭','uretim.php','red');
    card('Takvim','Termin takvimi','📅','calendar.php','blue');
    card('Müşteri Onayı','Onay bekleyen dosyalar','⏳','approval_waiting.php','yellow');
    card('Dış İşler','Dış atölye/tedarik','🏭','external.php','blue');
  }
  if(user_can('tasks')) {
    card('Tüm Görevler','Görev yönetimi (herkes)','✅','../tasks.php?web=1','teal'); // web'de var, mobilde köprü yoktu
  }
  if($isAdmin) {
    card('Görev Ata','Personele görev','🎯','task_new.php','teal'); // hâlâ admin-only
    card('Talepler','Talep onay merkezi','📨','requests.php','orange'); // hâlâ admin-only
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
    card('Stok Giriş/Çıkış','Manuel stok hareketi','↕️','../stock_movement_new.php?web=1','yellow'); // web'de var, mobilde köprü yoktu
    card('Ürün Kategorileri','Kategori yönetimi','🏷️','../product_categories.php?web=1','gray'); // web'de var, mobilde köprü yoktu
    card('Marka / Birim','Taksonomi yönetimi','🔖','../product_taxonomy.php?web=1','gray'); // web'de var, mobilde köprü yoktu
  }
  if(user_can('teklif')) {
    card('Teklif','Teklif hazırla/gönder','📄','teklif.php','blue');
  }
  ?>
</div>
<?php endif; ?>

<?php if(user_can('finance') || user_can('muhasebe')): ?>
<div style="font-weight:900;margin:16px 4px 8px">💰 Finans & Muhasebe</div>
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
    if($isAdmin) card('Muhasebe Kategorileri','Kategori yönetimi','⚙️','../accounting_categories.php?web=1','gray'); // web'de var, mobilde köprü yoktu
  }
  ?>
</div>
<?php endif; ?>

<?php if($isAdmin || user_can('users')): ?>
<div style="font-weight:900;margin:16px 4px 8px">👥 Ekip</div>
<div class="grid">
  <?php
  // NOT (2026-07-03): personnel.php/kpi.php hâlâ block_personel() (admin-only) kilitli — bu
  // sayfaların modül-yetkisine (personnel) taşınması ayrı bir karar gerektiriyor (özellikle
  // personnel.php maaş/IBAN gibi hassas alanlar içeriyor, daha önce kullanıcı bunun admin-only
  // kalmasını istemişti). Kartlar bu yüzden hâlâ $isAdmin'e bağlı.
  if($isAdmin) {
    card('Personel','Ekip listesi','👷','personnel.php','purple');
    card('Performans','Personel KPI','🏆','kpi.php','orange');
  }
  if(user_can('users')) {
    card('Kullanıcılar','Yetki yönetimi','👥','users.php','purple');
  }
  ?>
</div>
<?php endif; ?>

<?php if($isAdmin): // report.php hâlâ block_personel() — yönetici raporu ?>
<details class="panel" style="margin:16px 0">
  <summary style="font-weight:900;cursor:pointer">📊 Rapor</summary>
  <div class="grid" style="margin-top:10px">
  <?php
  card('Günlük Rapor','Bugünkü iş özeti','📅','../gunluk_rapor.php?web=1','blue');
  card('Tümü (Yekün)','Hepsi tek sunum','🗂️','report.php?modul=tumu','blue');
  card('Finans/Tahsilat','Tahsilat·ödeme·net','💰','report.php?modul=tahsilat','green');
  card('İş Takip','Açılan·tamamlanan·geciken','📋','report.php?modul=is','purple');
  card('Personel','Performans/KPI','👷','report.php?modul=personel','orange');
  card('Satış','Satış dökümü','🧾','report.php?modul=satis','yellow');
  card('Teklif','Teklif dökümü','📄','report.php?modul=teklif','blue');
  card('Cari','Bakiye·hareket','👥','report.php?modul=cari','teal');
  card('Stok','Kritik·stok değeri','📦','report.php?modul=stok','red');
  ?>
  </div>
</details>
<?php endif; ?>

<div style="font-weight:900;margin:16px 4px 8px">🕘 Sistem</div>
<div class="grid">
  <?php
  if($isAdmin) card('Son İşlemler','Aktivite akışı','🕘','activity.php','gray'); // activity.php block_personel()
  card('Bildirimler','Tüm bildirimler','🔔','notifications.php','yellow');
  card('Mesajlar','İç yazışma','💬','messages.php','teal');
  card('Web Sürümü','Masaüstü Sürüm','🖥','../dashboard.php?web=1','gray');
  card('Profil','Şifre & hesap','👤','profile.php','blue');
  if(user_can('users')) {
    card('Denetim Günlüğü','Kim ne değiştirdi','🔍','../audit_log.php?web=1','gray'); // web'de var, mobilde köprü yoktu
    card('WhatsApp Gönder','Anlık mesaj gönder','📤','wa_send_now.php','green');
    card('WhatsApp Ayarları','Gateway kurulumu','📱','wa_settings.php','gray');
    card('Logo / Marka','Marka ayarları','🎨','brand_settings.php','gray');
    card('Veri Temizleme','Canlıya hazırlık','🧹','../temizle_veri.php?web=1','red'); // web'de var, mobilde köprü yoktu
  }
  card('Çıkış Yap','Oturumu kapat','🚪','../logout.php','red');
  ?>
</div>
<?php botx(); ?>
