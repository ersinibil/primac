# Özellik Geçmişi

<!-- En yeni en üstte. Tamamlanan özellikler ve mimari kararlar. -->

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
