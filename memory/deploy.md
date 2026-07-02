# Deploy Prosedürü

## Ortamlar (2026-07-02 itibarıyla güncel)
| Ortam | URL | DB | Not |
|---|---|---|---|
| **ACANS prod** | acanstr.com/ots/ | u7883898_primacos | Artık tek ACANS canlısı. |
| **PRIMAC prod** | primac.tr | KENDİ AYRI DB'si (2026-07-02'de kullanıcı teyit etti) | İkinci marka, ayrı domain, ayrı veri. |
| Lokal | localhost:8080 | lokal config.php | |

DB'ler ayrı olduğu için `app_settings` (brand_settings.php logo/marka ayarları) iki domain arasında
PAYLAŞILMIYOR — her domain kendi logosunu/ayarını bağımsız yönetir, çapraz etkileşim riski yok.

**acanstr.com/erp/ KALDIRILDI** — eskiden ayrı bir prod yoluydu, artık gereksiz; ACANS canlısı
acanstr.com/OTS/ üzerinden yürütülüyor. Eğer sunucuda hâlâ `/erp/` klasörü duruyorsa bir sonraki
deploy'da silinmesi gerekiyor (bkz. [[backlog]]).

**Plan**: 2026-07-02'deki toplu düzeltmeden sonra HEM acanstr.com/ots HEM primac.tr'ye son bir ortak
yükleme yapılacak. Bu yüklemeden sonra bir süre sadece acanstr.com/ots geliştirilecek, primac.tr
güncellenmeyecek — yani gelecekteki değişiklikler iki domain'e eş zamanlı gitmeyebilir, deploy öncesi
hangi domain(ler)in hedeflendiği teyit edilmeli.

## Adımlar (gerçek mekanizma — 2026-07-02'de Masaüstü'ndeki GUNCELLEME klasörlerinden doğrulandı)
Gerçek deploy aracı repo içinde DEĞİL — kullanıcının Masaüstü'nde, domain başına ayrı bir klasörde:
`~/Desktop/ACANS-GUNCELLEME/` ve `~/Desktop/PRIMAC-GUNCELLEME/`. Her biri: `guncelleme.zip` (repo'nun
güncel export'u), `guncelle.php` (tek-tık güncelleyici), `config.php` (o domain'in gerçek DB bilgisi).

1. Yerelde repo güncellenince bu iki klasördeki `guncelleme.zip` YENİDEN üretilmeli (repo'nun taze bir
   zip'i) — eski zip otomatik güncellenmiyor, elle/scriptle tazelenmesi gerekiyor.
2. cPanel File Manager → ilgili site klasörüne (`acanstr.com/ots/` veya `primac.tr/`) `guncelleme.zip`
   + `guncelle.php` yükle. `config.php`'yi SADECE ilk kurulumda veya DB/marka değişikliğinde yükle.
3. Tarayıcıda `https://SITE/guncelle.php` aç. Kendi içinde: zip'i açar → `database/migrations/*.sql`
   dosyalarını (kendi migration runner'ıyla, repo'nun `migrate.php`'sini ÇAĞIRMADAN) sırayla uygular →
   `guncelleme.zip`, `ac.php`, `bitir.php`, `kur.php`, `migrate.php`, `guncelle.php`'nin kendisi dahil
   yardımcı dosyaları siler.
4. Repo'daki ayrı `temizle.php` (install_*.php/kontrol.php/iz.php/bak.php/fix_login.php/ac_extract.php/
   dev_check.php/ac.php/eski not dosyalarını siler) `guncelle.php`'den BAĞIMSIZ bir araç — legacy
   kurulum artıklarını temizlemek için ayrıca (gerekirse) çalıştırılır, her deploy'da zorunlu değil.
5. Lokal geliştirme: `php -S localhost:8080` kök dizinde, `config.php`'yi `config.sample.php`'den kopyala.

Anahtarlar (kod içinde sabit, `~/Desktop/REFERANS/OTS-PROJE-KUNYE.txt`'de de kayıtlı): migrate/temizle
key = `acans-migrate-2026` · cron key = `acans-cron-2026`. Detaylı Masaüstü klasör düzeni ve gerçek DB
bilgileri için REFERANS/OTS-PROJE-KUNYE.txt dosyasına bakın (git'e girmez, sadece Masaüstünde).

## Güvenlik notları
- `config.php` düz metin şifre içerir (normal) ama `.htaccess` ile `config.php`, `*.log`, `guncelleme.zip`,
  `*.sql` uzantıları `Require all denied` ile korunuyor — bu kural her yeni benzer dosya tipinde güncellenmeli.
- `push_subscribe.php` ve poll uçları auth kontrollü.
- Repo'da hiçbir zaman gerçek `config.php`, `vendor/`, `uploads/` veya `*.zip` bulunmamalı (`.gitignore`'da).
