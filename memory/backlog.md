# Backlog & Yapılacaklar

<!-- Açık geliştirme görevleri. Kapanan madde buradan silinip memory/features.md'ye taşınır. -->

## FAZ 2C — MOBILE DESIGN SYSTEM MIGRATION (2026-07-17, PRODUCT OWNER KARARI — AKTİF ÖNCELİK)
Öncelik değişikliği: R/2b (Akıllı Arama) geçici olarak backlog'a alındı (kod/commit `f817b32`
korunuyor, bkz. [[features]] "FAZ 2B-ii-R/2b") — gerekçe web ile mobil arasında Tek Ürün
Prensibi'ni bozan belirgin tasarım farkı. FAZ 2C bitmeden R/2b'ye dönülmeyecek.

**Hedef:** Mobil uygulama şu 12 alanda web ile AYNI tasarım sistemini (DF/`ds-foundation.css` +
`ds_lib.php`) kullanacak: Home, Search, Navigation, Listeler, Kartlar, Formlar, Empty State,
Badge, Typography, Spacing, Bottom Navigation, Header. Legacy mobil görünüm TAMAMEN
kaldırılmayacak (mevcut `mobile/common.php` topx/botx/card/mc/mm çatısı yok edilmeyecek) — ama
Compact Mobile yeni referans olacak. Kabul kriteri: kullanıcı platform değiştirdiğinde farklı
uygulama kullanıyormuş hissi yaşamamalı.

**Mobile Design System Audit (2026-07-17) kabul edildi** — 67 sayfa envanteri: 3 TAM COMPACT
(index/job_view/more), 2 "özel" (mytasks/task_view — $__navMode hiç yok, gövde koşulsuz DF), 62
TAM LEGACY. DF CSS + `ds_lib.php` her mobil sayfada zaten yükleniyor (kullanılmıyor) — göç yeni
altyapı değil markup dönüşümü.

**FAZ 2C-i (Mobile Shell Migration) CLOSED** (AUDIT+DEV+USER TEST PASS, 2026-07-17, Product Owner'ın
son onayı: "Bu faz yeniden açılmayacaktır") — bkz. [[features]] "FAZ 2C-i". Kalan fazlar (2C-ii Home,
2C-iii Search, 2C-iv Liste/Kart, 2C-v Formlar, 2C-vi Badge/EmptyState temizliği) sırayla, her biri
kendi Audit + DEV PASS + USER TEST kapısından geçerek ilerleyecek.

**FAZ 2C-ii — Mobile Home: Audit ONAYLANDI (2026-07-17), ama kodlama BAŞLAMAYACAK.** Product Owner
kararı: uygulamadan önce ayrı bir "Product Sprint" yapılacak — Home'un kullanıcı deneyimi yeniden
tasarlanacak, legacy'den hangi bileşenlerin korunacağı ve yeni Home'un NİHAİ bilgi mimarisi
(audit'te sunulan A/B seçeneğinin ötesinde, Product Owner'ın kendi tasarım kararı) bu sprintte
belirlenecek. **Açık gate: bu IA kararı gelmeden 2C-ii'nin implementasyonuna geçilmeyecek.** Ana bulgu: Home'da 3 varyant var — Legacy-Admin (KPI grid+Hızlı İşlemler+ay
karşılaştırması), Legacy-Personel (aynı desenin sade hâli), Compact (tek, rol-agnostik queue modeli
— `home_build_queue()`/`home_build_continue()` zaten `$canSee()` ile rol-farkında, web
`dashboard.php` ile ORTAK). Personel'in bugün legacy görmesi component eksikliği değil,
`nav_effective_mode()`'un varsayılan pilot-gate davranışı (rollout kararı, ayrı). Sunulan 2 seçenek:
(A) queue modelini tek standart yap (kod minimal, legacy KPI içeriği terk edilir), (B) legacy KPI
içeriğini DF bileşenleriyle yeniden üret (daha büyük iş, hiçbir bilgi kaybolmaz). **Karar
Product Owner'ın.** Ayrı bulgu (YÜKSEK risk, business logic DEĞİL, görünürlük kusuru): Legacy
Personel/Saha dalındaki 7 kısayol kartı `user_can()` kontrolsüz — yetkisiz tıklamada 403 "ne
nerede" tuzağı (NAV-001A'da bottom nav için zaten düzeltilen sınıfla aynı kusur, burada
düzeltilmedi). Tam rapor: `~/Desktop/FAZ2C-ii-Home-Audit.pdf`.

## ds-foundation.css'te kısmen ölü Launcher CSS'i (2026-07-17, Ece PX-002 FAZ 2B-ii review notu, çok düşük öncelik)
FAZ 2B-ii'de `layout_top.php`'nin eski Launcher paneli (web "Tüm Modüller" sağdan-kayan drawer)
kaldırıldı. Bunu üreten CSS'ten yalnızca `.df-nav-overlay`/`.df-nav-launcher` (sabit 380px sağ
panel kabuğu) ve `.nav-launcher-trigger`/`.nav-pin-empty` (sidebar'a özel tetikleyici) artık
gerçekten ölü — grep ile doğrulandı, hiçbir dosya basmıyor. **`.df-nav-launcher-group`/
`.df-nav-launcher-group-title`/`.df-nav-row*` ÖLÜ DEĞİL** — `mobile/more.php`'nin compact dalı
bunları hâlâ kullanıyor (kendi tam-genişlik Menü listesinde, drawer değil). Temizlik yalnızca
o dar kapsamda (overlay/launcher-panel/trigger) yapılmalı, FAZ 2B-iii'te mobil Launcher deseni
de gözden geçirilirken ele alınabilir — şimdi tek başına silmek riskli değil ama erken.

## Design/Workflow Backlog — "Üretimi Başlat" hızlı aksiyonu (2026-07-17, PX-002 FAZ 2B IA FREEZE kararı)
Mevcut bir işten üretim aşaması başlatma hızlı aksiyonu. Product Owner'ın IA revizyonu
sırasında önerildi ama mevcut tek route'un (`production.php`/`mobile/uretim.php`) gerçek
davranışının "üretim panosu / üretimdeki işleri görüntüleme" olduğu netleşti — ayrı bir gerçek
işlem/ekran/güvenli bağlam oluşmadan navigasyonda "Üretimi Başlat" etiketi kullanılmadı
(`production` taxonomy satırı `category=uretim_stok, isPrimaryAction=true` oldu ama label
"Üretimdeki İşleri Gör" olarak kaldı). Gerçek ihtiyaç netleşirse (örn. bir işin durumunu
"üretimde" yapan ayrı bir aksiyon/route) bu backlog maddesi değerlendirilecek.

## Web Module Launcher + kişisel pin sisteminin Compact Mode'da emekliye ayrılması (2026-07-17, PX-002 FAZ 2B Flag 1 kararı)
IA FREEZE sonrası: 5-kategori Rail/Menü modeli her rotayı zaten 1-2 tıkla erişilebilir kıldığı
için "Tüm Modüller" arama-tetikleyicili paneli ve kişisel "Sabitlenenler" pin sistemi Compact
Mode'da **kullanılmayacak** (FAZ 2B-ii web / FAZ 2B-iii mobil işi). Bu bir veri silme kararı
DEĞİL: `nav_pinned_modules()/nav_grouped_for_launcher()/nav_visible_targets()` fonksiyonları ve
`ajax_nav_prefs.php` endpoint'i **kodda kalıyor**, DB'deki `nav_pinned_web`/`nav_pinned_mobile`
tercihleri **silinmiyor/migrate edilmiyor** — sadece yeni Compact UI bunları hiç çağırmıyor.
Legacy Mode'a dönen kullanıcının eski pinleri aynen çalışmaya devam ediyor. FAZ 2B-iii'ün test
matrisinde "eski pin verisiyle giriş yapan kullanıcı" senaryosu ayrıca doğrulanacak.

## nav_lib.php dosya-başlığı yorumu artık eksik (2026-07-17, Elif PX-002 FAZ 2B-i review notu, çok düşük öncelik)
`nav_lib.php:2-3`'teki özet yorum ("Web sidebar, Web Module Launcher, mobile/more.php ve
mobile/common.php::botx() hepsi buradan beslenir") hâlâ doğru ama artık dosyanın FAZ 2B-i'de
eklenen ikinci katmanını (nav_category_keys/nav_items_for_category/nav_global_items/
nav_search_index — gelecekte web Rail + mobil Menü/İşler + arama motorunu besleyecek) saymıyor.
Hata değil, sadece belge takip maddesi — FAZ 2B-ii/iii bu fonksiyonları gerçek sayfalara
bağladığında başlık yorumu da güncellenmeli.

## Design System Backlog — DF-Modal/DF-ConfirmDialog/DF-Pagination/DF-LoadingState/DF-Skeleton (2026-07-17, PX-002 FAZ 2A "Dead Component Kararı")
Product Owner kararı: bu 5 component bugün üretilmedi çünkü kod genelinde 0 gerçek kullanım
yeri var (grep ile doğrulandı — modal/dialog markup'ı hiç yok, tüm onaylar native `confirm()`
ile — 48 dosya; pagination hiç yok; loading-state/skeleton hiç yok, tüm render senkron PHP).
**Karar verilmedi ama üretilmeyecek de değil** — gerçek bir sayfa bunlardan birini
gerektirdiğinde (örn. Madde 5'te bir onay akışı `confirm()`'den DF-ConfirmDialog'a taşınmak
istenirse) aynı token/component sözleşmesiyle (`body.nav-compact` kapsamı, df- namespace,
`ds_*()` PHP API deseni) inşa edilecek. `ds_kpi_card()`/`.ds-kpi-card` de aynı gerekçeyle
bu turda dokunulmadı — Product Owner'ın 44 component listesinde yoktu, 0 canlı çağrısı var
(mevcut `ds-kpi-card` hâliyle duruyor, `df-` karşılığı yok).

## nav_module_is_active() mobileUrl'den habersiz (2026-07-17, Ece PX-002 Madde 1 review notu, çok düşük öncelik)
`nav_lib.php::nav_module_is_active($key,$currentScript)` hâlâ sadece `$item['url']`'i
karşılaştırıyor, yeni `mobileUrl` alanından habersiz. Şu an kod içinde HİÇBİR YERDEN çağrılmıyor
(grep ile doğrulandı) — canlı bir risk yok. İleride biri bu fonksiyonu mobilde "aktif menü satırı"
vurgusu için kullanmaya kalkarsa `nav_url_for_platform()` ile tutarlı hale getirilmeli.

## PDP/SEC-002 — job_view.php/mobile/job_view.php telefon sorgusu prepared statement değil (2026-07-16, Ece PX-001B review notu, DÜŞÜK öncelik)
`$pdo->query("SELECT phone FROM contacts WHERE id=".(int)$j['customer_id'])` deseni (`job_view.php`
hem legacy hem yeni pilot dalda, `mobile/job_view.php`'de de aynı) prepared statement değil, ham
string birleştirme — ama değer `(int)` cast'li olduğu için enjeksiyon riski YOK. Güvenlik açığı
değil, CLAUDE.md'nin "Tüm SQL prepared statements" kuralına harfiyen uymayan, legacy'den kopyalanmış
bir stil notu. **Karar verilmedi** — genel bir kod temizliği turunda ele alınabilir.

## PX-001B-ÖN — job_view.php/mobile/job_view.php İKİ FARKLI zaman çizelgesi kaynağı kullanıyor (2026-07-16, PİLOT için çözüldü — LEGACY hâlâ açık)
Şema araştırmasında bulundu: web `job_view.php` (satır 371) zaman çizelgesi için kendi `job_logs`
tablosunu (`add_log()` ile yazılıyor) kullanıyor; mobil `mobile/job_view.php` (satır 304) ise
`activity_logs` tablosunu (`entity_type='job'`, genel `activity_log()`/`activity_recent()`
altyapısı) kullanıyor — iki ekran birbirinin yazdığı olayları GÖRMÜYOR. **PX-001B pilot dalı için
karar verildi ve uygulandı:** `job_detail_lib.php::job_detail_timeline()` TEK kaynak olarak
`activity_logs` kullanıyor (`activity_recent()` üzerinden). **Ama eski İKİ LEGACY ekranın kendi
`job_logs`/`activity_logs` yazma davranışı hâlâ DEĞİŞTİRİLMEDİ** (bilinçli kapsam dışı — ayrı bir
parite/birleştirme kararı gerektirir).

## PX-001 — Home Screen "Devam Et"/"Sırada" global sorgu, kişiye özel değil (2026-07-16, Selin review notu)
`home_lib.php`'deki sorgular modül YETKİSİNE göre filtreli ama kayıt SAHİPLİĞİNE göre değil — yani
"gecikmiş iş" sistemdeki EN gecikmiş işi gösteriyor, o kullanıcıya atanan işi değil. Bu, mevcut
legacy `dashboard_pulse_state()` deseniyle tutarlı bilinçli bir tasarım (güvenlik açığı değil).
**Karar verilmedi** — ileride "satış temsilcisi sadece KENDİ cirosunu/işini görmeli" gibi bir ürün
beklentisi doğarsa, `home_build_queue()`/`home_build_continue()`'a `responsible_personnel_id`/
`created_by` bazlı bir filtre eklenmesi ayrı bir ürün kararı ve tur gerektirir.

## PARITY-003 — ÇÖZÜLDÜ (2026-07-17, PX-002 Madde 1, commit `924c367`)
Aşağıda önerilen `mobileUrl` deseni birebir uygulandı — bkz. [[features]] "PX-002 Madde 1". 5
kalem `mobileUrl` ile doğru mobil dosyaya yönlendirildi, 5 kalem (`assembly`/`design`/
`work_center`/`trade_documents`/`finance_accounts` — mobil karşılığı hiç yok) `mobileHide` ile
Launcher'dan gizlendi. Bu, USER TEST FAIL raporunun (2026-07-17) "kesin 404 listesi"yle birebir
örtüştü — bilinçli ertelenen bu madde, ertelemenin öngördüğü riskin gerçekleştiğini doğruladı.

## PARITY-003 (orijinal not, referans için korunuyor) — Mobil Command Launcher'da bazı satırlar mobilde var olmayan URL'lere gidiyor (2026-07-16, Elif NAV-001 v3 review notu)
`nav_taxonomy()`'nin `url` alanları NAV-001B'den beri hiç değişmedi (bu turda da dokunulmadı) — ama
`mobile/more.php`'nin compact Launcher'ı bu URL'leri `../` öneki olmadan doğrudan basıyor, yani href
mobil-yerel yola çözülüyor. Şu anahtarların hedef dosyası `mobile/` altında YOK: `production.php`,
`assembly.php`, `design.php`, `work_center.php`, `trade_documents.php`, `finance.php`,
`finance_accounts.php`, `finance_new.php` (Tahsilat/Ödeme), `finance_transfer.php` — mobilde
karşılıkları ya farklı isimde (`uretim.php`, `kasa.php`, `transfer.php`, `collection.php`,
`payment.php` gibi) ya da hiç yok. `nav_effective_mode()` admin'i varsayılan compact yaptığından bu,
**admin dahil** compact moddaki her kullanıcıyı etkiliyor — tıklayınca mobilde 404.

**Bilinçli olarak bu turda düzeltilmedi** — NAV-001 v3'ün kapsamı sadece Sidebar/Launcher çakışması
+ etiketleme/gruplama düzeltmesiydi, `url` alanlarına dokunmak "gereksiz refactor" sınırını aşardı.

**Öneri (karar verilmedi):** `dashboard.php`'deki `dashboard_quick_actions_split()`'in zaten
kullandığı `mobileUrl` (web/mobil URL farkını aynı listede tutma) deseni `nav_taxonomy()`'ye de
eklenebilir — her satıra opsiyonel bir `mobileUrl` alanı, mobil Launcher varsa onu, yoksa `url`'yi
kullanır. Ayrı bir NAV-001C/parity turunun konusu.

## PDP/SEC-001 — ajax_dashboard_order.php CSRF korumasız (2026-07-16, NAV-001B notu)
`ajax_dashboard_order.php` (WEB UI ALIGNMENT & NAVIGATION SPRINT 001'den, dashboard kart/bölüm
sürükle-bırak sırası) `boot.php`'nin `$__csrf_enforced_pages` listesinde YOK — proje genelinde
bilinen, kademeli CSRF yayılımının henüz ulaşmadığı bir sayfa. NAV-001B'de eklenen yeni
`ajax_nav_prefs.php` (pin/sıra/layout-mode) BİLİNÇLİ olarak baştan bu listeye eklendi (Product Owner
kararı: "mevcut ajax_dashboard_order.php'de yok gerekçesiyle korumasız bırakılmayacaktır") — ama
`ajax_dashboard_order.php`'nin kendisi bu turda DÜZELTİLMEDİ (kapsam dışı). **Karar verilmedi** —
ayrı bir CSRF-yayılım turunda `ajax_dashboard_order.php`'nin de listeye eklenmesi düşünülebilir.

## PARITY-002 — task_view.php'de "Gönder" (WhatsApp) sadece mobilde var (2026-07-16, Elif PX-001B review notu)
`mobile/task_view.php`'de bir "Gönder" (WhatsApp, `wa_link()`) aksiyonu var, web `task_view.php`'de
hiç karşılığı yok — `job_view.php`/`mobile/job_view.php` ikisi de `share_buttons()` kullanırken
task_view bu konvansiyonun dışında kalmış. **PX-001B'de eklenmedi**, önceki sprintte (commit
`8400335`) mobil liste kartından buraya taşınırken web tarafı hiç eklenmemiş — bilinçli mi
gözden kaçmış mı netleşmedi. Ayrıca mobildeki Gönder linki `pphone` boşsa da koşulsuz render
oluyor (işlevsiz `wa.me` linki) — küçük bir kozmetik kusur, aynı notun parçası.
**Karar verilmedi** — PX-001B kapanış kararında Product Owner bunu "ayrı bir parity sprintinde ele
alınacak" olarak teyit etti (bilinçli erteleme, unutulmuş değil).

## NAV-001A — Optional Module Navigation + Mobile Experience Redesign BLUEPRINT (2026-07-16)
Product Owner'ın tam kapsamlı program talimatı üzerine (Workspace/Menü Görünürlüğü/Yetki üç
bağımsız katman, web+tablet+mobil bilgi mimarisi) 25 bölümlü bir Blueprint hazırlandı — KOD
YAZILMADI, sadece analiz. Tam metin sohbet geçmişinde ("NAV-001A BLUEPRINT" başlıklı mesaj) ve
kalıcı kopyası `memory/nav001a_blueprint.md`'de. Product Owner onayı bekleniyor; onay sonrası
önerilen küçük/geri-alınabilir pilot NAV-001B olarak ayrı bir sprintte ele alınacak.
**Öne çıkan bulgular:** mobile/index.php:151,157 ve mobile/common.php::botx() bottom nav'ı
"İş"/"Cari" hedeflerini user_can('jobs')/user_can('contacts') kontrolü OLMADAN herkese gösteriyor
(yetkisiz personel tıklarsa page_module_map() 403 veriyor) — mevcut kodda kanıtlanmış, gerçek bir
"Ne nerede?" kök nedeni. user_preferences tablosu (migration 044) + user_prefs_lib.php +
ajax_dashboard_order.php zaten pin/sıralama için whitelist-validated bir desen sağlıyor — NAV-001B
için YENİ migration gerekmiyor. ROADMAP.md'deki "Workspace (Multi-Tenant) Architecture" (tamamen
ayrı, işlevsiz "Aktif Şirket" dropdown'ı) ile isim çakışması var, ayrı terim önerildi.

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
