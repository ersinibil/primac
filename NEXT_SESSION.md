# NEXT_SESSION.md — Bir Sonraki Oturum İçin Başlangıç Referansı

Bu dosya bir "yapılacaklar listesi" değil, bir sonraki oturuma "neredeydik, neye dikkat et" diye
hızlı giriş yapmak için var. Detay için ilgili dosyalara bakın (`CHANGELOG.md`, `ROADMAP.md`,
`KNOWN_BUGS.md`, `VERSIONING.md`, `memory/*.md`).

## Son Durum
✅ **SECURITY SPRINT-004 — TAMAMLANDI (FINAL AUDIT: PASS, 2026-07-05)** — Merkezi CSRF Koruma
Altyapısı, 57 enforced basename, 15 commit, sıfır FAIL. `index.php` (login) kullanıcı onayıyla
SECURITY SPRINT-005'e taşınmıştı. Detay → `CHANGELOG.md` "SECURITY SPRINT-004 — FINAL AUDIT".

✅ **SECURITY SPRINT-005 — Login Hardening — TAMAMLANDI (FINAL AUDIT: PASS, 2026-07-05)** — 4 faz,
sıfır FAIL: **FAZ-1 Session Fixation** (`b973e01`), **FAZ-2 Login CSRF** (`f20e50d`), **FAZ-3
Brute-Force/Rate-Limit** (`310882a`), **FAZ-4 Remember-Me Hardening** (`dc92a6e`). FAZ-5
(Timing/Enumeration) kullanıcı kararıyla bilinçli olarak uygulanmadı (LOW risk). FINAL AUDIT'te
10/10 madde kod değiştirilmeden bağımsız yeniden doğrulandı, sıfır regresyon. `index.php` artık
CSRF korumalı + hız sınırlı; oturum kimliği kimlik doğrulama sonrası her zaman yenileniyor;
remember-me token'ları rotasyonlu + `SameSite=Lax`. Detay → `CHANGELOG.md` "SECURITY SPRINT-005 —
FINAL AUDIT", `VERSIONING.md` "Security Sprint Durumu", `ROADMAP.md` "Security Roadmap".

**Aktif gündem — REOPEN Backlog** (bkz. aşağı "REOPEN Listesi", öncelik sırası sabit): güvenlik
sprintleri bitti, artık yeni ürün geliştirmesine geçilmeyecek, önce şu üç iş sırayla ele alınacak:
**REOPEN-001 (Takvim Günlük Filtre) → REOPEN-002 (Son İşlemler Route Resolver) → REOPEN-003
(WhatsApp Conversation)**. Hiçbiri henüz başlanmadı — kullanıcı onayı bekliyor.

**Bu oturumda ayrıca tamamlanan işler** (detay → `CHANGELOG.md`/`VERSIONING.md`): UX/STABILITY
PATCH-003 (Takvim filtre — kod değişikliği yok, deploy açığı bulundu, sonradan REOPEN-001 olarak
yeniden açıldı), UX/STABILITY PATCH-004 (Son İşlemler resolver, commit `dff59d5` — sonradan
REOPEN-002 olarak yeniden açıldı), WhatsApp Conversation/Inbound MVP (commit `dae3e62` — sonradan
REOPEN-003 olarak yeniden açıldı, 7 teknik borç maddesi `ROADMAP.md`'de).

**Açık, CSRF ile ilgisiz yan bulgu**: `requests.php`/`mobile/requests.php`'de schema-drift hatası
(`manager_note` vs `response_note` kolon uyuşmazlığı, talep güncellemesi sessizce başarısız oluyor)
— ayrı bir bug-fix turu gerektiriyor, `ROADMAP.md`'ye eklendi.

✅ **SECURITY SPRINT-003 PASS** (2026-07-05) — `sifre_sifirla.php` brute-force + rate-limit
sertleştirmesi, yerel QA'da 8/8 senaryo PASS. Detay → `CHANGELOG.md`, `KNOWN_BUGS.md` "Son
Çözülenler", `VERSIONING.md` "Security Sprint Durumu".

**SECURITY SPRINT-004 — TAMAMLANDI (FINAL AUDIT: PASS).** FAZ-5A (CRM) PASS commit `4708cd6`,
FAZ-5B (Stok/Ürün) PASS commit `ae8116a`, FAZ-5C (İş/Görev) PASS commit `a68637a`, FAZ-5D
(Mesajlaşma/Talep) PASS commit `48d943f`, FAZ-5E (Satış/Satın Alma) PASS commit `b4b2c9a`, FAZ-5F
("Temizlik" grubu) PASS commit `7077a6d`. Toplam enforced basename: 57 — `index.php` DIŞINDA proje
genelinde enforced olmayan POST dosyası kalmadı (FINAL AUDIT'te tam tarama tekrarlandı, sonuç
aynı). Tamamlanan fazlar (FAZ-1 → FAZ-4F, FAZ-5A→5F), HIGH-RISK CHECKPOINT AUDIT ve FINAL AUDIT
detayı → `CHANGELOG.md`. **SECURITY SPRINT-005 — Login Hardening de TAMAMLANDI** (bkz. yukarı "Son
Durum"). **Sıradaki: REOPEN Backlog** (REOPEN-001 → REOPEN-002 → REOPEN-003, bkz. "REOPEN Listesi").

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
3. ~~**SECURITY SPRINT-004**~~ ve ~~**SECURITY SPRINT-005**~~ — **İKİSİ DE TAMAMLANDI** (FINAL
   AUDIT: PASS, sırasıyla 2026-07-05). SPRINT-004: Merkezi CSRF Koruma Altyapısı, 57 enforced
   basename. SPRINT-005: Login Hardening, 4 faz (`b973e01`/`f20e50d`/`310882a`/`dc92a6e`), FAZ-5
   bilinçli atlandı. Detay → `CHANGELOG.md`. `KNOWN_BUGS.md`'de hâlâ açık, henüz sprint numarasına
   atanmamış diğer bulgular: accounting.php XSS, users.php rol yükseltme, is_admin() bayatlığı —
   şimdilik ele alınmayacak (aktif gündem REOPEN backlog'u, aşağıya bakın).

**Production'a deploy YAPILMAYACAK** — ayrı, açık bir "DEPLOY MODE" komutu gerekir.

## REOPEN Listesi — ŞİMDİ AKTİF GÜNDEM (SECURITY SPRINT-005 2026-07-06'da resmen kapandı)
Kullanıcı gerçek kullanım testinde 3 önceki "PASS" bulguyu FAILED/REOPEN olarak işaretlemişti; hem
SECURITY SPRINT-004 hem SPRINT-005 artık TAMAMLANDI, bu yüzden bu üç iş **şimdi aktif gündem** —
sırayla, sıfırdan yeni kabul kriterleriyle ele alınacak:
- **REOPEN-001 — Takvim Günlük Filtre**: seçilen gün dışındaki günlerin not/görevleri de görünmeye
  devam ediyor (PATCH-003'ün "kod değişikliği yok, deploy açığı" bulgusu kullanıcı testinde
  doğrulanmadı — gerçek kök neden hâlâ açık).
  * Root-cause taraması bu oturumda TAMAMLANMADI (kullanıcı SPRINT-005 önceliğini koyunca durduruldu) — bir sonraki oturumda `takvim.php` gün-tıklama JS/PHP filtre mantığından başlanmalı.
- **REOPEN-002 — Son İşlemler Route Resolver**: satış/satın alma kayıtları hâlâ ilgisiz bir "yeni
  kayıt" formuna (`sales.php`/`purchase.php`) yönleniyor. **Kısmi kök neden bu oturumda bulundu**
  (kod değiştirilmedi): `activity_target_url()`'nin `$map` dizisinde `'sale'`/`'purchase'`
  `entity_type` değerleri hiç yok → fonksiyon `null` döner → `activity_row_html()` satır 126'daki
  `$resolved ?? ($r['url'] ?: '#')` stored-url fallback'ine düşer → stored url zaten write-time'da
  `sales.php`/`purchase.php` (`mobile/sales.php:112`, `mobile/purchase.php:68`, `sales.php:136`,
  `purchase.php:73`) — yani YENİ KAYIT formu, detay değil. Düzeltme: bu iki `entity_type` için ya
  gerçek bir detay görünümü ya da resolver'ın "detay ekranı yok → pasif/disabled" durumu döndürmesi
  gerekiyor (stored-url fallback'e körlemesine düşmemeli).
- **REOPEN-003 — WhatsApp Conversation**: mevcut ekran gerçek "Web WhatsApp" mantığında değil,
  gelen cevaplar ekranda görünmüyor (webhook/DB/sorgu zincirinin neresinde koptuğu bu oturumda analiz
  EDİLMEDİ — kullanıcı SPRINT-005 önceliğini koyunca durduruldu).

**Öncelik sırası (kullanıcı tarafından netleştirildi, 2026-07-05, değişmedi)**: ~~SECURITY
SPRINT-005~~ (tamamlandı) → **REOPEN-001 (sıradaki iş)** → REOPEN-002 → REOPEN-003 → Dashboard 2.0
→ Calendar 2.0 → CRM 2.0 → Purchase & Sales 2.0 → UX Polish → Performance → Mobile Experience →
System Audit → Release Candidate. Bu üç REOPEN işi tamamlanmadan yeni ürün geliştirmesine
geçilmeyecek. Detay ve çalışma felsefesi ("Evolution not Revolution", REOPEN durum makinesi:
OPEN→IN PROGRESS→USER TEST→PASS/REOPEN) → `memory/feedback_evolution_not_revolution.md`.

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
Şu an devam eden bir güvenlik sprint'i yok — **SECURITY SPRINT-004 ve SPRINT-005 ikisi de
TAMAMLANDI** (bkz. yukarı "Son Durum"). Aktif gündem: **REOPEN Backlog** (REOPEN-001 → REOPEN-002 →
REOPEN-003, bkz. "REOPEN Listesi"). Ayrıca UX/STABILITY PATCH-002 tamamlandı, commit edildi,
GitHub'a push edildi; DEV (primac.tr) deploy edildi, migration çalıştırıldı, DEV aktif (bkz. yukarı).

## Açık Kalan Hatalar
(Tam liste → `KNOWN_BUGS.md`)
1. ~~`sifre_sifirla.php`'de brute-force koruması yok~~ — **SECURITY SPRINT-003'te çözüldü**
   (2026-07-05).
2. `accounting.php`'de `tab` parametresiyle yansıyan XSS — henüz sprint numarası atanmadı.
3. `users.php`'de rol yükseltme açığı — henüz sprint numarası atanmadı.
4. `is_admin()` session'da bayatlıyor — henüz sprint numarası atanmadı.
5. ~~Login'de session fixation koruması yok~~ — **SECURITY SPRINT-005 FAZ-1'de çözüldü**
   (2026-07-05, commit `b973e01`).
6. FK kısıtı yok (yetim kayıt riski).
7. Bazı tablolarda eksik index.
8. Sabit migration/temizlik anahtarı (`acans-migrate-2026`).
9. **PWA Push** — Safari arka plan teslimatı (SERVER-SIDE PASS, cihaz testi bekliyor).
10. **Mobil Mesajlaşma boşluğu** — CSS düzeltildi (CONDITIONAL PASS, cihaz testi bekliyor).
11. **WhatsApp** — gelen mesaj takibi için MVP eklendi (commit `dae3e62`) ama gerçek kullanıcı
    testinde **REOPEN-003** olarak işaretlendi (2026-07-05) — ekran gerçek "Web WhatsApp" mantığında
    değil, gelen cevaplar görünmüyor. Kök neden bu oturumda analiz EDİLMEDİ (SPRINT-005 önceliği
    nedeniyle durduruldu) — bkz. yukarı "REOPEN Listesi".
12. `requests.php`/`mobile/requests.php` — `manager_note`/`response_note` schema-drift (FAZ-5D'de
    bulundu, CSRF ile ilgisiz, ayrı bugfix turu bekliyor).

## Açık Güvenlik Riskleri
1. **ORTA-YÜKSEK** — `accounting.php`'de yansıyan XSS (henüz sprint numarası atanmadı).
2. **ORTA** — `users.php` rol yükseltme, `is_admin()` session bayatlığı (henüz sprint numarası
   atanmadı). ~~Session fixation~~ — **SECURITY SPRINT-005 FAZ-1'de çözüldü** (`b973e01`).
3. ~~Proje genelinde CSRF token mekanizması yok~~ — **SECURITY SPRINT-004 ile 2026-07-05'te
   çözüldü** (57 enforced basename, `index.php` login formu hariç — bkz. SPRINT-005).

Detay ve sprint numaraları → `ROADMAP.md` "Security Roadmap".

## Dikkat Edilmesi Gereken Mimari Kararlar
- **`setcookie()`'ye SameSite eklemek için "path hack" (path'e `;samesite=` eklemek) KULLANMA** —
  yerel test ortamında (PHP 8.5) `setcookie(): "path" option cannot contain ";"` **ValueError**'ına
  çarptığı bulundu (SECURITY SPRINT-005 FAZ-4, 2026-07-05). Proje PHP 7.2 hedefliyor ve bu hack o
  sürümde muhtemelen çalışırdı, ama artık PHP 7.3+/8.x'te güvenilir DEĞİL — bir sonraki cookie
  değişikliğinde (ör. PHPSESSID'e ek bayrak eklenirse) bu deseni TEKRARLAMA. Bunun yerine ham
  `Set-Cookie` header'ı `header('Set-Cookie: ...', false)` ile elle oluştur (ikinci parametre
  `false` — PHPSESSID gibi diğer Set-Cookie header'larını EZMEMESİ için şart). Örnek: `boot.php`
  `remember_set()`.
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
