# ROADMAP.md — Açık Maddeler ve Bekleyen Kararlar

## Security Roadmap (2026-07-05'te netleştirildi)
Repository geçmişini esas alan sayım — tam gerekçe/QA detayı → `VERSIONING.md` "Security Sprint
Durumu", `CHANGELOG.md`, `KNOWN_BUGS.md` "Son Çözülenler".

Tamamlandı:
- ✅ **SECURITY SPRINT-001** — `mobile/personnel_view.php` keyfi şifre sıfırlama açığı (2026-07-04).
- ✅ **SECURITY SPRINT-002** — `mobile/task_view.php` IDOR. *Retroaktif belgeleme (2026-07-05):
  orijinali 2026-07-04'te genel dev sprint numaralandırmasıyla ("SPRINT-003", `5fb2c43`)
  kapatılmıştı, SECURITY SPRINT numaralandırmasına bu tarihte dahil edildi.*
- ✅ **SECURITY SPRINT-003** — `sifre_sifirla.php` brute-force + rate-limit sertleştirmesi
  (2026-07-05, yerel QA'da 8/8 PASS).
- ✅ **SECURITY SPRINT-004 — Merkezi CSRF Koruma Altyapısı — TAMAMLANDI (FINAL AUDIT: PASS,
  2026-07-05).** 57 enforced basename, 15 commit, 15 modül/işlevsel grup, sıfır FAIL, sıfır
  regresyon. `index.php` (login) kullanıcı onayıyla SECURITY SPRINT-005'e taşındı. Detay →
  `CHANGELOG.md` "SECURITY SPRINT-004 — FINAL AUDIT".
- ✅ **SECURITY SPRINT-005 — Login Hardening — TAMAMLANDI (FINAL AUDIT: PASS, 2026-07-05).** 4 faz
  (Session Fixation, Login CSRF, Brute-Force/Rate-Limit, Remember-Me Hardening), sıfır FAIL; FAZ-5
  (Timing/Enumeration) kullanıcı kararıyla bilinçli olarak uygulanmadı (LOW risk). FINAL AUDIT'te
  10/10 madde bağımsız olarak yeniden doğrulandı, regresyon yok. Detay → `CHANGELOG.md`
  "SECURITY SPRINT-005 — FINAL AUDIT".

Devam Ediyor:
- (yok — hem SPRINT-004 hem SPRINT-005 tamamlandı. Aktif gündem artık REOPEN backlog'u — bkz.
  `NEXT_SESSION.md` "REOPEN Listesi": REOPEN-001 Calendar → REOPEN-002 Recent Activity →
  REOPEN-003 WhatsApp. Bu üçü tamamlanmadan yeni ürün geliştirmesine geçilmeyecek.)

Arşiv (tamamlanmış fazların detayı, referans için korunuyor):
  - ✅ **FAZ-1 — Session Fixation Hardening: PASS** (commit `b973e01`) — `index.php` login sonrası +
    `boot.php` `remember_check()` sonrası `session_regenerate_id(true)`. Yerel QA'da normal login,
    remember-me, logout, `return_to`, çoklu-sekme senaryoları PASS, sıfır FAIL. Detay →
    `CHANGELOG.md`.
  - ✅ **FAZ-2 — Login CSRF Hardening: PASS** (commit `f20e50d`) — SADECE `index.php` (boot.php'ye
    dokunulmadı), form'a `csrf_field()` + POST'ta mevcut try/catch'e gömülü token kontrolü
    (başarısızsa 403 değil, dost mesajla login ekranında kalır). Yerel QA'da token'lı/token'sız/
    hatalı-token login, remember-me, `return_to`, logout PASS, sıfır FAIL. Detay → `CHANGELOG.md`.
  - ✅ **FAZ-3 — Login Brute-Force/Rate-Limit: PASS** (commit `310882a`) — `index.php` +
    `share_lib.php` (yeni sistem YAZILMADI, `sifre_sifirla.php`'nin deseni check/hit/clear'a
    ayrılarak genelleştirildi: `rate_limit_blocked()`/`rate_limit_hit()`/`rate_limit_clear()`, ayrı
    `login_ratelimit.json`). IP+kullanıcı adı bazında 10 dk'da 8 başarısız deneme. Yerel QA'da limit
    dolana kadar/dolunca/pencere sonrası/başarı-sonrası-temizlik/Remember-Me/`return_to`/Logout —
    hepsi PASS, sıfır FAIL. Detay → `CHANGELOG.md`.
  - ✅ **FAZ-4 — Remember-Me Hardening: PASS** (commit `dc92a6e`) — SADECE `boot.php`. Token
    rotasyonu (her otomatik girişte `remember_set()` yeniden çağrılıyor, eski token geçersiz) +
    `acans_remember`'a `SameSite=Lax` (PHP 7.2 "path hack"i modern PHP'de `setcookie()`
    ValueError'ına çarptığı için ham `Set-Cookie` header'ı `header()` ile elle oluşturuldu, hem
    PHP 7.2 hem 8.x doğrulandı). Yerel QA'da rotasyon/eski-token-reddi/yeni-token/logout/
    normal-login/rate-limit/CSRF regresyonu — hepsi PASS, sıfır FAIL. Detay → `CHANGELOG.md`.
  - ⬜ **FAZ-5 — Timing/enumeration inceliği** — kullanıcı kararıyla bilinçli olarak
    UYGULANMADI (LOW risk, kritik değil, gereksiz regresyon riski istenmedi, 2026-07-05).
  - ✅ **FAZ-6 — FINAL AUDIT: PASS** (2026-07-05, kod değiştirilmeden) — 10/10 madde bağımsız
    yeniden doğrulandı, sıfır FAIL, sıfır regresyon. Detay → `CHANGELOG.md`.

## Arşiv — SECURITY SPRINT-004 faz detayı (tamamlandı, referans için korunuyor)
FAZ-1 → FAZ-4F tamamlandı, **HIGH-RISK CSRF CHECKPOINT AUDIT: PASS** (checkpoint commit `a32893c`).
**FAZ-5A — CRM grubu PASS** (commit `4708cd6`). **FAZ-5B — Stok/Ürün grubu PASS** (commit
`ae8116a`). **FAZ-5C — İş/Görev grubu PASS** (commit `a68637a`). **FAZ-5D — Mesajlaşma/Talep grubu
PASS** (commit `48d943f`). **FAZ-5E — Satış/Satın Alma grubu PASS** (commit `b4b2c9a`). **FAZ-5F —
"Temizlik" grubu PASS** (`accounting_categories.php`, `check_note_view.php`, `report.php`,
`ajax_quick_add.php`, `wa_settings.php`, commit `7077a6d`, 2026-07-05). **Toplam enforced basename:
57.** Proje genelinde FINAL AUDIT'te tam tarama tekrarlandı — `index.php` (login) DIŞINDA hiçbir
POST-handling dosya kalmadı (57/57, fark sıfır). Detay → `CHANGELOG.md`, `VERSIONING.md` "Security
Sprint Durumu".

## FAZ-5D'de bulunan yan bulgu — `requests.php` schema drift (CSRF ile ilgisiz, 2026-07-05)
`requests.php`/`mobile/requests.php`'de talep durumu/not güncelleme kodu `manager_note` kolonuna
yazıyor ama `management_requests` tablosundaki gerçek kolon adı `response_note` — güncelleme PDO
exception fırlatıp try/catch ile sessizce yutuluyor, `$error` set ediliyor ama HİÇBİR YERDE render
edilmiyor (kullanıcı hiçbir hata görmüyor, durum/not güncellenmemiş olarak kalıyor, sanki
güncellenmiş gibi sayfa yeniden yükleniyor). FAZ-5D kapsamında (CSRF enforcement) DOKUNULMADI —
ayrı bir bug-fix turu gerektiriyor, kullanıcı onayı bekliyor.

`KNOWN_BUGS.md`'de hâlâ açık, henüz bir sprint numarasına atanmamış diğer bulgular: accounting.php
XSS, users.php rol yükseltme, `is_admin()` session bayatlığı, session fixation — bunlar SPRINT-004
kapsamına girip girmeyeceği kullanıcı onayı ile netleşecek.

## Security Technical Debt (2026-07-05, SECURITY SPRINT-003 sonrası not — bug değil, mimari/altyapı notu)
1. **Rate-limit mekanizması şu an JSON dosya tabanlı** (`reset_ratelimit.json`, SECURITY SPRINT-003).
   Bu sprintte KABUL EDİLDİ, değiştirilmedi. Uzun vadede merkezi bir `security_rate_limits` veya
   `security_events` veritabanı tablosuna taşınmalı — amaç: merkezi kayıt, çoklu sunucu desteği,
   cluster uyumluluğu, performans, güvenlik loglarının tek yerde tutulması. Kullanıcı onayı olmadan
   bu turda uygulanmayacak.
2. **`REMOTE_ADDR` kullanımı reverse proxy/Cloudflare/NGINX ortamlarında güvenilir olmayabilir** —
   bu bir yazılım hatası DEĞİL, deployment mimarisine bağlı bir durum (bkz. `KNOWN_BUGS.md`'ye
   bilerek eklenmedi). primac.tr/acanstr.com önüne bir reverse proxy/CDN katmanı eklenirse
   (`X-Forwarded-For` vb.) merkezi bir istemci-IP çözüm fonksiyonuna geçilmesi gerekecek —
   deployment mimarisi netleştiğinde ele alınacak.

## Sıradaki sıra (2026-07-05'te kullanıcı tarafından netleştirildi)
UX/STABILITY PATCH-002 DEV QA PASS aldı (bkz. `CHANGELOG.md`). Production'a deploy YAPILMAYACAK —
ayrı "DEPLOY MODE" komutu gerekir. Bir sonraki oturumun sırası:
1. **iPhone Safari gerçek cihaz testi** — mobil mesajlaşma boşluğu (CONDITIONAL PASS) ve PWA Push
   arka plan teslimatı (SERVER-SIDE PASS) için tek eksik doğrulama bu — aşağıdaki iki maddeye bakın.
2. **SYSTEM AUDIT** — büyük sprint sonrası standart denetim (bkz. `PROJECT_RULES.md` "Sürekli Kalite
   Denetimi Standardı").
3. ~~**SECURITY SPRINT-004**~~ — **TAMAMLANDI** (FINAL AUDIT: PASS, 2026-07-05, bkz. yukarı
   "Security Roadmap"). Sıradaki: **SECURITY SPRINT-005 — Login Hardening** (önerilen kapsam hazır,
   onay bekliyor).

## WhatsApp gelen mesaj takibi — ayrı WHATSAPP INTEGRATION SPRINT (2026-07-05)
Bu, UX/STABILITY PATCH-002 kapsamında bilinçli olarak dışarıda bırakıldı ve kendi başına bir sprint
olarak planlanacak (yeni webhook alıcı + yeni konuşma tablosu = yeni mimari, kullanıcı onayı
gerektirir). Detay için aşağıdaki "WhatsApp — gelen mesajların takibi" maddesine bakın — bu madde
artık ayrı bir sprint/proje başlığı olarak ele alınacak, rastgele bir sonraki turun kapsamına
sessizce eklenmeyecek.

## Yerel QA notu — migrate.php doğrudan çalıştırılmamalı (2026-07-05)
`migrate.php` başarılı migration sonrası KENDİNİ SİLİYOR (production güvenlik önlemi, bilinçli
tasarım). UX/STABILITY PATCH-002'nin QA MODE turunda bu yerel çalışma dizininde de tetiklendi ve
dosya silindi — `git restore migrate.php` ile geri getirildi, veri kaybı olmadı. **Bundan sonraki
her yerel QA turunda migrate.php'nin bir KOPYASI üzerinden çalıştırılması gerekiyor** (örn.
`cp migrate.php migrate_qa_copy.php` ile), orijinal dosya asla doğrudan yerel sunucuya karşı
çalıştırılmamalı.

## Finansal hatırlatmalar — ileride ayrı "Hatırlatmalar" modülü (2026-07-05, UX REFINEMENT PATCH)
Finansal vadeler, çek/senet hatırlatmaları ve ödeme günleri ileride ayrı Hatırlatmalar modülü altında
toplanacaktır. Bu turda `checks_notes.php`/`checks_notes_lib.php` mimarisine ve mevcut davranışına
dokunulmadı — sadece bu karar kayıt altına alındı, kullanıcı onayı olmadan uygulanmayacak.

## Web Push bildirimi — canlıda VAPID doğrulaması gerekiyor (2026-07-04, SPRINT CLOSE; 2026-07-05
UX/STABILITY PATCH-002'de yeniden gündeme geldi — Safari'de arka planda bildirim gelmiyor şikayeti)
Web push bildirimi (`sw.js`, `layout_bottom.php`) yerel ortamda gerçek Chromium ile uçtan uca
doğrulandı (abonelik + teslimat başarılı) — ancak bu test `push_lib.php`'nin fallback (kod içi
sabit) VAPID anahtarlarıyla çalıştı. primac.tr'nin GERÇEK sunucu `config.php`'sinde
`vapid_public`/`vapid_private` tanımlı mı, `gmp`/`bcmath` PHP eklentisi var mı — bunlar sadece canlı
sunucudan doğrulanabilir (bkz. `memory/deploy.md` "EYLEM GEREKİYOR" kaydı, 2026-07-03'ten beri açık).
Kullanıcının primac.tr'de gerçek bir cihazdan bildirim izni verip test etmesi gerekiyor.
**2026-07-05 güncellemesi**: `push_to_user()`'ın önceden TÜM hataları sessizce yuttuğu bulundu
(loglama yoktu) — `push_lib.php`'ye `push_log()` eklendi, artık başarısız bir push denemesi
`push_debug.log`'da görülebilir (repoya girmez, `.gitignore`'da `*.log` zaten var). Bu, sunucu
erişimi olmadan kesin teşhis yapılamamasının nedenini gidermez ama BİR SONRAKİ arızada gerçek hata
mesajını (VAPID mi, eklenti mi, ağ mı) sağlayacak.

## WhatsApp Conversation/Inbound — MVP tamamlandı, açık teknik borç (2026-07-05)
MVP (migration 041, `wa_webhook.php`, sender-scope mimari, web+mobil ekranlar) **PASS**, commit
`dae3e62` — detay → `CHANGELOG.md`, `VERSIONING.md`. Kullanıcı onayı ile MVP kapsamı dışında
bırakılan, ayrı kararlar gerektiren açık maddeler:
1. **Outbound `provider_message_id` parse edilmiyor** — `wa_send()` UltraMsg'in yanıtını hiç
   parse etmiyor, sadece true/false dönüyor. Giden mesaj için sağlayıcı id'si yakalanmıyor
   (inbound tarafını etkilemiyor, sadece giden mesajların provider tarafında iz sürülmesini
   kısıtlıyor).
2. **Gelen medya dosyaları indirilmiyor/proxy'lenmiyor** — sadece tip+varsa metin/caption
   kaydediliyor, dosyanın kendisi hiç çekilmiyor.
3. **Aynı telefon numarasına sahip birden fazla cari** — eşleştirme ilk bulduğu carriye bağlanıyor
   (deterministik ama keyfi), gerçek bir çözüm stratejisi (kullanıcıya seçtirme? en son işlem
   gören cariyi mi baz alma?) net değil.
4. **Konuşma arama** — `wa_conversations.php`/`mobile/wa_conversations.php` listesinde arama/filtre
   yok, sadece kronolojik liste.
5. **Mesaj teslim/okunma durumu senkronizasyonu** — UltraMsg'in `ack`/durum güncellemelerini
   (iletildi/okundu vb.) yakalayıp `wa_messages.status`'a yansıtan bir mekanizma yok, sadece ilk
   yazma anındaki durum tutuluyor.
6. **Attachment desteği** (giden tarafta) — `wa_send_now.php` zaten dosya/medya gönderebiliyor
   (`wa_upload_media()`), ama conversation ekranından doğrudan yeni bir ek göndermek için ayrı bir
   compose kutusu yok (mevcut `wa_send_now.php`'ye yönlendiriliyor).
7. **Gerçek zamanlı (websocket/polling) konuşma güncellemesi** — yeni bir inbound mesaj geldiğinde
   ekran otomatik yenilenmiyor, kullanıcı sayfayı manuel yenilemeli.

Hiçbiri kullanıcı onayı olmadan uygulanmayacak — bu liste görünürlük için.

## Finans Gider Türü sihirbazı — kategori raporu açık noktası (2026-07-04)
"Ne kaydediyorsun?" sihirbazının context-aware Gider Türü'ü `category_id` yerine `payment_type`
kolonuna yazıyor (migration yok, mevcut kolon genişletildi). Sonuç: bundan sonra sihirbazla girilen
YENİ gider kayıtları `category_id` taşımayacak — `accounting.php`'nin "Grup Özeti" sekmesi ve
`report_lib.php`'deki kategori-bazlı grafikler (INNER JOIN ile) bu yeni kayıtları hiç göstermeyecek
(eski kayıtlar etkilenmez). İstenirse ayrı bir turda `payment_type` bazlı bir özet/rapor eklenebilir
— kullanıcı onayı bekliyor, bu turda dokunulmadı.

## Personel kartları/sekmeli detay (web) — bilinçli kapsam dışı bırakılanlar (2026-07-04)
Web `personnel.php` (kart görünümü) ve `personnel_edit.php` (sekmeli detay — bu projede ayrı bir
`personnel_view.php` yok, view+edit tek dosyada birleşik) düzenlemesi sırasında:
- **"İzinler" (leave/izin) sekmesi eklenmedi** — şemada (`database/migrations/`) izin/leave ile
  ilgili hiçbir tablo yok (`personnel`, `personnel_devices` dışında personel-özel başka tablo yok).
  Eklemek yeni bir migration + yeni bir iş akışı (izin talebi/onay/bakiye) gerektirir — kullanıcı
  onayı olmadan uygulanmadı, ayrı bir karar/sprint konusu.
- **"Departman" alanı eklenmedi** — `personnel` tablosunda departman kolonu yok, sadece `role`
  (serbest metin) var. Kart görünümünde departman göstermek yerine sadece `role` kullanıldı,
  hayali bir kolon icat edilmedi.
- **Fotoğraf/photo kolonu yok** — kartlarda placeholder olarak isim baş harflerinden oluşan bir
  rozet kullanıldı (gerçek fotoğraf yüklemesi ayrı bir özellik kararı — `cv_path` bir belge/CV alanı,
  fotoğraf değil, bu ikisi karıştırılmadı).
- **Mobil parite eksik BİLEREK bırakıldı** — bu tur açıkça "SADECE web (personnel.php +
  personnel_edit.php)" kapsamıyla sınırlandırıldı (mobil `personnel_view.php` paralel bir güvenlik
  sprintinde aktif değiştiği için dokunulmadı). CLAUDE.md kural 7 ("yeni özellik hem web hem
  mobilde olmalı") bu madde için henüz KARŞILANMADI — mobilde kart görünümü/sekmeli detay ayrı bir
  turda ele alınmalı, kullanıcı onayı bekliyor.
- **`mobile/more.php`'de aynı yanıltıcı menü etiketi** ("🧭 Personel İş Takip Yönetimi") hâlâ duruyor
  — web'de `layout_top.php`'deki karşılığı bu turda "🧭 İş / Üretim Yönetimi" olarak düzeltildi ama
  görev kapsamı mobile dokunmayı içermediği için `mobile/more.php` değiştirilmedi. Parite için ayrı
  bir onay/tur gerekli.


Bu dosya "yapılacaklar listesi" değil, **karar bekleyen veya kapsamı netleşmemiş** maddelerin
kaydıdır. `PROJECT_RULES.md` gereği proje artık aktif geliştirme aşamasında değil — buradaki
hiçbir madde kullanıcı açıkça istemeden uygulanmaz. Amaç: bir sonraki oturumda "neredeydik"
sorusuna hızlı cevap.

## SECURITY SPRINT-001 sonrası açık nokta — "Bilgileri Düzenle" yetkisi (2026-07-04)
`mobile/personnel_view.php`'de şifre/hesap işlemleri artık admin + yeni `personnel_accounts`
yetkili "alt yönetici" ile sınırlı (bkz. `CHANGELOG.md`). Ancak aynı sayfadaki "✏️ Bilgileri
Düzenle" formu (Ad/Rol/Telefon/E-posta/IBAN/Notlar/Aktif) BİLİNÇLİ OLARAK dokunulmadan bırakıldı —
kullanıcı "personel sadece diğer personelin özel olmayan bilgilerini görüntüleyebilir (adres/
telefon/mail vb.), diğer bilgiler özeldir" dedi ama bu formu şimdilik admin-only yapmak yerine
"şimdilik dokunma" kararını verdi. Kullanıcı onayı olmadan bu form değiştirilmeyecek — ileride
netleşirse: (a) formu tamamen admin+alt-yönetici'ye kilitlemek, veya (b) alanları "özel" (IBAN,
Notlar, Aktif) / "özel olmayan" (Ad, Telefon, E-posta, Rol) olarak ikiye bölüp sadece özel olanları
kısıtlamak — iki seçenek de ayrı bir karar gerektirir.

## FINANCE UX REFACTOR — bilinen açık nokta (2026-07-04)
"Ne kaydediyorsun?" sihirbazı Ödeme/Gider (`finance_new.php`/`mobile/payment.php`) ekranına
`personnel_id` alanını ilk kez ekledi — bu, Muhasebe ekranının (`accounting.php`/
`mobile/accounting.php`) zaten sahip olduğu personel ödemesi girişiyle aynı anda var olacak.
Bu turda İKİSİ DE bilinçli olarak ayrı ekranlar olarak bırakıldı (kullanıcı onayı: "sistem
bütünlüğü açısında ikisini de düzenleyelim" ama TEK bir ekrana indirgeme YAPILMADI). İleride bu
iki giriş noktasının birleştirilip birleştirilmeyeceği ayrı bir karar/sprint gerektirir.

## SYSTEM AUDIT — Teknik Borç ve Öncelikler (2026-07-04, read-only denetim)
5 uzman ajanla (güvenlik, veri modeli, mimari/kod kalitesi, performans, UX/UI) yapılan kapsamlı
sistem denetiminin özeti — tam rapor bir Artifact olarak sunuldu, kritik/yüksek güvenlik bulguları
`KNOWN_BUGS.md`'ye işlendi. Burada sadece **kod değişikliği gerektiren, henüz karar bekleyen**
maddeler listeleniyor (denetim sırasında hiçbir kod/DB değiştirilmedi):

1. **Index eksikliği** — `jobs`, `tasks`, `finance_movements`, `stock_movements`,
   `internal_messages`, `internal_notifications` tablolarında PRIMARY KEY dışında index yok.
   Öneri: tek bir `040_add_missing_indexes.sql` (idempotent, `information_schema` kontrolü ile) —
   `ots-db-migration-dev`'e yazdırılabilir, kullanıcı onayı bekliyor.
2. **FK yok / silme akışlarında yetim kayıt riski** — personel/cari/iş silme akışları tüm bağımlı
   tabloları temizlemiyor (özellikle `job_logs` — ironik biçimde projenin önceki bir schema-drift
   bug'ının kaynağı olan tablo). Tam cascade listesi veya gerçek FK kısıtları eklenmeli.
3. **`personnel_devices` vs `personnel.telegram_*`** — paralel/çakışan iki model, ürün kararı
   gerekiyor (terk mi edildi, gelecek özellik mi).
4. **Yeni UX standardının (liste=sade, aksiyon=detayda) rollout'u** — şu an sadece
   `mobile/notifications.php`'de var, `jobs.php`/`tasks.php`/`mytasks.php` hâlâ eski, aksiyon-yüklü
   liste satırı deseninde. Kademeli bir taşıma planı gerekiyor.
5. **Design token benimsenmesi çok düşük** — mobilde 66 dosyadan sadece 2'si, webde 96 dosyadan
   sadece 1'i token kullanıyor. Web'in kendi ayrı/hardcoded paleti var, mobil ile senkron değil.
6. **`composer.json`/`composer.lock` repoda yok** — vendor'un hangi sürümlerle kurulduğu
   dokümante değil, reprodüksiyon imkânsız.
7. **En büyük dosyaların lib'e bölünmesi** — `messages.php` (684 satır), `dashboard.php` (564,
   5 fonksiyon gömülü), `sales.php` (455), `teklif.php` (360) — CLAUDE.md kural 5 ihlali.
8. **Toolbar aramadaki ölü `#searchSuggest` DOM'u** — canlı öneri JS'i hiç yazılmadı, kullanıcıyı
   yanıltan boş bir iskelet duruyor — ya inşa edilmeli ya kaldırılmalı.
9. **Web'de dar ekranda arama tamamen kayboluyor** (`layout_top.php:117`,
   `@media(max-width:960px){.search{display:none}}`) — alternatif erişim yok.
10. **CSRF token mekanizması proje genelinde yok** — büyük bir mimari karar, ayrı değerlendirme
    gerektirir.

Öncelik sırası ve tam gerekçeler → System Audit raporu (Artifact, 2026-07-04). Hiçbiri kullanıcı
onayı olmadan uygulanmayacak — bu liste sadece görünürlük için.

## Muhtemelen ÇÖZÜLDÜ, backlog.md güncellenmeli (not, bu dosyada kod değişikliği yapılmadı)
- `memory/backlog.md`'deki **"Web'de 'Görevlerim' sayfası yok"** maddesi (2026-07-03 tarihli) artık
  güncel değil: aynı gün içinde ilerleyen bir oturumda web'e `mytasks.php` ("İşlerim") eklendi,
  `task_new.php`'nin bildirim linki düzeltildi, ayrıca "Kendime İş Ekle" (`mytask_new.php` +
  `mobile/mytask_new.php`) eklendi ve "Görevlerim" ifadesi projede her yerde "İşlerim" olarak
  standardize edildi. Bu değişiklikler bu doküman turunda YAZILMADI ama daha önceki bir turda
  koda işlendi — commit durumu için `git status`/`git log` kontrol edilmeli, backlog.md ile
  memory/features.md buna göre güncellenmeli.

## Workspace (Multi-Tenant) Architecture — ayrı, büyük bir proje (2026-07-04'te açıldı)
`layout_top.php`'deki "Aktif Şirket" kutusu incelendiğinde tamamen işlevsiz olduğu görüldü:
`name`/`onchange` yok, seçenekler (PRIMAC/ACANS/MEDYAROTA/DİJİMED) sabit HTML metni, hiçbir
session/DB alanına bağlı değil. Kod genelinde `company_id`/`tenant_id`/`workspace` kavramı
HİÇBİR yerde yok — ACANS ve PRIMAC zaten ayrı sunucu + ayrı veritabanı olarak çalışıyor (bkz.
CLAUDE.md ortam modeli), yani bugün "workspace değiştirme" diye bir mekanizma yok, olamaz da
(fiziksel olarak ayrı kurulumlar). 2026-07-04'te bu kutu "Çalışma Alanı" bilgi etiketine
sadeleştirildi (bkz. `CHANGELOG.md`) — sahte dropdown kaldırıldı, DB/session/route/iş mantığı
DEĞİŞMEDİ.

Kullanıcının gelecekte istediği gerçek mimari — **ayrı bir proje olarak** — şunları kapsayacak:
- Çoklu şirket
- Çoklu şube
- Çoklu organizasyon
- Çoklu depo
- Yetki bazlı çalışma alanları (kullanıcı sadece yetkili olduğu çalışma alanlarını görür)

Bu, neredeyse her tabloya (`jobs`, `tasks`, `contacts`, `stock_items`, `finance_*`, `internal_notifications`
vb.) yeni bir `workspace_id` kolonu + kapsamlı bir yetki/oturum katmanı gerektirir — yeni migration(lar),
DB şema değişikliği ve yeni mimari anlamına gelir. Kullanıcı açıkça onaylamadan/istemeden
BAŞLANMAYACAK, ayrı bir sprint/proje olarak ele alınacak.

## Açık — kapsamı netleşmemiş
- **Mobil parite eksiği**: `work_center.php`, `trade_documents.php`, `design.php` sadece webde var,
  mobilde karşılığı yok (2026-07-03, commit `1ff6f1e` ile web tarafı eklendi ama mobil hâlâ eksik).
  Kapsam belirsiz: ayrı sayfa mı, yoksa mevcut `jobs.php`/`contacts.php` içine filtre olarak mı
  gömülecek — kullanıcıya danışılmadan seçilmeyecek.
- **`trade_document_view.php` mobil parite açığı** (2026-07-05, UX/STABILITY PATCH-004'te tekrar
  tespit edildi — Son İşlemler route resolver çalışması sırasında ortaya çıktı): web'de mali
  belge/ticari belge detay ekranı var, mobilde hiç yok. Resolver mobil kullanıcıyı da web'in mutlak
  URL'ine yönlendiriyor (önceden zaten böyleydi, artık en azından her zaman doğru belgeye gidiyor)
  — ama gerçek mobil parite için ayrı bir `mobile/trade_document_view.php` yazılması gerekiyor,
  kullanıcı onayı bekliyor.
- **Satış/Satın Alma detay ekranı yok** (2026-07-05, UX/STABILITY PATCH-004'te tespit edildi): DB'de
  gerçek bir "satış"/"satın alma" kaydı kavramı yok — mevcut `activity_logs` girişlerindeki
  `entity_id` aslında ilk satır kaleminin ÜRÜN id'si, bir satış/satın alma işlemine ait tekil bir
  detay sorgusu/sayfası hiçbir yerde tanımlı değil. Son İşlemler'den "satış detayına git" beklentisi
  ancak yeni bir ekran + muhtemelen yeni bir sorgu modeliyle (finance_movements+stock_movements'ı
  tek işlem etrafında gruplama) karşılanabilir — **yeni özellik**, kullanıcı onayı olmadan
  BAŞLANMAYACAK.
- **Native cihaz takvimi senkronizasyonu** (ICS/webcal export): Uygulamanın kendi Takvim sayfası
  (`takvim.php`/`mobile/calendar.php`) var ve çalışıyor, ama iOS/Android'in kendi Takvim uygulamasıyla
  gerçek senkron YOK. Kimlik doğrulamalı bir abonelik linki (webcal://…) gerektirir — ayrı, daha
  büyük bir özellik kararı, henüz istenmedi.
- **VAPID push anahtarı sunucu config.php'lerine elle taşınmalı** (2026-07-03): `push_lib.php` artık
  `app_config()`'ten okuyor, tanımlı değilse koddaki eski sabit değere düşüyor (kırılma yok). Kalıcı
  çözüm için ACANS/PRIMAC sunucularındaki gerçek `config.php`'lere 3 satır elle eklenmeli — repo
  dışı erişim gerektirir, kullanıcı seyahatten dönünce yapılacak. Detay → `memory/backlog.md`.

## UI/UX Sprinti (2026-07-04) — bilinçli kapsam dışı bırakılanlar (kullanıcı onayı: 2026-07-04)
Mobil ana ekran + toolbar arama iyileştirmesi sırasında şu maddeler kasıtlı olarak ERTELENDİ
(kapsam büyümesin diye, kullanıcıya danışılmadan yapılmayacak) — kullanıcı bu erteleme listesini
2026-07-04'te onayladı, madde başlıkları hâlâ "kullanıcı açıkça istemeden uygulanmaz" kuralına tabi:
- **Global aramanın gerçek zamanlı autocomplete'i**: Toolbar arama çubuğu (`mobile/common.php::
  topx()`) şu an sadece `search.php`'ye normal GET submit yapıyor; DOM'da `#globalSearchInput` +
  boş `#searchSuggest` kutusu ileride bir JS+debounce+AJAX katmanı için hazır duruyor ama bu katman
  YAZILMADI — yeni bir JSON uç noktası (`search.php?ajax=1` gibi) gerektirir, "yeni özellik
  eklenmeyecek" kısıtına takılır.
- **Arama kapsamının genişletilmesi**: `search_lib.php` şu an 9 modülü (iş, cari, stok, personel,
  finans hesap/hareket, çek-senet, teklif, ticari belge, sayfa kısayolları) kapsıyor. Kullanıcının
  istediği tam liste (banka/kasa/tahsilat/ödeme/fatura/sipariş/üretim/üretim aşaması/dosya/etiket/
  takvim/rapor/sistem kayıtları/İşlerim/Notlar/Bildirimler) çok daha geniş — her biri yeni SQL
  sorgusu demek, "iş mantığı değişmeyecek" kısıtının dışında, ayrı ve büyük bir sprint gerektirir.
- **FAB (Floating Action Button)**: Tasarım dili standardına eklendi ama somut bir ekrana
  UYGULANMADI — liste ekranlarında (jobs.php, contacts.php gibi "+ Yeni" ihtiyacı olan yerlerde)
  ayrı bir iş olarak değerlendirilmeli.
- **Web arayüzünün mobil tasarım diline taşınması**: Kullanıcı "zaman içinde" dedi, bu sprint
  sadece mobile/ ile sınırlı kaldı.
- **40+ mobil sayfanın tamamının yeniden tasarımı**: Bu sprint `mobile/index.php` (ana ekran),
  `mobile/common.php` (toolbar + design token'lar), `mobile/more.php` (test paneli taşıma) ile
  sınırlı kaldı. Diğer ekranlar (jobs.php, contacts.php, messages.php vb.) yeni design token'ları
  otomatik miras alıyor (ortak CSS'ten geliyor) ama sayfa-özel kompozisyon/yoğunluk iyileştirmesi
  görmedi — ayrı, kademeli bir iş olarak planlanmalı.

## Bilinçli olarak ERTELENMİŞ (kullanıcıya danışılmadan yapılmayacak)
- **Gerçek satın alma/satış detay ekranı** (2026-07-06, REOPEN-002 kapanışı sırasında kullanıcı
  isteği) — Son İşlemler'de bir satın alma/satış kaydına tıklayınca gerçek bir işlem detay sayfası
  açılsın isteniyor (şu an `no_detail` pasif durum gösteriliyor — REOPEN-002'nin güvenli/doğru ama
  geçici çözümü). Kullanıcı bunu **Purchase & Sales 2.0** kapsamına erteledi. Teknik ön koşul:
  `purchase.php`'de `stock_movements.finance_movement_id` HİÇ set edilmiyor (satıştaki
  `stock_reverse_sale()`'in dayandığı bağ satın almada yok) — önce bu bağ kurulmalı, sonra
  `finance_movement_id` "işlem kimliği" olarak `activity_log()`'a taşınmalı, yeni bir
  `purchase_view.php`/`mobile/purchase_view.php` (+ satışın kendi eşdeğeri, parite için) yazılmalı,
  son olarak `activity_lib.php`'nin haritasına `sale`/`purchase` eklenip `no_detail`'den çıkarılmalı.
  Web+mobil parite şart. Sıra: REOPEN-003 → Dashboard 2.0 → Calendar 2.0 → CRM 2.0 → **Purchase &
  Sales 2.0** (bu madde dahil) → ... (bkz. `NEXT_SESSION.md` öncelik sırası).
- Bildirim id'lerinde sahiplik kontrolü eksikliğinin (IDOR, düşük risk) kapatılması — bkz.
  `KNOWN_BUGS.md`. Düşük öncelikli ama net bir düzeltme var, `PROJECT_RULES.md`'nin "Bug Fix"
  önceliğine göre bir sonraki bakım turunda ele alınabilir.
- `tasks` tablosunda kimin (admin mi, kullanıcının kendisi mi) kaydı oluşturduğunu ayırt eden bir
  kolon yok (`created_by` gibi) — "Kendime İş Ekle" özelliğinin 2026-07-03'te eklenmesiyle ortaya
  çıktı, `tasks.php` ("Tüm Görevler") admin görünümünde iki tür kayıt görsel olarak ayırt edilemiyor.
  Güvenlik açığı değil, izlenebilirlik notu — bkz. `KNOWN_BUGS.md`.

## Referanslar
Teknik/öncelik kuralları → `PROJECT_RULES.md` ve `CLAUDE.md`. Geçmiş özellik kararları →
`memory/features.md`. Ham backlog → `memory/backlog.md` (bu dosyayla kısmen çakışıyor, bir sonraki
temizlik turunda ikisi birleştirilebilir).
