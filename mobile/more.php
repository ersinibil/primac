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
/* Taksonomi — web sol menüyle (layout_top.php) hizalı: Personel İş Takip Yönetimi, Muhasebe
   İşlemleri, Mesajlar, Raporlama, Genel Sistem Yönetimi (2026-07-03: önce 6 gruptan 4'e
   sadeleştirildi; aynı gün 2. turda "Mesajlaşma ve Raporlama" tek grubu ikiye ayrıldı —
   kullanıcı bildirimi: "mesajlaşmaları tek yere al, raporlama ayrı kalsın"). Kart
   içerikleri/yetki kontrolleri aynı, sadece gruplama değişti. */
?>

<div style="font-weight:900;margin:16px 4px 8px">🧭 Personel İş Takip Yönetimi</div>
<div class="grid">
  <?php
  card('İşlerim','Bana atanan işler','✅','mytasks.php','green');
  card('Kendime İş Ekle','Kendine iş kaydı oluştur','➕','mytask_new.php','green');
  if(user_can('jobs')) {
    card('İşler','İş takibi','📋','jobs.php','green');
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
    card('Talepler','Talep onay merkezi','📨','requests.php','orange'); // admin-only kalır
  }
  if($isAdmin || user_can('personnel')) {
    card('Personel','Ekip listesi','👷','personnel.php','purple');
    card('Performans','Personel KPI','🏆','kpi.php','orange');
  }
  if($isAdmin || user_can('report')) {
    card('Günlük Rapor','Bugünkü iş özeti','📅','gunluk_rapor.php','blue');
    card('İş Takip Raporu','Açılan·tamamlanan·geciken','📋','report.php?modul=is','purple');
    card('Personel Raporu','Performans/KPI','👷','report.php?modul=personel','orange');
  }
  ?>
</div>

<?php if(user_can('contacts') || user_can('stock') || user_can('teklif') || user_can('finance') || user_can('muhasebe')): ?>
<div style="font-weight:900;margin:16px 4px 8px">💰 Muhasebe İşlemleri</div>
<div class="grid">
  <?php
  if(user_can('contacts')) {
    card('Cariler','Müşteri/tedarikçi','👥','contacts.php','purple');
    card('Yeni Cari','Cari ekle','➕','contact_new.php','blue');
    card('Cari Raporlar','Alacaklı/borçlu · toplu ekstre','📊','contacts_report.php','teal');
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
<?php endif; ?>

<div style="font-weight:900;margin:16px 4px 8px">💬 Mesajlar</div>
<div class="grid">
  <?php
  card('Mesajlar','İç yazışma','💬','messages.php','teal');
  card('Bildirimler','Tüm bildirimler','🔔','notifications.php','yellow');
  if(user_can('users')) {
    card('WhatsApp Gönder','Anlık mesaj gönder','📤','wa_send_now.php','green');
  }
  ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">📊 Raporlama</div>
<div class="grid">
  <?php
  if($isAdmin) card('Son İşlemler','Aktivite akışı','🕘','activity.php','gray'); // activity.php block_personel()
  if($isAdmin || user_can('report')) {
    card('Genel Özet Rapor','Yekün özet','📊','report.php?modul=genel','blue');
    card('Tüm Modüller','Hepsi tek sunum','🗂️','report.php?modul=tumu','blue');
  }
  ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">🕘 Genel Sistem Yönetimi</div>
<div class="grid">
  <?php
  card('Talep Aç','Yönetime talep gönder','📨','request_new.php','orange');
  card('Profil','Şifre & hesap','👤','profile.php','blue');
  card('Bildirim Kur','Push bildirim kurulum & teşhis','🔔','push_enable.php','yellow');
  card('Web Sürümü','Masaüstü Sürüm','🖥','../dashboard.php?web=1','gray');
  if(user_can('users')) {
    card('Kullanıcılar','Yetki yönetimi','👥','users.php','purple');
    card('Denetim Günlüğü','Kim ne değiştirdi','🔍','audit_log.php','gray');
    card('WhatsApp Ayarları','Gateway kurulumu','📱','wa_settings.php','gray');
    card('Logo / Marka','Marka ayarları','🎨','brand_settings.php','gray');
    card('Veri Temizleme','Canlıya hazırlık','🧹','temizle_veri.php','red');
  }
  card('Çıkış Yap','Oturumu kapat','🚪','../logout.php','red');
  ?>
</div>
<?php botx(); ?>
