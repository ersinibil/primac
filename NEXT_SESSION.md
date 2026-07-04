# NEXT_SESSION.md — Bir Sonraki Oturum İçin Başlangıç Referansı

Bu dosya bir "yapılacaklar listesi" değil, bir sonraki oturuma "neredeydik, neye dikkat et" diye
hızlı giriş yapmak için var. Detay için ilgili dosyalara bakın (`CHANGELOG.md`, `ROADMAP.md`,
`KNOWN_BUGS.md`, `VERSIONING.md`, `memory/*.md`).

## Bir Sonraki Oturumun İlk Önceliği
**SECURITY SPRINT-001: `mobile/personnel_view.php` kritik şifre sıfırlama yetki açığının
kapatılması.** (Admin olmayan, sadece `personnel` modül yetkisi olan bir kullanıcı, `uid` POST
alanına başka bir kullanıcının id'sini yazarak o hesabın şifresini değiştirebiliyor — bkz.
`KNOWN_BUGS.md` madde 1, satır referansı `mobile/personnel_view.php:78-81`.) Bu, her şeyden önce
ele alınmalı.

## Bugün Tamamlanan Çalışmalar (2026-07-04)
- **UX SPRINT-001** (commit `d9c938b`): Bildirimler modülü kart/detay standardına taşındı,
  `notif_type_info()` ile DB'siz tip türetme, `mobile/notification_view.php` yeni detay ekranı,
  `notif_get_for_user()` ile IDOR kapatıldı. + **"Çalışma Alanı"** UX düzeltmesi. **primac.tr'de
  canlı cihazdan doğrulandı, DEPLOY MODE ile güncel referans sürüm olarak yüklendi.**
- **Deploy Standardı** (commit `2e2f2ca`): kalıcı 7 adımlı deploy akışı (Hazırla→Yükle→Doğrula→
  Migration→Smoke Test→Temizlik→Son Doğrulama) `PROJECT_RULES.md`'ye işlendi, `guncelle.php`
  otomatik temizlik+rapor+son doğrulama yapacak şekilde yeniden yazıldı.
- **SYSTEM AUDIT MODE** (read-only, kod/DB değişmedi): 5 ajanla mimari/güvenlik/performans/UX-UI/
  veri modeli/kod kalitesi kapsamlı denetimi yapıldı. Bulgular `KNOWN_BUGS.md`/`ROADMAP.md`'ye
  işlendi, tam rapor Artifact + Masaüstü metin dosyası olarak paylaşıldı. Bu denetim artık her
  büyük sprint/RC/major sürüm/production öncesi otomatik tekrarlanacak kalıcı standart.
- **FINANCE UX REFACTOR** (bu oturumun checkpoint'i, `checkpoint(security-prep)`): Ödeme/Gider +
  Muhasebe ekranlarına "Ne kaydediyorsun?" sihirbazı eklendi (7 seçenek). DB şeması DEĞİŞMEDİ, tür
  bilgisi `finance_record_type_info()` ile mevcut kayıttan türetiliyor. 6 dosya değişti
  (`finance_lib.php`, `finance_new.php`, `accounting.php`, `mobile/payment.php`,
  `mobile/movement_view.php`, `mobile/accounting.php`). Tahsilat/Gelir akışı hiç değişmedi.
  **Henüz primac.tr'de test edilmedi.**

## Devam Eden Sprint
**FINANCE UX REFACTOR primac.tr'de test/onay bekliyor** — bu oturumda DEV'e yüklendi ama henüz
kullanıcı tarafından smoke test yapılmadı. Test edilecekler: 7 sihirbaz seçeneğinde doğru alan
zorunlu/görünür oluyor mu (4 ekranda da — web+mobil, Ödeme/Gider+Muhasebe), düzenleme ekranlarında
eski kayıtlar doğru adımla açılıyor mu, Tahsilat/Gelir akışı hiç bozulmamış mı.

## Açık Kalan Hatalar
(Tam liste → `KNOWN_BUGS.md`)
1. `sifre_sifirla.php`'de brute-force koruması yok (6 haneli kod, deneme sınırı/lockout yok).
2. `accounting.php`'de `tab` parametresiyle yansıyan XSS (satır 8, 111-130).
3. `users.php`'de "users" modül yetkisi = fiili tam admin, kendine rol yükseltme mümkün.
4. `is_admin()` session'da bayatlıyor, `user_can()` gibi DB'den taze okumuyor.
5. Login'de `session_regenerate_id(true)` çağrılmıyor (session fixation).
6. Hiçbir tabloda FK kısıtı yok — personel/cari/iş silme akışlarında yetim kayıt riski (özellikle
   `job_logs`).
7. `jobs`/`tasks`/`finance_movements`/`internal_messages`/`internal_notifications` tablolarında
   eksik index (performans + veri büyüdükçe risk).
8. Sabit migration/temizlik anahtarı (`acans-migrate-2026`) hardcoded — repo public olursa
   değiştirilmeli.
9. `tasks` tablosunda kayıt-kaynağı ayrımı yok (`created_by` benzeri) — izlenebilirlik notu, güvenlik
   açığı değil.

## Açık Güvenlik Riskleri
1. **KRİTİK — `mobile/personnel_view.php` keyfi şifre sıfırlama** (satır 78-81): `uid` POST
   alanının gerçekten görüntülenen personelin hesabı olduğu doğrulanmıyor — admin olmayan bir
   kullanıcı herhangi bir hesabın (admin dahil) şifresini değiştirebilir. **Bir sonraki oturumun
   ilk işi (yukarı bakın).**
2. **YÜKSEK — `mobile/task_view.php` IDOR** (satır 14-19): görev durumu güncellemesinde sahiplik
   kontrolü yok, `?id=` değiştirilerek başkasının görevi değiştirilebilir.
3. **YÜKSEK** — `sifre_sifirla.php` brute-force + `accounting.php` XSS (yukarıdaki "Açık Kalan
   Hatalar" madde 1-2 ile aynı).
4. **ORTA** — `users.php` rol yükseltme, `is_admin()` session bayatlığı, session fixation
   (yukarıdaki madde 3-5 ile aynı).
5. **BİLGİ** — Proje genelinde CSRF token mekanizması yok.

Tam bulgu listesi ve satır referansları → `KNOWN_BUGS.md` ve 2026-07-04 tarihli System Audit raporu
(Artifact + `~/Desktop/OTS_System_Audit_2026-07-04.txt`).

## Dikkat Edilmesi Gereken Mimari Kararlar
- **Tek geliştirme ortamı modeli**: DEV=primac.tr (TÜM geliştirme/test burada), PROD=acanstr.com/ots
  (SADECE "DEPLOY MODE" komutuyla dokunulur, kod güncellenmez). Ayrı DB'ler — kod dağıtımı ile veri
  taşınması birbirinden bağımsız, asla karıştırılmamalı.
- **Deploy git-tabanlı DEĞİL**: `~/Desktop/PRIMAC-GUNCELLEME/` (DEV) klasöründeki `guncelleme.zip`
  (`git archive HEAD` + `vendor/`) + `guncelle.php` ile cPanel üzerinden yükleniyor. Zip tazelemeden
  önce her zaman güncel bir commit olduğundan emin olunmalı (`git archive HEAD` sadece commit
  edilmiş içeriği paketler).
- **7 adımlı Deploy Standardı** (`PROJECT_RULES.md`): Hazırla→Yükle→Doğrula→Migration→Smoke Test→
  Temizlik→Son Doğrulama. Temizlik Smoke Test'ten SONRA gelir, `guncelle.php` migration'dan hemen
  sonra kendini silmez — `?cleanup=1` ile ayrı bir adım.
- **Sürekli Kalite Denetimi Standardı**: SYSTEM AUDIT MODE her büyük sprint/RC/major sürüm/
  production öncesi otomatik tekrarlanır, bulgular 5 dokümana (CHANGELOG/VERSIONING/ROADMAP/
  KNOWN_BUGS/NEXT_SESSION) işlenir.
- **`user_notification_status` mimarisi** (migration 039): global bildirimler asla fiziksel
  silinmiyor, her kullanıcının okuma/gizleme durumu kendi satırında. Yeni bir bildirim-silme
  özelliği eklenecekse bu ayrımı BOZMAMAK gerekiyor.
- **"Ne kaydediyorsun?" sihirbaz deseni** (`finance_lib.php::finance_record_type_info()`,
  2026-07-04): tür bilgisi DB'de SAKLANMIYOR, mevcut dolu alanlardan (personnel_id/contact_id/
  category group_name/account_type) türetiliyor — `notif_type_info()` ile aynı desen. Yeni bir
  finans alanı eklenirken bu türetme fonksiyonunun öncelik sırası (personel > vergi/sgk > araç >
  kredi/kart > cari > işletme > diğer) bozulmamalı.
- **Ödeme/Gider ile Muhasebe ekranları bilerek AYRI bırakıldı** — personel ödemesi artık iki
  ekrandan da girilebiliyor (bkz. `ROADMAP.md` "FINANCE UX REFACTOR — bilinen açık nokta"),
  birleştirme ayrı bir karar gerektirir.
- **Design token sistemi** (`mobile/common.php`): yeni renk/radius eklenirken `var(--c-*)`/
  `var(--radius-*)` kullanılmalı. Web'de token benimsenmesi hâlâ çok düşük (System Audit bulgusu).
- **Mobil hâlâ referans tasarım**: yeni bir modül tasarlanırken önce mobil düşünülmeli.

## Referanslar
Ortam kuralları → `PROJECT_RULES.md`. Sürüm durumu → `VERSIONING.md`. Açık kararlar → `ROADMAP.md`.
Bilinen hatalar → `KNOWN_BUGS.md`. Değişiklik özeti → `CHANGELOG.md`. Deploy detayları →
`memory/deploy.md`.
