# NEXT_SESSION.md — Bir Sonraki Oturum İçin Başlangıç Referansı

Bu dosya bir "yapılacaklar listesi" değil, bir sonraki oturuma "neredeydik, neye dikkat et" diye
hızlı giriş yapmak için var. Detay için ilgili dosyalara bakın (`CHANGELOG.md`, `ROADMAP.md`,
`KNOWN_BUGS.md`, `VERSIONING.md`, `memory/*.md`).

## Bir Sonraki Oturumun İlk Önceliği
**UI/UX İyileştirmeleri + SPRINT-003 paketinin primac.tr'de test edilip commit edilmesi.** Bu paket
(7 iş: Üst Menü, Notlarım Düzenle, Satın Alma inline ürün, Global Arama, İşlerim Düzenle/Detay/Sil,
Personel kart+sekme, Finans bağlam-duyarlı Gider Türü) `php -l` temiz ve yerel MariaDB test
ortamında kısmen doğrulandı (İşlerim akışı uçtan uca test edildi) ama **primac.tr'nin kendisinde
hiç test edilmedi ve henüz commit edilmedi**. İkinci öncelik: SECURITY SPRINT-001'in primac.tr'de
smoke test edilmesi (kod zaten commit edildi, `d511fad` — sadece canlı doğrulama eksik).

## Bugün Tamamlanan Çalışmalar (2026-07-04)
- **SECURITY SPRINT-001** (commit `d511fad`): `mobile/personnel_view.php` kritik şifre sıfırlama
  açığı kapatıldı — `reset_pw`/`make_login` artık `$_POST['uid']`'e hiç güvenmiyor, hedef hesap
  DB'den görüntülenen personele (`$id`) bağlı gerçek hesaptan çekiliyor. Kullanıcı kararıyla kapsam
  genişledi: bu işlemler artık admin VEYA yeni `personnel_accounts` yetkili "alt yönetici" ile
  sınırlı (yeni migration gerekmedi, mevcut `permissions` JSON altyapısı kullanıldı).
  **Yerel MariaDB test ortamında (primac.tr'ye dokunmadan, izole) uçtan uca doğrulandı** — 8/8
  senaryo PASS. **primac.tr'nin kendisinde henüz smoke test yapılmadı.**
- **UI/UX İyileştirmeleri + SPRINT-003** (7 ajanla tamamlandı, HENÜZ commit edilmedi): Detay →
  `CHANGELOG.md` "UI/UX İyileştirmeleri + SPRINT-003" bölümü. Özetle: Üst Menü, Notlarım Düzenle,
  Satın Alma inline ürün, Global Arama (5 yeni modül), İşlerim (Düzenle/Detay/Sil + soft-delete,
  migration 040), Personel kart+sekme (SADECE web — "Personel İş Takip Yönetimi" adının aslında
  personel yönetmediği, sadece yanıltıcı bir menü etiketi olduğu bulundu, üretim/iş sistemi
  TAŞINMADI), Finans bağlam-duyarlı Gider Türü. Yan ürün olarak 2 önceden bilinen/yeni açık daha
  kapatıldı: `mobile/task_view.php` IDOR, `accounting.php` sihirbaz JS scope hatası. **php -l temiz,
  primac.tr'de HENÜZ test edilmedi, HENÜZ commit edilmedi.**
- **FINANCE UX REFACTOR** (önceki oturumun checkpoint'i, `8bb4c6a`): Ödeme/Gider + Muhasebe
  ekranlarına "Ne kaydediyorsun?" sihirbazı eklenmişti — bu oturumda madde 7 (Finans bağlam-duyarlı
  Gider Türü) ile üzerine inşa edildi, aynı commit turunda bekliyor.

## Devam Eden Sprint
**UI/UX İyileştirmeleri + SPRINT-003 primac.tr'de test/onay bekliyor** (yukarı bakın — bu oturumun
ana gövdesi). Test edilecekler: her 7 madde için CHANGELOG.md'deki tarifin çalıştığını doğrulamak,
özellikle migration 040'ın primac.tr'de doğru uygulandığını (`guncelle.php` ile) teyit etmek.

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

## Açık Güvenlik Riskleri
1. **YÜKSEK** — `sifre_sifirla.php` brute-force + `accounting.php` XSS.
2. **ORTA** — `users.php` rol yükseltme, `is_admin()` session bayatlığı, session fixation.
3. **BİLGİ** — Proje genelinde CSRF token mekanizması yok.

**Çözüldü (bu oturum)**:
- `mobile/personnel_view.php` keyfi şifre sıfırlama (SECURITY SPRINT-001, commit edildi).
- `mobile/task_view.php` IDOR (İşlerim işi sırasında bonus düzeltme, henüz commit edilmedi).

Tam bulgu listesi ve satır referansları → `KNOWN_BUGS.md` ve 2026-07-04 tarihli System Audit raporu
(Artifact + `~/Desktop/OTS_System_Audit_2026-07-04.txt`).

## Dikkat Edilmesi Gereken Mimari Kararlar
- **Tek geliştirme ortamı modeli**: DEV=primac.tr (TÜM geliştirme/test burada), PROD=acanstr.com/ots
  (SADECE "DEPLOY MODE" komutuyla dokunulur, kod güncellenmez). Ayrı DB'ler — kod dağıtımı ile veri
  taşınması birbirinden bağımsız, asla karıştırılmamalı.
- **Yerel `config.php` PROD veritabanı bilgisi içeriyor** (2026-07-04'te fark edildi) — bu makinede
  yerel MySQL kurulu değildi, bu yüzden bu dosya fiilen "ölü" kalıyordu, sızıntı riski yok
  (`.gitignore`'da) ama bir sonraki oturumda kafa karıştırmasın diye not: gerçek yerel geliştirme
  testi gerekiyorsa yerel bir DB (bu oturumda kurulan MariaDB + `ots_sectest` DB kullanılabilir,
  gerçek `config.php` ASLA prod'a bağlıyken üzerine yazılmamalı — geçici bir kopyayla test edilip
  orijinali restore edilmeli, bu oturumda bu yöntem izlendi).
- **"Alt yönetici" yetki modeli** (SECURITY SPRINT-001, 2026-07-04): `boot.php::module_list()`'e
  eklenen `personnel_accounts` yetkisi — admin, `users.php` üzerinden birine bu yetkiyi verirse o
  kişi de personel şifre sıfırlama/hesap oluşturma yapabilir. Admin her zaman tam yetkili, düz
  `personnel` yetkisi ARTIK bu işlemler için yeterli değil.
- **"Personel İş Takip Yönetimi" adı yanıltıcıydı** (2026-07-04 SPRINT-003 analizinde bulundu): bu
  menü grubu personel YÖNETMİYOR, jobs/tasks/production/design gibi şirket-geneli üretim sayfalarını
  içeriyor — web'de "İş / Üretim Yönetimi" olarak düzeltildi, `mobile/more.php`'deki karşılığı henüz
  düzeltilmedi (ayrı onay gerekiyor, bkz. `ROADMAP.md`).
- **Personel detay artık sekmeli (SADECE web, `personnel_edit.php`)** — Görevler/Takvim/Mesajlar/
  Notlar/Maaş-Avans-Prim sekmeleri var olan sorguların personele FİLTRELENMİŞ görünümleri, altta
  yatan jobs/tasks/finance modülleri TAŞINMADI. Mobilde karşılığı yok, ayrı bir tur gerekiyor.
- **`tasks` artık soft-delete kullanıyor** (migration 040, 2026-07-04): `deleted_at` dolu olan
  kayıtlar HİÇBİR yerde gösterilmemeli — yeni bir `tasks` sorgusu yazılırken `deleted_at IS NULL`
  filtresi unutulmamalı (bu oturumda `mytasks.php`/`tasks.php`/`task_view.php`/`mobile/*` güncellendi,
  ama `dashboard.php`/`kpi.php`/`takvim.php`/`personnel.php` gibi diğer `tasks` sayaç sorguları bu
  turda BİLİNÇLİ OLARAK güncellenmedi — bkz. `memory/backlog.md`).
- **Finans Gider Türü artık `payment_type` kolonunda** (bugün, migration yok) — `category_id`
  SADECE Tahsilat/Gelir tarafında kullanılıyor, gider tarafında düzenlemede dokunulmuyor (veri kaybı
  önlendi). Kategori-bazlı raporlar yeni giderleri kapsamıyor, bkz. `ROADMAP.md`.
- **Deploy git-tabanlı DEĞİL**: `~/Desktop/PRIMAC-GUNCELLEME/` (DEV) klasöründeki `guncelleme.zip`
  (`git archive HEAD` + `vendor/`) + `guncelle.php` ile cPanel üzerinden yükleniyor. Bir sonraki
  zip tazelemesi bugünün İKİ commit'ini de (SECURITY SPRINT-001 + UI/UX-SPRINT-003, ikincisi henüz
  commit edilmedi) kapsamalı.
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
