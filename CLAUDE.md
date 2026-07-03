# OTS (Online Takip Sistemi)

## Proje Amacı
Saf PHP ERP. Web masaüstü paneli + mobil PWA (mobile/).

**Çalışma önceliği (2026-07-03'ten itibaren) → `PROJECT_RULES.md`.** Proje aktif geliştirme
aşamasını geçti; öncelik artık stabilite/bugfix/tutarlılık/performans/web-mobil uyumu — yeni
özellik değil. Bu dosyadaki teknik kurallar hâlâ geçerli, `PROJECT_RULES.md` onların üzerine
eklenen çalışma-şekli katmanı.

## Teknik Yığın
- PHP **7.2** — `str_contains`, `match`, named args **YASAK**
- MySQL, PDO prepared statements
- Framework yok; composer sadece `minishlink/web-push` için

## Yerel Geliştirme
- `config.sample.php`'yi `config.php` olarak kopyala, kendi DB bilgilerini gir.
- Kök dizinde: `php -S localhost:8080`

## Sunucu / Deploy — TEK GELİŞTİRME ORTAMI MODELİ (2026-07-03'ten itibaren)
| Ortam | URL | DB | Rol |
|---|---|---|---|
| **Development (DEV)** | primac.tr | u7883898_primactr | TÜM geliştirme + test burada. |
| **Production (LIVE)** | acanstr.com/ots/ | u7883898_primacos | Sadece canlı — geliştirme yapılmaz, dosya güncellenmez. |
| Lokal | localhost:8080 | lokal config.php | |

**acanstr.com/ots'a (PROD) SADECE ayrıca verilecek "DEPLOY MODE" komutuyla dokunulur** — bu komut
gelmeden production dosyaları güncellenmez. Tüm ara geliştirme/test primac.tr (DEV) üzerinde yapılır.
DB'ler ayrı olduğu için bu sadece KOD dağıtım hedefidir, veri taşınmaz. Tam kural seti →
`PROJECT_RULES.md` "Ortam Yönetimi" bölümü.

`acanstr.com/erp/` kaldırıldı — PROD artık acanstr.com/ots/ üzerinden yürütülüyor.
Detaylı adımlar (migration, temizle.php çalıştırma sırası) → `memory/deploy.md`.

## Klasör Yapısı
```
ots/
├── mobile/          # Mobil PWA — common.php çatısı (topx/botx/card/mc/mm)
├── database/
│   └── migrations/  # 001..NNN_*.sql  (migrate.php çalıştırır)
├── memory/          # Backlog/özellik geçmişi/bug/deploy detayları (bkz. memory/MEMORY.md)
├── .claude/agents/  # Kalıcı proje ajanları (bkz. aşağıda)
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

## Kalıcı Proje Ajanları (.claude/agents/)
Bu projeye özel 7 ajan tanımlı, ikisi sürekli güvenlikten sorumlu. Kolay anılsın diye her birine bir
Türkçe takma isim verildi (teknik `name:` alanı kebab-case olarak kalıyor, çağırırken hep o kullanılır
— takma isim sadece sohbette referans içindir):
- `ots-feature-dev` (**Kerem**) — yeni özellik/bugfix (web+mobil parite).
- `ots-db-migration-dev` (**Burak**) — migration yazımı/incelemesi.
- `ots-code-reviewer` (**Ece**) — bu dosyadaki kurallara uyum incelemesi.
- `ots-security-auditor` (**Selin**) — uygulama içi güvenlik (SQLi/XSS/auth) — GÜVENLİK.
- `ots-deploy-security-guard` (**Can**) — sunucu/deploy hijyeni (tanı dosyaları, .htaccess, temizle.php) — GÜVENLİK.
- `ots-parity-auditor` (**Elif**, yeni 2026-07-02) — web+mobil parite ve yetki-tutarlılığı denetimi
  (block_personel()/page_module_map() çakışması, mobil-otomatik-yönlendirme tuzağı).
- `ots-schema-drift-guard` (**Deniz**, yeni 2026-07-02) — kodun varsaydığı tablo/kolonlarla migration'ların
  gerçekten oluşturduğu şema arasında sapma denetimi (ACANS/PRIMAC ayrı DB olduğu için önemli).

## Dil Tercihi
Türkçe.

## Asla Yapılmayacaklar
- PHP 8 sözdizimi.
- `config.php` commit etme.
- Tanı dosyalarını (kontrol.php, iz.php vb.) sunucuda bırakma.
- Tek .php'ye çok işlev tıkma — lib dosyalarına böl.
- Production'ı lokal test için kullanma.
- **"DEPLOY MODE" komutu verilmeden acanstr.com/ots (PROD) dosyalarını güncelleme** (bkz.
  `PROJECT_RULES.md` "Ortam Yönetimi").

## Detaylar
Geliştirme geçmişi → `memory/features.md` (özet → `CHANGELOG.md`). Açık işler → `memory/backlog.md`
(kararı netleşmemişler → `ROADMAP.md`). Bilinen sorunlar → `memory/bugs.md` (hızlı bakış →
`KNOWN_BUGS.md`). Deploy detayları → `memory/deploy.md`. Sürüm durumu → `VERSIONING.md`. Şema
envanteri → `DATABASE.md`. Çalışma önceliği/kuralları → `PROJECT_RULES.md`. Yeni bir özellik/oturum
tamamlandığında CLAUDE.md'ye DEĞİL, ilgili memory/*.md dosyasına tarihli bir madde ekle — bu dosya
kısa ve güncel kalmalı.
