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

## Current Development Version
**v1.1.0-dev** (primac.tr) — ortam ayrımından SONRAKİ ilk geliştirme turu
+ **SECURITY SPRINT-001** (2026-07-04, `d511fad` ile commit edildi): `mobile/personnel_view.php`
kritik şifre sıfırlama açığı (System Audit bulgusu) kapatıldı — `reset_pw`/`make_login` artık
`$_POST['uid']`'e hiç güvenmiyor, hedef hesabı DB'den görüntülenen personele (`$id`) bağlı gerçek
hesaptan çekiyor. Kullanıcı kararıyla kapsam genişledi: bu işlemler artık admin VEYA yeni
`personnel_accounts` yetkili "alt yönetici" ile sınırlı (`boot.php::module_list()`, yeni migration
gerekmedi). **Yerel MariaDB test ortamında (primac.tr benzeri, prod'a dokunulmadan) uçtan uca
doğrulandı** (8 senaryo PASS — admin/alt-yönetici/düz-personnel farklı yetki seviyeleri, tampered
`uid` reddi, admin şifresi korunması, hata logu temiz). **primac.tr'nin KENDİSİNDE henüz smoke test
yapılmadı** — bir sonraki oturumun ilk işi.
+ **UI/UX İyileştirmeleri + SPRINT-003** (2026-07-04, 7 ajanla tamamlandı, HENÜZ commit edilmedi):
Üst Menü (Takvim linki), Notlarım Düzenle, Satın Alma inline ürün, Global Arama (5 yeni modül),
İşlerim (Düzenle/Detay/Sil, soft-delete, migration 040), Personel kart+sekme (SADECE web,
"Personel İş Takip Yönetimi" adının aslında personel yönetmediği bulundu, üretim/iş sistemi
TAŞINMADI, sadece etiket düzeltildi), Finans bağlam-duyarlı Gider Türü. Yan ürün olarak 2 açık/bug
daha kapatıldı: `mobile/task_view.php` IDOR, `accounting.php` sihirbaz JS scope hatası. Detay →
`CHANGELOG.md`. **LOCAL QA MODE'da 7/7 modül yerel MariaDB'de uçtan uca test edildi, bulunan 4 sorun
(web Satın Alma'nın `mm()` hatasıyla tamamen kırık olması, görev arama route'u, personel soft-delete
sayaç filtresi, boş-tarih SQL hatası) düzeltilip yeniden doğrulandı.** `php -l` temiz. **primac.tr'de
HENÜZ test edilmedi, HENÜZ commit edilmedi** — SECURITY SPRINT-001'den ayrı, kendi onay/test turunu
bekliyor.
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
🟢 **primac.tr = UX SPRINT-001'in güncel referans sürümü (2026-07-04)** — `d9c938b` commit'i
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
