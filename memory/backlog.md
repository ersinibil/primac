# Backlog & Yapılacaklar

<!-- Açık geliştirme görevleri. Kapanan madde buradan silinip memory/features.md'ye taşınır. -->

## NAV-001 — Adaptive Workspace & Optional Module Navigation (2026-07-16, Product Owner notu)
Product Owner'ın PX-001A sırasında değerli bulduğu ama kapsamı büyütmemek için AYRI bir Epic'e
ertelediği bir fikir — bu oturumda henüz detaylandırılmadı (sadece isim + niyet not edildi).
Muhtemel motivasyon: sol menüde çok sayıda modül var (Komuta Merkezi/Takvim/Notlarım/Görevlerim/
Mesajlar/İş-Üretim/Ticaret/Finans/Raporlama/Genel Sistem Yönetimi) — "adaptive" ve "optional module
navigation" isimlendirmesi, kullanıcıya göre uyarlanabilir/gizlenebilir bir nav yapısına işaret
ediyor olabilir. **Kapsam netleşmedi, PX-001A DEV PASS aldıktan sonra ayrı bir Epic olarak ele
alınacak** — bu notun ötesinde bir karar/analiz yok, sonraki oturumda Product Owner'la netleştirilmeli.

## PARITY-001 — Görevlerim / Notlarım Web-Mobil Kapsam Farkı (2026-07-16)
PRODUCT DESIGN BLUEPRINT uygulama sprintinde (mytasks.php) kök neden analizi sırasında bulundu:
`mobile/mytasks.php` kişisel not sistemi ("📝 Notlarım" paneli — `personal_notes` tablosu,
WhatsApp gönderme, takvime işlenen termin, tamamla/düzenle/sil) içeriyor; web `mytasks.php`'de bu
özelliğin hiçbir karşılığı yok. Web'de tek karşılık `notes.php` — ayrı, bağımsız bir sayfa, aynı
"Görevlerim" ekranına gömülü değil.

**Bilinçli olarak bu sprintte eklenmedi** — Product Owner kararı: "Bu sprint yalnızca mytasks.php
kullanıcı deneyimi dönüşümünde kalacaktır." Ayrı bir parity sprintinin konusu.

**Öneri (karar verilmedi, sadece gözlem):** Web'e ya `mytasks.php` üstüne mobildekine benzer bir
"Notlarım" paneli eklenmeli, ya da mobildeki panel kaldırılıp her iki platform da `notes.php`
(web) / `mobile/notes.php` (varsa) gibi ayrı bir ekrana yönlendirilmeli — hangisi doğru kalıcı
çözüm, henüz kararlaştırılmadı.

## critical_alerts (web) hâlâ yetkisiz-görünür — mobil kısmı KAPANDI (2026-07-14, mobil düzeltme 2026-07-15)
UX SPRINT 002 Phase B3 (Dashboard Nabız Satırı) incelemesinde Selin (security) tarafından bulundu,
Ece ve Elif de bağımsız olarak teyit etti: `dashboard.php`'nin "Dikkat - Geciken İşler & Kritik
Stok" bölümü (`data-key="critical_alerts"`) ve `mobile/index.php`'nin "⚠️ Dikkat" paneli, hâlâ
sadece ham `$overdue_count > 0 || $critical_stock > 0` koşuluyla render ediliyordu — hiçbir yetki
kontrolü yoktu.

**Mobil kısmı KAPANDI (MOBILE UX BUGFIX SPRINT, 2026-07-15):** Nabız Satırı "İncele" linkini
gerçekten çalışır hale getirme turunda, `mobile/index.php`'nin "⚠️ Dikkat" paneli de
`$__pulseShowJobs`/`$__pulseShowStock` (`is_admin()||user_can('jobs')` / `user_can('stock')`) ile
yetki-filtreli hale getirildi — yetkisiz kategori artık panelde hiç görünmüyor, grid tek kutu
kalırsa otomatik tek sütuna düşüyor. Ece/Selin/Elif üçü de PASS verdi.

**Web kısmı hâlâ AÇIK:** `dashboard.php`'nin `critical_alerts` bölümü (satır ~546) hâlâ hiçbir
yetki kontrolü olmadan render ediliyor — `jobs`/`stock` yetkisi olmayan bir kullanıcı hâlâ ham
sayıları VE en kritik 5 geciken işin iş no/başlık/termin bilgisini görebiliyor. `dashboard.php`
zaten `page_module_map()`'te yer almadığı için (bilinçli tasarım, herkese açık ana sayfa) bu
davranış devam ediyor. Phase B3/B4 kapsamında BİLİNÇLİ olarak dokunulmadı (kullanıcının kendi
talimatı: "Kritik bölümün kendisi yine mevcut sıralanabilir bölüm olarak kalmalı") — düzeltme UX
SPRINT 002 **Phase B4 (Rol bazlı varsayılan görünüm)**'ün doğal kapsamı, hâlâ AÇIK. Öneri:
`dashboard.php`'nin critical_alerts render koşuluna `(is_admin()||user_can('jobs'))`/
`(is_admin()||user_can('stock'))` bazlı görünürlük eklenmesi (mobildeki desenin aynısı).

## AKTİF ÖNCELİK SIRASI — TAMAMLANDI (kullanıcı kararı, 2026-07-14 — "ÇALIŞMA PLANI GÜNCELLEMESİ")
~~1) Finance CRUD UX Patch 001~~ **PASS, CLOSED** → ~~2) Flow Unification 001~~ **PASS, CLOSED**
→ ~~3) Migration 042/043 DEV doğrulaması~~ **PASS, CLOSED** → ~~araya giren FINANCE ACCOUNT LIST
FILTER UX~~ **PASS, CLOSED** → ~~araya giren TOPBAR MESSAGE BADGE GHOST COUNT~~ **USER TEST
PASS/DEV PASS, CLOSED** → ~~4) Mobile Regression Sprint~~ **CLOSED (kod+sandbox doğrulaması)**.
Tüm sıra bitti (2026-07-14). Sıradaki iş: **UX SPRINT 002** (dashboard/genel arayüz mimari analizi,
şu an Faz A — analiz/mimari raporu, bu fazda kod/commit/push/zip YOK; rapor ayrıca teslim edildi,
repo'ya işlenmedi). Aşağıdaki maddeler hâlâ BEKLEMEDE: "Yaklaşan İşler" widget'ı, mobil çapraz
navigasyon, `deleted_at` filtre genişletmesi, VAPID yapılandırması, mobil karşılığı olmayan
ekranlar.

## MESSAGING CHANNEL SEMANTICS — İş/görev atamalarının Mesajlar kanalından çıkarılması (2026-07-14)
- TOPBAR MESSAGE BADGE GHOST COUNT bug fix'i (bkz. [[features]] / [[bugs]]) sadece kendine-atama
  kenar durumunu düzeltti — daha geniş bir mimari soru AÇIK kaldı: iş/görev ataması (`job_new.php`,
  `task_new.php`, `mobile/task_new.php`) ve talep onay/red (`requests.php`) hâlâ BAŞKA bir
  personele/kullanıcıya atandığında/onaylandığında `internal_messages`'a da yazmaya (dual-write)
  devam ediyor — yani bunlar teknik olarak birer sistem/iş olayı olsa da, hâlâ Mesajlar ekranında
  "sanki biri size yazmış gibi" görünüyorlar. Kullanıcının kendi "Kesin ürün kuralı"na göre
  ("Gerçek kullanıcıdan kullanıcıya yazışma → Mesajlar; iş/görev atama, sistem uyarısı veya
  hatırlatma → Bildirimler") bu dual-write tamamen kaldırılıp sadece `internal_notifications`'a
  (Bildirimler) taşınmalı. Bu, kullanıcı tarafından BİLİNÇLİ olarak bu turun kapsamı DIŞINDA
  bırakıldı ("bu daha geniş karar bu bug fix kapsamında uygulanmasın") — ayrı bir onay/tur
  gerektirir, kullanıcı deneyimi açısından mevcut alışkanlığı (görev atandığında Mesajlar'da da
  görünmesi) değiştireceği için dikkatli ele alınmalı.

## finance_accounts sil.php akışı filtre bağlamını korumuyor (2026-07-14, düşük öncelik)
- FINANCE ACCOUNT LIST FILTER UX CLOSED (bkz. [[features]]) — ama hesap "🗑 Sil" işlemi
  (`sil.php?t=account` üzerinden, sadece `finance_account_view.php`'nin kendi Sil butonu) hâlâ
  filtresiz `finance_accounts.php?deleted=1`'e dönüyor. `sil.php` paylaşılan, çok sayıda başka
  akışın kullandığı bir dosya olduğu için bilinçli olarak dokunulmadı (listenin kendi inline
  Sil'i zaten filtre bağlamını otomatik koruyor). İstenirse ayrı küçük bir iş olarak ele alınabilir.

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

## sales.php/purchase.php çapraz-navigasyon linkleri mobilde yok (2026-07-13, düşük öncelik)
- WEB UI ALIGNMENT & NAVIGATION SPRINT 001 Faz C'de `sales.php`/`purchase.php` başlıklarına eklenen
  küçük çapraz-navigasyon linkleri (Satış↔Alış, Satış/Alış Belgesi oluşturma) `mobile/sales.php`/
  `mobile/purchase.php`'ye taşınmadı (Ece'nin bulgusu, sprint CLOSED — bkz. [[features]]). Basit
  `<a>` linkleri oldukları için teknik engel yok, istenirse ayrı küçük bir iş olarak eklenebilir.

## mobile/sales.php stock_create_sale() ortak fonksiyonuna bağlanmadı (2026-07-11, düşük öncelik)
- Flow Unification 001 CLOSED (bkz. [[features]]) — ama `mobile/sales.php` hâlâ kendi inline
  satış-oluşturma mantığını taşıyor, yeni `stock_create_sale()` ortak fonksiyonuna bağlanmadı
  (Ece/Elif incelemesinde bulundu). Sonuç şu an tutarlı (aynı kurallar, aynı `finance_movements`
  şekli) ama kod paylaşımı yok — ileride `stock_create_sale()`'e bağlanırsa hem bakım kolaylaşır
  hem çekirdekte yapılacak gelecekteki düzeltmeler otomatik mobile yansır.

## Kontrollü Negatif Stok Politikası — küçük polish notu (2026-07-11)
- Özelliğin kendisi CLOSED (WEB) — bkz. features.md. Tek açık kalan, düşük öncelikli not: şu an
  yetersiz-stok-onaylı satışların "görünürlüğü" sadece `finance_movements.description`'a eklenen
  " ⚠️ Stok Yetersiz (Onaylandı)" metni ile sağlanıyor (migrationsız, düşük riskli tercih).
  İstenirse ileride Son Satışlar listesinde gerçek bir rozet/filtre (örn. "Tedarik Bekliyor")
  eklenebilir — bu turun kapsamı dışında bırakıldı (kullanıcı: "büyük UI refactor yapma").

## Mobile Regression Sprint — CLOSED (kod+sandbox doğrulaması, 2026-07-14)
- Kullanıcı kararı: "benim test yapmamı bekleme, hata bulursam kullanırken bildiririm — oradan
  başla bana sormadan hepsini bitir." Gerçek cihaz/tarayıcı testi YERİNE şu doğrulama yapıldı:
  **Kod incelemesi** — `mobile/purchase.php` yeni satış/alış oluştururken web ile AYNI paylaşılan
  `stock_lib.php` fonksiyonlarını (`stock_create_purchase`, `stock_update_purchase`,
  `stock_reverse_purchase`, `stock_can_edit_purchase`) doğrudan çağırıyor — ayrı kod yolu yok.
  `mobile/sales.php` satış OLUŞTURMA için kendi inline bloğunu koruyor (`stock_create_sale()`'e
  bağlanmadı — bkz. altta ayrı düşük öncelikli madde) ama düzenleme/silme için aynı paylaşılan
  fonksiyonları (`stock_update_sale`, `stock_reverse_sale`, `stock_can_edit_sale`,
  `stock_sale_build_lines`, `stock_insert_sale_movement`) kullanıyor; inline oluşturma bloğu
  satır satır `stock_create_sale()` ile karşılaştırıldı, tek fark `movement_type='mobile_sale'`
  (web'de `'sale'`) — bu etiket farkı zaten kasıtlı ve `contact_balance_case_sql()`/`sales.php`'nin
  "Son Satışlar" sorgusu tarafından tanınıyor (`movement_type IN ('normal','mobile')` DIŞINDA
  kalan her şey "borç oluşturan hareket" sayılıyor, `'mobile_sale'` de bu kapsamda).
  **Sandbox fonksiyonel test** (yerel MariaDB, `mobile/sales.php`'nin gerçek inline kodu + gerçek
  `stock_create_purchase()` çağrısı birebir simüle edildi) — 19/19 kontrol PASS: mobil satış
  oluşturma (Bekliyor/account_id NULL/movement_type doğru/stok düşüyor), cari bakiye tek yönlü
  sayılıyor (double-counting yok), tahsilatla kapanıyor, silme stoğu tam geri alıyor, Kontrollü
  Negatif Stok (onaysız 8/5 red + stok değişmedi, onaylı kabul + stok -3, silince 5'e dönüş),
  alış oluşturma/miktar düzenleme, avg_cost güvenlik kapısı (sonraki hareket varken düzenleme
  reddi) — hepsi web ile birebir aynı sonucu verdi. **Hiçbir kod değişikliği gerekmedi** — mobil
  taraf zaten doğruydu, sadece daha önce fiilen doğrulanmamıştı.
  **Not:** Bu, gerçek bir kullanıcı/cihaz testi DEĞİLDİR — kullanıcının kendi açık kararıyla
  atlanmıştır. Kullanım sırasında bir sorun fark edilirse ayrıca bildirilecek.

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
