# Backlog & Yapılacaklar

<!-- Açık geliştirme görevleri. Kapanan madde buradan silinip memory/features.md'ye taşınır. -->

## 🟡 DEVAM — 2026-07-18 ikinci tur (guncelleme.zip tazelendi, primac.tr durumu HÂLÂ TEYİT EDİLEMEDİ)

**Son commit:** `15959a2` (main, origin ile senkron, working tree TEMİZ — bu tur repo'ya commit
YAPMADI, sadece Masaüstü deploy paketine dokundu).

**Bulgu**: `~/Desktop/PRIMAC-GUNCELLEME/guncelleme.zip` **BAYAT** çıktı — `git archive` zaman
damgası ~12:27'ydi, oysa migration `048_checks_notes_lifecycle.sql` 12:31'de, commit `1f8a897`
12:13'te, commit `1f19528` 12:42'de, commit `9700a31` (nav accordion fix) daha da sonra oluştu.
Yani zip primac.tr'ye yüklenmiş olsaydı bile **çek/senet yaşam döngüsü (048), CPA satış P0 veri
bütünlüğü düzeltmesi ve nav accordion düzeltmesi sunucuya GİTMEMİŞ olacaktı.**
**Düzeltme**: zip `git archive HEAD` (commit `15959a2`) + `vendor/` yeniden üretildi (ilk denemede
vendor dosyaları yanlışlıkla zip KÖKÜNE gitmişti — `vendor/autoload.php` yerine `autoload.php` —
fark edilip düzeltildi). Yeni zip'te 045/046/047/048 migration'ları, `checks_notes_lib.php`,
`check_note_view.php`, `mobile/check_note_view.php`, `assets/js/ds-foundation.js`,
`vendor/autoload.php` doğru yollarda doğrulandı; `config.php` SIZMADI (sadece `config.sample.php`
var). `~/Desktop/PRIMAC-GUNCELLEME/guncelleme.zip` bu yeni zip ile DEĞİŞTİRİLDİ, yüklemeye hazır.

**⚠️ primac.tr'de migrate.php'nin GERÇEKTEN çalıştırılıp çalıştırılmadığı HÂLÂ TEYİT EDİLEMEDİ** —
bu ortamdan primac.tr'ye SSH/cPanel/admin oturumu erişimi yok. Salt-okunur kontrol: site erişilebilir
(200), `https://primac.tr/migrate.php` ve `https://primac.tr/guncelle.php` sunucuda MEVCUT (ikisi de
200 dönüyor, key olmadan migrate.php beklendiği gibi "Yetki yok" diyor) — ama bu dosyaların hangi
zip sürümünden geldiği ve DB'de hangi migration'ların uygulandığı buradan görülemiyor. **Yeni oturum/
Product Owner**: tazelenmiş zip'i primac.tr'ye (cPanel File Manager) yükleyip
`https://primac.tr/guncelle.php` çalıştırmalı (`NASIL.txt`'deki 3 adım) — idempotent olduğu için
daha önce kısmen uygulanmışsa bile zarar vermez.

**⚠️ Ayrı bulgu — Product Owner'a sorulmalı**: lokal `/Users/acans/ACANS-OTS/config.php`'de
`db_name` = `u7883898_primacos` — bu isim `CLAUDE.md`/`memory/deploy.md` tablosunda PROD
(acanstr.com/ots) veritabanı olarak kayıtlı, `db_host` ise `localhost`. Yerel makinede 3306 portunda
gerçekten bir MySQL çalışıyor, muhtemelen bu sadece PROD şemasının/adının lokale klonlanmış bir
kopyası (host `localhost` olduğu için teknik olarak bu makineden başka bir yere gitmiyor) — ama isim
çakışması yüzünden bu oturumda güvenlik sınıflandırıcısı bu DB'ye SELECT sorgusu atmamı bile
engelledi. Bu DB'nin gerçekten sadece lokal bir kopya mı yoksa yanlışlıkla gerçek PROD kimlik
bilgileri mi olduğu Product Owner tarafından doğrulanmalı — dokunulmadı.

## 🔴 CONTEXT HANDOFF — 2026-07-18 (context limiti doldu, yeni oturum bu bloktan devam etsin) [ÖNCEKİ TUR]

**Son commit:** `9700a31` (main, origin ile senkron, working tree TAMAMEN TEMİZ).
**Commit aralığı bu oturumda:** `4185989..9700a31` (10 commit, 38 PHP dosyası + JS/CSS, hepsi
`php -l` temiz, hiçbir yarım/bozuk dosya YOK).

### Genel ilke (değişmedi)
EVOLUTION, NOT REVOLUTION. DB/route/iş akışları/stok-cari-finans matematiği korunarak kademeli
geliştirme. PRIMAC OTS = şirketin günlük Operation OS'u, sıradan ERP değil. Web+mobil TEK ÜRÜN
hissi vermeli. Kesin UI kuralı: kullanıcıya görünen eski/legacy OTS ekranı YOK, Design System tek
görsel dil. Çalışma şekli: kısa/doğrudan görevler, gereksiz audit yok, uygula→test et→commit→push→
devam et, ~5 anlamlı işte tek toplu rapor, SADECE veri kaybı/migration/güvenlik/mimari Product
Owner kararı gerektiren durumlarda dur.

### ⚠️ KRİTİK UYARI — "Tamamlandı" raporuna güvenme
Bu oturumdaki TÜM işler kod seviyesinde tamamlandı, `php -l` temiz, CPA ve çek/senet için manuel
senaryo izi (code trace) ile doğrulandı — **ama bu ortamda gerçek tarayıcı/mobil cihaz/canlı DB
testi YAPILAMADI.** Örnek: bir önceki oturumda "Legacy UI Temizliği tamamlandı" + "İletişim
Merkezi tamamlandı" raporu verilmişti, ama kullanıcı gerçek cihazda test edince mobilde TÜM SAYFA
yatay kayan bir hata bulundu (P0 olarak bu oturumda düzeltildi, commit `db4ad66`). Yeni oturum
"commit var" görünce tamam saymasın — mümkünse gerçek UI/tarayıcı ile doğrulasın, değilse bunu
açıkça Product Owner'a bildirsin.

### Durum tablosu (Product Owner'ın istediği 10 başlık)

| # | Konu | Durum | Not |
|---|---|---|---|
| 1 | P0 Talep Yönlendirme | ✅ (önceki oturum) | Bu oturumda dokunulmadı — REOPEN backlog'a göre 2026-07-07'de kapatıldı. Yeni oturum gerçek kodla (mobile talep/request atama akışı) teyit ETMEDEN "tamam" saymasın. |
| 2 | Yetki/Güvenlik/Auth (P0-AUTH-01/02, mükerrer hesap, stale user_id, id=1 koruması) | ✅ (önceki oturum + bu oturumda kod incelemesiyle DOĞRULANDI) | `personnel_lib.php::personnel_create_login()/personnel_reset_password()/personnel_update_account_role()` okunup korumaların (mükerrer hesap engeli, stale user_id, deterministik şifre hedefi, id=1) hâlâ doğru çalıştığı teyit edildi — YENİ bir şey yazılmadı, sadece doğrulandı. |
| 3 | Design System / Legacy UI | ✅ kod tamam | Önceki oturum "11 gerekçeli istisna" ile kapatmıştı. Bu oturumda YENİ eklenen tüm ekranlar (WhatsApp, CPA, personel kimlik başlığı, Home, çek/senet) DS uyumlu yazıldı. Gerçek ekran taraması yapılmadı — yeni oturum şüpheliyse `grep` tabanlı legacy-class taramasını tekrar çalıştırabilir (yöntem: script yaklaşımı önceki oturum özetinde var). |
| 4 | İletişim Merkezi + WhatsApp | ✅ kod tamam, ⚠️ TEST BEKLİYOR | Bu oturumda: sekme sırası Sohbetler\|WhatsApp\|Bildirimler\|Taleplerim\|Duyurular (`share_lib.php::ic_tabs()`), WhatsApp ekranları (`wa_conversations.php`/`wa_conversation_view.php`/mobil eşdeğerleri) messages.php'nin DS diline taşındı, sol menüde İletişim Merkezi artık 5 kategoriyle (İşler/Ticaret/Üretim & Stok/Finans/Yönetim) AYNI `.df-rail-cat-btn` kutu stilini kullanıyor. **P0 mobil yatay taşma bugfix** (`.df-tabs` artık kendi içinde kayıyor, sayfa değil — `db4ad66`) — kullanıcı video ile bildirmişti. WhatsApp Toplu Gönderim (`wa_send_now.php`) nav'da Yönetim'den İletişim Merkezi'ne taşındı (`f4e08a3`). Gerçek cihazda TEST EDİLMEDİ. |
| 5 | Personel + OTS Hesabı + Yetki Tek Merkez | ✅ kod tamam, ⚠️ TEST BEKLİYOR | `personnel.php` (kartlı ana ekran) zaten doğruydu, DEĞİŞMEDİ. `personnel_edit.php`: sticky kimlik başlığı (avatar+ad+rol+Aktif/Pasif+OTS hesap rozeti) eklendi, sekmeler GENEL→OTS HESABI & YETKİLER→GÖREVLER→PERFORMANS önceliğine alındı (Takvim/Mesajlar/Notlar/Dosyalar/Maaş/Hareket SİLİNMEDİ, ikincil sırada kaldı — zaten çalışan işlevsellik). `mobile/personnel_view.php` aynı sekmeli yapıya (Genel/OTS Hesabı & Yetkiler/Görevler/Performans) dönüştürüldü. `nav_lib.php`: "Kullanıcı ve Yetkileri Yönet" → "Sistem Kullanıcıları", adminOnly, Yönetim kategorisinin sonuna taşındı (`users.php` SİLİNMEDİ, sadece nav'dan demote edildi + `is_admin()` kapısı sertleştirildi). Commit `3796f3f`. |
| 6 | Mobil Menü (kategori/niyet bazlı IA) | ✅ (önceki oturum, NAV-001B) | Bu oturumda dokunulmadı — `nav_lib.php::nav_grouped_for_launcher()` zaten is_takip/sat_tahsil/stok/iletisim/yonet gruplarıyla çalışıyor, alt nav Ana/İş/Cari/İletişim/Menü zaten kurulu. Yeni oturum gerçek mobil ekranda teyit edebilir. |
| 7 | CPA (Customer Procurement Allocation) | ✅ kod tamam (A+B katmanı), ⚠️ TEST BEKLİYOR, **migrate.php ÇALIŞTIRILMALI** | A) `cpa_preferences` (045, önceki oturum) — tercih edilen tedarikçi. B) `cpa_allocations`+`cpa_allocation_consumptions` (046+047, BU oturum) — miktarsal müşteri tahsisi (`cpa_allocation_lib.php`, `cpa_allocation.php`+mobil, `contact_view.php`/`product_view.php` "Tahsisli Stok" bölümleri). **P0 veri bütünlüğü kapatıldı** (`1f8a897`): satış düzenleme/silme artık `stock_lib.php::stock_update_sale()/stock_reverse_sale()` içinden `cpa_alloc_reverse_for_sale()`/`cpa_alloc_consume_for_sale()` ile TEK ortak noktadan (web+mobil+sil.php+trade_core.php hepsi buradan geçer) tüketimi geri alıp yeniden uyguluyor — çift tüketim/çift iade YOK (ledger-sil bazlı idempotent). Migration artık TEK şema otoritesi — `cpa_allocation_lib.php` runtime'da CREATE TABLE YAPMIYOR, **primac.tr'de migrate.php çalıştırılmadan bu özellik kullanılamaz** (yazma fonksiyonları açık hata verir). Senaryo (2500 alış→1000 tahsis→400 satış→700'e düzenle→sil) elle kod izi ile doğrulandı, gerçek DB'de DOĞRULANMADI. |
| 8 | Home Final UX | ✅ kod tamam, ⚠️ GÖRSEL DOĞRULAMA BEKLİYOR (Product Owner "web 1440px + mobil 390px doğrula" istedi, YAPILAMADI) | Tekrarlı sarı Nabız banner'ı kaldırıldı, yerine rol/yetkiye göre filtrelenmiş "BUGÜN" durum kartları (Kritik Stok/Açık-Geciken Görev/Bekleyen Talep — sıfırsa gizli) geldi. Web: BUGÜN→Hero→Hızlı İşlemler→Devam Et→2 kolon (Bugünün Akışı\|Bekleyenler)→Genel Bakış (admin-only,kapalı). Mobil: en önemli BUGÜN kartı tam genişlik+kalanı kompakt, "Bekleyenler" ayrı kolon yerine "Bugünün Akışı" başlığı altında birleşti. Yeni business logic YOK — `home_lib.php`'nin var olan fonksiyonları (`home_build_queue`/`home_build_continue`/`home_build_overview`/`task_my_stats`) yeniden düzenlendi. Commit `42947f1`. `dashboard.php`'nin `$__navMode==='legacy'` dalı (eski "Komuta Merkezi") DEAD KOD — nav_effective_mode() hep 'compact' döndürüyor, dokunulmadı (bilinçli). |
| 9 | Çek/Senet Finansal Yaşam Döngüsü | ✅ kod tamam, ⚠️ TEST BEKLİYOR, **migrate.php ÇALIŞTIRILMALI** | `checks_notes_lib.php`: `checks_notes_collect()`(Tahsil Et)/`pay()`(Öde) — seçilen kasa/banka hesabına gerçek hareket (`contact_id=NULL`, cari İKİNCİ KEZ etkilenmiyor). `endorse()`(Ciro Et) — kasa/banka hareketi YOK, sadece ciro edilen tedarikçinin borcu kapanıyor (ciro_contact_id ile zincir izlenebilir). `bounce()`(Karşılıksız)/`cancel()`(İptal) — orijinal kabul hareketini geri alıp müşteri borcunu yeniden açıyor (`checks_notes_reverse_finance()`, silmedeki AYNI fonksiyon). Durum makinesi: SADECE 'portfoyde'den aksiyon alınabilir, final durum değişmez, `checks_notes_update()` artık status kabul etmiyor (önceki kritik açık: serbest "Durum" dropdown'ı hiç para hareketi olmadan "Tahsil Edildi" yazdırabiliyordu — KAPATILDI). Silme sadece finansal olarak dokunulmamış kayıtlarda (`checks_notes_can_delete()`). Yeni `check_note_view.php` (web, YOKTU, oluşturuldu) + `mobile/check_note_view.php` (güncellendi) — durum makinesi butonları + hareket geçmişi zaman çizelgesi. Migration 048 (`settle_date`/`settle_account_id`/`settle_finance_movement_id`/`ciro_contact_id`/`ciro_finance_movement_id`/`settle_notes`) — **primac.tr'de migrate.php çalıştırılmadan yeni kolonlar yok, tahsil/ciro/öde SQL hatası verir.** Commit `1f19528`. |
| 10 | Navigation / Accordion State | ✅ kod tamam, ⚠️ TARAYICI TIKLAMA TESTİ BEKLİYOR | Kök neden bulundu ve düzeltildi: `assets/js/ds-foundation.js`'deki `dfRailToggle()` bir sessionStorage katmanı taşıyordu — kategori-dışı (Ana Sayfa/İletişim Merkezi) sayfalarda ÖNCEKİ sayfadan kalma kategoriyi client-side yeniden açıyordu (sunucu doğru şekilde kapalı bassa bile). sessionStorage TAMAMEN kaldırıldı — durum artık SADECE sunucunun `layout_top.php::$__catHasActive` hesaplamasından geliyor (tek merkezi state). Commit `9700a31`. |

### ⚠️ Ayrıca Product Owner'a bildirilecek (bu oturumun kapsamı DIŞINDA bırakıldı, silinmedi/değiştirilmedi)
1. **CPA — alış silme/düzenleme ile tahsis referansı**: bir alışın (satın alma) CPA tahsisi varken
   silinmesi/düzenlenmesi bu turda ele alınmadı (kapsam: sadece SATIŞ tarafı istenmişti). Mevcut
   `stock_purchase_avg_cost_safe()` güvenlik kapısı (bu üründe sonraki herhangi bir stok hareketi
   varsa alış silinemez/düzenlenemez) dolaylı koruma sağlıyor ama tüketilmemiş bir tahsis varken
   hiç satış olmadan alış silinmesi durumu ele alınmadı.
2. **Çek/Senet — kabul-anı cari işareti şüphesi**: `checks_notes_sync_finance()`'in (çek/senet
   KABUL edilince oluşan cari kapama hareketi, önceki oturumdan) işareti `contact_balance_case_sql()`
   üzerinden incelendi — `movement_type='cek_senet'` 'normal'/'mobile' listesinde olmadığı için
   ELSE dalına düşüyor (satış/alış gibi "borç ARTIRAN" işaret alıyor), oysa kod yorumu ("Alınan =
   Tahsilat") ve `status='Tahsil Edildi'` etiketi niyetin "borç AZALTAN" (gerçek Tahsilat gibi)
   olduğunu gösteriyor. Bu MEVCUT (bu oturumdan önceki, muhtemelen 2026-07-03'ten beri var olan)
   bir işaret tutarsızlığı OLABİLİR — ama TÜM geçmiş cari bakiyelerini geriye dönük etkileyeceği
   için SESSİZCE değiştirilmedi. **Product Owner'ın açık kararı gerekiyor**: gerçek bir örnek
   üzerinden (bir müşteriden 1000 TL'lik çek alındığında contact_balance() DOĞRU YÖNDE mi
   değişiyor, +1000 mi -1000 mi?) primac.tr'de canlı veriyle doğrulanmalı.

### Yeni oturumda İLK yapılacaklar (Product Owner'ın kendi sırası)
1. Bu bloğu oku.
2. `git status` + `git log --oneline -15` ile senkron olduğunu doğrula (beklenen HEAD: `9700a31`).
3. **primac.tr'de `migrate.php` çalıştırılmalı** (046/047/048 migration'ları uygulanmadıysa CPA
   tahsis ve çek/senet yaşam döngüsü kullanılamaz).
4. Yukarıdaki 10 maddelik tabloyu gerçek kod/gerçek ekran ile teyit et — "commit var" diye tamam
   sayma.
5. Bu işler bitmeden (özellikle gerçek test/doğrulama) yeni özellik BAŞLATMA.
6. Product Owner GENEL KAPANIŞ RAPORU istediğinde yukarıdaki tabloyu doğrudan kullanabilirsin
   (✅/⚠️/❌/🐛 formatı zaten hazır).


## RELEASE 0.9 — 2026-07-17 BÜYÜK OTURUM ÖZETİ (P0-AUTH-02, Legacy temizliği, İletişim Merkezi, DS migration dalgası)
Tek oturumda 31 commit. Sırayla: (1) **P0-AUTH-02** — Canan'ın gerçek giriş sorunu bulundu:
users.php'nin "Şifre Sıfırla ve WhatsApp Gönder" özelliği WA gönderimi başarısız olsa bile şifreyi
önceden değiştiriyordu (hesap kilitleniyordu) — DB yazımı artık sadece gönderim başarılıysa
yapılıyor; ayrıca geçersiz iç içe `<form>` yapısı da düzeltildi. Gerçek cihaz testi hâlâ
Product Owner'da bekliyor. (2) **Yetki mimarisi taraması** — 51 web + 70 mobil sayfa tek tek
kontrol edildi, `ics.php`'de gerçek bir açık bulunup kapatıldı. (3) **Legacy navigasyon TAMAMEN
kapatıldı** — `nav_effective_mode()` artık koşulsuz 'compact' döndürüyor, Rail/Topbar (web) ve
yeni bottom nav (mobil) artık herkesin varsayılanı ve TEK seçeneği (Product Owner kararı: "ne
mobilde ne web de eski görünüm görmek istemiyorum"). (4) **Kullanıcı/Yetki akışı birleştirme** —
web personnel_new.php artık mobildeki gibi "aynı işlemde giriş hesabı oluştur" seçeneği sunuyor
(personnel_create_login() ile, mükerrer-hesap korumalı). (5) **İLETİŞİM MERKEZİ** — Sohbetler/
Bildirimler/Taleplerim/Duyurular 4 sekmeli yapı kuruldu (web+mobil, `share_lib.php::ic_tabs()`
ortak şerit) — talep bildirimleri artık sohbete karışmıyor (internal_messages insert'leri
kaldırıldı), Bildirimler/Duyurular ayrımı gerçek veride (target_user_id kişisel/genel) yapıldı,
Taleplerim yeni personel-scoped salt-okunur görünüm (management_requests zaten var olan veri).
(6) **Design System Migration dalgası** — 21 web + 6 mobil dosya (personel ekranları tam, iş/görev/
cari/talep/stok/kullanıcı/finans/muhasebe ekranları tam veya kısmi — JS'e ağır bağımlı satış/alış
formları bilinçli olarak kısmi bırakıldı, risk yönetimi). Her dosya `php -l` ile lint edildi,
kritik/karmaşık olanlar headless Chrome ile görsel doğrulandı. guncelleme.zip defalarca yenilendi.
**Kalan (henüz DS'e taşınmamış):** accounting.php'nin sekme içerikleri, product ekranları, trade
documents, production/assembly/external/design, checks_notes/kasa/finance_accounts/finance_transfer,
ve bunların mobil karşılıkları — bkz. ayrı "DS Migration — kalan ekranlar" maddesi.

## DS Migration — kalan ekranlar (2026-07-17)
Henüz Design System'e taşınmamış (0 df- class kapsamı): `product_new.php`, `product_view.php`
(sadece yetki eklendi, görsel taşınmadı), `product_categories.php`, `product_taxonomy.php`,
`trade_documents.php`, `trade_document_new.php`, `trade_document_view.php`, `production.php`,
`assembly.php`, `external.php`, `design.php`, `work_center.php`, `checks_notes.php`, `kasa.php`,
`finance_accounts.php`, `finance_transfer.php`, `accounting.php`'nin 4 sekme içeriği (Kayıtlar/
Yeni Kayıt/Personel/Özet — sadece başlık+sekme şeridi taşındı), `sales.php`/`purchase.php`'nin
JS'e bağımlı ürün-satırı giriş formları (kasıtlı bırakıldı), `teklif.php`'nin detay/düzenle/yeni
formları (kasıtlı bırakıldı — JS'e bağımlı). Mobil tarafta da (personel hariç) hiçbir ekran henüz
taşınmadı — mobile/jobs.php, mobile/sales.php, mobile/contacts.php vb. hâlâ legacy görünümde.

## İLETİŞİM MERKEZİ — yeni ana modül kararı, mimari analiz bekleniyor (2026-07-17, PRODUCT OWNER KARARI)
USER TEST/ürün değerlendirmesi sonrası navigasyon bilgi mimarisi kararı: "Mesajlar" tek başına
modül olmaktan çıkıp yeni bir ana modül olan **İletişim Merkezi** altında birleşecek — 4 bölüm:
Dahili Mesajlar (mevcut business logic korunur), WhatsApp (mevcut altyapı korunur), Gruplar (ilk
sürümde yeni özellik yok), Bildirimler. Ürün prensibi: Mesaj (kullanıcılar arası iletişim) ile
Bildirim (sistem olayı) aynı kavram değil, bu ayrım korunacak. Sol menüde "Mesajlar" yerine
"İletişim Merkezi", Home Hızlı İşlemler'e de kısayol eklenecek (yetkiye göre ilgili bölümü açar).
Gelecek mimari: Telegram/E-posta/SMS/Teams gibi kanalların da aynı yapı altında barınabilmesi.
**Şu an istenen: kod yazmadan önce 7 başlıklı mimari analiz raporu** (mevcut veri yapısı, ortak
kullanılabilecek bileşenler, ayrılması gereken business logic, birleştirilebilecek ekranlar,
web+mobil bilgi mimarisi, Release 0.9 kapsamı, Release 1.0+ öneriler) — Product Owner onayından
sonra implementasyona geçilecek. **P0-AUTH-01/Release 0.9 P0 kapısı nedeniyle bu, analiz raporu
teslimiyle sınırlı; implementasyon P0 kapısı kapanmadan başlamayacak.**

## FAZ 2C-ii — Home v2 — DEV PASS, Product Owner onayı bekleniyor (2026-07-17, KOMUT 5)
Onaylanmış IA/wireframe'e (A-F) göre kodlandı — commit `c5870c4`. 5 bölüm: Nabız/Kritik Uyarılar
(`df-alert`, artık mobilde de çalışıyor), Queue/Sırada (değişmedi), Hızlı İşlemler (yeni kompakt
chip satırı, yetkisiz aksiyon `dashboard_quick_actions_split()`'te zaten filtreleniyor), Devam Et
(+ "Son Görev" — `home_build_continue()`'a opsiyonel `$pid` parametresiyle izole eklendi), Genel
Bakış (yalnızca Admin, varsayılan kapalı accordion, yeni matematik icat edilmedi — her sorgu
projede zaten var olan denetimden geçmiş bir ekrandan kopyalandı). Legacy mod (web+mobil) birebir
korundu — `mobile/index.php`'de Nabız satırının render'ı legacy/compact olarak ayrıştırıldı, git
diff ile legacy dalın byte-identical kaldığı doğrulandı. Rollout (F) için kod değişikliği
gerekmedi — `nav_effective_mode()` zaten pilot personeli admin ile aynı compact Home'a yönlendiriyor.
29/29 DB-free veri testi + web/mobil (320/390/430px) görsel doğrulama + Ece (kod)/Selin (güvenlik)/
Elif (parite) bağımsız incelemesi — üçü de PASS/tutarlı, kritik/yüksek bulgu yok (yalnızca 2 düşük
öncelikli, aksiyon gerektirmeyen not: `home_build_overview()`'da savunma-derinliği eksikliği,
`is_admin()`/mobil `$isAdmin` tanım farkı — canlıda davranış farkı yaratmıyor). Tam rapor:
`~/Desktop/PRIMAC-OTS-FAZ2C-ii-Home-DEV-PASS.pdf`.

**DEV PASS ONAYLANDI (2026-07-17), ama USER TEST PASS VERİLMEDİ (2026-07-17, Product Owner kararı
— "PRODUCT OWNER KARARI — FAZ 2C-ii USER TEST SONUCU").** Gerçek kullanıcı testinde 7 P0 blocker
bulundu (sebep kod kalitesi değil, ürünün aktif kullanıma henüz hazır olmaması): (1) Nabız pasif
banner, aksiyon üretmiyor — tıklanabilir aksiyon kartına çevrilecek; (2) Home'dan açılan ekranların
önemli kısmı hâlâ legacy — Release 0.9 tamamlanana kadar günlük kullanılan tüm ekranlar DS'e
taşınacak; (3) Personel ekranları aktif kullanım için kabul edilebilir değil, en yüksek önceliğe
alındı; (4) Personel/Kullanıcı/Yetki oluşturma tek akışa birleştirilecek; (5) yetki sistemi gerçek
testte beklendiği gibi davranmadı, personel görmemesi gereken alanları görebiliyor — web+mobil
tüm modül görünürlükleri (sunucu tarafı dahil) yeniden denetlenecek; (6) **P0-AUTH-01** — personel
yeni şifreyle giriş yapamıyor (bkz. aşağıdaki ayrı madde, DÜZELTİLDİ); (7) mobil alt navigasyon
dokunma hassasiyeti yetersiz. **FAZ 2C-ii henüz CLOSED değil.** Yeni Release 0.9 öncelik sırası:
P0=Authentication + Personel ekranları + Personel→Kullanıcı→Yetki + yetki doğrulaması + mobil
navigasyon + Home aksiyon iyileştirmeleri; P1=Web&Mobil DS Migration (günlük ekranlar); P2=Customer
Procurement Allocation; P3=Matematik/Veri Bütünlüğü Final Audit; P4=Pilot kullanıcı yayını. İlk iş:
sadece P0 maddeleri için MASTER AUDIT (kök neden/etkilenen dosyalar/mimari/risk/çözüm/sıra) —
henüz kod yazılmayacak, P0-AUTH-01 hariç (o ayrıca acil olarak işaretlendi, aşağıda).

## P0-AUTH-01 — Personel yeni şifreyle giriş yapamıyor — KOD DÜZELTİLDİ, gerçek DB doğrulaması bekleniyor (2026-07-17)
USER TEST'te bulunan P0 blocker madde 6. **Kök neden (kod incelemesiyle bulundu):** bir personele
ikinci bir `app_users` hesabı daha açılmasını engelleyen bir kontrol yoktu
(`personnel_lib.php::personnel_create_login()`), bu da personelin iki hesaba bağlı kalmasına yol
açabiliyordu. Admin'in "Şifre Sıfırla" özelliği hedef hesabı `ORDER BY` içermeyen bir
`LEFT JOIN ... OR ... LIMIT 1` sorgusuyla buluyordu — "şifre güncellendi" mesajı gösterilse bile
MySQL personelin gerçekte kullandığı hesap yerine eski/kullanılmayan bir hesabı seçip
güncelleyebiliyordu (tamamen sessiz, hiçbir hata üretmiyor). Self-servis şifre değiştirme
(`profile.php`/`mobile/profile.php`) etkilenmedi — o akış her zaman oturum id'siyle çalışıyordu.

**Düzeltme (commit `91d0567`):** `personnel_lib.php::personnel_create_login()` artık personele
zaten bağlı bir hesap varsa (sahiplik doğrulamalı) ikinci hesap açılmasını engelliyor;
`personnel_reset_password()` hedefi artık tek deterministik kaynaktan çözüyor
(`personnel.user_id`, varlığı VE hâlâ bu personele ait olduğu doğrulanarak; yoksa `personnel_id`
üzerinden en eski kayda güvenli fallback). `mobile/personnel_view.php`'nin bağımsız kopyasına aynı
düzeltme + görüntüleme sorgusu da aynı deterministik mantığa çekildi. `users.php`'nin "Yeni
Kullanıcı" akışı da (Ece'nin re-review'ında bulunan ikinci giriş noktası) aynı korumayı aldı ve
artık `personnel.user_id`'yi senkron tutuyor. Migration yok. Gerçek PDO (SQLite bellek-içi) ile
20/20 senaryo PASS (mükerrer hesap, taşınmış hesap, yetim user_id, normal tek-hesap regresyonu
dahil). Ece (kod) + Selin (güvenlik) bağımsız incelemesi PASS (Ece'nin 2 bulgusu — guard'ın yetim
id doğrulamaması, `users.php`'nin korumasız olması — aynı turda kapatıldı).

**Bekleyen (Claude'un erişimi dışında):** primac.tr'nin gerçek DEV verisinde bu mükerrer/tutarsız
kayıt deseninin fiilen var olup olmadığını doğrulamak ve gerçek bir personel hesabıyla uçtan uca
web+mobil login testi — ikisi de Product Owner/test ekibi tarafından çalıştırılacak (Claude'a
gerçek kimlik bilgisi hiçbir zaman verilmiyor — bkz. [[feedback_security_sprint_test_method]]).
Salt-okunur teşhis scripti + adım adım test paketi hazır: `~/Desktop/
PRIMAC-OTS-P0-AUTH-01-Dogrulama-Paketi.pdf`, script `~/Desktop/PRIMAC-GUNCELLEME/
_TEMP_p0auth_diag.php` (primac.tr'ye geçici yüklenip çalıştırılıp SONRA silinecek).

**Gelecek için not (şimdi yapılmadı — migration gerektirir):** Ece ve Selin ikisi de
`app_users.personnel_id` üzerinde DB-seviyeli `UNIQUE` kısıt olmadığını, uygulama-seviyesi guard'ın
teorik bir TOCTOU yarış durumuna (çift tıklama) karşı tam koruma sağlamadığını not etti — düşük
risk (sadece güvenilir admin tetikleyebilir) ama kalıcı çözüm için gelecekte bir migration (Burak)
önerilir: `ALTER TABLE app_users ADD UNIQUE KEY uniq_personnel (personnel_id)` (nullable kolonda
MySQL birden çok NULL'a izin verdiği için güvenli).

## ACİL HOTFIX PAKETİ — KAPANDI (2026-07-17, PRODUCT OWNER KARARI — R0.9 hotfix kapısı)
Master Envanter'in bulduğu 2 güvenlik açığı + 1 sessiz şema hatası, Home implementasyonuna (KOMUT 5)
geçmeden önce ayrı bir kapı olarak kapatıldı — **3/3 hotfix PASS**, 58/58 test, Ece+Selin bağımsız
incelemesi, 3 ayrı commit (`14f1485`/`b198be8`/`9404228`), migration yok. Tam detay → `memory/bugs.md`
"ÇÖZÜLDÜ — ACİL HOTFIX PAKETİ". Rapor: `~/Desktop/PRIMAC-OTS-Acil-Hotfix-DEV-PASS.pdf`.
İki küçük takip maddesi KNOWN_BUGS.md'ye yeni madde olarak eklendi (7: `permissions[]` whitelist
eksikliği MEDIUM, 8: requests.php liste-okuma catch bloğu LOW) — bilinçli olarak bu hotfix'in dışında.
**Gate kapandı: Product Owner onayı sonrası doğrudan KOMUT 5 / FAZ 2C-ii Home implementasyonuna geçilir.**

## MASTER ENVANTER & RELEASE 0.9 PLANI (2026-07-17, PRODUCT OWNER KARARI — çalışma modeli değişti)
Product Owner kararı: geliştirme mantığı "yeni özellik" değil "PRIMAC OTS'yi aktif kullanıma hazır hale
getirme" oldu (Evolution not Revolution, Business Logic korunur, Audit→Kod→DEV PASS→USER TEST→CLOSED
zorunlu, her faz Product Owner onayıyla kapanır). Bu doğrultuda kod yazılmadan önce **tam kapsamlı
Master Envanter** hazırlandı: 14 kategori (Design System/Personel-Kullanıcı-Yetki/Satış/Satın Alma/
Cari/Stok/Finans/Mesajlaşma/Bildirim/Raporlama/UX/Güvenlik/Teknik Borç/Ertelenmiş Backlog), ~70 madde,
her biri açıklama/mevcut durum/risk/bağımlılık/öncelik/büyüklük/Release alanlarıyla. Kaynak: memory/
backlog.md + memory/bugs.md + KNOWN_BUGS.md + ROADMAP.md + VERSIONING.md + git log taraması — Güvenlik
kategorisindeki her madde ayrıca **kodda satır numarasıyla yeniden doğrulandı** (bazı takip dosyaları
2026-07-05/14'ten beri güncellenmemiş, stale çıktı).
**Doğrulamada bulunanlar:**
- **AÇIK, gerçek — GUV-01**: `accounting.php:9,138,140,141,154-157` — `$_GET['tab']` h() olmadan href'e
  basılıyor, yansıtılmış XSS. Acil bugfix önerisi (R0.9, bağımsız).
- **AÇIK, gerçek — PKY-03/GUV-02**: `users.php:42-44` — `users` yetkili admin-olmayan biri `role` POST
  alanıyla herhangi bir kullanıcıyı (uid=1 hariç) admin'e yükseltebiliyor.
- **MUHTEMELEN ÇÖZÜLDÜ, doküman güncel değil**: GUV-07 (`ajax_dashboard_order.php` CSRF — artık
  `boot.php:529` enforced listede), GUV-09 (session fixation — `session_regenerate_id` index.php:63/
  boot.php:467'de var), GUV-10 (CSRF genel — SECURITY SPRINT-004 zaten 57 sayfayı kapsıyor). Üçü de
  KNOWN_BUGS.md'de hâlâ "açık" görünüyor — R1.0'da doküman temizliği yapılacak.
- **TBR-08 (yeni bulgu)**: `requests.php`/`mobile/requests.php` `management_requests.manager_note`'a
  yazıyor ama gerçek kolon `response_note` — güncelleme sessizce başarısız oluyor (FAZ-5D CSRF
  turunda bulunmuş ama hiç kapatılmamış, ROADMAP.md'de kayıtlıydı).

**Release Planı** (teknik bağımlılığa göre gerekçelendirildi):
- **R0.9** (aktif kullanıma hazır): Home (FAZ 2C-ii) → Web&Mobil DS Migration (FAZ 2C tam + FAZ 2D 20
  ekran) → Personel→Kullanıcı→Yetki (+ PKY-03 güvenlik açığı erken kapatılır) → Customer Procurement
  Allocation → Matematik/Veri Bütünlüğü kapsamlı denetim → Pilot Kullanıcı Testi.
- **R1.0**: Stabilizasyon — küçük/izole düzeltmeler + doküman temizliği (GUV-07/09/10) + R/2b USER TEST.
- **R1.1**: Kullanıcı talepleri — bilinçli ertelenmiş orta boyutlu maddeler (Purchase&Sales 2.0 dahil).
- **R1.2**: Yeni modüller — Workspace/Multi-Tenant gibi ayrı proje ölçeğinde kararlar.

**Customer Procurement Allocation — mimari öneri (KOMUT 4, kod YOK):** Yeni additive `stock_allocations`
tablosu (`purchase_movement_id`, `stock_item_id`, `contact_id` NULL=Genel Stok, `allocated_qty`,
`consumed_qty`, `status`) — mevcut `stock_movements`/`stock_items.quantity` matematiğine SIFIR
değişiklik, `stock_create_purchase()`/`stock_create_sale()`'e dokunulmuyor. Tahsis salt rezervasyon/
izlenebilirlik defteri; bir alım satırı birden çok cariye/satışa bölünebiliyor (`consumed_qty` her
tahsis satırında ayrı takip edilir). Reddedilen alternatif: `stock_items`'a `contact_id` eklemek —
stok'un TEK gerçek kaynak olma özelliğini bozar, yüzlerce mevcut sorguyu etkiler.

Tam rapor (tüm 70 madde + gerekçe + mimari diyagram): `~/Desktop/PRIMAC-OTS-Master-Envanter-Release-Plani.pdf`.
**Sıradaki adım (KOMUT 5): Release 0.9 uygulaması, öncelik sırasıyla — 1) Home, 2) DS Migration,
3) Personel→Kullanıcı→Yetki, 4) Customer Procurement Allocation, 5) Matematik/Veri Bütünlüğü,
6) Pilot Kullanıcı Testi. Her faz kendi Audit→Kod→DEV PASS→USER TEST→CLOSED kapısından geçecek.**

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
belirlenecek. **Açık gate: bu IA kararı gelmeden 2C-ii'nin implementasyonuna geçilmeyecek.**

**IA + Wireframe teslim edildi (2026-07-17), kod YOK.** Product Owner'ın onayladığı "queue-first,
Operasyon Merkezi" yönü 5 bölümlük IA'ya döküldü: 1) Nabız/Kritik Uyarılar (`df-alert`), 2) Queue/
Sırada (`df-home-hero`/`df-home-qlist`, değişmedi), 3) Hızlı İşlemler (YENİ kompakt chip satırı,
legacy buton duvarı yerine), 4) Devam Et (`df-home-continue`, "Son Görev" veri eksik), 5) Genel
Bakış (yalnızca Admin, `ds_accordion_item()` ile katlanabilir, Home'un ilk görünümüne hakim değil).
Mobil (390px) + web (Rail/Topbar bağlamında) wireframe'ler gerçek `ds-foundation.css`/`ds_lib.php`
ile render edildi. **Uygulama öncesi kapatılması gereken 3 boşluk bulundu (hepsi implementasyonda
kapatıldı, aşağıya bakın):** (1) `df-alert`/`df-accordion` CSS'i yalnızca web'in `body.nav-compact`
kapsamında — 2C-i'nin izole ettiği mobil `body.mob-compact`'a genişletilmesi gerekiyor, (2)
`home_build_continue()`'a "Son Görev" eklenmesi gerekiyor (küçük, izole), (3) df-native bir KPI kart
bileşeni yok (`ds_kpi_card()` hâlâ eski "ds-" isim alanında) — mini-stat satırı önerildi. Tam rapor:
`~/Desktop/FAZ2C-ii-Home-IA-Wireframe.pdf`. Ana bulgu: Home'da 3 varyant var — Legacy-Admin (KPI grid+Hızlı İşlemler+ay
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
