# VERSIONING.md — OTS Resmi Sürüm Takip Dokümanı

Bu dosya projenin resmi sürüm yönetim dokümanıdır. Sürüm numaralandırma öncesi bu projede HİÇ
formal versiyonlama yoktu (git tag yok, `VERSION` dosyası yok, composer.json'da version alanı yok).
Bu doküman 2026-07-03'te, tek-ortam (DEV/PROD) modeliyle birlikte İLK KEZ başlatıldı — bu tarihten
ÖNCEKİ "Dağıtım Geçmişi" satırları `memory/deploy.md` ve commit mesajlarından geriye dönük
yeniden inşa edildi, kesin sürüm numarası içermez (o dönemde numara yoktu).

**Numaralandırma şeması**: `MAJOR.MINOR.PATCH` (Semantic Versioning benzeri, elle takip edilir).
- MAJOR: mimari/DB şema kırılımı gerektiren büyük değişiklik.
- MINOR: yeni özellik veya çok sayıda küçük iyileştirme (bir "geliştirme turu").
- PATCH: sadece bug fix / tutarlılık düzeltmesi.

## Proje Adı
OTS — Online Takip Sistemi (ACANS/PRIMAC ortak PHP ERP)

## Ortam Ayrımından Önceki Son Ortak Durum (referans noktası, ne DEV ne PROD sürümü)
**Commit `bb8a710`** (2026-07-03) — web `mytasks.php` eklenmesi, mesajlaşma/bildirim düzeltmeleri,
migration 038'e kadarki set. Bu değişiklik DEV/PROD ayrımı RESMİLEŞMEDEN ÖNCE yapıldı — o an için
"DEV" veya "PROD" diye ayrı bir hedefi yoktu, iki Masaüstü paketi (ACANS-GUNCELLEME +
PRIMAC-GUNCELLEME) simetrik tazelenmişti. Bu yüzden aşağıdaki v1.0.0/v1.1.0-dev sayımının
İÇİNE dahil edilmiyor — sadece kronolojik referans noktası. Sunucuda (acanstr.com/ots) bu commit'in
gerçekten çalışır durumda olduğu ayrıca doğrulanmamıştır (bkz. `memory/deploy.md`).

## Security Sprint Durumu
Repository geçmişini esas alan, geriye dönük tutarlı hale getirilmiş sayım (2026-07-05'te
netleştirildi — bkz. `ROADMAP.md` "Security Roadmap"):
- ✅ **SECURITY SPRINT-001** — `mobile/personnel_view.php` keyfi şifre sıfırlama açığı (2026-07-04,
  `d511fad`). **PASS** (primac.tr'de smoke test edildi).
- ✅ **SECURITY SPRINT-002** — `mobile/task_view.php` IDOR (`task_status` güncellemesinde sahiplik
  kontrolü eksikliği). *Retroaktif belgeleme (2026-07-05): orijinali 2026-07-04'te genel dev
  sprint numaralandırmasıyla ("SPRINT-003", `5fb2c43`) kapatılmıştı — SECURITY SPRINT
  numaralandırmasına bu tarihte dahil edildi, uydurma tarih/detay eklenmedi.* **PASS**.
- ✅ **SECURITY SPRINT-003** — `sifre_sifirla.php` brute-force + rate-limit sertleştirmesi
  (2026-07-05). Yerel QA'da 8/8 senaryo **PASS**. Detay → `CHANGELOG.md`.
- 🔄 **SECURITY SPRINT-004** (devam ediyor) — Merkezi CSRF Koruma Altyapısı. FAZ-1 → FAZ-4F
  tamamlandı, **HIGH-RISK CSRF CHECKPOINT AUDIT: PASS** (checkpoint commit `a32893c`). **FAZ-5A —
  CRM: PASS** (`contact_new.php`, `contact_view.php`, commit `4708cd6`, yerel QA 4/4 senaryo PASS).
  Sıradaki faz: **FAZ-5B — kapsamı netleşmemiş** (Stok/Ürün, İş/Görev ana formları, Mesajlaşma/
  Talep, Satış/Satın Alma). Detay → `CHANGELOG.md`, `ROADMAP.md` "Security Roadmap".

## Current Development Version
**v1.1.0-dev** (primac.tr) — ortam ayrımından SONRAKİ ilk geliştirme turu
+ **WhatsApp Conversation/Inbound MVP: PASS** (2026-07-05, commit `dae3e62`): migration
  `041_whatsapp_conversations.sql` (`wa_conversations`+`wa_messages`), `wa_webhook.php` (inbound,
  DB'de saklanan rastgele `?key=`), sender-scope allowlist mimarisi (bugün sadece
  `wa_send_now.php` conversation history'ye yazıyor — OTP/sistem mesajları hariç), web+mobil
  konuşma ekranları, `contact_view.php` entegrasyonu. Detay → `CHANGELOG.md`.
+ **UX/STABILITY PATCH-004 — Son İşlemler Route Resolver: PASS** (2026-07-05, commit `dff59d5`):
  kök neden `activity_logs.url`'in yazma anında sabit string olarak donduruluyup render anında
  hiç yeniden çözülmemesiydi (platforma göre yanlış route açılabiliyordu). Merkezi
  `activity_target_url()` resolver'ı ile çözüldü, silinmiş kayıtlarda güvenli fallback eklendi.
  Bilinen kapsam dışı: Satış/Satın Alma detay ekranı yok (yeni özellik), Finans özel davranışları,
  trade_document mobil parite açığı — detay → `CHANGELOG.md`, `ROADMAP.md`.
+ **UX/STABILITY PATCH-003 — Takvim Günlük Filtre**: kod değişikliği YOK — yerel QA'da reprodüksiyon
  yapılamadı, kök neden primac.tr'nin muhtemelen `dd35352` (asıl düzeltme commit'i) öncesi `d7c593a`
  referans sürümünde kalmış olması (deploy açığı). Detay → `CHANGELOG.md`.
+ **SECURITY SPRINT-004 — DEVAM EDİYOR** (2026-07-05, checkpoint commit'ler `7934805`/`90dffa7`/
  `a32893c`/`4708cd6`): Merkezi CSRF koruma altyapısı. FAZ-1 (boot.php helper'ları + otomatik token
  enjeksiyonu) → FAZ-2 (AJAX `X-CSRF-Token` header) → FAZ-3A/3B/3C (pilot, Bildirimler, Finans/
  Muhasebe) → FAZ-4A/4B/4C/4D/4E/4F (Finans işlem ekranları, Personel, Kimlik/Sistem, Mali belge/
  Teklif, WhatsApp, İş/Görev) tamamlandı. **HIGH-RISK CSRF CHECKPOINT AUDIT: PASS.** **FAZ-5A — CRM
  PASS** (`contact_new.php`, `contact_view.php`, commit `4708cd6`, yerel `ots_sectest` QA'da 4/4
  token'lı/token'sız senaryo + GET regresyon testi PASS). Detay → `CHANGELOG.md`. Sıradaki faz:
  **FAZ-5B — kapsamı netleşmemiş** (Stok/Ürün, İş/Görev ana formları, Mesajlaşma/Talep, Satış/
  Satın Alma).
+ **SECURITY SPRINT-003 — DEV QA PASS** (2026-07-05, henüz commit/push edilmedi): `sifre_sifirla.php`
şifre sıfırlama brute-force sertleştirmesi — deneme sayacı+5-deneme iptali, IP bazlı rate-limit,
hesap bazlı resend-throttle, TTL 30dk→10dk, başarılı reset sonrası tam session temizliği. Yerel
`ots_sectest` QA'da 8/8 senaryo PASS. Detay → `CHANGELOG.md`.
+ **UX / STABILITY PATCH-002 — DEV QA PASS** (2026-07-05, henüz commit/push edilmedi, primac.tr'ye
henüz yüklenmedi): Son İşlemler routing (11 çapraz-platform link), Teklif liste/detay CRUD
tutarsızlığı, Çek/Senet F5 çift kayıt (PRG eklendi), Takvim günlük filtre — hepsi yerel `ots_sectest`
QA'da **PASS** aldı (gerçek HTTP istekleri + içerik doğrulamasıyla). İki madde **koşullu**:
Mobil Mesajlaşma boşluğu **CONDITIONAL PASS** (CSS düzeltmesi doğrulandı, piksel-seviye görsel teyit
gerçek cihaz gerektiriyor), PWA Push **SERVER-SIDE PASS** (sunucu→FCM teslimatı doğrulandı, Safari/iOS
arka plan teslimatı test edilemedi). WhatsApp gelen mesaj takibi kapsam dışı bırakıldı, ayrı
**WHATSAPP INTEGRATION SPRINT** olarak planlanacak. Detay → `CHANGELOG.md`, `KNOWN_BUGS.md`,
`ROADMAP.md` "Sıradaki sıra". **Production'a DOKUNULMADI, dokunulmayacak** (ayrı "DEPLOY MODE"
komutu bekliyor).
+ **SECURITY SPRINT-001** (2026-07-04, `d511fad` ile commit edildi): `mobile/personnel_view.php`
kritik şifre sıfırlama açığı (System Audit bulgusu) kapatıldı — `reset_pw`/`make_login` artık
`$_POST['uid']`'e hiç güvenmiyor, hedef hesabı DB'den görüntülenen personele (`$id`) bağlı gerçek
hesaptan çekiyor. Kullanıcı kararıyla kapsam genişledi: bu işlemler artık admin VEYA yeni
`personnel_accounts` yetkili "alt yönetici" ile sınırlı (`boot.php::module_list()`, yeni migration
gerekmedi). Yerel MariaDB test ortamında uçtan uca doğrulandı (8 senaryo PASS). **primac.tr'de
smoke test edilip PASS alındı.**
+ **UI/UX İyileştirmeleri + SPRINT-003** (2026-07-04, 7 ajanla tamamlandı, `5fb2c43`+`697f985` ile
commit edildi): Üst Menü (Takvim linki), Notlarım Düzenle, Satın Alma inline ürün, Global Arama
(5 yeni modül), İşlerim (Düzenle/Detay/Sil, soft-delete, migration 040), Personel kart+sekme
(SADECE web, "Personel İş Takip Yönetimi" adının aslında personel yönetmediği bulundu, üretim/iş
sistemi TAŞINMADI, sadece etiket düzeltildi), Finans bağlam-duyarlı Gider Türü. Yan ürün olarak 2
açık/bug daha kapatıldı: `mobile/task_view.php` IDOR, `accounting.php` sihirbaz JS scope hatası.
LOCAL QA MODE'da 7/7 modül yerel MariaDB'de uçtan uca test edildi, bulunan 4 sorun (web Satın
Alma'nın `mm()` hatasıyla tamamen kırık olması, görev arama route'u, personel soft-delete sayaç
filtresi, boş-tarih SQL hatası) düzeltilip yeniden doğrulandı (`697f985`).
+ **SPRINT CLOSE ek düzeltmeleri** (2026-07-04, `b5c8410`..`d7c593a` ile commit edildi): Komuta
Merkezi'ne Takvim modül kutusu (topbar pill denemesi kullanıcı adını bozduğu için geri alındı),
web mesaj rozeti + sıfırdan web Push bildirimi (`sw.js`, gerçek Chromium ile uçtan uca doğrulandı),
Takvim'de görev/not linklerinin düzeltilmesi + silinmiş görevin artık görünmemesi, web takvimde
gün numarasının tıklanabilir hale getirilip günlük filtreli detay paneli eklenmesi. Detay →
`CHANGELOG.md` "SPRINT CLOSE". **primac.tr'de smoke test edilip PASS alındı.**
İçerik (bb8a710'dan SONRA yapılan değişiklikler): "İşlerim"/"Görevlerim" terim standardizasyonu,
"Kendime İş Ekle" özelliği, emoji paneli konumlandırma düzeltmesi, tek-ortam (DEV/PROD) yönetim
modelinin resmileşmesi, `VERSIONING.md`/`ROADMAP.md`/`KNOWN_BUGS.md`/`DATABASE.md` dokümantasyon
seti + **Sprint-001** (2026-07-04): Bildirimler modülünde sahiplik/toplu-silme güvenlik açığının
kapatılması (migration 039, `notifications_lib.php`, yeni `user_notification_status` tablosu),
İşlerim/İş Ekle/Kendime İş Ekle'de küçük tutarlılık düzeltmeleri — **primac.tr'de fiilen test
edildi ve onaylandı, `0ba36da` ile lokal checkpoint commit atıldı** (push yok, release yok).
+ **UI/UX Sprinti** (2026-07-04): mobil ana ekran + paylaşılan toolbar'a design token sistemi,
global arama çubuğu, kart tutarlılığı/yoğunluk iyileştirmesi, bildirim test alanının admin'e
taşınması. Detay → `CHANGELOG.md`. **Lokal checkpoint commit ile kaydedildi** (bu oturumun sonu,
"END OF SESSION MODE"), primac.tr'de görsel doğrulama için DEV test paketi (`guncelleme.zip`,
`~/Desktop/PRIMAC-GUNCELLEME/`) tazelendi — push yok, release yok, PROD'a gönderilmedi.
+ **UX SPRINT-001** (2026-07-04): Bildirimler modülü kart/detay standardına taşındı —
`notif_type_info()` ile DB'siz tip türetme (ikon+renk), liste kartı tamamen tıklanabilir tek
kart, tekil aksiyonlar (Sil, İlgili Modüle Git) yeni `mobile/notification_view.php` detay
ekranına taşındı, `notif_get_for_user()` ile Sprint-001'deki sahiplik kuralı yeniden kullanıldı
(IDOR açılmadı). Yeni standart UX kuralı `PROJECT_RULES.md`'ye eklendi. **primac.tr'de
`primactr_ux_sprint001_test.zip` ile test edildi, 8/8 senaryo PASS** (liste görünümü, tam kart
tıklama, detay ekranı, URL gizliliği, sahiplik/IDOR testi, tekil silme konumu, toplu silme,
farklı ekran boyutları).
+ **UX İyileştirme — "Çalışma Alanı"** (2026-07-04): `layout_top.php`'deki işlevsiz "Aktif Şirket"
dropdown'ı (hiçbir session/DB alanına bağlı değildi, projede gerçek bir çoklu-şirket/tenant
altyapısı hiç yoktu) "Çalışma Alanı" bilgi etiketine sadeleştirildi — DB/session/route/iş mantığı
değişmedi. Gerçek çoklu-çalışma-alanı mimarisi ayrı, büyük bir proje olarak `ROADMAP.md`'ye
"Workspace (Multi-Tenant) Architecture" başlığıyla açıldı. Bu değişiklik DEV test kapsamına dahil
edildi (yukarıdaki 8/8 PASS ile birlikte doğrulandı).
+ **SYSTEM AUDIT MODE** (2026-07-04, read-only, kod/DB değişmedi): 5 ajanla mimari/güvenlik/
performans/UX-UI/veri modeli/kod kalitesi kapsamlı denetimi yapıldı. 2 kritik/yüksek güvenlik
açığı (`mobile/personnel_view.php` şifre sıfırlama, `mobile/task_view.php` IDOR) `KNOWN_BUGS.md`'ye
işlendi, teknik borç `ROADMAP.md`'ye işlendi. Bu denetim artık her büyük sprint/RC/major sürüm/
production öncesi otomatik tekrarlanacak kalıcı bir standart (`PROJECT_RULES.md`).
+ **FINANCE UX REFACTOR** (2026-07-04, `checkpoint(security-prep)` ile commit edilecek): Ödeme/
Gider + Muhasebe ekranlarına "Ne kaydediyorsun?" sihirbazı eklendi (7 seçenek: Cari Ödemesi/
İşletme Gideri/Personel Ödemesi/Vergi-SGK/Banka-Kredi-Kart/Araç Gideri/Diğer). DB şeması
DEĞİŞMEDİ — tür DB'de saklanmıyor, `finance_record_type_info()` ile mevcut kayıttan türetiliyor.
6 dosya (`finance_lib.php`, `finance_new.php`, `accounting.php`, `mobile/payment.php`,
`mobile/movement_view.php`, `mobile/accounting.php`), Tahsilat/Gelir akışı hiç değişmedi. Detay →
`CHANGELOG.md`. **Henüz primac.tr'de test edilmedi** — bu oturumda DEV'e yükleniyor, sonraki
oturumda test/onay bekliyor.

## Current Production Version
**v1.0.0** (acanstr.com/ots)
Ortam ayrımından önceki son bilinen durumu temsil eden BAŞLANGIÇ NOKTASI kabulü — yukarıdaki
`bb8a710` referans noktasına kadarki her şeyi kapsar. **Not**: Bu sürüm numarası retroaktif
(geriye dönük) atanmıştır, sunucudaki gerçek dosyaların bu commit ile birebir eşleştiği ayrıca
doğrulanmalı — geçmişte elle phpMyAdmin migration çalıştırma / zip'in gerçekten yüklenip
`guncelle.php`'nin çalıştırıldığı teyit edilmeden "canlıda" varsayılmamalı (bkz. `memory/deploy.md`).

## Next Planned Version
**v1.1.0** — v1.1.0-dev DEV'de onaylandıktan ve "DEPLOY MODE" komutu verildikten sonra production'a
(acanstr.com/ots) bu numarayla çıkacak.

## Release Durumu
🟢 **primac.tr = d7c593a'nın güncel referans sürümü (2026-07-04)** — `guncelleme.zip`
(`git archive HEAD` + `vendor/`) ile primac.tr'ye yüklendi (`guncelle.php` ile Doğrula→Migration→
Smoke Test akışı izlendi, migration 040 dahil uygulandı). SECURITY SPRINT-001 + UI/UX
İyileştirmeleri + SPRINT-003 + SPRINT CLOSE ek düzeltmelerinin TAMAMI primac.tr'de smoke test
edilip kullanıcı tarafından **PASS** onayı verildi. Production'a henüz gönderilmedi ("DEPLOY MODE"
için ayrı bir komut gerekir) — push GitHub'a bu SPRINT CLOSE turunda yapıldı (aşağıya bakın).

🟡 (Önceki tur) UX SPRINT-001'in referans sürümü — `d9c938b` commit'i
`guncelleme.zip` (`git archive HEAD` + `vendor/`) ile "DEV DEPLOY MODE" kapsamında primac.tr'ye
yüklendi. Canlı cihazdan alınan ekran görüntüsüyle doğrulandı: bildirim kart/detay tasarımı
(tip rozetleri, 3 satır özet, "Devamını gör →", "Aç" butonunun kaldırılması) ve "Çalışma Alanı"
UI değişikliği birebir çalışıyor. Migration tarafında yeni migration yoktu (39/39 zaten güncel).
Production'a henüz gönderilmedi ("DEPLOY MODE" için ayrı bir komut gerekir, bu sadece primac.tr/DEV
içindi) — push de yapılmadı.

🟡 (Önceki tur, Sprint-001) DEV'de fiilen test edildi (2026-07-04) — primac.tr'ye
`primactr_dev_sprint001_test.zip` ile yüklendi, manuel test sırasında 2 ek hata bulunup düzeltildi
(aşağıya bakın), `0ba36da` ile commit edildi.

## Release Tarihi
- v1.0.0 (production, tahmini/başlangıç): 2026-07-03
- v1.1.0 (production, planlanan): TBD — DEPLOY MODE komutunu bekliyor

## Son Release Notları (v1.1.0-dev, henüz yayınlanmadı)
- "Görevlerim" ifadesi kaldırıldı, her yerde "İşlerim" kullanılıyor (web+mobil).
- Yeni: "Kendime İş Ekle" (`mytask_new.php` + `mobile/mytask_new.php`) — `tasks` yetkisi olmayan
  kullanıcı da kendine iş kaydı ekleyebiliyor.
- "İş Ekle" (`task_new.php`, admin başkasına atar) ile "İşlerim" (`mytasks.php`, kendi listem)
  net şekilde ayrıştırıldı — mobil ana ekrandaki yanlış eşleşen kart etiketi düzeltildi.
- Emoji seçici paneli artık mesaj kutusunun üzerine binmiyor (yukarı açılıyor).
- Mesajlaşmada "bildirim var ama mesaj yok" hayalet rozet sorunu ve web'de bildirime tıklayınca
  mobil ekrana düşme sorunu kapatıldı (bkz. `KNOWN_BUGS.md`, migration 038).
- Ortam yönetimi resmileşti: DEV=primac.tr, PROD=acanstr.com/ots, PROD'a sadece "DEPLOY MODE" ile
  dokunulur (`PROJECT_RULES.md`, `CLAUDE.md`, `memory/deploy.md` güncellendi).
- **DEV testinde bulunan 2 ek hata (2026-07-04)**: (1) `notes_lib.php`'nin "Kendime Not Ekle"
  kendine-mesaj kaydı `is_read=0` ile oluşuyordu ve hiçbir zaman okundu işaretlenemiyordu (kişi
  listesi kendini hariç tutuyor) — 💬 rozeti kalıcı şişiyordu, `is_read=1` yapıldı. (2) Emoji
  butonu `share_lib.php`'de hâlâ composer'ın `width:50px` kısıtına takılıp taşıyordu — metin
  kaldırılıp ikon-only yapıldı.

## Bekleyen Sprintler
(Detay → `ROADMAP.md`)
1. Mobil parite eksiği: `work_center.php`/`trade_documents.php`/`design.php` mobilde yok.
2. ~~Bildirim id'lerinde sahiplik kontrolü (IDOR) kapatılması~~ — **Sprint-001'de çözüldü** (bkz.
   yukarı, `notifications_lib.php`).
3. `tasks` tablosuna kayıt-kaynağı ayrımı (`created_by` benzeri) eklenmesi kararı.
4. VAPID push anahtarının sunucu `config.php`'lerine elle taşınması (ACANS+PRIMAC).
5. Native cihaz takvimi senkronizasyonu (ICS/webcal) — kapsam kararı bekliyor, öncelik değil.
6. `notif_admin_delete_global()` (Sprint-001'de eklendi) henüz bir admin UI butonuna bağlı değil —
   istenirse tek satır ekleme.

## Dağıtım Geçmişi (Deployment History)
| Tarih | Hedef | Commit/Not | Kaynak |
|---|---|---|---|
| 2026-07-04 | DEV (primac.tr) | `d7c593a` — SECURITY SPRINT-001 + UI/UX SPRINT-003 + SPRINT CLOSE ek düzeltmeleri, migration 040 | Bu oturum — Doğrula→Migration→Smoke Test PASS, kullanıcı onayı |
| 2026-07-03 | DEV+PROD (ortak, ayrım öncesi) | `bb8a710` — mytasks.php web, mesajlaşma/bildirim düzeltmesi, migration 038 | `memory/deploy.md` — zip MD5 ile doğrulandı |
| 2026-07-02 | ACANS + PRIMAC (elle phpMyAdmin) | `26bffcb` — gider kategorisi + marka adı + yetki canlı yenileme, migration 022/023 | `memory/deploy.md` "schema_migrations tuzağı" kaydı |
| 2026-07-03 | — (deploy değil) | GitHub remote bağlandı (`ersinibil/primac`), 109 commit geçmişi push edildi | `memory/deploy.md` |
| ≤2026-06-30 | ACANS + PRIMAC (ayrıntı yok) | Migration 001–021 arası kurulum/özellik dalgaları — sürüm numarasıyla takip edilmedi | `memory/features.md` |

**Bilinen eksik**: Bu tarihten önceki dağıtımların HANGİ commit'in sunucuda o an çalıştığını kesin
gösteren bir kaydı yok (zip elle hazırlanıp yükleniyordu, otomatik iz sürülmüyordu). Bundan sonraki
her "DEPLOY MODE" çalıştırmasında bu tabloya yeni bir satır eklenmesi zorunlu.

## Otomatik Güncelleme Kuralı (2026-07-03'ten itibaren)
- Her **sprint/geliştirme turu sonunda**: bu dosya (`VERSIONING.md`) VE `CHANGELOG.md` güncellenir.
- Her **Production yayını** (DEPLOY MODE) sonrası: `Current Production Version` güncellenir,
  Dağıtım Geçmişi'ne yeni satır eklenir.
- Her **Development geliştirmesi** sonrası: `Current Development Version` (ve gerekiyorsa
  `-dev` içeriği) güncellenir.

## Referanslar
Ortam kuralları → `PROJECT_RULES.md` "Ortam Yönetimi". Deploy mekanizması → `memory/deploy.md`.
Özet değişiklik günlüğü → `CHANGELOG.md`. Açık işler → `ROADMAP.md`.
