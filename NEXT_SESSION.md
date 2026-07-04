# NEXT_SESSION.md — Bir Sonraki Oturum İçin Başlangıç Referansı

Bu dosya bir "yapılacaklar listesi" değil, bir sonraki oturuma "neredeydik, neye dikkat et" diye
hızlı giriş yapmak için var. Detay için ilgili dosyalara bakın (`CHANGELOG.md`, `ROADMAP.md`,
`KNOWN_BUGS.md`, `VERSIONING.md`, `memory/*.md`).

## Son Tamamlanan İşler
- **Sprint-001** (2026-07-04, commit `0ba36da`): Bildirimler modülünde toplu-silmenin TÜM
  kullanıcıları etkileyen sahiplik açığı kapatıldı (`user_notification_status` tablosu, migration
  039, `notifications_lib.php`); İşlerim/İş Ekle/Kendime İş Ekle tutarlılık düzeltmeleri; kendine-not
  mesaj rozeti sabitlenmesi; emoji buton taşma düzeltmesi. primac.tr'de fiilen test edildi, DEV test
  zip'i (`primactr_dev_sprint001_test.zip`) ve tüm yardımcı teşhis script'leri sunucudan temizlendi.
- **UI/UX Sprinti** (2026-07-04, bu oturumda lokal checkpoint commit edildi): `mobile/common.php`'ye
  design token sistemi (`:root` CSS değişkenleri), paylaşılan toolbar'a global arama çubuğu
  (`#globalSearchInput`, mevcut `search.php`'ye GET ile gidiyor, route/API değişmedi), ana ekran
  kart tutarlılığı (`card()` fonksiyonuna geçiş) + yoğunluk artışı, bildirim test panelinin admin-only
  "Genel Sistem Yönetimi" grubuna taşınması (`mobile/more.php`). Kullanıcı, ertelenen maddeler
  listesini (autocomplete, arama kapsamı genişletme, FAB, web tasarım taşıması, tam mobil redesign)
  onayladı — bkz. `ROADMAP.md`.
- Dokümantasyon seti (`CLAUDE.md`, `PROJECT_RULES.md`, `VERSIONING.md`, `DATABASE.md`,
  `KNOWN_BUGS.md`, `ROADMAP.md`, `memory/deploy.md`) tek-ortam (DEV=primac.tr / PROD=acanstr.com/ots)
  modeliyle uyumlu hale getirildi, staleness (eski/çelişkili ifadeler) temizlendi.

## Devam Eden İşler
- **UI/UX Sprinti'nin primac.tr'de görsel doğrulaması bekleniyor** — bu ortamda canlı tarayıcı/DB
  erişimi yok, tüm değişiklikler sadece `php -l` + git diff incelemesiyle statik doğrulandı. Kullanıcı
  primac.tr'ye yüklenen güncel `guncelleme.zip`'i test edip onaylamadan sonraki adıma geçilmeyecek.
- DEV test paketi bu oturumda `~/Desktop/PRIMAC-GUNCELLEME/guncelleme.zip` olarak tazelendi (`git
  archive HEAD` + `vendor/`) — primac.tr'ye cPanel File Manager ile yüklenip `guncelle.php`
  çalıştırılmalı. Yeni migration YOK bu turda (sadece HTML/CSS/kompozisyon değişikliği).
- PROD'a (acanstr.com/ots) hiçbir değişiklik gönderilmedi — "DEPLOY MODE" komutu bekleniyor,
  kullanıcının kararı.

## Açık Buglar
(Tam liste → `KNOWN_BUGS.md`)
1. Sabit migration/temizlik anahtarı (`acans-migrate-2026`) `migrate.php`/`temizle.php`'de hardcoded
   — admin oturumu zaten koruyor, repo public olursa değiştirilmeli.
2. `tasks` tablosunda kayıt-kaynağı ayrımı yok (`created_by` benzeri) — admin'in atadığı iş ile
   kullanıcının kendine eklediği iş `tasks.php`'de görsel ayırt edilemiyor. Güvenlik açığı değil,
   izlenebilirlik notu.
3. Bildirim id'lerinde düşük riskli IDOR (bkz. `ROADMAP.md` "Bilinçli olarak ERTELENMİŞ") — net
   düzeltme var ama kullanıcı onayı bekliyor.

## Bir Sonraki Oturumun İlk 10 Önceliği
1. Kullanıcının primac.tr'de UI/UX sprintini görsel olarak onaylaması — onaylanırsa VERSIONING.md
   "Release Durumu" güncellenir, onaylanmazsa geri bildirime göre küçük düzeltme turu açılır.
2. Görsel onay sonrası: PROD'a gönderim zamanlaması kararı (DEPLOY MODE ne zaman verilecek).
3. Mobil parite eksiği — `work_center.php`/`trade_documents.php`/`design.php` mobilde yok, kapsam
   kararı (ayrı sayfa mı, mevcut sayfaya filtre mi) kullanıcıdan bekleniyor.
4. VAPID push anahtarının ACANS+PRIMAC sunucu `config.php`'lerine elle taşınması (kullanıcı
   seyahatten dönünce yapılacak, bkz. `ROADMAP.md`).
5. Global arama canlı-autocomplete kararı — istenirse yeni `search.php?ajax=1` uç noktası + JS
   debounce katmanı ayrı bir iş olarak planlanmalı (DOM iskeleti zaten hazır).
6. Arama kapsamının kullanıcının istediği tam modül listesine genişletilmesi kararı (büyük, ayrı
   sprint gerektirir — bkz. `ROADMAP.md`).
7. FAB (Floating Action Button) desenin somut bir liste ekranına (jobs.php, contacts.php gibi)
   uygulanması kararı.
8. Web arayüzünün mobil tasarım diline ne zaman taşınacağı kararı ("zaman içinde" denildi, tarih yok).
9. `notif_admin_delete_global()` fonksiyonunun bir admin UI butonuna bağlanıp bağlanmayacağı kararı.
10. `/Users/acans/PRIMAC-OTS` (donmuş, 2026-07-02'den beri bağlantısız) ve `/Users/acans/ots`
    (neredeyse boş) klasörlerinin ne yapılacağına dair karar — hâlâ dokunulmadı, sadece risk notu.

## Dikkat Edilmesi Gereken Mimari Kararlar
- **Tek geliştirme ortamı modeli**: DEV=primac.tr (TÜM geliştirme/test burada), PROD=acanstr.com/ots
  (SADECE "DEPLOY MODE" komutuyla dokunulur, kod güncellenmez). Ayrı DB'ler (u7883898_primactr /
  u7883898_primacos) — kod dağıtımı ile veri taşınması birbirinden bağımsız, asla karıştırılmamalı.
- **Deploy git-tabanlı DEĞİL**: Sunucuda git yok, `~/Desktop/PRIMAC-GUNCELLEME/` (DEV) ve
  `~/Desktop/ACANS-GUNCELLEME/` (PROD) klasörlerindeki `guncelleme.zip` (`git archive HEAD` +
  `vendor/`) + `guncelle.php` ile cPanel üzerinden elle yükleniyor. `git archive HEAD` SADECE commit
  edilmiş içeriği paketler — uncommitted değişiklik varsa ya önce commit atılmalı ya da elle/curated
  zip hazırlanmalı (bu oturumda önce commit atıldı, sonra zip tazelendi). Commitsiz zip tazeleme,
  "eski kod"la yanlış test izlenimi vermişti (önceki oturumda yaşanan somut sorun) — bu yüzden zip
  tazelemeden önce her zaman güncel bir commit olduğundan emin olunmalı.
- **`user_notification_status` mimarisi** (migration 039): global (`target_user_id` NULL)
  bildirimler ARTIK HİÇBİR ZAMAN fiziksel silinmiyor — her kullanıcının okuma/gizleme durumu kendi
  satırında. Kişisel bildirimleri (`target_user_id` dolu) hâlâ doğrudan `internal_notifications`
  üzerinden fiziksel silinebiliyor, sadece sahibi tarafından. Yeni bir bildirim-silme özelliği
  eklenecekse bu ayrımı BOZMAMAK gerekiyor.
- **Design token sistemi** (`mobile/common.php` `:root` CSS değişkenleri, 2026-07-04): yeni renk/
  radius eklenirken ham hex/px yerine `var(--c-*)`/`var(--radius-*)` kullanılmalı — aksi halde
  dağınıklık geri gelir. Işık-modu (light-background) mini-stat kutularındaki bazı renkler (`#059669`,
  `#d97706`, `#fed7aa` vb.) BİLİNÇLİ OLARAK token'a çekilmedi (karanlık tema tonlarıyla uyumsuz) —
  bunlar için ayrı bir "light" token seti gerekirse yeni bir karar gerekir.
- **Mobil artık referans tasarım**: Kullanıcı mobil PWA'yı OTS'nin resmi referans UX'i ilan etti; web
  arayüzü zamanla buna hizalanacak (tarih yok). Yeni bir modül tasarlanırken önce mobil düşünülmeli.
- **Toolbar arama tüm mobil sayfalarda ortak component** — sayfa-özel override gerekiyorsa (ör. chat
  ekranı) `body.chat-mode` gibi CSS sınıfı deseniyle gizlenmeli, `topx()`'in kendisi çatallanmamalı.

## Referanslar
Ortam kuralları → `PROJECT_RULES.md`. Sürüm durumu → `VERSIONING.md`. Açık kararlar → `ROADMAP.md`.
Bilinen hatalar → `KNOWN_BUGS.md`. Değişiklik özeti → `CHANGELOG.md`. Deploy detayları →
`memory/deploy.md`.
