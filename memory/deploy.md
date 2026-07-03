# Deploy Prosedürü

## Ortamlar (2026-07-02 itibarıyla güncel)
| Ortam | URL | DB | Not |
|---|---|---|---|
| **ACANS prod** | acanstr.com/ots/ | u7883898_primacos | Artık tek ACANS canlısı. |
| **PRIMAC prod** | primac.tr | u7883898_primactr | Kendi ayrı DB'si (2026-07-02'de kullanıcı teyit etti), ikinci marka, ayrı domain, ayrı veri. |
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

## 2026-07-02: Gider kategorisi deploy'u — elle migration + schema_migrations tuzağı
- Commit `26bffcb` (gider kategorisi + marka adı + yetki canlı yenileme) için PHP CLI/sunucu erişimi
  olmayan bir ortamdan `migrate.php` çalıştırılamadığından, kullanıcı migration'ları (022, 023) ACANS'ta
  (`u7883898_primacos`) VE PRIMAC'ta (`u7883898_primactr`) phpMyAdmin → İçe Aktar ile elle çalıştırdı.
- **Tuzak**: `guncelle.php` uygulanan migration'ları `schema_migrations` tablosuyla takip ediyor. Elle
  phpMyAdmin'den çalıştırınca bu tabloya satır düşmüyor — sonraki `guncelle.php` çalıştırmasında aynı
  migration'lar TEKRAR uygulanır. `023`'teki `INSERT IGNORE INTO accounting_categories` satırında
  `name`'e unique kısıt olmadığı için bu, "Personel Yol Gideri"/"Günlük Yemek" kategorilerini
  MÜKERRER ekleyecekti. Çözüm: elle migration çalıştırıldıktan hemen sonra, aynı DB'de:
  ```sql
  CREATE TABLE IF NOT EXISTS schema_migrations(filename VARCHAR(190) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  INSERT IGNORE INTO schema_migrations (filename) VALUES ('<dosya_adi>.sql');
  ```
  çalıştırılıp `guncelle.php`'ye "bu zaten uygulandı" işaretlenmeli. Genel kural: **elle phpMyAdmin'den
  migration çalıştırılan her durumda bu adım de yapılmalı** — aksi halde bir sonraki `guncelle.php`
  çalıştırması sessizce mükerrer veri üretebilir (özellikle unique kısıtı olmayan INSERT'lerde).
- Her iki sitede de `guncelleme.zip` deploy klasörlerinde (`~/Desktop/ACANS-GUNCELLEME/`,
  `~/Desktop/PRIMAC-GUNCELLEME/`) elle tazelenip (`git archive HEAD` + `vendor/` eklenerek) yüklendi,
  `guncelle.php` her ikisinde de "0 yeni migration, 23 toplam" ile temiz çıktı verdi — deploy tamamlandı.

## Git remote (2026-07-03'e kadar yoktu, artık var)
Repo 2026-07-03'e kadar hiçbir uzak sunucuya bağlı değildi (sadece lokal `.git`). Bu tarihte
GitHub'a bağlandı: remote `origin` = `https://github.com/ersinibil/primac.git` (kullanıcının
`ersinibil@gmail.com` hesabı, `gh auth login --web` ile giriş yapıldı, `gh auth switch` ile aktif
hesap yapıldı). Tüm 109 commit'lik geçmiş tek seferde push edildi, `main` branch izleniyor.

**NOT**: Bu GitHub reposu deploy mekanizması DEĞİL — sunucuya yükleme hâlâ yukarıdaki
`guncelle.php`/Masaüstü GUNCELLEME klasörleri yöntemiyle yapılıyor. GitHub sadece kod yedeği/geçmişi
için. Kullanıcı isteği: bundan sonra her commit'ten hemen sonra otomatik `git push origin main` de
yapılmalı (elle istenmeden) — bkz. auto-memory `project_github_remote` kaydı.

## Güvenlik notları
- `config.php` düz metin şifre içerir (normal) ama `.htaccess` ile `config.php`, `*.log`, `guncelleme.zip`,
  `*.sql` uzantıları `Require all denied` ile korunuyor — bu kural her yeni benzer dosya tipinde güncellenmeli.
- `push_subscribe.php` ve poll uçları auth kontrollü.
- Repo'da hiçbir zaman gerçek `config.php`, `vendor/`, `uploads/` veya `*.zip` bulunmamalı (`.gitignore`'da).

## EYLEM GEREKİYOR — VAPID push anahtarı sunucu config.php'lerine eklenmeli (2026-07-03)
5-ajan güvenlik denetiminde bulundu: `push_lib.php`'deki Web Push VAPID private key'i düz metin
kod içine gömülüydü (repo artık GitHub'a bağlı olduğu için bu bir sızıntı riski). `push_lib.php`
artık `app_config()`'ten (`vapid_public`/`vapid_private`/`vapid_subject`) okuyor, config'te
tanımlı değilse ESKİ (hâlâ kodda duran) sabit değerlere düşüyor — yani **hiçbir şey hemen
bozulmaz**, geri uyumlu. Ama kalıcı çözüm için ACANS ve PRIMAC sunucularındaki gerçek `config.php`
(cPanel File Manager, repo dışı, bende erişim yok) dosyalarına şu satırlar EKLENMELİ:
```php
'vapid_public'=>'BKEqJl3sOt2lxHVBXjtCu_nFTCgH42b7NVTjE4BsGq5xC81cdwF1llwIiAmXMbDieoC74QLHZOhZ1dSkgQjLP3c',
'vapid_private'=>'lEr2og5nZs8UfiLd3EJeWAsT0NeSoj9aseWYJtxlusw',
'vapid_subject'=>'mailto:admin@acanstr.com',
```
Bu, MEVCUT anahtarları taşımak içindir (rotasyon değil) — böylece hiçbir kullanıcının push aboneliği
bozulmaz. Gerçek bir key ROTASYONU (yeni anahtar üretme) ayrı bir karar — yapılırsa TÜM kullanıcıların
bildirimleri yeniden açması gerekir, kullanıcıya danışılmadan yapılmadı. Lokal `config.php`'ye zaten
eklendi (gitignore'da, referans için).

## 2026-07-03: Görevlerim/mesajlaşma düzeltmesi sonrası zip tazeleme
Commit `bb8a710` (bkz. [[bugs]] "Web'de bildirime tıklayınca mobile'a zıplama...") için her iki
`guncelleme.zip` (`~/Desktop/ACANS-GUNCELLEME/`, `~/Desktop/PRIMAC-GUNCELLEME/`) `git archive HEAD` +
`vendor/` eklenerek tazelendi, içerik MD5 ile doğrulandı (repo dosyalarıyla birebir eşleşiyor).
**Yeni migration 038 var** — `guncelle.php` çalıştırıldığında otomatik uygulanmalı (elle phpMyAdmin
gerekmiyor bu sefer, CLI/guncelle.php erişimi varsa). Kullanıcı zip'i sunucuya yükleyip
`guncelle.php`'yi çalıştırdıktan sonra hem web hem mobilde gizli sekmede/hard-refresh ile test etmeli
(önceki turda bu adım atlanınca "güncelleme almamış" yanlış izlenimi oluşmuştu — aslında zip
doğruydu, sorun kod tarafındaydı, şimdi giderildi).
