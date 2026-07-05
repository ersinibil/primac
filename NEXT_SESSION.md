# NEXT_SESSION.md — Bir Sonraki Oturum İçin Başlangıç Referansı

Bu dosya bir "yapılacaklar listesi" değil, bir sonraki oturuma "neredeydik, neye dikkat et" diye
hızlı giriş yapmak için var. Detay için ilgili dosyalara bakın (`CHANGELOG.md`, `ROADMAP.md`,
`KNOWN_BUGS.md`, `VERSIONING.md`, `memory/*.md`).

## Son Durum
🔄 **SECURITY SPRINT-004 — DEVAM EDİYOR** (2026-07-05) — Merkezi CSRF Koruma Altyapısı. FAZ-1'den
FAZ-4F'ye kadar tüm fazlar tamamlandı, **HIGH-RISK CSRF CHECKPOINT AUDIT: PASS**. **FAZ-5A — CRM
grubu PASS** (`contact_new.php`, `contact_view.php`, commit `4708cd6`, GitHub'a push edildi). Son
checkpoint commit: `4708cd6`. Sıradaki faz: **FAZ-5B — kapsamı netleşmemiş** (Stok/Ürün, İş/Görev
ana formları, Mesajlaşma/Talep, Satış/Satın Alma), kullanıcı onayı bekliyor.
Detay → `CHANGELOG.md`, `VERSIONING.md` "Security Sprint Durumu", `ROADMAP.md` "Security Roadmap".

**Bu turda ayrıca kuyruğa alındı (sırayla ele alınacak)**: UX/STABILITY PATCH-003 (Takvim günlük
filtre — kullanıcı bildirimi: PATCH-002'de "PASS" denen düzeltme gerçek görev verisiyle hâlâ
bozuk), UX/STABILITY PATCH-004 (Son İşlemler route/activity-target-resolver — çapraz-platform
yönlendirme hâlâ tutarsız), ardından WhatsApp inbound/conversation entegrasyonu (ayrı, büyük mimari
iş — kullanıcı "bunlardan sonra" dedi, henüz başlanmadı).

✅ **SECURITY SPRINT-003 PASS** (2026-07-05) — `sifre_sifirla.php` brute-force + rate-limit
sertleştirmesi, yerel QA'da 8/8 senaryo PASS. Detay → `CHANGELOG.md`, `KNOWN_BUGS.md` "Son
Çözülenler", `VERSIONING.md` "Security Sprint Durumu".

**Devam Eden Sprint: SECURITY SPRINT-004 — Sıradaki Faz: FAZ-5B (kapsam netleşmemiş)**
FAZ-5A (CRM, `contact_new.php`/`contact_view.php`) PASS, commit `4708cd6`. Tamamlanan fazlar
(FAZ-1 → FAZ-4F, FAZ-5A) ve HIGH-RISK CHECKPOINT AUDIT detayı → `CHANGELOG.md`.

Ayrıca açık **Security Technical Debt** (bug değil, mimari/deployment notu — bkz. `ROADMAP.md`):
rate-limit'in uzun vadede `security_rate_limits`/`security_events` tablosuna taşınması,
`REMOTE_ADDR`'ın reverse proxy/Cloudflare/NGINX ortamında merkezi istemci-IP çözümüne geçirilmesi.

## Bir Sonraki Oturumun İlk Önceliği
**UX / STABILITY PATCH-002**: Tamamlandı. Commit edildi. GitHub'a push edildi.
**DEV (primac.tr)**: Deploy edildi. Migration çalıştırıldı. DEV aktif.
Production'a (acanstr.com/ots) henüz dokunulmadı — ayrı "DEPLOY MODE" komutu bekliyor.

## Bu Oturumda Yapılanlar (2026-07-05)
1. **UX REFINEMENT PATCH** — 4 madde (Ödeme/Gider kartları, çek/senet hatırlatma notu, Son İşlemler
   parite, Bildirim Kur sadeleştirme).
2. **UX / STABILITY PATCH-002** — 7 madde (Son İşlemler routing, Teklif CRUD, WhatsApp tespiti,
   Mesajlaşma boşluğu, PWA Push loglaması, Takvim çift kayıt, Takvim günlük filtre).
3. **QA MODE** — yukarıdaki 7 maddenin TAMAMI yerel `ots_sectest` MariaDB + gerçek HTTP istekleriyle
   test edildi. Sonuç tablosu:
   - Son İşlemler routing: **PASS**
   - Teklif liste/detay: **PASS**
   - Çek/Senet çift kayıt: **PASS**
   - Takvim günlük filtre: **PASS**
   - **Mobil Mesajlaşma: CONDITIONAL PASS** — CSS düzeltmesi doğrulandı, gerçek iPhone Safari cihaz
     testi bekliyor (piksel-seviye görsel teyit yerelde yapılamadı).
   - **PWA Push: SERVER-SIDE PASS** — sunucu→FCM teslimatı gerçek abonelikle doğrulandı (201 OK),
     Safari/iOS arka plan teslimatı gerçek cihaz testi bekliyor.
   - **WhatsApp: kapsam dışı** — kod değişikliği yok, ayrı **WHATSAPP INTEGRATION SPRINT** olarak
     planlanacak (yeni webhook + yeni tablo gerektiriyor, yeni özellik).
   - FAIL yok.

## Sıradaki Sıra (kullanıcı tarafından netleştirildi)
1. **iPhone Safari gerçek cihaz testi** — Mobil Mesajlaşma (CONDITIONAL PASS) + PWA Push
   (SERVER-SIDE PASS) için tek eksik doğrulama. Test notları aşağıda.
2. **SYSTEM AUDIT** — büyük sprint sonrası standart denetim.
3. **SECURITY SPRINT-004** — Merkezi CSRF Koruma Altyapısı, devam ediyor. FAZ-1→FAZ-4F + FAZ-5A
   (CRM) tamamlandı (checkpoint `4708cd6`). Sıradaki faz: FAZ-5B — kapsamı netleşmemiş (Stok/Ürün,
   İş/Görev ana formları, Mesajlaşma/Talep, Satış/Satın Alma). `KNOWN_BUGS.md`'de hâlâ açık, henüz
   sprint numarasına atanmamış diğer bulgular: accounting.php XSS, users.php rol yükseltme,
   is_admin() bayatlığı, session fixation.

**Production'a deploy YAPILMAYACAK** — ayrı, açık bir "DEPLOY MODE" komutu gerekir.

## iOS Safari Gerçek Cihaz Test Notları (bir sonraki oturumun 1. maddesi)
Bu iki madde SADECE gerçek bir iPhone + Safari ile doğrulanabilir, yerel ortamda (curl/php -S)
zaten test edildi ama piksel/cihaz seviyesinde teyit edilemedi:

**A) Mobil Mesajlaşma boşluğu (CONDITIONAL PASS → PASS/FAIL)**
1. primac.tr'ye giriş yap, uygulamayı ana ekran ikonundan (standalone) aç.
2. Mesajlar → herhangi bir sohbete gir.
3. Yazma alanına (composer) dokun, klavye açılsın.
4. **Beklenen**: son mesaj balonu ile composer arasında boş alan KALMAMALI.
5. **FAIL olursa**: `mobile/common.php`'deki `body.chat-mode.kb{padding-bottom:0}` kuralının
   gerçekten devrede olup olmadığını Safari'nin Web Inspector'ıyla (Mac'e USB ile bağlayıp
   Safari → Geliştir menüsünden) kontrol et — Elements panelinde `<body>` etiketinin class'ları
   arasında hem `chat-mode` hem `kb` var mı, hangi kural "computed" olarak kazanıyor.

**B) PWA Push — Safari arka planda/kapalıyken bildirim (SERVER-SIDE PASS → PASS/FAIL)**
1. primac.tr'yi ana ekran ikonundan aç, `mobile/push_enable.php` ("Bildirim Kur", admin menüsü) ile
   bildirimleri aç, izin ver.
2. Uygulamayı TAMAMEN kapat (arka plandan da kaldır) veya sadece arka plana al.
3. Başka bir kullanıcıdan/ekrandan bu kullanıcıya bir mesaj/görev bildirimi tetikle (ör. birine iş
   ata, mesaj gönder) — bu `notify_user()` üzerinden `push_to_user()`'ı tetikler.
4. **Beklenen**: telefon kilit ekranında/bildirim merkezinde sistem bildirimi görünmeli.
5. **FAIL olursa**: primac.tr sunucusunda `push_debug.log` dosyasını kontrol et (proje kök dizini,
   `push_lib.php`'nin yanında) — artık her başarısız denemede gerçek hata mesajı yazıyor
   (`push_available()=false` → gmp/bcmath eksik; `İSTİSNA` → VAPID/ağ hatası; `BAŞARISIZ endpoint=...
   reason=...` → spesifik HTTP hata kodu). Bu log dosyasının içeriği bir sonraki oturuma aynen
   yapıştırılabilir, kesin teşhis için yeterli olacak.

İkisi de PASS alırsa UX/STABILITY PATCH-002 tamamen kapanır, `VERSIONING.md`/`KNOWN_BUGS.md`'deki
"CONDITIONAL"/"SERVER-SIDE" etiketleri düz "PASS"a çevrilir. FAIL alırsa ilgili dosya (mobile/common.php
veya push_lib.php/config.php) için ayrı, küçük bir düzeltme turu açılır — commit a36db68'e yeni bir
commit olarak eklenir, üzerine yazılmaz.

## Devam Eden Sprint
**SECURITY SPRINT-004 — Merkezi CSRF Koruma Altyapısı** (FAZ-1 → FAZ-4F tamamlandı, HIGH-RISK
CSRF CHECKPOINT AUDIT PASS, checkpoint commit `a32893c`). Sıradaki faz: FAZ-5A — CRM
(`contact_new.php`, `contact_view.php`). Ayrıca UX/STABILITY PATCH-002 tamamlandı, commit edildi,
GitHub'a push edildi; DEV (primac.tr) deploy edildi, migration çalıştırıldı, DEV aktif (bkz. yukarı).

## Açık Kalan Hatalar
(Tam liste → `KNOWN_BUGS.md`)
1. `sifre_sifirla.php`'de brute-force koruması yok.
2. `accounting.php`'de `tab` parametresiyle yansıyan XSS.
3. `users.php`'de rol yükseltme açığı.
4. `is_admin()` session'da bayatlıyor.
5. Login'de session fixation koruması yok.
6. FK kısıtı yok (yetim kayıt riski).
7. Bazı tablolarda eksik index.
8. Sabit migration/temizlik anahtarı (`acans-migrate-2026`).
9. **PWA Push** — Safari arka plan teslimatı (SERVER-SIDE PASS, cihaz testi bekliyor).
10. **Mobil Mesajlaşma boşluğu** — CSS düzeltildi (CONDITIONAL PASS, cihaz testi bekliyor).
11. **WhatsApp** — gelen mesaj takibi yok (ayrı sprint, yeni mimari gerektiriyor).

## Açık Güvenlik Riskleri
1. **YÜKSEK** — `sifre_sifirla.php` brute-force + `accounting.php` XSS.
2. **ORTA** — `users.php` rol yükseltme, `is_admin()` session bayatlığı, session fixation.
3. **BİLGİ** — Proje genelinde CSRF token mekanizması yok.

Bunların tamamı **SECURITY SPRINT-003**'ün kapsamı (bkz. yukarı "Sıradaki sıra").

## Dikkat Edilmesi Gereken Mimari Kararlar
- **Tek geliştirme ortamı modeli**: DEV=primac.tr, PROD=acanstr.com/ots (SADECE "DEPLOY MODE"
  komutuyla dokunulur). Ayrı DB'ler.
- **Yerel `config.php` PROD veritabanı bilgisi içerir** — yerel test SIRASINDA geçici olarak
  `ots_sectest`'e yönlendirilip test bitince BİREBİR orijinaline geri yüklendi (diff ile
  doğrulandı). Bir sonraki oturumda da AYNI yöntem izlenmeli: gerçek config.php'yi asla kalıcı
  değiştirme, geçici kopyayla test et, orijinali diff ile doğrulayarak geri yükle.
- **`migrate.php` yerelde çalıştırılınca KENDİNİ SİLİYOR** (production güvenlik önlemi). Bu oturumda
  yerel QA sırasında silindi, `git restore` ile geri getirildi. **Bundan sonra yerel QA'da
  migrate.php'nin bir KOPYASI üzerinden çalıştırılması gerekiyor**, orijinali üzerinde değil (bkz.
  `ROADMAP.md`).
- **Yerel MariaDB + `ots_sectest` DB kurulu kaldı** (`brew services` ile başlatılır, `mysql -u acans`
  ile şifresiz erişilir — unix_socket auth, OS kullanıcı adıyla eşleşiyor). İçinde artık QA test
  verisi de var (QA Test Cari, QA Test Ürün, QA-CEK-001 vb. — gerçek veri değil, temizlenmedi).
- **`altyonetici` test kullanıcısının şifresi/yetkileri bu oturumda değiştirildi** (yerel
  `ots_sectest`'te, GERÇEK sunucuda DEĞİL) — `edit_delete`+`teklif` yetkisi eklendi, şifre
  `QaTest123!` yapıldı, teklif CRUD testinde admin-olmayan kullanıcı senaryosu için kullanıldı.
- **`tasks` soft-delete kullanıyor** (migration 040): `deleted_at IS NULL` filtresi unutulmamalı.
- **Son İşlemler (`activity_logs`) linkleri artık iki kalıpla düzeltiliyor**: aynı isimli dosya
  her iki tarafta da varsa (`contact_view.php`, `sales.php`, `purchase.php`, `product_view.php`)
  önek YOK (bare path); isim ayrışıyorsa (`finance.php`/`kasa.php`, mobil `personnel_view.php`,
  `trade_document_view.php`) `base_url()` ile MUTLAK path. Yeni bir `activity_log()` çağrısı
  eklenirken bu kalıba uyulmalı.
- **Teklif Düzenle artık `can_edit_delete()` kullanıyor** (web+mobil), ham `is_admin()` değil —
  Sil hâlâ paylaşılan `delete_button()` üzerinden bilinçli olarak admin-only (mimari karar,
  değiştirilmedi).
- **Design token sistemi** (`mobile/common.php`): yeni renk/radius eklenirken `var(--c-*)`/
  `var(--radius-*)` kullanılmalı.

## Referanslar
Ortam kuralları → `PROJECT_RULES.md`. Sürüm durumu → `VERSIONING.md`. Açık kararlar → `ROADMAP.md`.
Bilinen hatalar → `KNOWN_BUGS.md`. Değişiklik özeti → `CHANGELOG.md`. Deploy detayları →
`memory/deploy.md`.
