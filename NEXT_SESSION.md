# NEXT_SESSION.md — Bir Sonraki Oturum İçin Başlangıç Referansı

Bu dosya bir "yapılacaklar listesi" değil, bir sonraki oturuma "neredeydik, neye dikkat et" diye
hızlı giriş yapmak için var. Detay için ilgili dosyalara bakın (`CHANGELOG.md`, `ROADMAP.md`,
`KNOWN_BUGS.md`, `VERSIONING.md`, `memory/*.md`).

## Bir Sonraki Oturumun İlk Önceliği
**Bu oturumdaki sprint primac.tr'de test edilip PASS aldı, GitHub'a push edildi — sprint kapandı.**
Bir sonraki oturum tamamen yeni bir sprint için boş bir sayfa. Açık/karar bekleyen maddeler için
`ROADMAP.md`'ye bakın (öne çıkanlar: web push'un primac.tr'nin GERÇEK sunucusunda VAPID/gmp-bcmath
doğrulaması, Finans Gider Türü kategori-raporu açık noktası, Personel modülü mobil parite borcu).

## Bugün Tamamlanan Çalışmalar (2026-07-04) — hepsi primac.tr'de test edildi, PASS, GitHub'a push edildi
- **SECURITY SPRINT-001** (`d511fad`): `mobile/personnel_view.php` kritik şifre sıfırlama açığı
  kapatıldı — `reset_pw`/`make_login` artık `$_POST['uid']`'e hiç güvenmiyor. Kapsam kullanıcı
  kararıyla genişledi: admin VEYA yeni `personnel_accounts` yetkili "alt yönetici" ile sınırlı.
- **UI/UX İyileştirmeleri + SPRINT-003** (`5fb2c43`): Üst Menü, Notlarım Düzenle, Satın Alma inline
  ürün, Global Arama (5 yeni modül), İşlerim (Düzenle/Detay/Sil + soft-delete, migration 040),
  Personel kart+sekme (SADECE web), Finans bağlam-duyarlı Gider Türü. Yan ürün: `mobile/task_view.php`
  IDOR + `accounting.php` JS scope hatası da kapatıldı.
- **LOCAL QA MODE düzeltmeleri** (`697f985`): 7 modülün tamamı yerel MariaDB'de test edildi, 4 bulgu
  düzeltildi — en önemlisi, web'den **Satın Alma tamamen kırıktı** (`mm()` fonksiyonu web'de
  tanımsızdı, bugünkü sprintten ÖNCE de vardı).
- **SPRINT CLOSE ek düzeltmeleri** (`b5c8410`..`d7c593a`): Komuta Merkezi'ne Takvim modül kutusu
  (topbar pill denemesi kullanıcı adını bozduğu için geri alınıp doğru yere taşındı), web mesaj
  rozeti + sıfırdan web Push bildirimi (gerçek Chromium ile uçtan uca doğrulandı), Takvim'de
  görev/not linklerinin düzeltilmesi + silinmiş görevin artık görünmemesi, web takvimde gün
  numarasının tıklanabilir hale getirilip günlük filtreli detay paneli eklenmesi.

Detaylı liste → `CHANGELOG.md` "SPRINT CLOSE" ve üstündeki bölümler.

## Devam Eden Sprint
Yok — bu oturumun sprinti kapandı (primac.tr'de PASS, GitHub'a push edildi). Bir sonraki oturum
yeni bir sprint/talep ile başlayacak.

## Açık Kalan Hatalar
(Tam liste → `KNOWN_BUGS.md`)
1. `sifre_sifirla.php`'de brute-force koruması yok (6 haneli kod, deneme sınırı/lockout yok).
2. `accounting.php`'de `tab` parametresiyle yansıyan XSS (satır 8, 111-130).
3. `users.php`'de "users" modül yetkisi = fiili tam admin, kendine rol yükseltme mümkün.
4. `is_admin()` session'da bayatlıyor, `user_can()` gibi DB'den taze okumuyor.
5. Login'de `session_regenerate_id(true)` çağrılmıyor (session fixation).
6. Hiçbir tabloda FK kısıtı yok — personel/cari/iş silme akışlarında yetim kayıt riski (özellikle
   `job_logs`).
7. `jobs`/`finance_movements`/`internal_messages`/`internal_notifications` tablolarında eksik index
   (performans + veri büyüdükçe risk) — `tasks` bugün eklenen migration 040 ile kısmen iyileşti.
8. Sabit migration/temizlik anahtarı (`acans-migrate-2026`) hardcoded — repo public olursa
   değiştirilmeli.
9. Finans Gider Türü sihirbazının `category_id` yerine `payment_type` kullanması nedeniyle
   kategori-bazlı raporların yeni kayıtları kapsamaması (bkz. `ROADMAP.md`, güvenlik açığı değil).
10. Web push bildiriminin primac.tr'nin GERÇEK sunucusunda VAPID/gmp-bcmath doğrulaması henüz
    yapılmadı (bkz. `ROADMAP.md`) — yerelde Chromium ile doğrulandı ama canlı cihaz testi eksik.

## Açık Güvenlik Riskleri
1. **YÜKSEK** — `sifre_sifirla.php` brute-force + `accounting.php` XSS.
2. **ORTA** — `users.php` rol yükseltme, `is_admin()` session bayatlığı, session fixation.
3. **BİLGİ** — Proje genelinde CSRF token mekanizması yok.

**Çözüldü (bu oturum, primac.tr'de PASS aldı)**:
- `mobile/personnel_view.php` keyfi şifre sıfırlama (SECURITY SPRINT-001).
- `mobile/task_view.php` IDOR (İşlerim işi sırasında bonus düzeltme).

Tam bulgu listesi ve satır referansları → `KNOWN_BUGS.md` ve 2026-07-04 tarihli System Audit raporu
(Artifact + `~/Desktop/OTS_System_Audit_2026-07-04.txt`).

## Dikkat Edilmesi Gereken Mimari Kararlar
- **Tek geliştirme ortamı modeli**: DEV=primac.tr (TÜM geliştirme/test burada), PROD=acanstr.com/ots
  (SADECE "DEPLOY MODE" komutuyla dokunulur, kod güncellenmez). Ayrı DB'ler — kod dağıtımı ile veri
  taşınması birbirinden bağımsız, asla karıştırılmamalı.
- **Yerel `config.php` PROD veritabanı bilgisi içeriyor** (2026-07-04'te fark edildi) — bu makinede
  yerel MySQL kurulu değildi, bu yüzden bu dosya fiilen "ölü" kalıyordu, sızıntı riski yok
  (`.gitignore`'da). Bu oturumda kurulan yerel MariaDB + `ots_sectest` DB, gerçek `config.php`'ye ASLA
  dokunmadan (geçici kopyayla test edip orijinali restore ederek) tüm testler için kullanıldı — bir
  sonraki oturumda da aynı yöntem izlenebilir (MariaDB kurulu kaldı, `brew services` ile başlatılır).
- **"Alt yönetici" yetki modeli** (SECURITY SPRINT-001): `boot.php::module_list()`'e eklenen
  `personnel_accounts` yetkisi — admin, `users.php` üzerinden birine bu yetkiyi verirse o kişi de
  personel şifre sıfırlama/hesap oluşturma yapabilir. Düz `personnel` yetkisi ARTIK yetersiz.
- **"Personel İş Takip Yönetimi" adı yanıltıcıydı** (SPRINT-003 analizinde bulundu): bu menü grubu
  personel YÖNETMİYOR, jobs/tasks/production/design gibi şirket-geneli üretim sayfalarını içeriyor —
  web'de "İş / Üretim Yönetimi" olarak düzeltildi, `mobile/more.php`'deki karşılığı henüz
  düzeltilmedi (ayrı onay gerekiyor, bkz. `ROADMAP.md`).
- **Personel detay artık sekmeli (SADECE web, `personnel_edit.php`)** — Görevler/Takvim/Mesajlar/
  Notlar/Maaş-Avans-Prim sekmeleri var olan sorguların personele FİLTRELENMİŞ görünümleri, altta
  yatan jobs/tasks/finance modülleri TAŞINMADI. Mobilde karşılığı yok, ayrı bir tur gerekiyor.
- **`tasks` artık soft-delete kullanıyor** (migration 040): `deleted_at` dolu olan kayıtlar HİÇBİR
  yerde gösterilmemeli — yeni bir `tasks` sorgusu yazılırken `deleted_at IS NULL` filtresi
  unutulmamalı. Bugüne kadar güncellenenler: `mytasks.php`/`tasks.php`/`task_view.php`/`mobile/*`/
  `personnel.php`/`personnel_edit.php`/`takvim.php`/`mobile/calendar.php`. `dashboard.php`/`kpi.php`
  gibi bazı diğer sayaç sorguları hâlâ BİLİNÇLİ OLARAK güncellenmedi — bkz. `memory/backlog.md`.
- **Web Push bildirimi bugün sıfırdan eklendi** (`sw.js`, `layout_bottom.php`) — mobildeki
  `mobile/sw.js`'in aksine offline cache YOK, sadece push. `push_subscribe.php` zaten paylaşılan/
  path-agnostik olduğu için değişmedi. Yeni bir sayfa eklenirken `layout_bottom.php`'nin her sayfada
  yüklendiğinden emin olunmalı (push script'i orada).
- **Finans Gider Türü artık `payment_type` kolonunda** (migration yok) — `category_id` SADECE
  Tahsilat/Gelir tarafında kullanılıyor. Kategori-bazlı raporlar yeni giderleri kapsamıyor, bkz.
  `ROADMAP.md`.
- **Deploy git-tabanlı DEĞİL**: `~/Desktop/PRIMAC-GUNCELLEME/` (DEV) klasöründeki `guncelleme.zip`
  (`git archive HEAD` + `vendor/`) + `guncelle.php` ile cPanel üzerinden yükleniyor. Bu oturumun
  sonunda primac.tr'ye yüklenen sürüm `d7c593a` — bir sonraki değişiklikte zip yeniden tazelenmeli.
- **Sürekli Kalite Denetimi Standardı**: SYSTEM AUDIT MODE her büyük sprint/RC/major sürüm/
  production öncesi otomatik tekrarlanır.
- **"Ne kaydediyorsun?" sihirbaz deseni** (`finance_lib.php::finance_record_type_info()`): tür
  bilgisi DB'de SAKLANMIYOR (Gider Türü artık `payment_type`, kayıt tipi hâlâ türetiliyor).
- **Ödeme/Gider ile Muhasebe ekranları bilerek AYRI bırakıldı** — bkz. `ROADMAP.md`.
- **Design token sistemi** (`mobile/common.php`): yeni renk/radius eklenirken `var(--c-*)`/
  `var(--radius-*)` kullanılmalı.
- **Mobil hâlâ referans tasarım**: yeni bir modül tasarlanırken önce mobil düşünülmeli — ANCAK bu
  oturumdaki Personel kart+sekme işi istisnai olarak SADECE web'de yapıldı (kullanıcı onayıyla),
  mobil parite borcu var.

## Referanslar
Ortam kuralları → `PROJECT_RULES.md`. Sürüm durumu → `VERSIONING.md`. Açık kararlar → `ROADMAP.md`.
Bilinen hatalar → `KNOWN_BUGS.md`. Değişiklik özeti → `CHANGELOG.md`. Deploy detayları →
`memory/deploy.md`.
