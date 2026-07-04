# CHANGELOG.md — Özet Değişiklik Geçmişi

Bu dosya `memory/features.md`'nin (tam gerekçe/kod detayıyla) kök dizindeki kısa özetidir — hızlı
taramak için. Detaylı "neden böyle yapıldı" analizleri için `memory/features.md`'ye bakın.

## FINANCE UX REFACTOR (2026-07-04, DEV — checkpoint commit ile kaydedildi, push/release yok)
Ödeme/Gider ve Muhasebe ekranlarında cari/kategori/personel/kasa/ödeme yöntemi karışıklığını
çözmek için "Ne kaydediyorsun?" sihirbazı eklendi (Cari Ödemesi / İşletme Gideri / Personel
Ödemesi / Vergi-SGK / Banka-Kredi-Kart / Araç Gideri / Diğer). DB şemasına hiçbir yeni kolon
eklenmedi — tür bilgisi hiçbir yerde saklanmıyor, mevcut kayıtlardan `finance_lib.php`'ye eklenen
`finance_record_type_info()` ile türetiliyor (bildirim sprintindeki `notif_type_info()` ile aynı
desen). Kapsam: `finance_new.php` + `accounting.php` (web), `mobile/payment.php` +
`mobile/movement_view.php` + `mobile/accounting.php` (mobil) — hepsi sadece gider/ödeme (`direction
='out'`/`type='gider'`) tarafında aktif, Tahsilat/Gelir akışı hiç değişmedi. `personnel_id` alanı
(migration 035'te zaten vardı, sadece Muhasebe ekranında kullanılıyordu) ilk kez Ödeme/Gider
ekranlarına da eklendi. Düzenleme ekranlarında da sihirbaz çalışıyor — eski kayıtlar dolu alanlarına
bakılarak doğru adımla açılıyor. Detay → `memory/features.md`.

## SYSTEM AUDIT MODE (2026-07-04, read-only denetim — kod/DB DEĞİŞMEDİ, commit yok)
5 uzman ajanla (güvenlik, veri modeli, mimari/kod kalitesi, performans, UX/UI) OTS'nin ürün olarak
kapsamlı denetimi yapıldı. 2 kritik/yüksek güvenlik açığı bulundu (`mobile/personnel_view.php`
keyfi şifre sıfırlama, `mobile/task_view.php` IDOR) — `KNOWN_BUGS.md`'ye işlendi. Mimari/performans/
UX teknik borçları (eksik index'ler, FK'siz silme akışları, design token benimsenmesinin çok düşük
olması, yeni UX standardının sadece bildirimlerde uygulanması) `ROADMAP.md`'ye işlendi. Bu denetim
artık her büyük sprint/RC/major sürüm/production öncesi otomatik tekrarlanacak kalıcı bir standart
(`PROJECT_RULES.md` "Sürekli Kalite Denetimi Standardı"). Tam rapor: Artifact + Masaüstü metin
dosyası olarak paylaşıldı.

## UX İyileştirme — "Çalışma Alanı" (2026-07-04, DEV — primac.tr'de test edildi, lokal checkpoint commit edildi; push/release yok)
`layout_top.php`'deki "Aktif Şirket" kutusu incelendi ve tamamen işlevsiz (sahte) bir dropdown
olduğu bulundu — hiçbir session/DB alanına bağlı değildi, seçenekleri sabit metindi. Projede
gerçek bir çoklu-şirket/tenant filtreleme altyapısı hiç var olmadığı için (ACANS/PRIMAC zaten ayrı
sunucu+DB), bu turda SADECE arayüz sadeleştirildi: kutu "Çalışma Alanı" bilgi etiketine çevrildi,
sahte dropdown kaldırıldı, gerçek uygulama adı (`app_config()['app_name']`) statik metin olarak
gösteriliyor. DB/session/route/iş mantığı DEĞİŞMEDİ. Gerçek çoklu-çalışma-alanı mimarisi ayrı,
büyük bir proje olarak `ROADMAP.md`'ye "Workspace (Multi-Tenant) Architecture" başlığıyla açıldı.

## UX SPRINT-001 (2026-07-04, DEV — primac.tr'de 8/8 test PASS, lokal checkpoint commit edildi; push/release yok)
Mobil Bildirimler modülü kart/detay standardına taşındı, mimariye/DB şemasına/API'ye dokunulmadı
(sadece görüntü katmanı). Kapsam bilinçli olarak mobil ile sınırlandı, web `notifications.php`
bu turda değişmedi (ayrı bir sprintte hizalanacak).
- **Bildirim tipi artık DB'siz türetiliyor**: `notifications_lib.php`'ye eklenen `notif_type_info()`
  mevcut başlık emoji ön ekini (📋 Görev, 📨 Talep, 🏭/📦 Üretim, 📊 Rapor, ⚠️ Uyarı, ✅ Onay, 🖼
  Dosya Onayı, 📝 Not, 🌅 Hatırlatma, + ileride için 💬 Mesaj/👤 Personel) ikon+etiket+renge çeviriyor
  — yeni bir `type` kolonu eklenmedi, migration yok.
- **Liste kartı sadeleşti**: `mobile/notifications.php`'de her satır artık renkli ikon rozeti +
  temiz başlık + en fazla 3 satır özet (uzunsa "Devamını gör →" ipucu) + tarih gösteren, TAMAMI
  tıklanabilir tek bir kart. Ham URL hiçbir zaman kartta gösterilmiyor, ayrı "Aç" butonu kaldırıldı.
- **Yeni Detay ekranı**: `mobile/notification_view.php` — tam metin, tarih/saat, tip rozeti,
  "İlgili Modüle Git" ve "Sil" butonları. Tekil `?id=` sorgusu `notif_get_for_user()` ile
  Sprint-001'deki sahiplik kuralının AYNISINI uyguluyor (başkasının kişisel bildirimi
  görüntülenemez) — yeni bir IDOR açmadan tekil-görüntüleme eklendi.
- **Yeni standart UX kuralı**: "Liste ekranı sade kalır, tekil aksiyonlar (sil/git/işaretle) sadece
  Detay ekranında olur" — `PROJECT_RULES.md`'ye eklendi, bundan sonraki TÜM mobil liste ekranları
  için geçerli.

## UI/UX Sprinti (2026-07-04, DEV — lokal checkpoint commit edildi, push/release yok)
Mobil PWA'nın tasarım dili standartlaştırıldı, mimariye/SQL'e/route'lara dokunulmadı (sadece
HTML/CSS/kompozisyon). Kapsam bilinçli olarak `mobile/index.php` + `mobile/common.php` +
`mobile/more.php` ile sınırlandı (detay → `ROADMAP.md` "UI/UX Sprinti — kapsam dışı bırakılanlar").
- **Design token sistemi**: `mobile/common.php`'ye `:root` CSS değişkenleri eklendi
  (`--radius-sm/md/lg`, `--c-accent/danger/success/warn/muted`) — önceden 8/10/13/14/16/17/22/24px
  arası dağınık radius değerleri ve tekrarlanan hex renkler tek merkezi kaynağa bağlandı.
- **Global arama artık toolbar'ın parçası**: `topx()` fonksiyonu her mobil sayfada ~50px
  yükseklikte, tam genişlik, sol ikonlu bir arama çubuğu gösteriyor (mevcut `search.php`'ye normal
  GET ile gidiyor, route/API SIFIR değişiklik). Chat-mode'da (mesajlaşma ekranı) gizleniyor.
  Canlı-autocomplete İNŞA EDİLMEDİ (kasıtlı, bkz. ROADMAP.md) — sadece DOM iskeleti hazır.
- **Ana ekran kart tutarlılığı**: Personel görünümündeki 2 elle yazılmış kart, admin görünümüyle
  tutarlı olacak şekilde ortak `card()` fonksiyonuna çevrildi. Kart yoğunluğu artırıldı
  (min-height 112→104px, padding 15→14px, grid gap 12→10px) — ilk ekranda daha az boşluk.
- **Bildirim test alanı admin'e taşındı**: `mobile/more.php`'nin en üstündeki koşulsuz-görünür
  "Bildirim & Ses" test paneli ve "Bildirim Kur" kartı artık SADECE `user_can('users')` (admin/
  yönetici) görüyor — normal personel arayüzünden kaldırıldı. Ayrıca artık gereksiz olan (toolbar'da
  global arama olduğu için) "Ara" kartı `more.php`'den kaldırıldı.

## Sprint-001 (2026-07-04, DEV — primac.tr'de test edildi, `0ba36da` ile lokal checkpoint commit edildi; push/release yok)
8 hedef modül (İşler, İşlerim, İş Ekle, Kendime İş Ekle, Notlarım, Kendime Not Ekle, Mesajlar,
Bildirimler) tarandı; İşler temiz bulundu, diğerlerinde:
- **Mesajlar — DEV testinde bulundu, kalıcı okunmamış rozet hatası**: `notes_lib.php`'nin
  "Kendime Not Ekle" sırasında kendine attığı iç mesaj `is_read=0` ile oluşturuluyordu, ama mesaj
  kişi listesi kullanıcının kendisini hariç tuttuğu için (kendinle sohbet diye bir giriş yok) bu
  mesaj HİÇBİR ZAMAN okundu işaretlenemiyordu — 💬 rozeti kalıcı olarak şişiyordu. `is_read=1` ile
  oluşturulacak şekilde düzeltildi (kendi yazdığın bir notun kopyası için "okunmadı" uyarısı
  anlamsız). Zaten var olan takılı kalmış satırlar için tek kullanımlık `debug_unread.php` yardımcı
  script'i hazırlandı.
- **Emoji butonu hâlâ taşıyordu**: önceki turda panel konumu düzeltilmişti ama butonun kendisi
  composer'daki genel `.composer button{width:50px}` kuralı yüzünden "😀 Emoji" metniyle taşmaya
  devam ediyordu. Metin kaldırıldı, diğer ikon-only composer butonlarıyla (📎, 🎤) tutarlı hale
  getirildi (`share_lib.php`).
- **Bildirimler — güvenlik açığı kapatıldı**: `mobile/notifications.php`'deki "Okunanları Sil"/
  "Tümünü Sil"/tekil silme sahiplik kontrolü YOKTU, SİSTEMDEKİ TÜM KULLANICILARIN bildirimlerini
  silebiliyordu. Kök çözüm: yeni `user_notification_status` tablosu (migration 039) + yeni
  `notifications_lib.php` — genel (target_user_id=NULL) bildirimler artık HİÇBİR ZAMAN fiziksel
  silinmiyor, her kullanıcının okunma/gizleme durumu kendi satırında tutuluyor, kişisel bildirimi
  sadece sahibi silebiliyor. `mobile/common.php`, `layout_top.php`, `mobile/poll.php`,
  `dashboard.php` artık tek ortak sayaç/liste fonksiyonunu kullanıyor (3 yerde kopyalanmış sorgu
  kaldırıldı). Web'e mobildeki silme/temizleme butonları artık güvenli şekilde eklendi (parite).
  Ayrıca web'deki DB'de hiç var olmayan `type`/`severity` kolon referansı (dead code) kaldırıldı.
  `temizle_veri.php` + mobil karşılığı yeni tabloyu da temizleme listesine aldı.
- **İşlerim**: `mobile/mytasks.php`'deki ham int-cast sorgu prepared statement'a çevrildi (stil
  tutarlılığı, CLAUDE.md kural 2).
- **İş Ekle**: `mobile/task_new.php`'de yanlış etiketlenmiş `activity_log` kaynağı (`jobs.php` →
  `tasks.php`) düzeltildi.
- **Kendime İş Ekle**: `mytasks.php`'de daha önce hiç okunmayan `?ok=1` parametresi artık
  "İş eklendi" mesajı gösteriyor (notes.php'deki mevcut desenle aynı). Ayrıca DEV testinde bulundu:
  personel kaydı olmayan hesaplar (örn. saf admin) için hata mesajı `mytask_new.php` +
  `mobile/mytask_new.php`'de yönlendirici hale getirildi ("Genel Sistem Yönetimi > Kullanıcılar
  bölümünden personel eşleştirmesi yapabilirsiniz") — veri yapılandırması (personel eşleştirme)
  hâlâ kullanıcı tarafından `users.php` üzerinden yapılması gerekiyor, kod bunu otomatik çözmüyor.

## 2026-07-03 (en yoğun gün — çoklu tur)
- **Tek geliştirme ortamı modeli resmileşti**: DEV=primac.tr / PROD(LIVE)=acanstr.com/ots ayrımı
  kondu (`CLAUDE.md`, `PROJECT_RULES.md`, `memory/deploy.md` güncellendi). PROD'a artık SADECE
  "DEPLOY MODE" komutuyla dokunuluyor. `VERSIONING.md` (resmi sürüm dokümanı) oluşturuldu, Release
  (DEV'e paket hazırlama) ve Deploy (PROD'a DEPLOY MODE ile gönderme) süreçleri artık ayrı adımlar.
- **İşlerim/Görevlerim terim standardizasyonu + "Kendime İş Ekle"**: "Görevlerim" ifadesi projede
  her yerde "İşlerim" olarak birleştirildi (mytasks.php, mobile/mytasks.php, mobile/index.php'deki
  yanlış eşleşen kart etiketi düzeltildi). Admin'in başkasına iş ataması artık "İş Ekle" olarak
  adlandırılıyor (`task_new.php`). Yeni: kullanıcının kendine iş ekleyebildiği ayrı bir form
  (`mytask_new.php` + `mobile/mytask_new.php`, `tasks` yetkisi istemiyor). Emoji seçici paneli
  artık mesaj kutusunun ÜZERİNE binmeden (yukarı) açılıyor.
- **Web'de bildirime tıklayınca mobile'a zıplama + hayalet mesaj rozeti + mobil görev yetki açığı**
  (commit `bb8a710`): web'e `mytasks.php` eklendi, bildirim rozeti/kişi listesi sorguları tutarlı
  hale getirildi, mobil görev güncellemesine `personnel_id` kilidi eklendi. Migration 038.
  Ayrıca aynı gün önceki turda: takvime `tasks` (görevler) entegrasyonu, kişisel Not/Görev alanı
  ("Notlarım", migration 037), 5-ajan güvenlik denetimi (job_view.php yazma yetkisi, requests.php/
  activity.php/contact_documents.php IDOR kapatmaları), satış/satın alma sepet + KDV, personel
  yetki senkronu, mobil PWA offline + barkod/QR okutma, çek/senet modülü genişletmeleri, muhasebe
  KDV, kullanıcı/yetki ekranı iyileştirmeleri, stock_movements şema sapması düzeltmesi (commit
  `3137e68`), personel CV/özgeçmiş yükleme (commit `f606cf9`).
- Bu günün tam listesi (30+ madde) → `memory/features.md` üst kısmı.

## 2026-07-02
- Çek/Senet takip modülü (dosya eki + otomatik hatırlatma görevi), Global arama güvenlik düzeltmesi
  + kapsam genişletme, "Düzenleme/Silme Yetkisi" kademeli izin, Finans hesapları/hareketleri
  düzenle-sil, Marka adı yaygınlaştırma + yetki canlı yenileme, Gider kaydında kategori desteği,
  Bildirim `action_url` + mesaj görünürlük onarımı (+ open-redirect kapatma), Muhasebe modülü.

## 2026-07-01
- Sabah raporu + geciken iş sayısı bildirimi.

## 2026-06-30
- Günlük yönetici PDF raporu + talep akışı, 4 ajan turu (cari alanları, modül aktif/pasif, şifre
  sıfırlama, mesaj düzenle), Paylaşım + Teklif + Mesajlaşma onarımı, Teklif PDF + ACANS logo,
  Geciken İş + Teklif Raporu + Takvim + Web Responsive.

## Daha eskisi
Proje kuruluşundan 2026-06-30'a kadar olan temel modüller (auth, CRM, işler/görevler, stok/ürün,
finans, ticari belgeler, mesajlaşma, muhasebe...) migration `001`–`020` ile atıldı — bkz.
`DATABASE.md` tablo envanteri ve `memory/features.md`'nin alt kısımları.

## Referanslar
Tam detay → `memory/features.md`. Şema değişiklikleri → `DATABASE.md`. Açık işler → `ROADMAP.md`.
