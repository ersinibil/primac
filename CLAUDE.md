# OTS (Online Takip Sistemi)

## Proje Amacı
Saf PHP ERP. Web masaüstü paneli + mobil PWA (mobile/).

## Teknik Yığın
- PHP **7.2** — `str_contains`, `match`, named args **YASAK**
- MySQL, PDO prepared statements
- Framework yok; composer sadece `minishlink/web-push` için

## Sunucu / Deploy
| Ortam | URL | DB |
|---|---|---|
| Prod | acanstr.com/erp/ | u7883898_primacos |
| Dev/Test | acanstr.com/OTS/ | aynı DB |
| Lokal | localhost:8080 | lokal config.php |

Upload: cPanel File Manager → zip yükle → `ac.php` ile extract.  
Migration varsa `migrate.php` çalıştır → sonra sil.

## Klasör Yapısı
```
ots/
├── mobile/          # Mobil PWA — common.php çatısı (topx/botx/card/mc/mm)
├── database/
│   └── migrations/  # 001..NNN_*.sql  (migrate.php çalıştırır)
├── vendor/          # web-push
├── config.php       # DB bağlantısı — GİT'E GİRMEZ
├── boot.php         # session, require_login(), base_url(), mc(), mm()
└── migrate.php      # migration runner
```

## Kodlama Kuralları
1. PHP 7.2 uyumlu yaz.
2. Tüm SQL: prepared statements.
3. Mobil sayfalar: POST işlemini `topx()` ÖNDEN yap → redirect.
4. Yeni migration: `database/migrations/NNN_aciklama.sql`.
5. İş mantığı `*_lib.php`'de paylaşılır (web + mobil ortak).
6. Tablo/kolon eksikliğine dayanıklı: `IF NOT EXISTS`, `try/catch`.
7. Yeni özellik hem web hem mobilde olmalı.

## Dil Tercihi
Türkçe.

## Asla Yapılmayacaklar
- PHP 8 sözdizimi.
- `config.php` commit etme.
- Tanı dosyalarını (kontrol.php, iz.php vb.) sunucuda bırakma.
- Tek .php'ye çok işlev tıkma — lib dosyalarına böl.
- Production'ı lokal test için kullanma.

## Detaylar
Geliştirme geçmişi, backlog ve deploy notları → `memory/` klasörüne bakın.

## Paylaşım + Teklif + Mesajlaşma Onarımı (2026-06-30) ✅
- share_lib.php: WhatsApp+Mail ortak butonları (wa_link/mail_link/share_buttons/cred_wa). İş (mobil+web), görev (mytasks), personel şifre (WA) paylaşımı. Sadece METİN taşır; PDF gereken yerde rapor PDF paylaşımı.
- Teklif modülü (mobil/teklif.php, 014_quotes.sql): dinamik kalemli form (canlı toplam+KDV), liste, rapor-stili görünüm, PDF/WhatsApp/Mail (report_share.js), durum (Taslak/Gönderildi/Kabul/Red). Admin+personel menüde. Tablo güvencesi var. WEB PARİTESİ: teklif.php (web) + sol menü Teklif bölümü.
- Mesajlaşma çoklu dosya "bağlantı hatası" onarımı: AJAX yanıtında temiz JSON (ob_start + ob_end_clean + display_errors off) — araya giren PHP uyarısı r.json()'u bozuyordu. İstemci artık gerçek hata sebebini gösterir.
- Bu oturum git ile takipte (repo init edildi; config.php/vendor/uploads/*.zip .gitignore'da).

## Teklif PDF + ACANS Logo (2026-06-30) ✅
- PDF tek-sayfa: report_share.js `ACANS_PDF_FIT` modu → belge tek A4'e sığar, ikinci sayfa/uzama yok. Teklif kartından inline min-height kaldırıldı (ekranda kompakt), PDF'de footer A4 altına JS ile sabitlenir. `ACANS_PDF_PAD`/`BG`/`FG` yapılandırılır.
- ACANS marka kırmızısı kurumsal kimlikten örneklendi → #cf3030 (footer lacivert #1b2431).
- ACANS beyaz "A" silüeti: kurumsal PDF kapağından saf-python alfa-maske ile şeffaf PNG'e çıkarıldı (logo_acans_a.png, 5KB). firm_list 'mark' alanı → teklif başlığında kırmızı banda doğrudan beyaz A + firma adı. PRIMAC beyaz çipte kendi logosu.
- Kaynak büyük PDF'ler (47MB+462KB) işi bitince silindi (yer/token tasarrufu).
- Veri temizleme aracı (temizle_veri.php) Sistem menüsünde — canlıya geçiş test verisi temizliği.

## Geciken İş + Teklif Rapor + Takvim + Web Responsive (2026-06-30) ✅
- job_overdue_lib.php: termini geçen tamamlanmamış işler → sorumlu+yöneticilere bildirim/push. boot.php hook (giriş varsa), saatte bir dosya kilidi, iş başına gün/1 kez. Migration 016 (overdue_notified_at).
- report_lib.php: 'teklif' modülü (sayı/tutar/kabul + duruma göre + döküm); web/mobil tab + 'Tümü' otomatik; mobil menüde kart.
- ics.php + job_view (web+mobil) '📅 Takvime Ekle': işi cihaz takvimine (.ics VEVENT + 1 gün önce alarm). Termin varsa görünür.
- Teklif PDF kesin çözüm: footer absolute-bottom + JS ile sabit A4 yükseklik (html2canvas flexbox'ı render etmiyordu) → içerik üst, footer alt, tek tam A4.
- Web responsive: layout_top.php mobilde sol menü hamburger çekmece (☰, soldan açılır, overlay); topbar esnek. Menü solda kalır.
