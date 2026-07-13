# Özellik Geçmişi

<!-- En yeni en üstte. Tamamlanan özellikler ve mimari kararlar. -->

## UX TERMINOLOGY FIX — "İş Takip"/"İşlerim" karışıklığı çözüldü: CLOSED — USER TEST PASS (2026-07-13)
Kullanıcı bildirimi: sol menüde "İşlerim" ve "İş Takip" isimleri birbirine çok yakındı, operasyonel
iş kayıtları (jobs.php) ile kullanıcıya atanmış kişisel görev/hatırlatmalar (mytasks.php) arasında
karışıklık yaratıyordu — üstelik jobs.php'ye giden yolun kendisi de üç farklı isimle anılıyordu
("İş Takip" sayfa başlığı/mobil, "İş Merkezi" web sidebar, sade "İşler" dashboard kartı/mobil kart).

Kesin terminoloji kararı (route/dosya/tablo/yetki anahtarı DEĞİŞMEDİ, sadece görünen isim):
- **"İş Takip" / "İş Merkezi" / "İşler" (jobs.php'ye giden TÜM etiketler) → "İş Emirleri"**
  (alt açıklama: "Müşteri işleri ve operasyon takibi").
- **"İşlerim" → "Görevlerim"** (alt açıklama: "Bana atanan görevler ve hatırlatmalar").

Değişen dosyalar: `layout_top.php` (sol menü + rapor linki), `jobs.php`/`mytasks.php` (h1 + alt
açıklama), `mytask_new.php`, `task_view.php`, `report_lib.php` (modül adı + rapor başlığı),
`contact_view.php` (cari-iş geçmişi başlığı sadeleştirildi), `dashboard.php` (modül kartı + "Son
İşler" bölümü linki), `mobile/index.php`, `mobile/more.php`, `mobile/jobs.php`,
`mobile/mytasks.php`, `mobile/task_view.php`, `mobile/uretim_new.php` (WhatsApp/iç mesaj bildirim
metni), artı ilgili `tasks_lib.php`/`search_lib.php` yorumları. `PROJECT_RULES.md`'deki "Kavram
Standardı" bölümü (2026-07-03'te "Görevlerim ifadesi artık kullanılmaz" diyen ESKİ kararı) bu yeni
kararla güncellendi — bilinçli bir tersine çevirme, unutkanlık değil.

Kapsam dışı bırakılanlar (kasıtlı, dokunulmadı): dashboard'daki "Bugün Teslim"/"Geciken İş"/
"Bekleyen İş" gibi KPI kart adları (bunlar "iş" kelimesini genel sayaç anlamında kullanıyor, menü/
sayfa kimliği değil); çek vadesi gibi kayıtların "Görevlerim" altında kalması (mevcut
`daily_reminder_lib.php` mantığına dokunulmadı, zaten doğru gruplanıyordu).

Doğrulama: `php -l` tüm 17 değişen PHP dosyasında temiz; `git diff` ile her satırın SADECE
görünen metin olduğu, hiçbir href/route/SQL/yetki anahtarının değişmediği teyit edildi.

**USER TEST PASS (2026-07-13):** Kullanıcı DEV'de doğruladı — İş Emirleri doğru ekrana gidiyor,
Görevlerim doğru ekrana gidiyor, çek vadesi ve kişisel hatırlatmalar Görevlerim altında doğru
kalıyor, web/mobil terminoloji ayrımı anlaşılır ve doğru. **CLOSED.**

## WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Dashboard sürükle-bırak + sol menü sadeleştirme + ortak tasarım dili: CLOSED — USER TEST PASS (2026-07-13)
Bu sprintten itibaren PRIMAC OTS'ta ad-hoc "Patch" isimlendirmesi bırakıldı, kalıcı bir Sprint
sistemine geçildi (SECURITY/FINANCE/UX/MOBILE/PERFORMANCE/INTEGRATION/REPORTING/REFACTOR SPRINT) —
her sprintin kendi kapsamı, tek commit zinciri, tek teslim raporu var ve kendi Regresyon Sprint'i
geçmeden CLOSED yazılmıyor. Bu kural artık standing (kalıcı) proje kuralı.

Sprint 3 fazdan oluştu:

**Faz A — Komuta Merkezi kart sırası (kullanıcı bazlı, sürükle-bırak):**
- Yeni migration `044_user_preferences.sql`: genel amaçlı `user_preferences(user_id, pref_key,
  pref_value)` key/value tablosu — bu sprintte SADECE `dashboard_tile_order` anahtarı kullanılıyor,
  başka tercih alanı eklenmedi (kullanıcının kendi kararı).
- Yeni `user_prefs_lib.php` (`user_pref_get`/`user_pref_set`) ve yeni `ajax_dashboard_order.php`
  (CSRF korumalı POST endpoint, `boot.php` `$__csrf_enforced_pages`'e eklendi).
- `dashboard.php`: navtiles artık `$__tileDefs` veri dizisinden render ediliyor, kaydedilmiş sıra
  varsa uygulanıyor (yetkisi kalkan/yeni eklenen kart sessizce doğru yere düşüyor). HTML5
  `draggable` ile sürükleme SADECE küçük bir `.tile-drag` tutamaçla yapılıyor — kartın tamamı
  draggable değil, link tıklaması bozulmuyor (kullanıcının kesin talimatı).
- Migration henüz DEV'de çalıştırılmamışken de (`user_preferences` tablosu yokken) sayfa fatal
  vermiyor — `user_pref_get`/`set` try/catch ile sessizce default'a düşüyor, doğrulandı.

**Faz B — Sol menü sadeleştirme:**
- "İş / Üretim Yönetimi" → "İş / Üretim" (web+mobil aynı isim, kullanıcının kararı).
- Eski tek "Muhasebe İşlemleri" grubu **TİCARET** (Cariler/Teklif/Stok/Satış/Alış) ve **FİNANS**
  (Finans Paneli/Hesaplar/Tahsilat/Ödeme/Çek-Senet/Muhasebe) olarak ikiye ayrıldı; içindeki rapor
  linkleri (`report.php?modul=...` + `contacts_report.php`) tamamen Raporlama grubuna taşındı —
  hiçbir route iki grupta birden görünmüyor (script ile doğrulandı). `mobile/more.php`'ye birebir
  aynı gruplama uygulandı (parite).
- Taşıma sırasında fark edilen pre-existing bir uyumsuzluk da düzeltildi: `contacts_report.php`
  menüde `user_can('report')` şartına bağlıydı ama gerçek sayfa yetkisi (`page_module_map()`)
  `contacts` idi — artık menü de `user_can('contacts')` şartına bağlı (mobil zaten doğruydu).

**Faz C — Ortak tasarım dili (sadece 8 öncelikli ekran: Komuta Merkezi, Sol Menü, Üst Bar, ortak
sayfa başlığı, Cari Detay, Finans Hareketleri, Satış/Alış ana listeleri):**
- `layout_top.php`'ye yeni CSS: `.page-header` (sayfa üst başlığı için `.panel-head`'i genişletir),
  `.row-actions` (tablo satırındaki Düzenle/Sil/kaynak-linki butonlarını sarmalar), `.quick-action`
  (başlık aksiyon butonları). `.module-card`/`.section-card` ise mevcut `.ntile`/`.command-card`/
  `.panel` kurallarını DEĞİŞTİRMEDEN sadece isim olarak ekleniyor (extend, don't replace).
  `boot.php::badge()` artık ek olarak `status-badge` class'ı da basıyor (görsel etki yok).
- `dashboard.php`, `contact_view.php`, `finance.php`, `sales.php`, `purchase.php` başlıkları
  standartlaştırıldı; `sales.php`'nin eskiden çıplak `<h1>` olan başlığı bu sırada diğer 3 ekranla
  aynı `.panel-head`/`.actions` kalıbına çekildi (pre-existing tutarsızlık, fırsattan
  düzeltildi). `sales.php`/`purchase.php` başlıklarına küçük çapraz-navigasyon linkleri eklendi
  (Satış↔Alış, Satış/Alış Belgesi) — mobil karşılığı bilinçli olarak bu sprintin kapsamı dışında
  bırakıldı (bkz. [[backlog]]).

**Kapsam dışı bırakılan (bilinçli):** Faz A'nın sürükle-bırağı SADECE web'de — mobilin sabit kart
sırası değişmedi (kullanıcının kararı, dokunma-touch ortamında HTML5 native drag zaten uygun değil).

**Test notu:** Bu oturumda bir tarayıcı otomasyon aracı mevcut değildi ve gerçek görsel/responsive
test (A/B/C test matrisi maddeleri) bu yüzden yapılamadı — sadece kod seviyesinde regresyon (D
maddesi: route/yetki/CSRF/dashboard link/mobil view/mobil menü/bildirim-mesaj linki/kullanıcı
menüsü) doğrulandı + Ece/Selin/Elif incelemesinden geçti (kritik/orta bulgu yok). Görsel/responsive
doğrulama ve sprintin kendi kapanış kriterleri (sürükle-bırak çalışıyor mu, sol menü anlaşılır mı,
web mobille aynı ürün ailesi gibi hissettiriyor mu) kullanıcının DEV'de bizzat test etmesini
bekliyor — PASS gelmeden CLOSED yazılmayacak.

**2026-07-13 EK — Faz A tamamlandı (bölüm sürükle-bırak) + kalıcı kalite kuralı:** İlk kullanıcı
testinde tile-drag PASS aldı (kartların sağ üst köşesindeki ⠿ tutamaç görülüp fiilen sürüklendi,
sıra kaydedildi) — AMA kullanıcının asıl talebinin "sayfanın TAMAMININ" bloklar halinde
sıralanabilmesi olduğu, sadece Ana Modül Kartları'yla sınırlı kalındığı ortaya çıktı. Bunun
üzerine **Seviye 2** eklendi: `dashboard_section_order` (yeni tercih anahtarı, aynı
`user_preferences` tablosu, yeni migration YOK) — Komuta Merkezi'nin 10 gerçek bölümünün
(module_tiles, month_comparison, six_month_trend, critical_alerts, operation_kpis, notes,
recent_actions, live_notifications, today_and_late_lists, recent_jobs — `dashboard_section_keys()`
tek doğru kaynak) her biri artık kendi ⋮⋮ tutamacıyla ayrı ayrı taşınabiliyor, tile-level sıralama
ile tamamen bağımsız (ayrı state, ayrı DOM seçicisi). Ece'nin code review'unda bulunan bir "ölü
bölge" bugu (bir SECTION, module_tiles'ın kart alanına bırakılınca stopPropagation() event'i
yutuyordu) aynı turda düzeltildi. **Kalıcı kalite kuralı (kullanıcı talimatı, standing rule):**
bundan böyle Drag&Drop/Modal/Menü/Accordion/Hover/Responsive/JS/AJAX/Bildirim/Arama/Filtreleme gibi
kullanıcı-etkileşimli özellikler için "Kod tamamlandı" ile "Kullanıcı davranışı doğrulandı" ayrı
başlıklar altında raporlanır; gerçek kullanıcı testi geçmeden hiçbiri "TAMAMLANDI"/CLOSED
yazılmaz. Bu sprint için durum: **tile-drag PASS (kullanıcı doğrulaması var), section-drag KOD
TAMAMLANDI + code review geçti ama KULLANICI DAVRANIŞI HENÜZ DOĞRULANMADI** — commit `59e51dc`,
push edildi, zip hazır. Kullanıcının "Son İşlemler"/"Notlarım"/"Canlı Bildirimler" bölümlerini
gerçekten sürükleyip sayfa yenilendiğinde/çıkış-girişten sonra sırasının korunduğunu doğrulaması
gerekiyor.

**USER TEST PASS — SPRINT CLOSED (2026-07-13):** Kullanıcı DEV'de section-level sürükle-bırağı da
doğruladı: ana bölümler blok halinde sürüklenebiliyor, sıra kullanıcı tarafından değiştirilebiliyor,
sayfa yenilendiğinde korunuyor, tile-drag section-drag ile çakışmıyor, kullanıcı etkileşimleri
(link/grafik/buton) bozulmuyor. Sprint durumu: Dashboard Tile Drag PASS, Dashboard Section Drag
PASS, Navigation UX PASS, Web UI Alignment PASS. **Sprint CLOSED.** (Not: Dashboard Tarih Mantığı düzeltmesi bu sprintin kapsamında
DEĞİLDİR, ayrı bir bugfix turu — bkz. `memory/bugs.md`; o iş hâlâ USER TEST BEKLİYOR, yarınki
(2026-07-14) yeni günlük bildirim ile ayrı doğrulanacak.)

## Finance CRUD UX Patch 001 — Finans Hareketi Düzenle/Sil Her Ekrandan Erişilebilir: CLOSED — USER TEST PASS (2026-07-12, testi 2026-07-14)
Sorun: bir finans hareketini (tahsilat/ödeme) düzenlemek/silmek için kullanıcının "Raporlar >
Son İşlemler" (aslında ana finans ekranı, finance.php) yolunu bilmesi gerekiyordu — cari detay ve
hesap (kasa/banka) detay ekranları aynı satırları SADECE görüntülüyordu, işlem yapılamıyordu.

**Yeni bir CRUD motoru YAZILMADI.** `finance_new.php` (düzenleme) ve `sil.php`'nin `t=finance`
dalı (silme) — ikisi de zaten CSRF korumalı ve `finance_movement_editable_types()` ile
yetki/tip kontrollü — olduğu gibi yeniden kullanıldı, sadece bu route'lara giden LİNKLER yeni
ekranlara eklendi.

- **`finance_lib.php::finance_movement_actions($row)`** — TEK merkezi karar fonksiyonu: manuel mi
  (`movement_type` normal/mobile), düzenlenebilir/silinebilir mi, değilse hangi "kaynağı aç"
  linkinin gösterileceği (`document_id` doluysa "🧾 Belgeyi Aç", `sale`/`mobile_sale` ise "🧾
  Satışı Aç", `purchase` ise "🛒 Alışı Aç", `settles_movement_id` doluysa "🔗 Bağlı Hareketi Aç" —
  migration 042'nin kolonu, henüz hiçbir yazma yolu yok ama ileriye dönük eklendi — `transfer`/
  bilinmeyen tip düz "Otomatik"). `finance.php`, `contact_view.php`, `finance_account_view.php` ve
  mobil eşdeğerleri artık kendi ayrı `$canEdit` mantıklarını YAZMIYOR, hepsi buradan okuyor.
- **`finance_lib.php::finance_return_url($context,$ref,$default)`** — İşlem sonrası kullanıcı
  geldiği ekrana dönsün diye güvenli dönüş URL'i üretir. SADECE 3 sabit isim (`contact`/`account`/
  `finance`) + basit bir tamsayı id kabul eder, ASLA ham URL/host kabul ETMEZ — open redirect
  riski yok (Selin'in denetiminde doğrulandı).
- `finance_new.php`/`sil.php`: `return_context`/`return_ref` GET/POST'tan okunup form'a hidden
  alan olarak taşınıyor, başarılı işlem sonrası sabit `finance.php` yerine bu bağlama göre
  hesaplanan hedefe yönlendiriyor.
- `contact_view.php`/`finance_account_view.php`: "Finans Hareketleri"/"Hesap Hareketleri"
  tablolarına yeni bir "İşlem" kolonu eklendi (manuel → ✏️ Düzenle/🗑 Sil, sistem kaynaklı →
  kaynak linki).
- Mobil: `mobile/contact_view.php` + `mobile/account_view.php` aynı ayrımı uyguluyor ama yeni bir
  CRUD YAZMADAN — zaten var olan `mobile/movement_view.php` (kendi edit+delete ekranı) route'una
  bağlanıyor; o dosyaya da aynı `return_context`/`return_ref` deseni (kendi yerel `mv_return_url()`
  ile) eklendi.
- **Review'da bulunup aynı turda kapatılan iki gerçek bug:** (1) önceki sprintte (Flow Unification
  001) `mobile/purchase.php`/`mobile/sales.php`'ye eklenen "Belgeyi Aç" linki `../` önekini
  unutmuştu, mobilde 404 veriyordu — düzeltildi (bkz. [[bugs]]). (2) `contact_view.php` ve
  `finance_account_view.php`'nin "Tip"/"Tür" kolonu `finance_movement_type_label()` yerine ham
  `direction`'a bakıyordu, satış/alış kaynaklı eski kayıtları yanlış etiketliyordu — düzeltildi.
- Test: yerel MariaDB sandbox'ta `finance_movement_actions()`/`finance_return_url()` için 18 birim
  test (her movement_type/document_id/settles_movement_id kombinasyonu, open-redirect denemesi
  dahil) + kullanıcının verdiği örneğe birebir uyan bir DB senaryosu (manuel tahsilat → Düzenle/Sil
  açık → silme → kasa bakiyesi doğru geri alınıyor → kayıt kayboluyor; bekleyen satış kaydı →
  Düzenle/Sil kapalı → `finance_movement_delete()` reddediyor) PASS. Ece/Selin incelemesinden
  geçti — Selin kritik/yüksek bulgu vermedi (open redirect yok, yetki kontrolü backend'de
  bağımsız ikinci kez doğrulanıyor, CSRF/XSS temiz); Ece 2 gerçek bug (yukarıda) + 2 düşük öncelik
  not (mobil "silindi" toast'ı eksikti → eklendi; `mobile/movement_view.php` merkezi fonksiyonu
  kullanmıyordu → bağlandı) buldu, hepsi aynı turda kapatıldı.
- Commit: `1cb9e31`. **USER TEST PASS (2026-07-14):** kullanıcı DEV/primac.tr'de 7 maddelik test
  paketini çalıştırdı (cari ekranından/hesap ekranından manuel hareket düzenleme/silme, doğru
  ekrana geri dönüş, sistem kaynaklı hareketlerde doğru kaynak linki, bekleyen satışta Düzenle/Sil
  kapalı, mobil parite) — PASS. **CLOSED.**

## Flow Unification 001 — Alış/Satış Belgesi Akışını Finance Core ile Birleştirme: USER TEST BEKLİYOR (2026-07-11)
Kullanıcı bir BUG REPORT ile başlattı: `ALI-20260707-5177` (bir alış belgesi) `contact_view.php`
"Bu Cariye Ait İşler"de görünmüyordu ve `purchase.php` "Son Alışlar"da yoktu, ama "Belge" ekranında
vardı. Kök neden analizi (kod yazmadan, önce) doğruladı: ana menüde aynı işi (alış/satış girişi)
yapan İKİ bağımsız, birbirinden habersiz veri modeli aynı anda canlıydı — bkz. [[bugs]] "Çözüldü".

**Kesin karar (kullanıcı tarafından verildi):** `finance_movements` + `stock_movements` sistemin
tek finans/stok doğruluk kaynağı; `stock_lib.php` ortak işlem katmanı; `trade_documents` +
`trade_document_items` sadece belge/satır/görüntüleme/PDF katmanı olarak kalır ama artık kendi
finans/stok etkisini üretmez.

- `stock_lib.php::stock_create_purchase()`'a `$documentId=null` parametresi eklendi,
  `finance_movements` INSERT'üne `document_id` kolonu eklendi (hızlı alışta NULL kalır).
- **Yeni `stock_lib.php::stock_create_sale()`** — `sales.php`'nin eski inline satış-oluşturma
  bloğu davranış değiştirmeden buraya taşındı; `$documentId`/`$confirmed` parametreleri var,
  `StockShortageException`'ı yutmadan yukarı taşır.
- **Dış-transaction desteği:** her iki fonksiyon da `$pdo->inTransaction()` kontrolü yapıyor —
  kendi başına (purchase.php/sales.php) çağrılırsa kendi transaction'ını açıp kapatıyor, bir
  çağıranın (trade_document_new.php) transaction'ı içinde çağrılırsa kendi commit/rollback'ini
  YAPMIYOR, hatayı yukarı fırlatıyor.
- `trade_core.php::trade_apply_document($documentId, $confirmed=false)` tamamen yeniden yazıldı:
  eski inline stok/avg_cost/`finance_movements(movement_type='document')`/
  `finance_accounts.current_balance` doğrudan güncelleme kodu SİLİNDİ; artık
  `stock_create_purchase()`/`stock_create_sale()`'i çağırıyor. Bu fonksiyon TRANSACTION YÖNETMİYOR
  — sahibi `trade_document_new.php`.
- `trade_document_new.php`: Ödeme Durumu/Ödeme Hesabı/Ödenen Tutar alanları tamamen kaldırıldı.
  Satış belgesinde hiçbir INSERT'ten ÖNCE `stock_sale_build_lines(...,$confirmed)` ile ön kontrol
  yapılıyor — onaysız yetersiz stokta hiçbir tabloya yazılmıyor, sales.php ile aynı uyarı+onay
  kutusu (form İÇİNDE, Selin'in daha önce bulduğu "checkbox form dışında" hatası tekrarlanmadı,
  ayrıca bu turda yeniden doğrulandı) gösteriliyor. Ön kontrol geçerse tek transaction: belge+satır
  INSERT → `trade_apply_document()` → commit; herhangi bir adım hata verirse TAMAMI rollback.
  Yeni belgeler her zaman `account_id=NULL`, `paid_amount=0`, finans durumu `Bekliyor`.
  **Teknik not:** `activity_lib.php::activity_install()` her çağrıda `CREATE TABLE IF NOT EXISTS`
  çalıştırıyor — bu DDL, MySQL/MariaDB'de tablo zaten var olsa bile İMPLİCİT COMMIT yapıyor.
  `trade_apply_document()` başarıyla bitip `activity_log()`'a ulaştığında transaction bu yüzden
  erken kapanabiliyordu; `trade_document_new.php`'deki commit/rollback çağrıları
  `$pdo->inTransaction()` ile korumalı hale getirilerek (aksi halde başarılı bir işlem hatalıymış
  gibi görünürdü) çözüldü — `activity_lib.php`'ye dokunulmadı (paylaşılan, hassas dosya).
- `purchase.php`/`sales.php` "Son Alışlar/Satışlar": `document_id`/`trade_documents` JOIN'i
  eklendi, belge kaynaklı satırlarda Düzenle/Sil yerine "🧾 Belgeyi Aç" (`trade_document_view.php`)
  linki gösteriliyor.
- `contact_view.php`: "Bu Cariye Ait İşler" → "Bu Cariye Ait İşler / İş Emirleri" (kavram
  netleştirmesi, sorgu değişmedi — `jobs` tablosu), altına ayrı "Alış / Satış Belgeleri" bölümü
  eklendi (`trade_documents WHERE contact_id=?`).
- **Kod incelemesinde (Ece, Elif) bulunan ve AYNI turda kapatılan güvenlik/bütünlük açığı:**
  `stock_can_edit_purchase()`/`stock_can_edit_sale()`/`stock_reverse_purchase()`/
  `stock_reverse_sale()` `document_id`'yi hiç kontrol etmiyordu — web'de bu sadece listede
  Düzenle/Sil butonunu gizliyordu (backend'de crafted POST ile hâlâ açıktı), mobilde hiç koruma
  yoktu. Bu risk, belge kaydının artık `movement_type='purchase'/'sale'` olması sayesinde (eskiden
  `'document'` idi, listelerde hiç görünmüyordu) bu sprintle birlikte ilk kez fiilen tetiklenebilir
  hale gelmişti. Dört fonksiyona `document_id IS NOT NULL` kilidi eklendi (tek merkezden hem web
  hem mobili kapsıyor); Selin'in savunma-derinliği önerisiyle `stock_update_purchase()`/
  `stock_update_sale()`'e de aynı kilit eklendi. `mobile/purchase.php`/`mobile/sales.php` listeleri
  de web ile aynı "🧾 Belgeyi Aç" davranışına getirildi.
- Migration YOK — `finance_movements.document_id` kolonu zaten mevcuttu. Eski veriye (backfill,
  toplu UPDATE, `ALI-20260707-5177` dahil) hiç dokunulmadı.
- Test: yerel MariaDB sandbox'ta 6 zorunlu senaryo (hızlı satış, hızlı alış, trade satış belgesi,
  trade alış belgesi, onaysız-red/onaylı-kabul negatif stok, zorunlu hatada tam rollback) + ayrıca
  document_id kilidinin edit/delete'i doğru reddettiği ve normal (belgesiz) kayıtlarda regresyon
  olmadığı doğrulandı. Ece/Selin/Elif paralel incelemeden geçti (üçü de en az bir gerçek bulgu
  verdi, hepsi aynı turda düzeltildi).
- Commit: `d518103`. USER TEST: DEV/primac.tr'de kullanıcı testi BEKLİYOR — bkz. [[backlog]]
  "Flow Unification 001 DEV PASS takibi açık" (8 senaryo: belge oluşturma her iki tip, belgeye
  ödeme girme, stok fazlası belge denemesi, finans yetkisiz kullanıcı, eski belgeye dokunulmadığı,
  Son Alışlar/Satışlar'da belge rozeti/link, cari ekranında Alış/Satış Belgeleri bölümü).

## Kontrollü Negatif Stok Politikası: CLOSED (WEB) (2026-07-11)
USER TEST: Web PASS (2026-07-11) / Mobile Pending → Mobile Regression Sprint'e eklendi (bkz. backlog.md).

**KONTROLLÜ NEGATİF STOK POLİTİKASI artık sistem standardıdır. Varsayılan davranış satışın reddi
değil, bilinçli kullanıcı onayıdır. Onay backend tarafından doğrulanır; yalnızca istemci tarafı
uyarısına güvenilmez.**

**Negatif stok yasak DEĞİLDİR. Yetersiz stokta kullanıcı onayı zorunludur.** Bu davranış, satın
alma öncesi satış/sipariş açılabilmesi için bilinçli olarak tasarlanmıştır (örn. müşteri siparişi
alınır, ürün henüz satın alınmamıştır — satış önce girilir, alış sonra tamamlanır).

Önceki tur (aynı gün): kullanıcı satış düzenleme/silme akışlarının bir denetimini istedi (stok
etkisi tam geri alınıyor mu, negatif stok engeli var mı, COGS/kârlılık bozuluyor mu — bkz.
bugs.md). Denetim sonucu sert bir negatif-stok REDDİ eklendi (commit `b536494`). Kullanıcı DEV
testinde bu ret davranışının gerçek bir iş akışını (satın alımdan önce satış siparişi girme)
engellediğini fark edip kararı revize etti — sert ret geri alındı (commit `e330b99`), yerine
**bu maddedeki kontrollü onay akışı** kondu.

- `stock_lib.php::stock_shortage_info($currentStock,$reserveQty,$requestedQty)` — merkezi karar
  noktası, REDDETMEZ, sadece `available_stock`/`resulting_stock`/`requires_confirmation` hesaplar.
- `stock_lib.php::StockShortageException` — stok yetersiz + onaysız durumda fırlatılır, hangi
  ürün(ler)de ne kadar açık olduğunu (`$shortages`) taşır.
- `stock_sale_build_lines()` ve `stock_update_sale()` artık `$confirmed` parametresi alıyor —
  `$_POST['allow_negative_stock']=='1'` ise satışa izin verilir (stok negatife düşebilir),
  aksi halde `StockShortageException` fırlatılır. **Onay SADECE backend'de zorunlu kılınıyor**
  (frontend JS uyarısı değil) — bu parametre olmadan istek gönderilirse backend reddeder.
- Düzenlemede eski miktar "rezerve" sayılır (mevcut stok + geri alınacak eski miktar) — aksi
  halde geçerli bir düzenleme (örn. stok 2, eski satış 5, yeni satış 6 → kullanılabilir 7) bile
  yanlışlıkla uyarı üretirdi.
- Görünürlük (migrationsız, düşük riskli): onaylı bir yetersiz-stok satışının açıklamasına
  (`finance_movements.description`) otomatik " ⚠️ Stok Yetersiz (Onaylandı)" eklenir — yeni kolon
  AÇILMADI, mevcut açıklama alanı kullanıldı (Son Satışlar listesinde zaten görünüyor).
- `sales.php`/`mobile/sales.php`: stok yetersizse aynı sayfada uyarı kutusu + "onaylıyorum"
  onay kutusu gösterilir, kullanıcının az önce girdiği değerler (DB'den değil, POST'tan) forma
  yeniden doldurulur — onaylayıp tekrar gönderdiğinde işlem tamamlanır.
- Silme (`stock_reverse_sale`) etkilenmedi — negatif stoklu bir satış da dahil, silme her zaman
  stoğu tam ve atomik geri yükler.
- Test: 8 hedefli senaryo (yeterli/tam sınır/yetersiz-onaysız-red/yetersiz-onaylı-kabul, hem
  oluşturma hem düzenleme için, silme dahil) yerel MariaDB sandbox'ta PASS. Ece/Selin review'dan
  geçti — Selin ilk yazımda KRİTİK bir hata buldu: onay checkbox'ı `<form>` etiketinin DIŞINDAydı,
  kullanıcı onaylasa bile hiçbir zaman POST edilmiyordu (özellik fiilen çalışmıyordu); checkbox
  form içine taşınıp yeniden test edildi.
- Commit: `3d927c7`. USER TEST (2026-07-11, DEV/primac.tr): Web PASS — doğrulanan tüm maddeler:
  yeterli stokta normal devam, yetersiz stokta onaysız ilk denemede red, onaylı denemede kabul +
  kontrollü negatif stok, açıklamada görünür uyarı, silmede stok tam iadesi, mevcut sale-edit/
  Finance Core akışlarında regresyon yok. Mobil testi henüz yapılmadı → Mobile Regression
  Sprint'e eklendi (bkz. backlog.md); kod incelemesinde web/mobil parite PASS aldığı için bu
  karar mobil testi beklemeden verildi.

## Finance Core Stabilization — Satış/Alış Artık Ödeme Yapmıyor: CLOSED (WEB) (2026-07-11)
USER TEST: Web PASS (2026-07-11) / Mobile Pending → ayrı Mobile Regression Sprint (bkz. backlog.md).

Kök kural: satış ekranı tahsilat yapmaz, alış ekranı ödeme yapmaz — ikisi de sadece cariye/
tedarikçiye açık borç oluşturur (durum her zaman "Bekliyor", account_id her zaman NULL, kasa/
banka/kart hiçbir zaman etkilenmez). Borcun kapanması SADECE Tahsilat/Ödeme ekranından olur.
Migration YOK bu sprintte — sadece kod değişti.

- **Kök neden düzeltmesi (session başından beri süregelen "cari bakiye double-counting" bug'ı):**
  aynı ekonomik olayın (bir satış VE onun sonradan girilen tahsilatı) ikisi de finance_movements'ta
  aynı yönde (`direction='in'`) toplandığı için cari açık bakiye olduğundan yüksek görünüyordu —
  bu oturumun başında "Yasmin Gelişim Merkezi" carisinde somut bir olayla ortaya çıkmıştı.
  `contacts_lib.php::contact_balance_case_sql()` ile kalıcı çözüldü: satış/alış kendi yönüyle,
  Tahsilat/Ödeme TERS işaretle sayılıyor (biri borç açar, diğeri kapatır). `contact_view.php`,
  `mobile/contact_view.php`, `contacts.php`, `contacts_report.php`, `mobile/contacts_report.php`,
  `report_lib.php` (cari listesi + tekil ekstre) artık hepsi bu tek formülü kullanıyor — daha
  önce sadece `contact_view.php` düzeltilip diğerleri unutulmuştu, kod incelemesinde (Ece) bulunup
  aynı turda tamamlandı.
- `sales.php`/`mobile/sales.php` + `purchase.php`/`mobile/purchase.php`: ödeme yöntemi seçimi
  tamamen kaldırıldı. `purchase.php`'ye İLK KEZ "Son Alışlar" listesi + Düzenle/Sil eklendi
  (`stock_lib.php`: `stock_reverse_purchase`/`stock_can_edit_purchase`/`stock_update_purchase`,
  `sales.php`'nin migration-043 edit altyapısıyla simetrik). Ağırlıklı ortalama maliyet (avg_cost)
  için güvenlik kapısı: bu alıştan sonra aynı üründe başka stok hareketi varsa hem düzenleme HEM
  silme reddedilir (ilk yazımda sadece düzenleme korunuyordu — Selin/ots-security-auditor'ın
  bulduğu asimetri, `stock_purchase_avg_cost_safe()` ortak fonksiyonuyla kapatıldı).
- `finance_lib.php::finance_movement_type_label()` — Satış/Alış/Satış Belgesi/Alış Belgesi
  kaynaklı otomatik kayıtlar artık Tahsilat/Ödeme diye etiketlenmiyor (`finance.php`,
  `mobile/kasa.php`, `report_lib.php`). Dashboard/finans toplamları (`dashboard.php`,
  `finance.php`, `mobile/kasa.php`, `report_lib.php`) artık sadece `account_id IS NOT NULL`
  (gerçek kasa/banka hareketi) satırlarını sayıyor.
- Test: yerel MariaDB sandbox'ta 13 zorunlu senaryo (satış→tahsilat, alış→ödeme, eski peşin
  satışı veresiyeye çevirme, alış miktar düzenleme) + ayrı bir güvenlik-kapısı testi — hepsi PASS.
  Review: Ece/Selin/Elif (ots-code-reviewer/ots-security-auditor/ots-parity-auditor); ilk turda
  bulunan 3 sorun (`purchase.php`'nin try/catch dışı silme bloğu, alış silme-düzenleme avg_cost
  asimetrisi, cari listesi/raporundaki eksik formül) düzeltilip yeniden test edildi.
- Commit: `d02665b` (Finance Core ana commit). Aynı sprint içindeki önceki ilişkili işler:
  `871f63e` (satış düzenleme özelliği + migration 043), `8292583` (migration 042,
  settles_movement_id altyapısı — bu sprintte KULLANILMADI, "satış kaydı değişmeden kalır" kararı
  yüzünden gerek kalmadı), `c363727` (İş 3, migration'sız kısmi önlemler), `d3252b2` (İş 2,
  trade_document_new.php + web-mobil parite).
- Açık kalan: migration 042/043 DEV PASS takibi ve mobil doğrulama → [[backlog]].

## Görev Detayı — "Çek / Senet Bilgileri" Kartı: CLOSED (2026-07-07)
Kullanıcı isteği: takvimdeki Çek/Senet Vadesi görev kartının UX'i yetersizdi — tür/numara/cari/
banka/tutar/vade/durum tek bakışta görünmüyordu, ekran "finans ekranının küçük bir özeti gibi"
davranmalıydı. Görev sistemine/takvime/route resolver'a dokunulmadan, mevcut görev kartının
hemen altına salt-okunur bir özet kart eklendi.

- **İlişki**: `checks_notes.task_id → tasks.id` (migration 027'den beri var olan TEK yönlü bağ;
  `tasks` tablosunda tersi bir referans yok, başlık/açıklama parsing'i kullanılmadı). Yeni
  `checks_notes_get_by_task($pdo,$taskId)` (`checks_notes_lib.php`) — mevcut `checks_notes_get()`
  ile birebir aynı desen, sadece `WHERE cn.task_id=?`.
- **Güvenlik gate'i (kritik karar)**: `task_view.php`/`mobile/task_view.php`, `job_view.php` ile
  aynı desende BİLİNÇLİ olarak `page_module_map()` dışında/korumasız (personel bildirimden kendi
  görevini açabilsin diye). `checks_notes_lib.php`'deki 2026-07-02 tarihli önceki bir güvenlik
  denetimi notu tam olarak bu yüzden cari/banka/tutar gibi hassas alanların görev açıklamasına
  YAZILMAMASI gerektiğini söylüyor ('tasks' yetkisi olup 'finance' yetkisi olmayan biri de bu
  ekranı açabiliyor). Yeni kart bu kısıtı ihlal ETMEDEN eklendi: `$canSeeFinance =
  user_can('finance')` + `$cn = $canSeeFinance ? checks_notes_get_by_task(...) : null` — finans
  yetkisi olmayan kullanıcıda sorgu bile çalışmıyor, kart hiç render edilmiyor (mevcut
  `$canEdit`/`$canReassign` yerel yetki değişkeni deseniyle birebir tutarlı bir "reusable" desen).
- **Alanlar**: tür, çek/senet no, cari (contact_id join), banka, tutar, vade, portföy durumu
  (`checks_notes_statuses($direction)` — yön-duyarlı etiket, sabit metin KULLANILMADI), açıklama
  (`cn.notes`), "Finans Kaydına Git" (finance_movement_id NULL ise mevcut "⚠️ Finans hareketi
  oluşturulamadı" konvansiyonu tekrar kullanıldı, yeni bir şey icat edilmedi).
- **"Finans Kaydına Git" hedefi — bilinçli platform asimetrisi**: mobilde zaten var olan
  `check_note_view.php?id=` kullanıldı (değişiklik yok). Webde tekil bir çek/senet görüntüleme
  sayfası hiç yoktu (sadece `checks_notes.php` listesi + satır-içi gizli düzenleme) — kullanıcı
  onayıyla yeni bir detay sayfası AÇILMADI, bunun yerine `checks_notes.php`'ye küçük/additive bir
  `?open=<id>` desteği eklendi (satırın düzenleme alanını otomatik açar, sayfayı o satıra
  kaydırır, kısaca sarı highlight yapar). Kod tekrarını önlemek için durum-renk eşleme mantığı
  `checks_notes_lib.php`'ye `checks_notes_status_tone()` olarak taşındı (hem `checks_notes.php`
  hem `task_view.php` kullanıyor, ots-code-reviewer bulgusu üzerine).
- Migration YOK — hiçbir yeni tablo/kolon gerekmedi, `checks_notes` (024/026/027/033/034) zaten
  tüm alanlara sahipti.
- Teknik inceleme: Ece (ots-code-reviewer) ve Selin (ots-security-auditor) ile paralel — ikisi de
  PASS (Ece'nin bulduğu tek küçük kod-tekrarı bulgusu anında düzeltildi).
- **CLOSED (2026-07-07, commit `b3e0def`, USER TEST: PASS)** — primac.tr'de (DEV) test edildi:
  finans yetkili kullanıcıda kart görünüyor, "Finans Kaydına Git" doğru kaydı açıp highlight
  ediyor, normal görev detayları etkilenmedi, mobil görünüm sorunsuz, genel kullanımda hata
  görülmedi. `acanstr.com/ots` (PROD) bu turda DOKUNULMADI — ayrı "DEPLOY MODE" komutu bekliyor.

## "İşlerim" — Düzenle/Detay/Sil (soft delete) + yorum/dosya/geçmiş (2026-07-04)
Kullanıcı isteği: task kartlarına Düzenle/Detay/Sil eklenmesi (web+mobil). Yeni ortak
`tasks_lib.php` (web+mobil paylaşımlı iş mantığı, CLAUDE.md kural 5):
- **Migration 040**: `tasks` tablosuna `created_by`/`updated_by`/`deleted_at` eklendi + yeni
  `task_comments`/`task_files` tabloları (job_files ile aynı upload deseni, `uploads/task_files/`,
  mevcut `uploads/.htaccess` script-engelleme kuralı otomatik kapsıyor).
- **Soft delete**: `task_soft_delete()` — hiçbir görev fiziksel silinmiyor, `deleted_at IS NULL`
  filtresi `mytasks.php`/`mobile/mytasks.php`/`tasks.php`/`mobile/tasks.php`'nin tüm SELECT'lerine
  eklendi. `sil.php`'nin `'task'` dalı da (Tüm Görevler ekranından silme) artık soft-delete'e
  yönlendiriliyor (fiziksel `DELETE FROM tasks` kaldırıldı — daha önce hem `sil.php` hem
  `mobile/tasks.php`'nin kendi inline'ı hard-delete yapıyordu, artık ikisi de aynı ortak fonksiyona
  bağlı). **Bilinen istisna**: bir `jobs` kaydı `sil.php` ile silinirse, o işe bağlı `tasks`
  satırları hâlâ (job silme akışının children temizliği üzerinden) fiziksel siliniyor — bu, iş
  silme akışının kendi kapsamı olduğu için bu turda DEĞİŞTİRİLMEDİ (bkz. backlog).
- **Yetki**: `task_can_edit()`/`task_can_delete()` — admin, `edit_delete` yetkili, görevi oluşturan
  YA DA göreve atanan personelin kendisi. `task_can_reassign()` daha dar — "Atanan Personel" alanını
  sadece admin/edit_delete/oluşturan değiştirebilir (IDOR'a karşı, sıradan atanan kişi başkasına
  devredemez). `mobile/task_view.php`'de daha önce durum güncellemesi (task_status) hiç sahiplik
  kontrolü yapmıyordu (herhangi bir girişli kullanıcı `?id=` tahmin edip başka birinin görevini
  değiştirebilirdi) — düzeltildi, artık `task_can_edit()` şart.
- **Detay ekranı**: `task_view.php` (web, YENİ) + `mobile/task_view.php` (var olan dosya
  genişletildi) — geçmiş/hareket kaydı (`activity_recent(50,'task',$id)`, var olan
  `activity_lib.php` — yeni log sistemi kurulmadı), yorumlar (`task_comments`), dosyalar
  (`task_files`, job_files ile aynı beyaz liste/boyut limiti), oluşturan/son güncelleyen kullanıcı
  adı. GET erişimi bilinçli olarak `job_view.php` ile aynı desende KORUMASIZ bırakıldı (boot.php'de
  zaten belgelenen "personel bildirimden kendi görevini açabilsin" kararı) — sadece YAZMA işlemleri
  (durum/düzenle/sil/yorum/dosya) sahiplik kontrollü.
- **Mobil UX Standardı** (PROJECT_RULES.md) uygulandı: `mobile/mytasks.php` kart listesinde
  Düzenle/Sil YOK, sadece "👁 Detay" → `task_view.php` (tekil aksiyonlar orada). Web'de bu kısıt
  yok (mevcut `tasks.php` zaten liste içi inline düzenleme kullanıyordu), `mytasks.php` (web)
  kartlarına Düzenle (inline toggle panel) + Sil + Detay eklendi.
- `task_new.php`/`mobile/task_new.php`/`mytask_new.php`/`mobile/mytask_new.php` artık `created_by`
  dolduruyor (yeni kolonun anlamlı veri taşıması için gerekliydi).
- Kapsam dışı bırakılanlar (bilinçli, scope disiplini): `jobs.php`/`personnel_view.php`'deki görev
  sayaçları ve `dashboard.php`/`kpi.php`/`report_lib.php`/`gunluk_rapor.php`/`daily_reminder_lib.php`/
  `takvim.php`/`mobile/calendar.php`/`personnel.php`/`mobile/personnel.php`/`mobile/profile.php`
  gibi diğer `tasks` sayaç/rapor sorguları `deleted_at IS NULL` filtresi almadı — bu dosyalara
  DOKUNULMADI (paralel ajan çakışması riski + talimatta açıkça "dokunma" denen ikisiyle aynı
  muamele). Soft-silinen bir görev bu ekranlarda sayıya dahil olmaya devam edebilir — takip için
  bkz. `memory/backlog.md`.

## Personel kartları + sekmeli detay (web) (2026-07-04)
Kullanıcı yanlış izlenimi ("personel iki modülde yönetiliyor") netleştirildi: `layout_top.php`'deki
"🧭 Personel İş Takip Yönetimi" grubu personel YÖNETMİYOR, şirketin tüm iş/üretim takip sayfalarını
(jobs/tasks/production/assembly/design/work_center vb.) barındırıyor — gerçek personel yönetimi tek
yer (`personnel.php`/`personnel_new.php`/`personnel_edit.php`). Bu menü grubunun adı yanıltıcı
olduğu için "🧭 İş / Üretim Yönetimi" olarak değiştirildi, altındaki linkler DEĞİŞMEDİ.
- **`personnel.php`**: Tablo görünümü modern kart görünümüne çevrildi. Her kartta baş harf
  rozeti (fotoğraf/photo kolonu şemada yok), ad/rol, aktif/pasif rozeti, telefon/e-posta, Bugünkü
  Görev (`tasks.due_date=CURDATE()`) ve Açık Görev (`tasks.status!='Tamamlandı'`) sayaçları, ve
  Detay/Görevler/Mesaj Gönder (bağlı kullanıcı hesabı varsa)/Performans (kpi.php) butonları var.
- **`personnel_edit.php`** (bu projede ayrı bir web `personnel_view.php` yok — view+edit tek
  dosyada birleşik): mini-ERP mantığında sekmeli hale getirildi — Genel Bilgiler (mevcut form,
  değişmedi), Görevler (mevcut sorgu, salt-okunur), Takvim (jobs.responsible_personnel_id +
  tasks.due_date, filtrelenmiş, YENİ ama sadece mevcut desenlerin birleşimi), Mesajlar
  (messages.php?u= linki, personelin bağlı app_users hesabı varsa), Notlar (personal_notes
  WHERE user_id=bağlı hesap — hesap yoksa "yok" mesajı), Dosyalar (CV görüntüle/kaldır — CV
  yükleme/değiştirme hâlâ Genel Bilgiler formunda, tek form olduğu için ayrılmadı), Performans
  (kpi.php'ye link, kpi.php'ye dokunulmadı), Maaş/Avans/Prim (finance_movements WHERE
  personnel_id=X, salt-okunur), Giriş Hesabı (mevcut "🔐 Yetkiler" panelinin taşınmış hali,
  sadece `user_can('users')`), Hareket Geçmişi (mevcut `activity_user_html()`, değişmedi). Sekme
  URL'de `?tab=` whitelist ile seçiliyor, JS gerekmedi.
- **Bilinçli kapsam dışı** (bkz. `ROADMAP.md` 2026-07-04): İzinler sekmesi (şema yok), Departman
  alanı (kolon yok), gerçek fotoğraf yükleme (cv_path CV/belge alanı, fotoğraf değil) ve **mobil
  parite** (bu tur açıkça web'e sınırlandırıldı, `mobile/personnel_view.php` paralel bir güvenlik
  sprintinde aktif değiştiği için dokunulmadı — CLAUDE.md kural 7 bu madde için henüz karşılanmadı,
  ayrı bir onaylı tur gerekiyor).
- Test: `php -l` tüm değişen dosyalarda temiz. Tüm yeni sorgular `personnel_id`/`user_id` ile
  prepared statement filtreli (başka personelin verisi karışmıyor). Mevcut POST akışları (profil
  kaydet, CV yükle/kaldır, yetki kaydet, telegram — dokunulmadı) davranışsal olarak değişmedi.

## Global arama: "personel aranmıyor" kök neden + kapsam genişletme (2026-07-04)
Kullanıcı şikayeti: "personel aranmıyor". İnceleme: kolonlar (`role`/`work_type`), `user_can('personnel')`
kontrolü ve web+mobil render kodu ZATEN doğruydu (2026-07-02'de düzeltilmişti) — kök neden farklıydı:
kullanıcı muhtemelen "personel" kelimesinin KENDİSİNİ yazıyordu (bir isim değil, modül adı), ve bu
kelime hiçbir personel kaydının `name/role/work_type/phone/email` alanında geçmediği için 0 sonuç
dönüyordu. "çek"/"teklif"/"belge"/"rapor" için zaten var olan "modül adı yazılırsa son kayıtlar
listelensin" deseni personelde yoktu — `search_lib.php`'de aynı desen personele de eklendi
(`personelModuleMatch`).
- Kapsam ayrıca genişletildi (kullanıcı isteği): **Görevler** (`tasks` tablosu, `user_can('tasks')`),
  **Dosyalar** (`job_files`, `user_can('jobs')` — mevcut `documents` anahtarından ayrı tutuldu, çünkü o
  sadece `trade_documents`/ticari belgeleri kapsıyor), **Kullanıcılar** (`app_users`,
  `user_can('users')`), **Notlarım** (`personal_notes`, modül izni yok — `notes.php` gibi sadece
  `user_id=?` sahiplik filtresiyle korunuyor, IDOR'a kapalı), **Mesajlar** (`internal_messages`,
  modül izni yok — 1-1 mesajlarda gönderen/alıcı, grup mesajlarında `chat_thread_members` üyeliği
  kontrolü, IDOR'a kapalı).
- "Satın Alma"/"Tahsilat"/"Ödeme" için AYRI bölüm eklenmedi — kod incelemesinde bunların zaten
  `finance_movements` (mevcut `movements` anahtarı) üzerinden dolaylı arandığı doğrulandı
  (`stock_lib.php: stock_add_purchase_finance()`, `collection.php` hep bu tabloya yazıyor).
- Değişen dosyalar: `search_lib.php` (tüm yeni sorgular + kök neden düzeltmesi), `search.php` (web
  render + yeni bölümler), `mobile/search.php` (mobil render + yeni bölümler). `boot.php`,
  `mobile/personnel_view.php`, `notes.php`, `notes_lib.php`, `mytasks.php`, `mobile/mytasks.php`,
  `tasks.php`, `messages.php` gibi paralel sprint kapsamındaki dosyalara dokunulmadı (sadece referans
  için okundu).
- **Bilinen tutarsızlık (düzeltilmedi, kapsam dışı)**: `mobile/users.php` giriş kontrolü
  `$isAdmin`'e bağlı (admin/yönetici rolü), web tarafı ise `page_module_map()` üzerinden
  `user_can('users')` ile çalışıyor — admin olmayan ama `users` yetkisi verilmiş bir kullanıcı arama
  sonucunda "Kullanıcılar" bölümünü görebilir ama mobilde `mobile/users.php`'ye tıklayınca
  `index.php`'ye yönlendirilir. Bu, arama özelliğinin bir yan etkisi değil, önceden var olan bir
  parite/yetki çakışması (Elif/`ots-parity-auditor` kapsamı) — bilinçli olarak dokunulmadı.

## Takvim'e atanan görevler (tasks) eklendi (2026-07-03, 3. tur)
Kullanıcı şikayeti: "görev atadım kendime, takvime işlemedi." İnceleme: `takvim.php` (web) ve
`mobile/calendar.php` sadece `jobs` (termin tarihi) ve `personal_notes` (Notlarım) kaynaklarını
gösteriyordu — `tasks` tablosu (Görevler modülü, `task_new.php` ile atanan) hiç dahil değildi.
- Her iki dosyaya da `tasks` sorgusu eklendi: admin tüm görevleri, personel sadece
  `personnel_id IN (SELECT id FROM personnel WHERE user_id=...)` ile kendine atananları görür
  (jobs sorgusuyla aynı desen). Takvimde 🎯 ikonuyla ayrı gösteriliyor.
- **Parite/yetki tuzağı**: `tasks.php` (web) `page_module_map()`'te `'tasks'` modülüne bağlı —
  bu yetkisi olmayan personel oraya tıklarsa 403 görür. Web'de "kendi görevlerim" için ayrı bir
  sayfa yok (mobilde `mytasks.php` var, web'de karşılığı yok — bu ayrı bir backlog maddesi,
  bkz. [[backlog]]). Geçici çözüm: web takviminde `user_can('tasks')` yoksa görev maddesi
  tıklanamaz düz metin olarak gösteriliyor (kaybolmuyor ama 403'e de düşürmüyor). Mobilde ise
  zaten `mytasks.php` olduğu için `user_can('tasks')` yoksa oraya yönlendiriliyor — sorunsuz.
- Not: "telefonun kendi (native) takvimi" (iOS/Android Takvim uygulaması) ile senkronizasyon HİÇ
  yok ve bu değişiklikle de eklenmedi — sadece uygulamanın KENDİ takvim sayfası güncellendi. Native
  cihaz takvimine senkron (ICS/webcal export) ayrı, daha büyük bir özellik kararı — kimlik
  doğrulamalı bir abonelik linki gerektirir, kullanıcıya danışılmadan yapılmadı.

## Kişisel Not/Görev alanı — "Notlarım" (2026-07-03)
Kullanıcı isteği: "görevlerim ekranında kendime de görev-not alanı olsun, bunu personel görmesin,
takvime de işlensin, bana kendi numarama ve sistem içi mesaj ile bildirim olsun."
- Migration 037: `personal_notes` tablosu (id, user_id, title, note, due_date, status, created_at).
  Bilerek `tasks` tablosundan AYRI — tasks.php ("Tüm Görevler") personel tarafından görülebiliyor,
  bu yeni tabloya HİÇBİR sorgu `user_id` filtresiz dokunmuyor, gizlilik ayrı tabloyla garanti altına
  alınıyor (retrofit yetki kontrolü riski yok).
- `notes_lib.php` (yeni, web+mobil ortak): `personal_note_create()` — kayıt oluşturunca otomatik
  olarak (a) `notify_user()` ile sistem içi bildirim + Web Push, (b) `internal_messages`'a kendine
  mesaj (Mesajlar ekranında da görünsün), (c) `wa_send()` ile GERÇEK otomatik WhatsApp (mevcut
  `share_lib.php::wa_send()` — UltraMsg/custom gateway, `wa_settings.php`'de ayarlıysa; ayarlı
  değilse sessizce atlanır, hata değildir) kendi numarasına (personel.phone → yoksa app_users.phone).
- Web: `notes.php` (tam CRUD sayfası, contact_new.php ile aynı PRG deseni) + `dashboard.php`'de
  kompakt önizleme paneli + sol menüde "📝 Notlarım" linki (Komuta Merkezi'nin hemen altında).
- Mobil: `mobile/mytasks.php` ("Görevlerim") içine gömülü not ekleme/listeleme/tamamlama/silme —
  kullanıcının literal isteğiyle birebir aynı ekranda.
- Takvim entegrasyonu: `takvim.php` (web) + `mobile/calendar.php` — notlar sadece kendi `user_id`'ne
  ait olduğu için (JOIN/filtre yok, ayrı sorgu + PHP tarafında birleştirme) başka hiçbir kullanıcının
  takviminde görünmüyor.

## 5-ajan güvenlik denetimi — CONFIRMED yetki-atlatma açıkları kapatıldı (2026-07-03)
Selin (ots-security-auditor) + Elif (ots-parity-auditor) denetiminde bulunan 4 CONFIRMED açık:
- **job_view.php + mobile/job_view.php [KRİTİK]**: GÖRÜNTÜLEME (GET) bilinçli olarak
  `page_module_map()` dışında bırakılmıştı (personel bildirimden kendi işini açabilsin diye), ama
  YAZMA işlemleri (stage_status, job_status, dosya yükle/sil, not ekle/sil, save_job, init_stages,
  stage_set, produce_stock, link_produce, mobilde ayrıca assign/set_status) hiç yetki kontrolü
  yapmıyordu — `jobs` yetkisi olmayan biri URL'den id tahmin edip başka birinin işini
  değiştirebiliyordu. Çözüm: `job_stages_lib.php`'ye ortak `job_can_write($pdo,$jobId)` eklendi —
  `user_can('jobs')` YA DA iş `responsible_personnel_id` alanında o kullanıcının personeline
  atanmışsa true döner (GET tarafındaki "kendi işini aç" mantığıyla tutarlı). Hem web hem mobil
  POST bloklarının başına bu kontrol eklendi; AJAX aşama butonları da aynı kontrolden geçsin diye
  `stage_ajax_respond()` içine taşındı (tek merkezden, iki call-site de otomatik korunuyor).
  Mobildeki `assign` (sorumlu değiştirme) action'ı için ekstra daraltma: own-job istisnası
  YETERLİ SAYILMADI, sadece gerçek `jobs` yetkisi olanlar sorumlu değiştirebilir (UI zaten sadece
  admine gösteriyordu, sunucu tarafı artık da eşleşiyor). `mobile/job_view.php`'deki admin-only
  `delete_job` bloğuna dokunulmadı.
- **requests.php (web) [KRİTİK]**: Hiç korumasızdı (sadece `require_login()`), `mobile/requests.php`
  admin-only (`block_personel()`). Web'e `is_admin()` kontrolü eklendi — parite sağlandı.
- **activity.php (web) [YÜKSEK]**: `user_can()` filtresi yoktu, Finans dahil TÜM modüllerin
  aktivite logu herkese açıktı. `mobile/activity.php` admin-only. Web'e aynı `is_admin()` koruması
  eklendi (en basit/tutarlı çözüm — modül bazlı filtre yerine).
- **contact_documents.php [YÜKSEK]**: `id` GET parametresiyle herhangi bir carinin trade_documents
  (tutar/ödenen) kayıtlarını IDOR ile gösteriyordu, hiç yetki kontrolü yoktu. `require_permission
  ('contacts')` eklendi + `boot.php` `page_module_map()`'e `'contact_documents.php'=>'contacts'`
  eklendi (savunma derinliği — otomatik merkezi korumaya da dahil).
- Tüm değişen dosyalar `php -l` ile doğrulandı. PHP 7.2 uyumlu, prepared statement, web+mobil parite
  korundu — hiçbir görüntüleme (GET) akışı kısıtlanmadı, sadece yazma/POST işlemlerine kilit eklendi.

## Satış/Satın Alma sepeti + KDV + personel yetki senkronu + WhatsApp medya (2026-07-03, 2. dalga)
- **Satış/Satın Alma → sepet mantığı**: Tek ürün yerine tek işlemde birden fazla ürün satırı
  (kullanıcı: "bir kişiye bir firmaya birden fazla ürün satılabilir"). Her satırın kendi KDV oranı
  var (stok kartındaki `vat_rate` otomatik doluyor). `finance_movements`'a `vat_rate`/`vat_amount`
  eklendi (migration 032). `stock_reverse_sale()` artık bir finans hareketine bağlı birden fazla
  stok hareketini geri alabiliyor. Sepet işlemleri artık **transaction** içinde (agent code review
  bulgusu: çoklu ürüne geçince yarım-işlem riski doğmuştu, kapatıldı).
- **Personel yetki senkronu (mobil)**: `block_personel()` artık `$module` parametresi alıyor,
  `user_can($module)` true ise admin olmayan da girebiliyor — kullanıcı onayıyla personnel.php/
  personnel_view.php/personnel_new.php/kpi.php → 'personnel', task_new.php → 'tasks', report.php
  → 'report' açıldı. `mobile/more.php` menü kartları da senkron edildi (yoksa "erişilebilir ama
  görünmez" hatası oluşurdu — bkz. [[backlog]] eski not, artık geçersiz).
- **Güvenlik**: `personnel_edit.php` (maaş/IBAN) hiç yetki kontrolüne sahip değildi — kapatıldı.
  `wa_upload_media()` ilk halinde her uzantıyı kabul ediyordu — beyaz liste eklendi.
- **Cari ekstre**: contact_view.php'ye "Bu Cariye Ait İşler" tablosu (web), `report_lib.php`
  cari_detay'daki iş satırı sütun karışıklığı düzeltildi, koyu rapor kartlarında okunmayan yazı
  rengi düzeltildi. contacts.php'nin kendine link veren "Toplam Bakiye" kartı contacts_report.php'ye
  yönlendirildi.
- **Teklif ekranı**: Kalemler artık başlıklı tablo (web+mobil), form-grid düzeni.
- **Arama**: "teklif" yazınca 0 sonuç dönme sorunu (çek/senet ile aynı desen uygulandı), mobil
  arama kutusunun flex taşması düzeltildi.
- **WhatsApp + iç mesajlaşma**: `emoji_picker_html()` ortak emoji seçici (web+mobil, wa_send_now.php
  + messages.php). `wa_send_media()`/`wa_upload_media()` ile WhatsApp'tan dosya/video/ses gönderimi
  (UltraMsg medya uç noktaları; bilinmeyen gateway'de linke düşer).
- Kullanım kılavuzu PDF'leri (ACANS/PRIMAC, `~/Desktop/REFERANS/`) v2.1'e güncellendi.

## Büyük dalga sonrası kritik düzeltmeler (2026-07-03)
Bu oturumda paralel çalışan çok sayıda ajanın ürettiği özellikler (Görevler, Satın alma raporu,
Satış+hızlı ekleme, İş/Personel mobil silme, Muhasebe+kasa gizleme, Audit log+oturum, Mobil
offline+barkod, WhatsApp teklif onayı, Dashboard trend) güvenlik+kod incelemesinden geçirildi.
Bulunan KRİTİK sorunlar (hepsi bu commit'te düzeltildi):
- **`boot.php` oturum zaman aşımı hiç çalışmıyordu** (dead code) — last_activity her istekte
  koşulsuz yenileniyordu, timeout kontrolü hep ~0 fark görüyordu. Yenileme artık SADECE
  `require_login()`'in timeout kontrolünden SONRA yapılıyor.
- **`boot.php` `session_destroy()` sonrası `$_SESSION` temizlenmiyordu** — aynı istekte "çıkış"
  fiilen etkisizdi (PHP'nin session_destroy() davranışı, array'i elle boşaltmak gerekiyor). `$_SESSION=[]` eklendi.
- **`mobile/personnel_view.php` + `sil.php`: silinen personelin `app_users` hesabı aktif kalıyordu**
  — kişi sistemden silinse bile kullanıcı adı/şifresiyle (veya "beni hatırla" çereziyle) giriş
  yapmaya devam edebiliyordu. Personel silinmeden önce bağlı hesap artık `active=0` yapılıyor.
- **`accounting_lib.php` bakiye geri-alma işaret hatası** — muhasebe kaydı düzenlenirken/silinirken
  eski etki geri alınacağına AYNI YÖNDE tekrar uygulanıyordu (gelir sildikçe bakiye artıyordu).
  Yön düzeltildi (`finance_lib.php`'deki doğru referans desenle uyumlu hale getirildi).
- **`mobile/stock.php` geçersiz PHP sözdizimi** (`'BarcodeDetector' in window` JS operatörü PHP'ye
  sızmış) — TÜM stock.php sayfasını tüm kullanıcılar için kırıyordu (Fatal parse error). Düzeltildi,
  buton her zaman basılıp görünürlüğü JS'e bırakıldı.
- **`task_new.php` geçersiz PHP sözdizimi** (`??')===` — tırnak/parantez kayması) — tüm sayfayı
  kırıyordu. Düzeltildi.
- **`mobile/sw.js` cache-first stratejisi TÜM `.php` sayfalarını (bildirim/mesaj sayısı, bakiye
  içeren dinamik içerik dahil) önbellekten dönüyordu** — kullanıcı sinyali olsa bile hep ESKİ veri
  görüyordu. Sadece gerçek statik varlıklar (js/css/görsel/icon.php/manifest.php) cache-first kaldı,
  dinamik `.php` sayfaları network-first'e çevrildi (cache sadece offline fallback). Versiyon v27.
- **`stock_lib.php` `stock_reverse_sale()` satış silmede YANLIŞ stok hareketini buluyordu**
  ("aynı gün + en son eklenen" tahmini — aynı gün birden fazla satışta yanlış ürünün stoku
  bozulabiliyordu) VE finans kaydı asla silinemiyordu (`finance_movement_delete()` 'sale'/
  'mobile_sale' tipini kasıtlı reddediyor). Migration 030: `stock_movements.finance_movement_id`
  kesin referans kolonu eklendi, `sales.php`/`mobile/sales.php` artık önce finans kaydını oluşturup
  id'sini stok hareketine yazıyor; `stock_reverse_sale()` artık bu kesin referansla eşleşiyor ve
  finans bakiyesini/kaydını `finance_movement_reverse_balance()` ile doğrudan (whitelist'i atlayarak,
  bu özel akış için) geri alıyor. `sil.php`'nin `$map`'inde `'sale'` anahtarı hiç yoktu (t=sale
  bloğu dead code'du) — eklendi.
- **`mobile/sales.php` satış silme butonu `block_personel(true,'edit_delete')` gibi var olmayan bir
  imzayla çağrılıyordu** — `block_personel()` parametre almıyor, `!null` her zaman true olduğu için
  admin dahil HERKES "yetkiniz yok" hatası alıyordu. `can_edit_delete()` ile değiştirildi.
- **`quote_approve.php` (girişsiz teklif onay sayfası) durum değişikliğini sunucu tarafında
  kilitlemiyordu** — token'ı bilen biri Kabul/Red kararını sınırsız tekrar değiştirebiliyordu
  (replay). `UPDATE ... WHERE status NOT IN('Kabul','Red')` + `rowCount()` kontrolü eklendi.
- Düşük öncelikli, bu oturumla ilgisiz (dokunulmadı, sadece not): `report_lib.php`'nin `tahsilat`/
  `muhasebe` case'leri `'report'` yetkisiyle açılıyor, `finance`/`muhasebe` yetkisinden bağımsız —
  bu yüzden hem `report` hem `muhasebe` (ama `finance` değil) yetkisi olan biri finans hareket
  detaylarını görebilir (current_balance değil, ama tutar/kategori kırılımı). İleride ele alınmalı.

## Dashboard Trend & Karşılaştırma Grafikleri (2026-07-03)
- **Web Dashboard (dashboard.php)**:
  - "Bu Ay vs Geçen Ay Karşılaştırması" paneli: Tahsilat, Ödeme/Gider, Yeni Açılan İş, Tamamlanan İş metriklerinin bu ay vs geçen ay karşılaştırması.
  - Her metriğin yanında yüzde değişim göstergesi (▲ yeşil artan, ▼ kırmızı azalan, → griyle sabit).
  - "Son 6 Ay Trend" grafiği: Tahsilat ve Ödeme/Gider için aylık toplamlarını gösteren bar chart (native CSS, report_lib.php'deki deseniyle tutarlı).
  - "Dikkat - Geciken İşler & Kritik Stok" uyarı panosu: Gecikmiş iş sayısı + en kritik 5 işin listesi (iş numarası, başlık, kaç gün gecikmiş — job_view.php'ye link).
  
- **Mobil Dashboard (mobile/index.php)**:
  - "Bu Ay vs Geçen Ay" kartı: Tahsilat ve Ödeme tutarlarının bu ay/geçen ay gösterimi (basitleştirilmiş, mobil ekrana sığacak şekilde).
  - "Dikkat" uyarı kartı: Gecikme sayısı ve kritik stok sayısı grid'de, "Geciken İşleri Gör" butonu.

## Teklif onayı WhatsApp üzerinden (2026-07-03)
- **Müşteri tarafından giriş yapmadan onay/red**: `quotes` tablosuna `approval_token (VARCHAR(64))` ve `approval_decision_at (TIMESTAMP NULL)` kolonları eklendi (migration 029).
- **Yeni `quote_approve.php` (girişsiz)**: `public_file.php` ile aynı desen — token ile teklifi bulup salt-okunur gösterir. Müşteri "✅ Kabul Ediyorum" / "❌ Reddetme" tıklayıp karar verir. Hemen `quotes.status` güncellenir (Kabul/Red).
- **Teklif oluşturunda token üret**: `teklif.php` (web) + `mobile/teklif.php`, yeni teklif INSERT'inde `approval_token=bin2hex(random_bytes(24))` oluşturuluyor (48 karakter hex, tahmin edilemez).
- **WhatsApp paylaşım linki genişletme**: `share_buttons()` çağrısından önceki metin (`$txt`) artık onay linkini içeriyor: `✅ Onaylamak için: [quote_approve.php?token=...]` satırı eklendi (web+mobil parite).
- **Müşteri kararına bildirim**: Onay/red sonrasında teklifi oluşturan personele `internal_notifications`'a otomatik bildirim yazılıyor (title: "✅ Teklif Kabul" vb., action_url: teklif detay sayfası). `activity_log` da kaydediliyor (✅/❌ ikonu ile).
- **Güvenlik**: token tahmin edilemez uzunluğu, SQL prepared statement, XSS'e karşı `htmlspecialchars`, `public_file.php` deseniyle tutarlı.
- **boot.php güncelleme**: `quote_approve.php` → `$__mpub` listesine eklendi (mobil yönlendirme tuzağından kurtarıldı).

## Mobil PWA — Offline çalışma + Barkod/QR okutma (2026-07-03)
- **Gerçek offline çalışma (Service Worker cache)**:
  - `mobile/sw.js`: CACHE versiyonu `v26`'ya güncellendi. `install` event'inde statik kaynaklar (manifest.php, icon.php, .js, .css, resimler) önbelleğe alınıyor. `fetch` event: GET istekleri önce cache'e bak, yoksa network'e git, başarılıysa cache güncelle (stale-while-revalidate + network fallback). POST istekleri hiçbir zaman cache'lenmiyor.
  - `mobile/common.php`: Offline banner HTML + CSS eklendi (kırmızı uyarı, "📴 Offline — Sinyal bulunamadı"). JS: `navigator.onLine` ile durum izleniyor, online/offline event'lerine tepki veriyor.
  - Aktivasyon: eski cache versiyonları (v25 vb.) otomatik silinip yeni cache doldurulur — kullanıcıya manuel cache temizleme gerek yok.

- **Barkod/QR okutma (stok işlemleri)**:
  - `mobile/stock.php`: Tarayıcı BarcodeDetector API'sini destekliyorsa (Chrome/Android) "📷 Barkod Okut" butonu gösterilir (feature-detection: `if('BarcodeDetector' in window)`). iOS Safari kısmi/desteklenmiyor → buton gizli kalır (uygulama bozulmuyor).
  - Modal + video stream: getUserMedia ile arka kameryi başlat → BarcodeDetector ile EAN-13/QR/Code-128 vb. formatları oku. Oktu sonra ürün listesini fetch → taranan kod ile product_code/barcode alanında eşleş → bulunursa product_view.php?id=X'e otomatik yönlendir.
  - Fallback: BarcodeDetector desteklenmediğinde buton gösterilmiyor (graceful degradation). Kamera izni reddedilirse alert + modal kapat.
  - Web tarafına eklenmedi (mobil PWA'ya özgü özellik, desktop kullanıcısı için gerekli değil).

- **Teknik detaylar**: PDO prepared statement (yok, cache/offline işlemi). PHP 7.2 uyumlu. PRG deseni (yok, form POST değil). Mobil-Web parite (gerekli değil, native mobil cihaz özelliği).

## Satış modülü: düzenle/sil + sidebar linki + hızlı cari/ürün ekleme (2026-07-03)
- **Satış düzenle/sil**: Web `sales.php` ve mobil `mobile/sales.php`'de silme butonu eklendi. Satış
  silişi `stock_lib.php`'deki yeni `stock_reverse_sale()` fonksiyonu üzerinden — stok geri koyuluyor
  (stok_items.quantity+=), stok hareketi silinip, finans hareketi `finance_movement_delete()` (finance_lib.php)
  üzerinden geri alınıyor (bakiye sıfırlanıyor). Silme yetkisi `can_edit_delete()` ile korumalı (admin VEYA
  ayrı verilen 'edit_delete' izni), `sil.php` üzerinden (`t=sale`). `$editDeleteTypes`'a `'sale'` eklendi.
- **Web sidebar**: "Satış" linki `layout_top.php`'de Ürün/Stok grubuna eklendi.
- **Hızlı cari/ürün ekleme**: Yeni `ajax_quick_add.php` endpoint'i — `t=contact|product` ile minimal ad
  kaydı oluşturup JSON id döner. Web'de HTML5 `<dialog>` (sales/purchase/checks_notes.php), mobilde
  `<details>` inline form (mobile/sales/purchase/checks_notes.php). Aynı endpoint, no code duplication.
  Yetkilendirme: `user_can('contacts')` / `user_can('stock')`.
- PHP 7.2 uyumlu, tüm SQL prepared statement, web+mobil parite.

## Muhasebe kaydı düzenleme + ürün kategorisi silme + güvenlik kontrolü (2026-07-03)
- **Muhasebe kaydı düzenleme**: `accounting_lib.php`'ye `accounting_entry_update()` ve `accounting_entry_delete()` fonksiyonları eklendi — hesap bakiyesini "eski etkiyi geri al, yeni etkiyi uygula" desenine göre yönetiyor (finance_lib.php ile tutarlı). Web `accounting.php` ve mobil `mobile/accounting.php`'ye her kaydın yanında ✏️ Düzenle butonu + inline/details açılır düzenleme formu eklendi. Silme yetkisi `is_admin()`'den `can_edit_delete()`'e taşındı (tutarlılık).
- **Ürün kategorisi silme**: `product_categories.php`'ye 🗑 Sil butonu eklendi. Kategori kullanımdaysa `active=0` soft-delete, kullanılmıyorsa kalıcı silinir (finance_accounts.php deseni). `sil.php`'ye `'product_category'` case'i eklendi.
- **Muhasebe personeli hesap bakiyesi güvenlik**: Kontrol sonucu sızıntı YOK. Muhasebe personeli sadece `'muhasebe'` yetkisine sahip, `'finance'`/`'report'` erişemez. `accounting.php`'deki hesap dropdown'unda bakiye gösterilmez. Boot.php'deki otomatik koruma garantiliyor.
- `sil.php`: `$editDeleteTypes` listesine `'accounting'`, `'product_category'` eklendi.

## İki güvenlik iyileştirmesi: Değişmez denetim günlüğü + oturum zaman aşımı (2026-07-03)
### 1) Denetim Günlüğü (Audit Log)
- **Migration 028_audit_log.sql**: Yeni `audit_log` tablosu — `id, user_id, action (create/update/delete), table_name, record_id, old_value LONGTEXT, new_value LONGTEXT, ip_address, created_at` ile değişmez denetim kaydı.
- **audit_lib.php** (yeni, web+mobil ortak): `audit_log($userId, $action, $table, $recordId, $oldValue, $newValue)` fonksiyonu. Eski/yeni değerler JSON'a dönüştürülür. try/catch sarılı — audit yazımı ana işlemi asla bozmasın.
- **finance_lib.php** entegrasyonu: 4 kritik finansal fonksiyonda audit_log çağrısı:
  - `finance_account_update()`: hesap düzenleme (ad/tür/banka/IBAN/not/aktif)
  - `finance_account_delete()`: hesap silme (soft-delete kaydı)
  - `finance_movement_update()`: hareket düzenleme (tahsilat/ödeme)
  - `finance_movement_delete()`: hareket silme
  Her işlemde eski satır UPDATE/DELETE ÖNCESINDE okunup, işlem başarılı olduktan sonra audit kaydı yapılıyor.
- **Web görüntüleme**: Yeni `audit_log.php` (admin/users yetkisi zorunlu), tarih/kullanıcı/tablo filtreli liste (LIMIT 300, en yeni en üstte). JSON old/new değerler `<details>` açılır detay olarak gösterilir.
- **Sidebar linki**: `layout_top.php` "İzleme" bölümüne "🔍 Denetim Günlüğü" kartı eklendi (admin/users yetkisiyle).

### 2) Oturum Zaman Aşımı (Idle Timeout)
- **boot.php güncelleme**: `require_login()` içine idle timeout (2 saat inaktivite) kontrol eklendi. Session kapalı ise, remember-me çerezi varsa otomatik giriş, yoksa login sayfasına yönlendir.
- **Her istekte aktivite güncellemesi**: `$_SESSION['last_activity'] = time()` (boot.php'de remember_check sonrası).
- **Remember-me uyumluluğu**: Timeout nedeniyle session kapalı olsa bile, remember-me çerezi geçerliyse otomatik giriş (kullanıcı deneyimi bozulmaz). Manual logout remember_clear() çağırıyor.

## Görevler (tasks) modülü düzenle/sil + Personel görünümü + Satın alma raporu (2026-07-03)
- **Görevler modülü (web + mobil parite)**:
  - `tasks.php` (web): görev başlığı/açıklaması/termin/öncelik/atanan personel artık düzenlenebiliyor (inline form, `<details>` açılır). Silme işlemi `can_edit_delete()` ile kontrol ediliyor.
  - `task_new.php` (web, yeni): görev oluşturma sayfası — personel seçimi, bildirim + iç mesaj otomasyonu.
  - `mobile/task_view.php` (mobil, yeni): görev detayı + düzenle + sil — durum değiştirme, personel atama, silme işlemleri.
  - `mobile/mytasks.php`: "📝 Detay" linki eklendi (task_view.php'ye).
  - `sil.php`: `$editDeleteTypes` listesine `'task'` eklendi.
  - **Personel bazlı filtreleme/görünüm**: `tasks.php` dropdown filtreleme (açık işlerde personel seçilecek), sıralama personel+termin tarihine göre (geciken işler ilk).
  - **Geciken görev göstergesi**: tarih geçmiş görevler kırmızı ve `⏰` ikon ile vurgulnıyor.

- **Görevler rapor modülü** (`report_lib.php`):
  - `rpt('gorevler', ...)` case: Oluşturulan/Tamamlanan/Açık/Geciken metrikleri, duruma göre grafik, personel bazlı tablo.

- **Satın alma raporu** (`report_lib.php`):
  - `rpt('satinalma', ...)` case: Toplam tutarı, tedarikçi bazlı dağılım, ödeme yöntemi dağılımı, detay tablosu.
  - `stock_lib.php` ile entegrasyon: `movement_type='purchase'` ile satın alma hareketleri işaretleniyor.

- **Teknik**: PHP 7.2 uyumlu, prepared statements, PRG deseni, `can_edit_delete()` tutarlılığı.

## Mobil İş/Personel silme (2026-07-03)
- **mobile/job_view.php**: İş silme butonu eklendi (admin-only). Silme anında job_stages, job_files, job_notes, tasks alt kayıtları da temizleniyor (web'deki sil.php mantığıyla tutarlı).
- **mobile/personnel_view.php**: Personel silme butonu eklendi (admin-only). Silme anında personnel_devices alt kayıtları temizleniyor. app_users.personnel_id referansı yetim kalıyor (mevcut sil.php'deki tutarsızlık korunmuş — ileride FK kaskad DELETE veya app_users.personnel_id SET NULL yapılabilir, ama bugünkü oturumda tutarlılık sağlamak için sil.php davranışı kopyalandı).
- Her iki silme işlemi de PRG deseni kullanıyor (POST → redirect).
- Silme formu JS `confirm()` ile onay istiyoruz.

## Mobil menü/yetki tutarsızlığı düzeltmesi + kritik açık kapatma (2026-07-03)
- Bugünkü mobil menü (isAdmin→user_can()) refactor'ü sonrası güvenlik denetiminde bulundu: bazı
  kartlar `user_can()` ile açılıyordu ama hedef sayfa hâlâ `block_personel()` (admin-only) kilitliydi
  — "kart görünüyor, tıklayınca anasayfaya atılıyor" hatası. Daha kötüsü: `mobile/collection.php`
  (tahsilat girişi) hiçbir yetki kontrolüne sahip değildi — `page_module_map()`'te de yoktu,
  `block_personel()` de yoktu — oturum açmış HERHANGİ bir kullanıcı tahsilat kaydı girebiliyordu.
  (`mobile/transfer.php` için ayrı bir güvenlik bulgusu da geldi ama YANLIŞ ÇIKTI — o sayfa zaten
  `page_module_map()`'te `'finance'`e bağlıydı, ajan sadece dosya içine bakıp merkezi korumayı
  gözden kaçırmış.)
- Düzeltme: `boot.php` `page_module_map()`'e `'collection.php'=>'finance'` ve `'payment.php'=>'finance'`
  eklendi. `mobile/payment.php` ve `mobile/purchase.php`'deki `block_personel()` kaldırıldı (web
  tarafları zaten sadece modül yetkisi istiyordu, mobil admin-only kalmıştı — parite sağlandı).
- Ama `personnel.php`/`kpi.php`/`task_new.php`/`requests.php`/`report.php`/`activity.php` hâlâ
  `block_personel()` kilitli bırakıldı (bunları modül-yetkisine açmak için bugün açık bir kullanıcı
  onayı yoktu, özellikle personnel.php maaş/IBAN içeriyor — önceki oturumda kasıtlı admin-only
  bırakılmıştı). Bunun yerine `mobile/more.php`'deki bu sayfalara giden kartlar `$isAdmin`'e geri
  alındı — menü artık gerçek erişimle eşleşiyor, kimseye yanlış söz vermiyor.
- **Ders (ileride tekrarlanmasın)**: bir sayfanın erişim modelini (block_personel↔modül yetkisi)
  değiştirmeden önce, o sayfaya giden TÜM giriş noktalarını (menü kartları + varsa doğrudan linkler)
  kontrol et — tek dosyayı değiştirip diğerlerini menüyle senkronsuz bırakmak "görünür ama erişilemez"
  ya da (daha kötü) "hiç korunmayan" sayfa yaratabilir.

## Kullanıcı & Yetki ekranı: personelden gelince otomatik doldur (2026-07-03)
- Kullanıcı şikayeti: "personeli web de yetki ve kullanıcı adı tanımla ekranına geldiğinde boş geliyor.
  düzenle dediğimiz personele ait bilgi direk gelsin." Kök neden: `personnel_edit.php`'deki "bu
  personelin giriş hesabı yok, users.php'den oluşturun" linki hiçbir parametre taşımıyordu, `users.php`
  "Yeni Kullanıcı" formu her zaman bomboş açılıyordu.
- `personnel_edit.php`: link artık `users.php?personnel_id=X&full_name=...&phone=...` şeklinde.
  `users.php`: bu GET parametreleri "Yeni Kullanıcı" formunun Ad Soyad/Telefon alanlarını ve Personel
  Bağlantısı seçimini otomatik dolduruyor — admin sadece kullanıcı adı/şifre/yetki işaretleyip kaydediyor.

## Satın alma stok senkronu — kod incelemesi düzeltmeleri (2026-07-03)
- Web `purchase.php`'nin ilk hali `layout_top.php`'yi (HTML çıktısı) POST işlemeden ÖNCE çağırıyordu —
  bu yüzden `header('Location:...')` "headers already sent" nedeniyle fiilen çalışmıyordu, PRG deseni
  kırıktı. `require_once boot.php + require_login()` en başa alındı, `layout_top.php` POST bloğunun
  SONRASINA taşındı (`product_new.php`'deki doğru desenle aynı hizaya getirildi).
- Mobil ödeme yöntemi listesine web ile parite için "Çek"/"Senet" eklendi (web'de zaten vardı, mobilde
  eksikti).

## WhatsApp ile giriş bilgisi gönderimi — tekli & toplu (2026-07-03)
- Kullanıcı isteği: "personellere whatsaptan site giriş ekranı kullanıcı adları ve şifrelerini kendi
  telefonlarına gönderebileceğim bir yer olsun. toplu olarak ya da tek tek."
- `users.php` (web) + `mobile/users.php`'ye toplu ("Tüm Aktif"/"Seçilenler") ve tekli WhatsApp gönderim
  eklendi. Şifreler hash'li saklandığı için (var olanı geri okunamaz), gönderim öncesi YENİ rastgele
  şifre üretilip kaydediliyor, düz metin şifre sadece gönderim anında kullanılıyor.
- `share_lib.php`'ye `generate_random_password()` eklendi. Gerçek gönderim `wa_send()` (UltraMsg API,
  `wa_settings.php`'den yapılandırılır) üzerinden — kullanıcıya `~/Desktop/WHATSAPP-API-KURULUM.txt`
  adım adım kurulum kılavuzu bırakıldı.
- NOT: bu özelliği yazan ajan görevi bitirince KENDİ BAŞINA commit attı (`4de5f1f`) — beklenen akış
  (feature-dev → security/code review → ben commit) atlanmış oldu. Sonraki agent dispatch'lerde
  "commit atma, working tree'de bırak" talimatını açıkça eklemek gerekiyor.

## Personele WhatsApp ile giriş bilgisi gönderimi — tekli & toplu (2026-07-03)
- Kullanıcı isteği: "personellere WhatsApp'tan site giriş ekranı kullanıcı adları ve şifrelerini kendi telefonlarına gönderebileceğim bir yer olsun. toplu olarak ya da tek tek."
- **share_lib.php** — `generate_random_password($length=10)` eklendi (büyük+küçük+rakam+özel karakter karması, güvenli rastgele üretim).
- **Web (users.php)**: 
  - Yeni section: "📲 Toplu WhatsApp Gönderimi" — radio (Tüm Aktif / Seçilenlere), checkbox listesi (aktif kullanıcılar, telefon yok olanlar ayrı gösterilir), "Yeni rastgele şifre üret" checkbox (default checked), Gönder butonu.
  - Her mevcut kullanıcı satırında: `<details>` açılır form "📲 Şifre Sıfırla ve WhatsApp ile Gönder" — mini form, hidden input'lar ile gönderim yapılıyor.
  - POST handler `send_bulk_wa`: seçilen her kullanıcı için rastgele şifre üret → `password_hash()` ile kaydet → `wa_send()` ile gönderi → sonuç array'de (başarılı/başarısız/telefon yok).
  - Gönderim sonucu: başarılı/başarısız/telefon yok sayıları + detay tablo (her kişinin gönderim durumu).
- **Mobil (mobile/users.php)**:
  - Toplu form aynı — details açılır, radio + checkbox listesi + generate checkbox + Gönder.
  - Her user'da "📲 WhatsApp ile Gönder" details formu (tekli gönderim).
  - POST handler aynı, ama sonuç session'a kaydedilip redirect sonrası okunuyor (`$_SESSION['wa_results']`, load'da temizlenir — PRG deseni).
  - Gönderim özeti: mobil card-style, grid layout (başarılı/başarısız/telefon yok), detay liste scroll-able.
- **Önemli tasarım kararı:**
  - Toplu gönderim DAIMA yeni rastgele şifre üretir (kullanıcı kabul etmişse). Bu, zaten kayıtlı bir kullanıcının şifresini bilmeyerek yeniden tahmin ettiremez, ancak admin'in kontrol altındadır (generate checkbox).
  - Şifre sıfırlama ve WhatsApp gönderimi TEK İŞLEM — aynı POST'ta yapılır, başarısızlık durumunda DB update de yapılmayabilir (try/catch'te transaction yok, ama telefon numarası boşsa baştan fail).
  - Telefon numarası zorunlu — boş olanlar listede ayrı gösterilir ("Telefon yok"), gönderim kapsam dışında.
- **Yetki kontrolü**: Web'de `require_permission('users')`, mobilde `block_personel()` (yönetici only).
- **SQL & PHP 7.2**: Tüm sorguların prepared statement'lar, PHP 7.2 uyumlu (str_contains/match/named args YASAK).
- **Web+Mobil parite**: Aynı POST handler (`send_bulk_wa`), aynı mantık, UI platform-specific (web grid, mobil details + card özeti).
- **Bilinen sınırlamalar**: 
  - wa_send() fonksiyonu `app_settings` (admin panel) veya eski config sabitlerinden WA API ayarlarını okur — ayar yoksa false döner (mesaj gönderilmez).
  - Bir başta "Başarısız" sonucu API hatası veya koşulsuz başarısızlık olabilir (hata mesajı detayda).



## Mobil menü (mobile/more.php) yetki tabanlı render düzeltmesi (2026-07-03)
- **Yapısal sorun**: Mobil menü TÜM bölümleri (Stok, Cari & Satış, Finans, Muhasebe, Personel & İş, Raporlar, Sistem) tek bir `if($isAdmin): ... else: ...` bloğuna bağlıydı. Admin olmayan ama belirli modül yetkileri (finance, stock, muhasebe vb.) verilen personel, menüde ilgili modüle giden LİNK görmüyordu — sadece URL'yi bilse boot.php'nin `require_permission()` vasıtasıyla sayfaya erişebiliyordu, menü kapalı kaldığı için bulmayı olanaksız.
- **Çözüm**: Admin/personel ikili yapısı kaldırılıp, her bölüm/kart şimdi ilgili `user_can('modül')` kontrolünden geçiyor:
  - **📦 Stok & Ürün**: `user_can('stock')`
  - **👥 Cari & Satış**: Bölüm gösterilir eğer YARINDAN BİRİ: contacts, stock, teklif, finance → Kart bazında ayrı kontrol (Cariler=contacts, Satış=stock, Tahsilat=finance, Teklif=teklif)
  - **💰 Finans**: `user_can('finance')`
  - **📒 Muhasebe**: `user_can('muhasebe')`
  - **👷 Personel & İş**: Her zaman gösterilir (İşlerim/Talep Aç kişisel kartlar herkese açık) → Modül kartları (Personel=personnel, Görev Ata=tasks, İşler/Üretim/Takvim=jobs)
  - **📊 Raporlar**: `user_can('report')`
  - **⚙ Sistem**: Her zaman gösterilir (Profil/Çıkış/Mesajlar herkese açık) → Kullanıcılar/Logo kartları `user_can('users')`
- **Modül eşleştirmesi**:
  - collection.php (tahsilat) → finance
  - payment.php (ödeme) → finance
  - uretim.php → jobs
  - calendar.php (takvim) → jobs
  - task_new.php → tasks
  - requests.php → jobs
  - activity.php, profile.php, mytasks.php, messages.php, request_new.php → her zaman (kişisel)
- **Admin davranışı**: İçinde `user_can()` tüm modülleri true döndüğü için, admin menü tamamen aynen gösterilir — sadece personel için görunürlük artar.
- **Sonuç**: Admin OLMAYAN ama belirli yetkileri olan personel artık kendi yetkili olduğu bölümleri mobil menüde görebiliyor, menü yapısı admin/personel ayrımı yerine yetki tabanlı.

## Satın alma veri bütünlüğü düzeltmesi (2026-07-03)
- **Bug**: Web tarafındaki `purchase.php` sadece jobs tablosunu listeliyor, stok güncellemiyor; mobil
  `mobile/purchase.php` ise doğru çalışıyor (stock_items/stock_movements/finance_movements güncelliyor).
  Web'de satın alma "işi" oluşturulsa da asıl stok artışı hiç yaşanmıyordu.
- **Çözüm (Yaklaşım A seçildi):** Yeni `stock_lib.php` oluşturuldu — ortak stok işlemleri:
  - `stock_add_purchase()`: stok kartı oluştur/güncelle + hareketi kaydet (averaj maliyet hesaplaması dahil)
  - `stock_add_purchase_finance()`: ödeme metoduna göre finansal hareket + hesap bakiyesi
  - Web ve mobil aynı fonksiyonları çağırıyor, kod tekrarı ortadan kaldırıldı.
- `purchase.php` (web): mobil deseniyle "Hızlı Satın Alma" formu eklendi (ürün seç, miktar/fiyat gir,
  kaydet → stok+hareketi güncelle). Var olan "Satın Alma İşleri" listesi korundu (iki bölüm).
  POST işlem topx() ÖNCE yapılıyor (PRG deseni), başarıysa redirect, sonra form gösterilir.
- `mobile/purchase.php`: inline stok mantığı kaldırılıp `stock_lib.php` fonksiyonlarını çağıracak şekilde
  refactor edildi (kod tekrarı azaldı). PRG deseni (POST → redirect) korundu.
- `stock_lib.php` hem web hem mobil tarafından require edilebilir şekilde kuruldu (../../stock_lib.php
  mobilde). Tüm SQL prepared statement (7.2 uyumlu). PHP 7.2 uyumlu (str_contains, match, named args YASAK).
- Ödeme metodlarına "Çek" ve "Senet" eklendi (mevcut "Veresiye/Peşin/Banka/Kredi Kartı/POS"'un yanına),
  bunları account_type='Diğer' hesaplara eşleyen map fonksiyon stock_lib.php'de.
- Boot.php `page_module_map()` kontrolü: `purchase.php` zaten 'stock' modülüne bağlı, dokunulmadı.
- Mobil/web parite: ikisi de aynı stok/finansal güncellemeyi yapıyor, ikisinde de aynen çalışıyor.

## Arama güvenlik düzeltmesi + Çek/Senet foto/görev otomasyonu (2026-07-02, hızlı commit — kullanıcı limit uyarısı)
- **KRİTİK güvenlik düzeltmesi**: `search_lib.php`'nin ilk hali (aynı gün eklendi) modül yetkisi
  kontrolü yapmıyordu — `finance`/`personnel`/`stock` vb. yetkisi olmayan bir personel arama kutusu
  üzerinden banka bakiyesi/IBAN, personel iletişim bilgisi gibi hassas verileri görebiliyordu.
  `ots-security-auditor` denetiminde bulundu, `search_run()`'daki her bölüm artık ilgili
  `user_can('finance')`/`user_can('personnel')`/`user_can('jobs')`/`user_can('contacts')`/
  `user_can('stock')`/`user_can('teklif')` kontrolünden geçmeden sorgulanmıyor.
- Çek/Senet modülüne fotoğraf/dosya eki (migration 026, `uploads/check_files/`, mevcut
  `uploads/.htaccess` script-engelleme koruması alt klasörleri de kapsıyor, dosya adı tamamen
  üretilmiş — path traversal riski yok) ve vade tarihinde otomatik Görev (tasks) oluşturma
  (migration 027, `checks_notes.task_id`) eklendi.
- **Bilinen tasarım tercihi (izlenmeli)**: Otomatik oluşturulan görevin başlığı/açıklaması
  (kime verildi/hangi banka/ne kadar) `tasks` tablosuna `finance` yetkisinden bağımsız yazılıyor —
  yani sadece `tasks`/Görevler yetkisi olup `finance` yetkisi olmayan biri, görev listesinde çek/senet
  finansal detayını görebilir. Kullanıcının isteği zaten "muhasebe VE yönetimin iş ekranına... otomatik
  kaydetsin" olduğu için bilinçli bir tasarım, ama ileride görev bazlı hassasiyet/gizlilik istenirse
  gözden geçirilmeli.
- NOT: Bu iki özellik kullanıcının kullanım limiti bitmek üzereyken hızlıca commit edildi — Çek/Senet
  foto/görev kısmının tam güvenlik denetimi (`ots-security-auditor`) arka planda başlatılmıştı ama
  commit anında henüz tamamlanmamıştı; sadece dosya yükleme güvenliği (uzantı whitelist + .htaccess
  kapsamı + path traversal) manuel hızlı kontrolden geçirildi, SQL/yetki tarafı ayrıntılı incelenmedi.
  Sonraki oturumda ajan denetim sonucu gelirse gözden geçirilmeli.

## Çek/Senet: dosya eki + otomatik görev (2026-07-02)
- Kullanıcı isteği: "çek ekranında bir cariye çekle ödeme yaptık diyelim. bu çekin bir fotoğrafını
  sisteme ekleyebilelim. ve çek tarihi muhasebe ve yönetimin iş ekranına tarihi ile otomatik kaydetsin.
  kime verildi hangi banka ne kadar…" — bugün eklenen Çek/Senet takip modülünün (bkz. yukarıdaki madde)
  üzerine iki ek özellik.
- Migration `026_checks_notes_attachment.sql`: `checks_notes.attachment VARCHAR(255) NULL` (kök-göreli
  dosya yolu). `027_checks_notes_task_link.sql`: `checks_notes.task_id INT NULL` (otomatik oluşan
  hatırlatma görevine link, durum senkronu için).
- `checks_notes_lib.php`'ye `checks_notes_handle_upload()` eklendi (job_view.php'deki `job_file` yükleme
  deseniyle aynı: `UPLOAD_ERR_*` kontrolü, uzantı/mime beyaz listesi `jpg/jpeg/png/webp/gif/pdf`, 15 MB
  limit, `uploads/check_files/` altına `move_uploaded_file`). `checks_notes_create()`/`checks_notes_update()`
  içine entegre edildi — dosya seçilmezse mevcut ek korunur (update'te). Web `checks_notes.php` (yeni
  kayıt + inline düzenleme) ve mobil `mobile/checks_notes.php` + `mobile/check_note_view.php` formlarına
  `enctype="multipart/form-data"` ve `<input type="file" name="attachment">` eklendi, ek varsa
  "📎 Dosyayı Gör" linki (`base_url().$r['attachment']`) gösteriliyor. Silmede dosya diskten silinmiyor
  (proje genelindeki tutarlı davranış, job_files'ta da aynı).
- Otomatik görev: `checks_notes_create()` içinde vade tarihi girilmişse `tasks` tablosuna otomatik satır
  ekleniyor (`checks_notes_auto_create_task()`) — `job_id=NULL`, `personnel_id=NULL` (genel/atanmamış
  görev; hem web `tasks.php` hiçbir personel filtresi uygulamadığı için hem de mobil
  `mobile/mytasks.php`'de admin görünümünde bu görevler görünüyor — kontrol edildi, ayrıca admin'e
  atamaya gerek kalmadı). `title`: "Çek Vadesi: NO — TUTAR ₺" (senet ise "Senet Vadesi"), `description`:
  "Kime verildi / Banka / Tutar / Durum" satırları. `priority`: vadeye ≤7 gün kaldıysa 'Yüksek', değilse
  'Normal'. Görev otomasyonu `try/catch` ile sarılı — başarısız olsa da çek/senet kaydı yine oluşur.
  Oluşan `tasks.id`, `checks_notes.task_id`'ye geri yazılıyor (`checks_notes_update()`'te durum
  'tahsil_edildi'/'ciro_edildi'/'iptal' olduğunda ilişkili görevi otomatik 'Tamamlandı' işaretlemek için
  — `checks_notes_sync_task_status()`).

## Global arama (search.php) düzeltme + kapsam genişletme + mobil parite (2026-07-02)
- Kullanıcı şikayeti: "personel ismi yazıyorum bulunamadı, kredi kartı yazıyorum yok."
- **Kök neden 1 (aynı sınıf hata 3 bölümde birden — sadece personel değil):** `search.php`'deki
  sorgular gerçekte var olmayan kolonları arıyordu, `try/catch` "Unknown column" hatasını sessizce
  yutup boş dizi dönüyordu — yani "İşler" hariç NEREDEYSE HİÇBİR bölüm hiçbir zaman sonuç vermiyordu:
  - Personel: `title`/`department` yok → gerçek kolonlar `role`/`work_type` (`001_core_auth.sql`).
  - Cari: `tax_no` yok → gerçek kolon `tax_number` (`002_contacts_crm.sql`).
  - Stok: `sku`/`description` yok → gerçek kolonlar `product_code`/`barcode`/`notes` (`004_stock_products.sql`).
  Kullanıcı sadece personel/kredi kartını fark etmiş ama Cari ve Stok araması da aynı şekilde kırıktı,
  bu turda hepsi düzeltildi. Ayrıca personel sonucunun linklediği `personnel_view.php` web'de hiç
  yoktu (mobilde var, web'de yok) — bu da ayrı bir ölü link bug'ıydı, `personnel_edit.php?id=`'e
  (gerçek web detay/düzenleme sayfası) çevrildi.
- **Kök neden 2 (kapsam eksikti):** Sadece İşler/Cari/Stok/Personel aranıyordu; Finans Hesapları
  (`finance_accounts` — Kasa/Banka/Kredi Kartı/POS) ve Finans Hareketleri (`finance_movements`) hiç
  arama kapsamında değildi, bu yüzden "kredi kartı" gibi aramalar hiç eşleşmiyordu. Kapsam ayrıca
  Çek/Senet (`checks_notes`) ve Teklif (`quotes`) ile genişletildi (kolay ek kapsam, aynı desende).
- Yeni `search_lib.php` (web+mobil ortak, `*_lib.php` kuralı): `search_run($pdo,$q)` tüm 8 tabloyu
  prepared statement ile arayıp ham satır dizileri döner (İşler/Cari/Stok/Personel/Finans Hesapları/
  Finans Hareketleri/Çek-Senet/Teklif), `search_hl()` vurgulama fonksiyonu, `search_total_count()`.
  HTML/link üretimi kasıtlı olarak lib'de DEĞİL — web ve mobilin aynı modül için detay sayfası URL'leri
  farklı (örn. hesap detayı web'de `finance_account_view.php`, mobilde `account_view.php`; hareket
  detayı sadece mobilde var — `movement_view.php`, web'de `finance.php` listesine genel link veriyor).
  Bu yüzden her sayfa kendi linklerini kendi render mantığıyla kuruyor.
  - Bir de `mobile/index.php` gibi çekilmeyen kolay-ekstra kapsamı var: `movement_view.php` YALNIZ mobilde.
  - `finance_accounts`'ta `account_type='Kredi Kartı'` gibi tam değerler var — `LIKE '%kredi kartı%'`
    zaten collation (utf8mb4_unicode_ci) sayesinde case-insensitive eşleşiyor, ekstra normalize kod
    gerekmedi.
- Web `search.php`: yeni Finans Hesapları/Finans Hareketleri/Çek-Senet/Teklif bölümleri eklendi
  (mevcut panel/tablo desenine uygun), personel bug'ı düzeltildi. `layout_top.php` topbar arama
  placeholder'ı ve `search.php`'nin kendi placeholder'ı yeni kapsamı yansıtacak şekilde güncellendi.
- **Mobil parite:** önceden mobilde arama sayfası HİÇ yoktu — yeni `mobile/search.php` eklendi
  (`topx`/`botx`/`mm()` mobil deseninde, aynı `search_lib.php`'yi kullanıyor). `mobile/more.php`
  menüsünün en üstüne "🔍 Ara" kartı eklendi (hem admin hem personel görünümünde).
  Mobil finans/personel detay linkleri kendi sayfalarının zaten var olan yetki kısıtlarına (`finance`
  modül izni, `block_personel()`) otomatik tabi — arama sayfasının kendisi ekstra bir izin kontrolü
  eklemedi (mevcut İşler/Cari bölümleri de zaten hiç izin kontrolü yapmıyordu, tutarlılık korundu).
- NOT: `kpi.php` (web) de aynı ölü `personnel_view.php` linkini kullanıyor — bu turda dokunulmadı,
  `memory/backlog.md`'ye ayrı madde düşüldü.

## "Düzenleme/Silme Yetkisi" — kademeli ayrı izin (2026-07-02)
- Kullanıcı isteği: "yapılan işlemi düzenleme ve silme yetkisi herkese verilmemeli, personel yetki
  ekranında buna bir buton verilebilir, ver-verme gibi." Yani modül yetkisi (örn. 'finance') artık
  SADECE görüntüleme/yeni kayıt eklemeyi kapsıyor; VAR OLAN bir kaydı düzenlemek/silmek için ayrıca
  yeni bir genel yetki gerekiyor.
- `boot.php`: `module_list()`'e `'edit_delete'=>'Var Olan Kaydı Düzenleme / Silme Yetkisi'` eklendi —
  `users.php`/`mobile/users.php` zaten `module_list()`'i dinamik checkbox listesi olarak render ettiği
  için ekstra bir UI değişikliği gerekmedi, yeni checkbox otomatik çıktı. `can_edit_delete()` yardımcı
  fonksiyonu: `is_admin() || user_can('edit_delete')`.
- `sil.php`: eskiden TÜM silme türleri (`t=`) blanket `is_admin()` şartına bağlıydı. Şimdi
  `$editDeleteTypes=['account','finance']` listesindeki türler `can_edit_delete()` ile de geçebiliyor,
  listede olmayanlar (cari/iş/teklif/ürün/personel — henüz bu yeni yetkiye taşınmadı) hâlâ admin-only.
  **Kademeli genişletme**: kalan modüller (satış/görevler/muhasebe/ürün kategorisi) için düzenle-sil
  eklenirken bunlar da `$editDeleteTypes`'a ve ilgili sayfalardaki kontrole eklenecek.
- Şu an bu yetkiyle korunan ekranlar: `finance_accounts.php`/`finance_account_view.php`/
  `mobile/account_view.php` (hesap düzenle/sil), `finance.php`/`finance_new.php`/`mobile/movement_view.php`
  (hareket düzenle/sil), `checks_notes.php`/`mobile/check_note_view.php` (çek/senet düzenle/sil).
  Her birinde hem POST handler (sunucu tarafı asıl garanti) hem buton/form görünürlüğü (`can_edit_delete()`)
  güncellendi — sadece UI gizleme değil, gerçek sunucu tarafı kontrol.
- Yeni kayıt OLUŞTURMA bu yetkiye bağlı DEĞİL — sadece ilgili modül yetkisi (örn. 'finance') yeterli,
  tutarlı bir şekilde her yerde aynı ayrım korundu.

## Çek / Senet takip modülü (2026-07-02)
- Kullanıcı isteği: "muhasebe sistemine ödeme metodu olarak çek ve senet eklenmeli ve onlara da kayıt
  kart açılabilmeli, bunda da değişiklik ekle sil vs alanları olmalı."
- Migration `024_checks_notes.sql`: yeni `checks_notes` tablosu (tür, numara, tutar, vade tarihi,
  opsiyonel cari, banka adı, durum: portföyde/tahsil edildi/ciro edildi/karşılıksız/iptal, not).
- `checks_notes_lib.php` (yeni, web+mobil ortak): CRUD fonksiyonları.
- Web `checks_notes.php` + mobil `mobile/checks_notes.php` (liste+yeni kayıt, vadesi geçen/yaklaşan
  satırlar renkli vurgulu) ve `mobile/check_note_view.php` (detay+düzenle+sil, `account_view.php` ile
  aynı desen). `boot.php` `page_module_map()`'e `'finance'` yetkisine bağlı eklendi.
- Web sidebar (Finans grubu) ve mobil menü (💰 Finans bölümü, "Çek / Senet" kartı) linklendi.
- Ödeme yöntemi olarak "Çek" ve "Senet" ayrıca `finance_movements.payment_channel` seçeneklerine
  (web `finance_new.php`, mobil `mobile/movement_view.php`/`payment.php`/`collection.php`) eklendi —
  bu, ayrı takip kartı zorunlu olmadan normal bir tahsilat/ödeme kaydında da "Çek"/"Senet" seçilebilmesi
  içindir (`mobile/payment.php`+`mobile/collection.php`'deki hesap-tipi eşleme fonksiyonlarında
  Çek/Senet → 'Diğer' hesap tipine düşüyor, `pay_acc_for_pm`/`acc_for_pm`).
- Düzenle/sil `can_edit_delete()` yetkisine bağlı (bkz. yukarıdaki "Düzenleme/Silme Yetkisi" maddesi).
- NOT: Bu özelliği uygulayan ajanın bağlantısı yanıt sırasında koptu (menü linkleri ve "Senet" seçeneği
  bazı dosyalarda eksik kalmıştı) — kalan parçalar elle tamamlandı, dosyalar (migration, lib, web+mobil
  sayfalar) tek tek okunup bütünlük doğrulandı.

## Finans hareketleri (tahsilat/ödeme) düzenle/sil (2026-07-02)
- `finance_movements` (tahsilat/ödeme kayıtları) için düzenleme hiç yoktu, silme hiçbir ekrandan
  çağrılamıyordu (`sil.php`'de `t=finance` case'i tanımlıydı ama ölü koddu — hiçbir UI onu POST
  etmiyordu). Bu turda entegre edildi.
- `finance_lib.php`'ye (paralel bir ajanın aynı anda eklediği Finans HESAPLARI fonksiyonlarının
  yanına) yeni bölüm eklendi: `finance_movement_editable_types()`, `finance_movement_get()`,
  `finance_movement_reverse_balance()`, `finance_movement_apply_balance()`,
  `finance_movement_update()`, `finance_movement_delete()`.
- **Kapsam kısıtı (kritik):** `finance_movements` sadece elle giriş (web `finance_new.php`,
  mobil Ödeme/Tahsilat → `movement_type='normal'|'mobile'`) değil, başka modüllerden de otomatik
  satır alıyor: satış (`sale`/`mobile_sale`), alış/satış belgesi ödemesi (`document`, `document_id`
  ile bağlı), hesaplar arası transfer (`transfer`, `target_account_id` ile). Bu otomatik satırlar
  başka tabloların (stock_movements, trade_documents.paid_amount, karşı hesap bakiyesi) kaynağı
  olduğu için düzenleme/silme SADECE `normal`/`mobile` tipli hareketlerde izinli — diğerleri
  `finance_movement_update/delete` içinde Exception/`ok=false` ile reddediliyor, UI'da "Otomatik"
  etiketiyle gösteriliyor (buton yok).
- Bakiye tutarlılığı: silme/düzenlemede önce eski hareketin hesap bakiyesine etkisi geri alınıyor
  (`finance_movement_reverse_balance` — transfer ise HER İKİ hesap, normal ise tek hesap), sonra
  (düzenlemede) yeni değerin etkisi tekrar uygulanıyor. `accounting_entries` (Muhasebe modülü)
  ile `finance_movements` arasında DB ilişkisi YOK (bağımsız tablolar) — kontrol edilip doğrulandı.
- `finance.php` (web liste): "İşlem" kolonu — düzenilebilir satırlarda ✏️ Düzenle (`finance_new.php?id=`)
  + admin'e özel 🗑 Sil (`sil.php`, `t=finance`); otomatik satırlarda "Otomatik" etiketi.
- `finance_new.php`: `?id=` ile düzenleme moduna giriyor (aynı form, mevcut kayıt önden dolduruluyor,
  kaydet `finance_movement_update()` çağırıyor); yeni kayıt akışı değişmedi.
- `sil.php`: `t=finance` artık genel DELETE akışının dışına alınıp `finance_movement_delete()`'e
  yönlendiriliyor (t=account'un yanına, aynı desende) — bakiye geri alma + tip kısıtı garanti.
- Mobil: yeni `mobile/movement_view.php` (hesap dengi `account_view.php`'nin deseni) — hareket
  detayı + `<details>` içinde düzenleme formu (düzenilebilir tiplerde) + admin-only silme butonu.
  `mobile/kasa.php`'nin "Son Hareketler" listesi artık her satırdan `movement_view.php?id=`'e
  linkleniyor. `mobile/payment.php` ve `mobile/collection.php`'ye de kendi son 10 kaydını gösteren
  ve movement_view.php'ye linkleyen mini liste eklendi (önceden bu ekranlarda hiç liste yoktu).
  `boot.php` `page_module_map()`'e `movement_view.php=>finance` eklendi.
- ~~Silme yetkisi admin-only~~ GÜNCELLEME: aynı gün sonra "Düzenleme/Silme Yetkisi" maddesiyle
  `can_edit_delete()`'e taşındı (admin VEYA ayrı verilen `edit_delete` izni) — yukarıdaki ilgili maddeye bak.
- Yeni migration gerekmedi — `finance_movements.category_id`/`reference_no`/`target_account_id`
  zaten önceki migrationlarda vardı.
- Not: Aynı anda paralel çalışan bir ajan `finance_accounts.php`/`mobile/account_view.php`/
  `finance_lib.php`/`sil.php`'yi Finans HESAPLARI (Kasa/Banka/Kart) düzenle/sil için değiştiriyordu.
  `finance_lib.php`'ye sadece yeni fonksiyonlar eklendi (var olanlara dokunulmadı), `sil.php`'ye
  `t=account`'ın hemen altına yeni bir `t=finance` bloğu eklendi — çakışma yaşanmadı, iki özellik
  birbirinden bağımsız çalışıyor.

## Finans hesapları düzenle/sil (2026-07-02)
- `finance_accounts` (Kasa/Banka/Kredi Kartı/POS) için düzenleme hiç yoktu, silme sadece
  `finance_account_view.php`'de vardı (liste sayfasında yoktu), mobilde ikisi de yoktu.
- `finance_lib.php` (yeni, web+mobil ortak): `finance_account_types()`, `finance_account_has_movements()`,
  `finance_account_update()`, `finance_account_delete()`.
- Silme stratejisi: hesap `finance_movements`de (account_id veya target_account_id) kullanılmışsa
  referans bütünlüğü bozulmasın diye KALICI silinmiyor, `active=0` yapılıp soft-delete uygulanıyor
  (projedeki ürün/stok aktif-pasif deseniyle tutarlı). Kullanılmamışsa kalıcı silinir.
- `finance_accounts.php` (web liste): her satıra ✏️ Düzenle (inline açılır form, job_view.php'deki
  `<details>` desenine benzer ama tablo satırı içinde JS toggle) ve admin'e özel 🗑 Sil eklendi.
- `finance_account_view.php` (web detay): `<details>` içinde düzenleme formu eklendi; mevcut
  `delete_button('account',$id)` (admin-only, sil.php üzerinden) korundu.
- `sil.php`: `t=account` türü artık genel DELETE akışının dışına alınıp `finance_account_delete()`'e
  yönlendiriliyor — böylece hem liste sayfasından hem detay sayfasından hem de doğrudan sil.php'ye
  POST edilse bile aynı güvenli (soft-delete korumalı) yol izleniyor.
- `mobile/account_view.php`: düzenleme formu (herkes, finance yetkisiyle) + silme butonu (sadece
  yönetici/admin, `$isAdmin`, `confirm()` ile) eklendi. POST işlemleri PRG deseniyle `topx()`'ten
  ÖNCE işlenip redirect ediliyor.
- ~~Silme yetkisi admin-only tutuldu~~ GÜNCELLEME: aynı gün sonra "Düzenleme/Silme Yetkisi" maddesiyle
  `can_edit_delete()`'e taşındı (admin VEYA ayrı verilen `edit_delete` izni) — yukarıdaki ilgili maddeye bak.
- Yeni migration gerekmedi — `finance_accounts.active` kolonu zaten 005_finance.sql'de vardı.
- İnceleme sonrası düzeltmeler: (1) `finance_account_has_movements()` sadece `finance_movements`e bakıyordu,
  `accounting_entries.account_id` referansını görmüyordu — sadece muhasebe modülünde kullanılmış bir hesap
  yanlışlıkla kalıcı silinip yetim kayıt bırakabilirdi, düzeltildi (artık ikisini de kontrol ediyor).
  (2) Mobilde soft-delete başarı mesajı ("pasife alındı") yanlışlıkla kırmızı hata kutusunda gösteriliyordu,
  düzeltildi. (3) Web liste sayfası detay sayfasından gelen `?deleted=1` parametresini okumuyordu, silme
  sonrası mesaj görünmüyordu — düzeltildi.

## Marka adı yaygınlaştırma + yetki canlı yenileme (2026-07-02)
- 448a28a'da `layout_top.php` için başlatılan "hardcode ACANS OTS → dinamik `app_config()['app_name']`"
  düzeltmesi (PRIMAC başlık sorunu) tamamlanmadan kalmıştı; kalan sabit metinler temizlendi:
  `ics.php` (takvim PRODID), `mobile/manifest.php` (PWA adı/kısa adı/açıklaması), `mobile/index.php`
  (topx başlığı), `mobile/teklif.php`+`teklif.php` (logo yoksa firma adı yerine gösterilen metin),
  `public_file.php` (dosya onay sayfası başlık/footer), `share_lib.php` (`cred_wa`/`share_buttons`
  varsayılan konu/metin), `wa_settings.php` (WhatsApp test mesajı varsayılanı). Hepsi `?? 'OTS'`
  fallback'li, `app_config()` yoksa/erişilemezse kırılmıyor.
- `boot.php` `user_can()`: izinler artık HER ÇAĞRIDA `app_users.permissions`'tan taze okunuyor (önceden
  sadece login anındaki session kopyasına bakıyordu). Kök neden: aynı oturumdaki `mobile/users.php`
  (yönetici → personel yetki değişikliği) sonrası hedef kullanıcı çıkış/giriş yapmadan yeni yetkiyi
  görmüyordu. DB'ye erişilemezse (`Throwable`) session'daki son bilinen kopyaya düşülüyor — tamamen
  erişimsiz kalınmıyor. Not: her yetki kontrolünde ek bir DB sorgusu getiriyor (performans notu, kritik değil).

## Gider kaydında kategori (cari zorunlu değil) (2026-07-02)
- Kullanıcı şikayeti: "muhasebe tarafında gider işlerken sadece cari seçilebiliyor... personel yol
  giderini cariye mi işleyeceğim? olmaz. vergi giderleri, günlük yemek, yakıt, telefon gibi kendi
  kategorilerimizi oluşturabilmeliyiz, raporlama da bunları kendi altında göstermeli."
- Kök neden: `accounting.php` (Muhasebe modülü) zaten kategori bazlı çalışıyordu (cari alanı hiç yok),
  ama kullanıcının fiilen kullandığı hızlı gider ekranı — mobil "Ödeme / Gider" (`mobile/payment.php`) ve
  web dengi `finance_new.php?direction=out` — `finance_movements` tablosuna sadece Cari (opsiyonel) ile
  yazıyordu, kategori alanı hiç yoktu. Cari boş bırakılınca gider tamamen etiketsiz kalıyordu.
- Migration `023_finance_movement_category.sql`: `finance_movements.category_id` (NULL, accounting_categories
  FK'siz referans) eklendi + eksik iki varsayılan gider kategorisi ("Personel Yol Gideri", "Günlük Yemek").
- `finance_new.php` + `mobile/payment.php`: Kategori dropdown'u eklendi (Cari'nin yanına, ikisi de opsiyonel,
  accounting.php'deki tip filtreleme JS deseniyle aynı). `finance.php` (liste) ve `mobile/kasa.php` (son
  hareketler) kategori kolonunu/etiketini gösteriyor.
- `report_lib.php`: yeni `muhasebe` rapor modülü eklendi (accounting_entries'i kategoriye göre kırar —
  Gelir/Gider ayrı grafik). `tahsilat` modülüne ikinci bir grafik (`chart2`) + Kategori tablo kolonu
  eklendi (Ödeme/Gider kategoriye göre kırılım). `report_render()` artık opsiyonel `$R['chart2']` destekliyor.
  Değişiklik `report.php`+`mobile/report.php` ile otomatik parite sağlıyor (ikisi de aynı report_lib.php'yi
  kullanıyor).
- `accounting_categories.php` zaten CRUD sağlıyordu, dokunulmadı — kullanıcı zaten oradan yeni kategori
  ekleyebiliyor, sadece gider giriş formlarına bağlanmamıştı.

## Bildirim action_url + mesaj görünürlük onarımı (2026-07-02)
- Kullanıcı şikayeti: "mesaj bildirimi görünüyor ama ekranda mesaj yok" + "sabah bildirimi geldi ama
  içeriği sadece bildirimler ekranında, o da saçma şekilde". Kök neden analizi ve düzeltme
  `ots-feature-dev` ajanına yaptırıldı, `ots-security-auditor` ile bağımsız incelendi.
- `internal_notifications` tablosunda **hiç var olmamış `action_url` kolonu** eklendi (migration 021 +
  `mobile/common.php`'de runtime lazy-migration). Önceden `dashboard.php`/`notifications.php` bu kolonu
  okuyordu ama kolon DB'de yoktu → her bildirim türü (iş atama, geciken iş, sabah hatırlatma, günlük
  rapor, talep onay/red, müşteri dosya onayı) "Aç"/"Detay" linkinde hep genel dashboard'a düşüyordu,
  gerçek içeriğe hiç gidilemiyordu.
- Web `notifications.php`: "Aç"/"Tümünü Okundu Yap" var olmayan bir tabloyu (`notifications` yerine
  `internal_notifications` olmalıydı) güncellemeye çalışıp PDOException ile sayfayı çökertiyordu — düzeltildi.
- `mobile/notifications.php`: çok satırlı mesajlar (`\n` ile ayrılmış sabah raporu gibi) `nl2br` eksikliği
  yüzünden tek satıra sıkışıyordu — düzeltildi, ayrıca her bildirime "Aç" linki eklendi (önceden hiç yoktu).
- **"Mesaj bildirimi var ama ekranda mesaj yok" kök nedeni**: `daily_reminder_lib.php`, sabah
  hatırlatma/günlük rapor içeriğini `internal_messages`'a `sender_user_id=NULL` ("sistem" mesajı) olarak
  da yazıyordu. Mesajlar ekranının (web+mobil) kişi listesi sorgusu `sender_user_id`'yi gerçek bir
  `app_users.id` ile eşleştirdiği için NULL gönderen hiçbir zaman eşleşmiyordu — mesaj asla görünmüyordu.
  Üstelik `mobile/poll.php` bu satırları `msg_unread`'e sayıp `messages.php?with=0` gibi kırık bir
  toast/deep-link üretiyordu ("Kullanıcı bulunamadı"). Bu ikili `internal_messages` yazımı kaldırıldı —
  içerik zaten `internal_notifications` (artık action_url'li) + push + WhatsApp üzerinden gidiyor.
- Açık kalan/gelecek iş: sistem-kaynaklı toplu mesajların Mesajlar sekmesinde görünmesi isteniyorsa
  gerçek bir "Sistem" pseudo-thread/sender eklenmeli (NULL yerine) — bu bir özellik eklemesi, bugfix değil.

## Muhasebe modülü + 4 düzeltme (2026-07-02)
- accounting.php / accounting_categories.php / accounting_lib.php eklendi.

## Sabah raporu + geciken iş sayısı (2026-07-01/02)
- Sabah raporu HTML e-posta formatına geçti, geciken iş sayısı eklendi.

## Global arama + Telegram temizliği (2026-07-02)
- search.php: web topbar arama artık çalışıyor (iş/cari/stok/personel).
- Telegram bot poller kodu kaldırıldı. NOT: telegram_activation_code/telegram_bound gibi DB alanları ve
  personnel_new.php/personnel_edit.php/activity.php'deki ilgili UI kalıcı olarak korunuyor (aktif özellik).
  Sadece bot'un konuşma state dosyası (telegram_states.json) kod tarafından hiç okunmuyordu → silindi.

## Günlük yönetici PDF raporu + talep akışı (2026-06-30)
- gunluk_rapor.php: tüm personel işleri + PDF indir, sabah bildiriminden linkli.
- Talep akışı: ödeme/özel talep kategorileri, onay-red sonrası talep sahibine iç mesaj+bildirim (web+mobil).

## 4 ajan turu: cari alanları, modül aktif-pasif, şifre sıfırlama, mesaj düzenle (2026-06-30)
- Cari kapsamlı bilgi alanları (2. telefon/web/posta kodu/IBAN).
- Modül aktif-pasif + sil (mobil ürün/stok, onaylı).
- Şifremi unuttum + güvenli sıfırlama (6 haneli kod → WhatsApp/mail, 30dk).
- Mesaj düzenle/sil (sadece kendi) + ses/video HTML5 player + güvenli yükleme.
- `IF NOT EXISTS` → MySQL 5.7 uyumluluk düzeltmeleri.

## Paylaşım + Teklif + Mesajlaşma onarımı (2026-06-30)
- share_lib.php: WhatsApp+Mail ortak butonları (wa_link/mail_link/share_buttons/cred_wa). İş (mobil+web),
  görev (mytasks), personel şifre (WA) paylaşımı. Sadece METİN taşır; PDF gereken yerde rapor PDF paylaşımı.
- Teklif modülü (mobil/teklif.php, web/teklif.php, 014_quotes.sql): dinamik kalemli form (canlı toplam+KDV),
  liste, rapor-stili görünüm, PDF/WhatsApp/Mail (report_share.js), durum (Taslak/Gönderildi/Kabul/Red).
  Admin+personel menüde. WEB PARİTESİ sağlandı.
- Mesajlaşma çoklu dosya "bağlantı hatası" onarımı: AJAX yanıtında temiz JSON (ob_start + ob_end_clean +
  display_errors off) — araya giren PHP uyarısı r.json()'u bozuyordu.
- Bu proje git ile takibe alındı (config.php/vendor/uploads/*.zip .gitignore'da).

## Teklif PDF + ACANS Logo (2026-06-30)
- PDF tek-sayfa: report_share.js `ACANS_PDF_FIT` modu → belge tek A4'e sığar, footer absolute-bottom + JS ile
  sabit A4 yükseklik (html2canvas flexbox'ı render etmiyordu).
- ACANS marka kırmızısı #cf3030 (footer lacivert #1b2431), beyaz "A" silüeti logo_acans_a.png (alfa-maskeli PNG).
  firm_list 'mark' alanı → teklif başlığında kırmızı banda doğrudan beyaz A + firma adı.
- Veri temizleme aracı (temizle_veri.php) Sistem menüsünde — canlıya geçiş test verisi temizliği.

## Geciken İş + Teklif Rapor + Takvim + Web Responsive (2026-06-30)
- job_overdue_lib.php: termini geçen tamamlanmamış işler → sorumlu+yöneticilere bildirim/push. boot.php hook,
  saatte bir dosya kilidi, iş başına gün/1 kez. Migration 016 (overdue_notified_at).
- report_lib.php: 'teklif' modülü (sayı/tutar/kabul + duruma göre + döküm); web/mobil tab + 'Tümü' otomatik.
- ics.php + job_view (web+mobil) '📅 Takvime Ekle': işi cihaz takvimine (.ics VEVENT + 1 gün önce alarm).
- Web responsive: layout_top.php mobilde sol menü hamburger çekmece (☰, soldan açılır, overlay).

## Mobil saha modülleri tamamlandı (tarih belirsiz, 2026-07-02 denetiminde doğrulandı)
- mobile/sales.php: artık gerçek satış akışı var (stok düşümü + tahsilat INSERT/UPDATE), placeholder değil.
- mobile/job_view.php: durum güncelleme (`set_status`) + sorumlu atama (`assign`) POST aksiyonları çalışıyor.
- mobile/jobs.php: personel kendi işini (`personnel_id` eşleşmesi) görüyor, durum değiştirebiliyor.
- tasks.php (web): görev durumu güncelleme + silme POST aksiyonları var, salt-okunur değil.
- Bunlar 2026-06-28 tarihli RAPOR.md'de "yapılmadı" olarak işaretliydi; 2026-07-02 denetiminde kodda
  çözülmüş bulundu. RAPOR.md güncelliğini kaybettiği için kaldırıldı, kalan açık maddeler [[backlog]]'a taşındı.

## Web bildirim merkezi kişiselleştirmesi + sol menü/mobil Menü hizalaması (2026-07-03)
- notifications.php, layout_top.php (zil sayacı), dashboard.php (Canlı Bildirimler widget'ı): artık
  mobile/notifications.php'deki gibi `target_user_id IS NULL OR target_user_id=?` ile filtreleniyor.
  Öncesinde herkes herkesin (örn. personelin kişisel günlük hatırlatmasının) bildirimini görüyordu —
  kullanıcı canlıda fark etti (yönetici hesabında personele özel bildirim görünüyordu).
- layout_top.php sol menü 9 gruptan 6 gruba indirildi (51f9ad1): İş Takip, Ticaret, Finans & Muhasebe,
  Ekip, Rapor, Sistem. mobile/more.php aynı 6 grupla hizalandı (53cd1c8) — kart-grid yapısı korundu,
  sadece başlıklar/gruplama eşleştirildi.

## Cari bakiye tutarsızlığı + Teklif onay/PDF/WhatsApp iyileştirmesi (2026-07-03)
- contacts.php "Toplam Bakiye" kartı tüm finance_movements'ı (cari_id'siz dahil) topluyordu,
  cari satırlarıyla uyuşmayan bir rakam üretiyordu — düzeltildi (contacts_report.php'nin
  doğru per-contact toplama deseniyle hizalandı).
- quote_approve.php: Open Graph meta etiketleri (WhatsApp önizleme kartı) + "PDF İndir" butonu
  eklendi (report_share.js). teklif.php/mobile/teklif.php WhatsApp mesaj metni güncellendi.

## Rapor detay linkleri + Hızlı Ekle web paritesi + Muhasebe KDV (2026-07-03)
- report_lib.php (web+mobil ortak): rapor satırlarına "Detay →" linki eklendi (tahsilat/satış/
  satın alma → contact_view.php, iş takip → job_view.php, teklif → teklif.php, cari →
  contact_view.php, stok → product_view.php). Muhasebe modülü ve personel özet satırları
  bilinçli atlandı (uygun hedef yok / bkz. [[backlog]] eski "Ölü link" maddesi).
- sales.php/purchase.php/checks_notes.php (web): "+" düğmesi + dialog modal deseni kaldırıldı,
  mobildeki (118a0f0) gibi dropdown'un son seçeneği "Yeni Ekle" ile entegre edildi — web+mobil
  artık aynı etkileşim desenini kullanıyor.
- Muhasebe modülüne KDV Dahil/Hariç girişi eklendi (migration 031, accounting_lib.php
  acc_calc_vat(), web+mobil formlar) — bkz. commit 4353d71.

## Backlog temizliği: ölü link + menü eksikleri + push_enable linki (2026-07-03, commit 1ff6f1e)
- kpi.php: web'de var olmayan personnel_view.php linki personnel_edit.php'ye düzeltildi.
- layout_top.php + boot.php: work_center.php ("İş Motoru"), trade_documents.php (alış/satış
  belgeleri), design.php ("Grafik Tasarım") sol menüye ve page_module_map() yetki eşlemesine
  eklendi. trade_documents.php öncesinde hiçbir yetki kontrolüne bağlı değildi — artık
  user_can('contacts') gerektiriyor (bilinçli sıkılaştırma, kod incelemesinde onaylandı).
- mobile/more.php: push_enable.php'ye "🔔 Bildirim Kur" linki eklendi (gerçek özellik, daha önce
  hiçbir mobil menüden erişilemiyordu). temizle.php'nin silme listesinde push_enable.php hiç
  yokmuş — önceki denetimdeki endişe yanlış çıktı, düzeltme gerekmedi.
- Mobil parite notu: work_center/trade_documents/design.php'nin mobil karşılığı yok, bilinçli
  olarak bu turun kapsamı dışında bırakıldı → [[backlog]]'a yeni madde olarak taşındı.

## "Notlarım" düzenleme (2026-07-04)
notes.php'deki her açık not kartına WhatsApp/Tamamla/Sil'in yanına "✏️ Düzenle" eklendi.
Web'de aynı sayfada açılan bir overlay/modal (JS ile data-edit-* attribute'larından doldurulur,
sayfa değişmez); mobilde (mobile/mytasks.php) modal yerine kart içinde açılıp kapanan inline
form (platform deseni, `<button onclick>` ile toggle). Backend: notes_lib.php'ye
`personal_note_update($pdo,$id,$userId,$title,$note,$dueDate)` eklendi — UPDATE'te WHERE
`id=? AND user_id=?` ile IDOR'a kapalı (personal_note_delete/set_status ile aynı desen).
personal_notes şemasında (migration 037) sadece title/note/due_date/status var — "Öncelik" ve
"Hatırlatma bilgisi" alanları şemada/ekleme formunda hiç yoktu, bu tur kapsamında YENİ kolon
eklenmedi (DB şeması genişletilmedi); istenirse ayrı bir migration+kapsam onayı gerekir →
backlog'a not düşülebilir. Tamamlanan notlarda (filtre "done") Düzenle gösterilmiyor (bilinçli,
kullanıcı isteğiyle uyumlu).

## Personel CV/Özgeçmiş yükleme (2026-07-03, commit f606cf9)
Personel kartına opsiyonel CV dosyası (pdf/doc/docx/jpg/jpeg/png, 15 MB) eklendi — `uploads/
personnel_cv/` altında saklanıyor, `personnel.cv_path` (migration 036). Ortak yükleme mantığı yeni
`personnel_lib.php::personnel_handle_cv_upload()`'da, `checks_notes_handle_upload()` deseniyle
birebir aynı güvenlik seviyesinde. Web (personnel_new.php/personnel_edit.php, indirme+kaldırma
linki) ve mobil (mobile/personnel_new.php/mobile/personnel_view.php) ikisinde de çalışıyor.
`personnel_has_cv_column()` ile migration henüz çalışmamış ortamda (ACANS/PRIMAC ayrı DB) sayfa
kırılmıyor. Kullanıcı isteği: SİSTEM YENİDEN GRUPLAMA notunda ("cvs dedin") sorulmuş, karar
Claude'a bırakılmıştı — "evet ekle" kararı verildi.
