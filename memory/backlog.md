# Backlog & Yapılacaklar

<!-- Açık geliştirme görevleri. Kapanan madde buradan silinip memory/features.md'ye taşınır. -->

## "Yaklaşan İşler / Yaklaşan Vadeler" widget'ı — resmi backlog maddesi (2026-07-13)
- Dashboard Tarih Mantığı KARAR'ının (bkz. [[features]] "Dashboard Tarih Mantığı Düzeltmesi")
  4 numaralı iş kuralı: "Bugün Yapılacaklar", "Gecikenler", "Açık İşler" yanında dördüncü kavram
  olan "Yaklaşan İşler" bu turda YENİ bir widget olarak EKLENMEDİ (kullanıcının kendi kararı,
  kapsam dışı bırakıldı) — ama mimari buna açık bırakıldı. İleride eklenirse mantık:
  `DATE(due_date) BETWEEN CURDATE()+1 AND CURDATE()+7` (jobs/tasks için), checks_notes.php'nin
  zaten var olan "⏳ Yaklaşıyor" mantığıyla (`due_date>=$today AND due_date<=$soon`) tutarlı
  olmalı. Nerede gösterileceği (dashboard.php'de yeni bir bölüm mü, sadece daily_reminder_lib.php
  bildirimine mi ek, yoksa ikisi de mi) henüz kararlaştırılmadı — ayrı bir küçük tur olarak ele
  alınmalı.

## WEB UI ALIGNMENT & NAVIGATION SPRINT 001 DEV PASS takibi açık (2026-07-13)
- Kod tamamlandı (Faz A+B+C + Faz A'nın devamı olan bölüm sürükle-bırak), commit `9bdff1e` →
  `3b431ec` (docs) → `59e51dc` (section-drag). Push edildi, guncelleme.zip/MD5 hazır
  (`8d6250c17fbffd88296f7c8ed4923e4a`). php -l tüm dosyalarda temiz, route/yetki seti değişmedi
  (script ile doğrulandı), Ece/Selin/Elif incelemesinden geçti (kritik/orta bulgu yok; düşük
  öncelikli contacts_report.php yetki uyumsuzluğu VE section-drag'daki bir "ölü bölge" bugu aynı
  turlarda düzeltildi, bkz. [[bugs]]/[[features]]).
- **KALICI KALİTE KURALI (kullanıcı talimatı, 2026-07-13):** Drag&Drop/Modal/Menü/Hover/
  Responsive/JS/AJAX/Bildirim/Arama/Filtreleme gibi kullanıcı-etkileşimli özellikler için "Kod
  tamamlandı" ile "Kullanıcı davranışı doğrulandı" ARTIK AYRI raporlanıyor; ikincisi doğrulanmadan
  hiçbiri TAMAMLANDI/CLOSED yazılmıyor. Bu kural PRIMAC OTS'un tüm gelecek sprintleri için geçerli.
- **Durum (bu kurala göre):** Tile-drag (Ana Modül Kartları'nın iç sırası) → **PASS**, kullanıcı
  DEV'de ⠿ tutamacı görüp fiilen sürükledi, doğruladı. Section-drag (10 ana bölümün sayfa
  sırası, `dashboard_section_order`) → **KOD TAMAMLANDI, KULLANICI DAVRANIŞI HENÜZ
  DOĞRULANMADI**. Kullanıcının doğrulaması gereken somut adımlar: "Son İşlemler"/"Notlarım"/
  "Canlı Bildirimler" bölümlerini ⋮⋮ tutamacından sürükleyip taşımak, sayfayı yenilemek (sıra
  korunmalı), çıkış-giriş yapmak (sıra hâlâ korunmalı), "↺ Sayfa Düzeni" sıfırlama butonunu
  denemek.
- **Görsel/responsive test yapılamadı** — bu oturumda tarayıcı otomasyon aracı yoktu, sprintin
  kendi test matrisinin A/B/C maddeleri (görsel tutarlılık, kullanılabilirlik, responsive
  Safari/Chrome 1440/1280/1024/tablet) sadece kod seviyesinde değil, gerçek görsel bir
  değerlendirme gerektiriyor. Kullanıcının DEV/primac.tr'de bizzat doğrulaması gereken kapanış
  kriterleri: (1) dashboard'da hem kart hem sayfa düzeni sürükle-bırakı çalışıyor mu, (2) sol menü
  grupları (Ticaret/Finans/İş-Üretim/Raporlama) anlaşılır mı, (3) web arayüzü mobille aynı ürün
  ailesi gibi hissettiriyor mu, daha sade/hızlı mı. PASS gelmeden CLOSED yazılmayacak.
- **Bilinçli kapsam dışı bırakılan (mobil parite notu, Ece'nin bulgusu):** `sales.php`/
  `purchase.php` başlıklarına Faz C'de eklenen küçük çapraz-navigasyon linkleri (Satış↔Alış,
  Satış/Alış Belgesi oluşturma) `mobile/sales.php`/`mobile/purchase.php`'ye taşınmadı — basit
  `<a>` linkleri oldukları için teknik engel yok, istenirse ayrı küçük bir iş olarak eklenebilir.
- **Bilinçli kapsam dışı bırakılan (kullanıcının kendi kararı):** Faz A'nın sürükle-bırak kart
  sıralaması SADECE web'de — mobilin sabit kart sırası bu sprintte değişmedi.

## Finance CRUD UX Patch 001 DEV PASS takibi açık (2026-07-12)
- Kod tamamlandı, yerel MariaDB'de 18 birim test + Bahçera Restaurant benzeri gerçek senaryo
  (manuel tahsilat → düzenlenebilir/silinebilir → silme → bakiye doğru geri alınıyor; bekleyen
  satış → düzenlenemez → reddediliyor) PASS, Ece/Selin incelemesinden geçti, commit `1cb9e31`,
  push edildi, guncelleme.zip/MD5 hazır — bkz. [[bugs]] "Çözüldü" ve [[features]] "Finance CRUD UX
  Patch 001". Kullanıcının DEV/primac.tr'de gerçek bir manuel tahsilat/ödeme kaydını cari ekranı +
  hesap ekranı + ana finans ekranından yönetebildiğini, bekleyen bir satış/alış/belge kaydında
  Düzenle/Sil YERİNE doğru kaynak linkinin göründüğünü test edip PASS vermesi bekleniyor; PASS
  gelmeden CLOSED yazılmayacak.

## Flow Unification 001 DEV PASS takibi açık (2026-07-11)
- Kod tamamlandı, yerel MariaDB'de 6 zorunlu senaryo + document_id edit/delete kilidi PASS, Ece/
  Selin/Elif incelemesinden geçti, commit `d518103`, push edildi, guncelleme.zip/MD5 hazır — bkz.
  [[bugs]] "Çözüldü" ve [[features]] "Flow Unification 001". Kullanıcının DEV/primac.tr'de gerçek
  kullanıcı testi (8 test senaryosu: bkz. features.md) yapıp PASS vermesi bekleniyor; PASS
  gelmeden CLOSED yazılmayacak.
- Küçük artık (düşük öncelik, bu sprintin kapsamı dışında bırakıldı): `mobile/sales.php` hâlâ
  kendi inline satış-oluşturma mantığını taşıyor, yeni `stock_create_sale()` ortak fonksiyonuna
  bağlanmadı (Ece/Elif incelemesinde bulundu). Sonuç şu an tutarlı (aynı kurallar, aynı
  `finance_movements` şekli) ama kod paylaşımı yok — ileride `stock_create_sale()`'e bağlanırsa
  hem bakım kolaylaşır hem çekirdekte yapılacak gelecekteki düzeltmeler otomatik mobile yansır.

## Kontrollü Negatif Stok Politikası — küçük polish notu (2026-07-11)
- Özelliğin kendisi CLOSED (WEB) — bkz. features.md. Tek açık kalan, düşük öncelikli not: şu an
  yetersiz-stok-onaylı satışların "görünürlüğü" sadece `finance_movements.description`'a eklenen
  " ⚠️ Stok Yetersiz (Onaylandı)" metni ile sağlanıyor (migrationsız, düşük riskli tercih).
  İstenirse ileride Son Satışlar listesinde gerçek bir rozet/filtre (örn. "Tedarik Bekliyor")
  eklenebilir — bu turun kapsamı dışında bırakıldı (kullanıcı: "büyük UI refactor yapma").

## Migration 042 (settles_movement_id) ve 043 (satır bazlı fiyat/KDV) DEV PASS takibi açık
- İkisi de primac.tr'ye deploy edilen kod tabanında hazır (sale-edit özelliği migration 043'e
  bağımlı) ama kullanıcının DEV'de `migrate.php`'yi çalıştırıp teknik doğrulama yapması Finance
  Core Stabilization commit'inden (`d02665b`, migration İÇERMİYOR) BAĞIMSIZ ayrı ayrı takip
  ediliyor. Her ikisi de yerel MariaDB sandbox'ta idempotency + fonksiyonel senaryolarla test
  edildi (bkz. features.md), ama DEV üzerinde kullanıcı PASS'ı gelmeden CLOSED yazılmayacak.

## Mobile Regression Sprint — Finance Core + Kontrollü Negatif Stok mobil doğrulaması (2026-07-11)
- Finance Core Stabilization (satış/alış artık ödeme yapmıyor, cari bakiye formülü düzeltmesi —
  bkz. features.md, bugs.md "Çözüldü") ve Kontrollü Negatif Stok Politikası (stok yetersizken
  onay akışı) WEB tarafında kullanıcı PASS aldı, ikisi de CLOSED (WEB) işaretlendi. Kod
  incelemesinde (Elif/ots-parity-auditor, Ece/ots-code-reviewer) web/mobil parite PASS aldığı
  için bu kararlar bloklanmadı, ama kullanıcı henüz mobilde fiilen test etmedi. Ayrı bir sprint
  olarak: `mobile/sales.php` + `mobile/purchase.php`'de aynı 13 zorunlu Finance Core senaryosu
  (satış→tahsilat, alış→ödeme, eski satışı veresiyeye çevirme, alış miktar düzenleme, avg_cost
  güvenlik kapısı) VE Kontrollü Negatif Stok senaryosu (stoğu 5 olan üründen 8 adet satış → ilk
  denemede red, onaylı denemede kabul + stok -3, silince stok 5'e dönüş) mobil arayüzde elle
  doğrulanmalı.

## tasks.deleted_at soft-delete filtresi bazı sayaç/rapor sorgularına eklenmedi (2026-07-04)
- "İşlerim" Düzenle/Detay/Sil turu (bkz. features.md) `tasks` tablosuna soft-delete (`deleted_at`)
  ekledi ve `mytasks.php`/`mobile/mytasks.php`/`tasks.php`/`mobile/tasks.php`/`task_view.php`
  (web+mobil) sorgularının hepsine `deleted_at IS NULL` filtresi eklendi. Ama şu dosyalardaki
  `tasks` sayaç/rapor sorguları bilinçli olarak DOKUNULMADI (kapsam disiplini + paralel ajan
  çakışma riski): `jobs.php`, `personnel_view.php` (web+mobil), `dashboard.php`, `kpi.php`,
  `mobile/kpi.php`, `report_lib.php`, `gunluk_rapor.php`, `mobile/gunluk_rapor.php`,
  `daily_reminder_lib.php`, `takvim.php`, `mobile/calendar.php`, `personnel.php`,
  `mobile/personnel.php`, `mobile/profile.php`, `personnel_edit.php`. Soft-silinen bir görev bu
  ekranlardaki "açık görev" sayaçlarına/raporlara hâlâ dahil olabilir. Kalıcı çözüm: bu dosyaların
  tamamına tek tek `AND deleted_at IS NULL` eklenmesi — küçük ama çok dosyayı aynı anda değiştiren
  bir iş, ayrı bir tur olarak ele alınmalı (bu turun kapsamı sadece "İşlerim" akışıydı).
- Ayrıca `sil.php`'nin `'job'` dalı bir işi silerken bağlı `tasks` satırlarını hâlâ FİZİKSEL siliyor
  (children temizliği, job silme akışının kendi kapsamı) — task soft-delete kuralı bu senaryoyu
  kapsamıyor. Job silme akışına dokunmak ayrı bir karar gerektirir.

## VAPID push anahtarı sunucu config.php'lerine elle eklenmeli (2026-07-03)
- `push_lib.php` artık `app_config()` (config.php) üzerinden `vapid_public`/`vapid_private`/
  `vapid_subject` okuyor, tanımlı değilse koddaki ESKİ sabit değerlere düşüyor (geri uyumlu, acil
  değil, hiçbir şey bozulmaz). Ama kalıcı çözüm için ACANS ve PRIMAC sunucularındaki gerçek
  `config.php` dosyalarına (cPanel File Manager, repo dışı, buradan erişim yok) şu 3 satır elle
  eklenmeli:
  ```php
  'vapid_public'=>'BKEqJl3sOt2lxHVBXjtCu_nFTCgH42b7NVTjE4BsGq5xC81cdwF1llwIiAmXMbDieoC74QLHZOhZ1dSkgQjLP3c',
  'vapid_private'=>'lEr2og5nZs8UfiLd3EJeWAsT0NeSoj9aseWYJtxlusw',
  'vapid_subject'=>'mailto:admin@acanstr.com',
  ```
  Bunlar mevcut anahtarlar (taşıma, rotasyon değil) — repo artık GitHub'a bağlı olduğu için kodda
  sabit durması sızıntı riski (bkz. [[deploy]]). Kullanıcı seyahatte, dönünce elle eklenecek.

## Web'de "Görevlerim" sayfası yok — mobilde var (mytasks.php)
- 2026-07-03 (takvim/tasks incelemesi sırasında bulundu): mobilde personel kendi görevlerini
  `mobile/mytasks.php`'de görüyor (yetki gerektirmez, sadece kendi `personnel_id`'sine bakar).
  Web'de karşılığı YOK — web'de görevler sadece `tasks.php` üzerinden görünüyor ve bu sayfa
  `page_module_map()`'te `'tasks'` modülüne bağlı, yani `tasks` yetkisi verilmemiş bir personel
  kendine atanan görevi web'den göremiyor (dashboard'daki "Açık Görev" kartı ve takvimdeki 🎯
  madde bu kullanıcılar için tıklanamaz/düz metin bırakıldı, bkz. features.md). Kalıcı çözüm:
  web'e de `mytasks.php` benzeri, yetki gerektirmeyen bir "Görevlerim" sayfası eklenmeli (CLAUDE.md
  kural 7 — parite). Kapsam/tasarım henüz netleşmedi.

## Mobil parite eksiği — work_center.php, trade_documents.php, design.php sadece web'de
- 2026-07-03 (commit `1ff6f1e`): bu üç sayfa web sol menüsüne ve `boot.php` yetki eşlemesine
  eklendi (bkz. features.md). Ama mobilde hiç karşılığı yok (`mobile/work_center.php`,
  `mobile/trade_documents.php`, `mobile/design.php` gibi dosyalar yok). CLAUDE.md kural 7 ("Yeni
  özellik hem web hem mobilde olmalı") açısından açık kalan tek madde bu — henüz kapsam belirlenmedi
  (mobilde ayrı sayfa mı, yoksa mevcut jobs.php/contacts.php içine filtre olarak mı gömülecek).

## ÇÖZÜLDÜ (yanlış çıktı) — Web tarafı rol kısıtı
- RAPOR.md'nin (2026-06-28) "web'de rol kısıtı yok" iddiası 2026-07-02 denetiminde YANLIŞ bulundu:
  `boot.php`'de `page_module_map()`, `user_can()`, `require_permission()` ile gerçek modül-bazlı yetki
  sistemi var ve `$__pmap` üzerinden sayfa başına otomatik koruma uygulanıyor. Madde kaldırıldı.

## Diagnostik dosyalar prod sunucuda kalmış olabilir
- `temizle.php` (kökte, admin girişi veya `?key=acans-migrate-2026` ile çalışır) install_*.php,
  kontrol.php, iz.php, bak.php, fix_login.php, ac_extract.php, dev_check.php, ac.php, eski not
  dosyalarını siler ve kendini de siler. NOT (2026-07-02 düzeltmesi): asıl deploy aracı `guncelle.php`
  (Masaüstü'nde, repo dışı — bkz. [[deploy]]) her deploy'da KENDİ yardımcı dosyalarını zaten siliyor;
  `temizle.php` bundan bağımsız, daha eski legacy artıkları (install_*.php vb.) temizlemek için ayrı
  bir araç — deploy'un zorunlu bir adımı değil, gerektiğinde manuel çalıştırılır.
