# Özellik Geçmişi

<!-- En yeni en üstte. Tamamlanan özellikler ve mimari kararlar. -->

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
