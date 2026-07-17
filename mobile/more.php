<?php require_once 'common.php'; topx('Menü');
$pdo=db();
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
<div class="panel" style="text-align:center">
  <b>🔔 Bildirim & Ses (Test)</b>
  <p class="small" style="margin-top:4px">Sadece yöneticiler görür — geliştirme/teşhis amaçlıdır.</p>
  <button class="btn dark" style="width:100%;padding:13px;margin-top:8px" onclick="var m=window.ACANS_TEST?ACANS_TEST():'hazır değil';document.getElementById('tres').textContent=m;">Bildirimleri Aç / Test Et</button>
  <p id="tres" class="small" style="margin-top:8px;color:#4ade80"></p>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── COMPACT — NAV-001B Module Launcher (Product Owner kararı) ── -->
<?php
$__canSee = function($perm){ return user_can($perm); };
$__pinnedRaw = user_pref_get($pdo, $ME, 'nav_pinned_mobile', '');
$__pinnedMods = nav_pinned_modules($__canSee, $isAdmin, $__pinnedRaw, 'mobile');
$__groups = nav_grouped_for_launcher($__canSee, $isAdmin, $__pinnedRaw, 'mobile');
$__pinnedKeys = array_filter(array_map('trim', explode(',', $__pinnedRaw)));
?>
<div class="toolbar-search" style="margin-bottom:14px">
  <span class="ts-icon"><?=ds_icon('search',18)?></span>
  <input type="text" id="navLauncherSearch" placeholder="Modül ara..." autocomplete="off" oninput="navLauncherFilter(this.value)">
</div>

<div id="navLauncherBody">
<?php if($__pinnedMods): ?>
<div class="df-nav-launcher-group">
  <div class="df-nav-launcher-group-title">Sabitlenenler</div>
  <div class="df-panel" style="padding:6px">
    <?php foreach($__pinnedMods as $__item): ?>
    <div class="df-nav-row-wrap" style="display:flex;align-items:center" data-nav-label="<?=h(mb_strtolower($__item['label']))?>">
      <a class="df-nav-row" style="flex:1" href="<?=h(nav_url_for_platform($__item,'mobile'))?>"><?=h($__item['label'])?></a>
      <button type="button" class="df-nav-pin-btn is-pinned" aria-label="Sabitlemeyi kaldır" onclick="navTogglePin('<?=h($__item['key'])?>',true,this)"><?=ds_icon('close',15)?></button>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php foreach(['is_takip','sat_tahsil','stok','iletisim','yonet'] as $__g): if(empty($__groups[$__g])) continue; ?>
<div class="df-nav-launcher-group df-nav-launcher-group--<?=h($__g)?>">
  <div class="df-nav-launcher-group-title"><?=h(nav_group_label($__g))?></div>
  <div class="df-panel" style="padding:6px">
    <?php foreach($__groups[$__g] as $__item):
        $__isPinned = in_array($__item['key'], $__pinnedKeys, true);
    ?>
    <div class="df-nav-row-wrap" style="display:flex;align-items:center" data-nav-label="<?=h(mb_strtolower($__item['label']))?>">
      <a class="df-nav-row" style="flex:1" href="<?=h(nav_url_for_platform($__item,'mobile'))?>"><?=h($__item['label'])?></a>
      <?php if(empty($__item['primary'])): ?>
      <button type="button" class="df-nav-pin-btn<?=($__isPinned?' is-pinned':'')?>" aria-label="<?=($__isPinned?'Sabitlemeyi kaldır':'Sabitle')?>" onclick="navTogglePin('<?=h($__item['key'])?>',<?=$__isPinned?'true':'false'?>,this)"><?=ds_icon($__isPinned?'close':'plus',15)?></button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="df-nav-launcher-group">
  <div class="df-nav-launcher-group-title">Diğer</div>
  <div class="df-panel" style="padding:6px">
    <div class="df-nav-row-wrap" data-nav-label="web sürümü"><a class="df-nav-row" href="../dashboard.php?web=1">Web Sürümü (Masaüstü)</a></div>
    <?php if(user_can('users')): ?>
    <div class="df-nav-row-wrap" data-nav-label="bildirim kur"><a class="df-nav-row" href="push_enable.php">Bildirim Kur (Push)</a></div>
    <?php endif; ?>
    <div class="df-nav-row-wrap" data-nav-label="çıkış yap"><a class="df-nav-row" href="../logout.php">Çıkış Yap</a></div>
  </div>
</div>
</div>

<?php if(user_can('users')): ?>
<div class="panel" style="text-align:center">
  <b>🔔 Bildirim & Ses (Test)</b>
  <p class="small" style="margin-top:4px">Sadece yöneticiler görür — geliştirme/teşhis amaçlıdır.</p>
  <button class="btn dark" style="width:100%;padding:13px;margin-top:8px" onclick="var m=window.ACANS_TEST?ACANS_TEST():'hazır değil';document.getElementById('tres').textContent=m;">Bildirimleri Aç / Test Et</button>
  <p id="tres" class="small" style="margin-top:8px;color:#4ade80"></p>
</div>
<?php endif; ?>

<script>
function navTogglePin(key,isPinned,btn){
    fetch('../ajax_nav_prefs.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':window.CSRF_TOKEN},
        body:'action='+(isPinned?'unpin':'pin')+'&platform=mobile&key='+encodeURIComponent(key)+'&csrf_token='+encodeURIComponent(window.CSRF_TOKEN)})
      .then(function(r){return r.json();}).then(function(d){ if(d.ok) location.reload(); });
}
function navLauncherFilter(q){
    q = q.toLowerCase().trim();
    document.querySelectorAll('#navLauncherBody .df-nav-row-wrap').forEach(function(row){
        row.style.display = (!q || row.getAttribute('data-nav-label').indexOf(q)!==-1) ? '' : 'none';
    });
    document.querySelectorAll('#navLauncherBody .df-nav-launcher-group').forEach(function(g){
        var anyVisible = Array.prototype.some.call(g.querySelectorAll('.df-nav-row-wrap'), function(r){ return r.style.display!=='none'; });
        g.style.display = anyVisible ? '' : 'none';
    });
}
</script>
<?php endif; ?>
<?php botx(); ?>
