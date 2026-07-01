<?php require_once 'common.php'; topx('Menü'); ?>
<div class="panel" style="text-align:center">
  <b>🔔 Bildirim & Ses</b>
  <button class="btn dark" style="width:100%;padding:13px;margin-top:8px" onclick="var m=window.ACANS_TEST?ACANS_TEST():'hazır değil';document.getElementById('tres').textContent=m;">Bildirimleri Aç / Test Et</button>
  <p id="tres" class="small" style="margin-top:8px;color:#4ade80"></p>
</div>

<?php if($isAdmin): ?>
<div style="font-weight:900;margin:16px 4px 8px">📦 Stok & Ürün</div>
<div class="grid">
  <?php card('Stok','Ürün listesi','📦','stock.php','red'); card('Yeni Ürün','Ürün ekle','➕','product_new.php','green'); ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">👥 Cari & Satış</div>
<div class="grid">
  <?php card('Cariler','Müşteri/tedarikçi','👥','contacts.php','purple'); card('Yeni Cari','Cari ekle','➕','contact_new.php','blue');
  card('Satış','Satış yap','🧾','sales.php','orange'); card('Satın Alma','Alış + stok kartı','🛒','purchase.php','green');
  card('Tahsilat','Tahsilat gir','💰','collection.php','yellow');
  card('Teklif','Teklif hazırla/gönder','📄','teklif.php','blue'); ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">💰 Finans</div>
<div class="grid">
  <?php card('Kasa Durumu','Banka/kasa/kart','🏦','kasa.php','teal');
  card('Tahsilat','Tahsilat gir','💰','collection.php','yellow');
  card('Ödeme / Gider','Gider/ödeme gir','💸','payment.php','red');
  card('Transfer','Hesaplar arası / karta ödeme','↔️','transfer.php','blue'); ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">📊 Raporlar</div>
<div class="grid">
  <?php
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

<div style="font-weight:900;margin:16px 4px 8px">👷 Personel & İş</div>
<div class="grid">
  <?php card('Personel','Ekip listesi','👷','personnel.php','purple'); card('Görev Ata','Personele görev','🎯','task_new.php','teal');
  card('İşler','İş takibi','📋','jobs.php','green'); card('Üretim','Aşama panosu','🏭','uretim.php','red'); card('Takvim','Termin takvimi','📅','calendar.php','blue'); card('Performans','Personel KPI','🏆','kpi.php','orange');
  card('Talepler','Talep onay merkezi','📨','requests.php','orange');
  card('Müşteri Onayı','Onay bekleyen dosyalar','⏳','approval_waiting.php','yellow');
  card('Dış İşler','Dış atölye/tedarik','🏭','external.php','blue'); ?>
</div>

<div style="font-weight:900;margin:16px 4px 8px">⚙ Sistem</div>
<div class="grid">
  <?php card('Son İşlemler','Aktivite akışı','🕘','activity.php','gray'); card('Web Sürümü','Masaüstü ERP','🖥','../dashboard.php?web=1','gray'); card('Profil','Şifre & hesap','👤','profile.php','blue'); ?>
</div>
<?php else: ?>
<div class="grid">
  <?php
  card('Satış','Satış yap','🧾','sales.php','orange');
  card('Tahsilat','Tahsilat gir','💰','collection.php','yellow');
  card('Cariler','Müşteri/tedarikçi','👥','contacts.php','purple');
  card('Teklif','Teklif hazırla/gönder','📄','teklif.php','blue');
  card('Stok','Ürünleri gör','📦','stock.php','red');
  card('İşlerim','Bana atanan görevler','✅','mytasks.php','green');
  card('Talep Aç','Yönetime talep gönder','📨','request_new.php','orange');
  card('Mesajlar','İç yazışma','💬','messages.php','teal');
  card('Profil','Şifre & hesap','👤','profile.php','gray');
  ?>
</div>
<?php endif; ?>
<?php botx(); ?>
