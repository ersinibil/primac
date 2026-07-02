# OTS (Online Takip Sistemi)

## Proje Amacı
Saf PHP ERP. Web masaüstü paneli + mobil PWA (mobile/).

## Teknik Yığın
- PHP **7.2** — `str_contains`, `match`, named args **YASAK**
- MySQL, PDO prepared statements
- Framework yok; composer sadece `minishlink/web-push` için

## Yerel Geliştirme
- `config.sample.php`'yi `config.php` olarak kopyala, kendi DB bilgilerini gir.
- Kök dizinde: `php -S localhost:8080`

## Sunucu / Deploy
| Ortam | URL | DB |
|---|---|---|
| ACANS prod | acanstr.com/ots/ | u7883898_primacos |
| PRIMAC prod | primac.tr | aynı DB (marka: firm_list/brand_settings.php) |
| Lokal | localhost:8080 | lokal config.php |

`acanstr.com/erp/` kaldırıldı — ACANS artık acanstr.com/ots/ üzerinden yürütülüyor.
Detaylı adımlar (migration, temizle.php çalıştırma sırası, iki-domain notu) → `memory/deploy.md`.

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
Bu projeye özel 5 ajan tanımlı, ikisi sürekli güvenlikten sorumlu:
- `ots-feature-dev` — yeni özellik/bugfix (web+mobil parite).
- `ots-db-migration-dev` — migration yazımı/incelemesi.
- `ots-code-reviewer` — bu dosyadaki kurallara uyum incelemesi.
- `ots-security-auditor` — uygulama içi güvenlik (SQLi/XSS/auth) — GÜVENLİK.
- `ots-deploy-security-guard` — sunucu/deploy hijyeni (tanı dosyaları, .htaccess, temizle.php) — GÜVENLİK.

## Dil Tercihi
Türkçe.

## Asla Yapılmayacaklar
- PHP 8 sözdizimi.
- `config.php` commit etme.
- Tanı dosyalarını (kontrol.php, iz.php vb.) sunucuda bırakma.
- Tek .php'ye çok işlev tıkma — lib dosyalarına böl.
- Production'ı lokal test için kullanma.

## Detaylar
Geliştirme geçmişi → `memory/features.md`. Açık işler → `memory/backlog.md`. Bilinen sorunlar →
`memory/bugs.md`. Deploy detayları → `memory/deploy.md`. Yeni bir özellik/oturum tamamlandığında
CLAUDE.md'ye DEĞİL, ilgili memory/*.md dosyasına tarihli bir madde ekle — bu dosya kısa ve güncel kalmalı.
