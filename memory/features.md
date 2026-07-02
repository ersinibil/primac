# Özellik Geçmişi

<!-- En yeni en üstte. Tamamlanan özellikler ve mimari kararlar. -->

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
