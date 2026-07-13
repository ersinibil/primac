# PROJECT_RULES.md — OTS Geliştirme Sonrası Dönem Kuralları

Bu proje aktif geliştirme (yeni özellik) aşamasını geçti (2026-07-03 itibarıyla). Bu dosya
bundan sonraki TÜM çalışmalarda `CLAUDE.md`'nin ÜZERİNE eklenen bir öncelik/çalışma-şekli
katmanıdır — `CLAUDE.md`'deki teknik kurallar (PHP 7.2, prepared statement, parite vb.) hâlâ
geçerli, bu dosya onların yerine değil, önüne geçer.

## Öncelik Sırası (yeni özellik DEĞİL)
1. **Stabilite** — mevcut çalışan akışı bozmamak her şeyden önce gelir.
2. **Bug Fix** — bildirilen hatayı kökten çöz, yanına başka iş ekleme.
3. **Tutarlılık** — isimlendirme/kavram/route aynı anlamda kullanılmalı (bkz. "Kavram Standardı"
   bölümü — "İş Emirleri" vs "Görevlerim" ayrımı, 2026-07-13'te kesinleşti).
4. **Performans** — gereksiz sorgu/döngü varsa iyileştir, ama ölçülmeden "iyileştirme" adı
   altında yeniden yazma yapma.
5. **Web/mobil uyumu** — bir ekranda değişen her mantık diğer platformda da uygulanmalı.

Yeni özellik istekleri bu sıranın DIŞINDA sayılır — kullanıcı açıkça "yeni özellik" ya da
"ekle" demedikçe kapsam genişletilmez.

## Sert Kurallar
- **Mevcut çalışan mimari korunacak.** Bir bug'ı düzeltmek için mimariyi değiştirmek gerekiyorsa
  önce kullanıcıya sorulur, sessizce büyük yeniden tasarım yapılmaz.
- **Dosyalar komple yeniden yazılmaz.** Write tool'u sadece YENİ dosya için kullanılır; var olan
  bir dosyada değişiklik gerekiyorsa Edit ile (mevcut yapıyı koruyarak) nokta atışı yapılır.
- **Sıra: önce analiz, sonra kod, sonra test.**
  1. Analiz: ilgili dosyalar `grep`/`Read` ile bulunur, kök neden netleşmeden kod yazılmaz.
  2. Kod: sadece kök nedene dokunan, en küçük değişiklik.
  3. Test: en az `php -l` (sözdizimi) + mümkünse ilgili akışın mantıksal doğrulaması (SQL
     parametre sayısı/sırası, yetki kontrolü, PRG deseni vb.) — "düzelttim" demeden önce.
- **Her değişiklik sonunda rapor.** Hangi dosya, ne değişti, NEDEN değişti — kısa liste halinde,
  kullanıcı tekrar sormadan görebilsin.

## Ortam Yönetimi (2026-07-03'ten itibaren — RESMİ STANDART)
Proje tek geliştirme ortamı modeliyle yönetilir. Bu bölüm `CLAUDE.md`'deki eski "iki paralel prod"
tanımının (ACANS + PRIMAC ayrı ayrı canlı) YERİNE geçer.

| Ortam | URL | Rol |
|---|---|---|
| **Development (DEV)** | https://primac.tr | Tüm geliştirme + test burada yapılır. |
| **Production (LIVE)** | https://acanstr.com/ots | Sadece canlı. Üzerinde geliştirme yapılmaz, dosya güncellenmez. |

1. Bundan sonraki TÜM geliştirme yalnızca **primac.tr (DEV)** üzerinde yapılır.
2. **acanstr.com/ots** production'dır — bu ortamda geliştirme yapılmaz, dosya güncellenmez.
   `ots-deploy-security-guard`/genel kural: acanstr.com/ots'a dosya yazan hiçbir işlem "DEPLOY MODE"
   komutu verilmeden başlatılmaz.
3. Her geliştirme önce DEV'de tamamlanır, test edilir, onaylanır.
4. Git deposu tek kaynak kod deposudur (Single Source of Truth).
5. Lokal çalışma dizini, Git kayıtları ve Masaüstü güncelleme paketleri (`~/Desktop/ACANS-GUNCELLEME/`,
   `~/Desktop/PRIMAC-GUNCELLEME/`) yalnızca DEV (primac.tr) sürümünü temel alır — ACANS klasörü artık
   sadece "DEPLOY MODE" anında, DEV'de onaylanmış son hâli göndermek için kullanılır, ara adımlarda
   dokunulmaz.
6. Her geliştirme turu sonunda bir **Release paketi** hazırlanır (tazelenmiş `guncelleme.zip` + neyin
   değiştiğinin özeti) — commit/push zaten yapılıyor olsa da, DEPLOY MODE gelene kadar bu paket sadece
   DEV'e (primac.tr) gönderilir.
7. **Production (acanstr.com/ots) güncellemesi SADECE ayrıca verilecek "DEPLOY MODE" komutuyla yapılır.**
   Bu komut gelmeden ACANS-GUNCELLEME klasörüne/production sunucusuna dosya yazma/güncelleme işlemi
   BAŞLATILMAZ. **"DEPLOY MODE" açıkça verilmeden acanstr.com/ots üzerinde HİÇBİR dosya, zip, config,
   VAPID anahtarı veya production ayarı değiştirilmez** — production üzerindeki HER değişiklik
   (kod, migration, `config.php`, güvenlik anahtarı, marka/ayar dosyası fark etmeksizin) "DEPLOY MODE"
   kapsamındadır, istisnasız.
8. DEV ve PROD'un paralel geliştirilmesine son verilmiştir — artık iki ortama eşzamanlı, birbirinden
   bağımsız değişiklik gitmez.
9. Bundan sonraki tüm analiz/test/hata düzeltme/yeni geliştirme sadece DEV'de (primac.tr) yürütülür.
10. DB'ler hâlâ AYRI (primac.tr = `u7883898_primactr`, acanstr.com/ots = `u7883898_primacos`) — bu
    ortam modeli sadece KOD dağıtımı içindir, DEV'de test edilen veri PROD'a taşınmaz/senkron
    edilmez. Detay → `memory/deploy.md`.

**Lokal klasör durumu (2026-07-03 tespiti, NOT — silme/taşıma/arşivleme yapılmadı)**:
- `/Users/acans/ACANS-OTS` — GÜNCEL AKTİF çalışma klasörü. GitHub'a bağlı (`ersinibil/primac`),
  tüm güncel iş burada yürütülüyor.
- `/Users/acans/PRIMAC-OTS` — ESKİ/DONMUŞ klasör (2026-07-02'den beri değişmemiş, remote'suz,
  eksik ajan/dosya seti). İsim benzerliği ("PRIMAC") DEV=primac.tr ortamıyla KARIŞTIRILMAMALI —
  bu klasör primac.tr ortamının çalışma dizini DEĞİL. Şimdilik dokunulmuyor.
- `/Users/acans/ots` — boş/yardımcı klasör (sadece `.claude` ayarları var). Şimdilik dokunulmuyor.

## Deploy Standardı (2026-07-04'ten itibaren — RESMİ STANDART, DEV+PROD'da otomatik uygulanır)
Bundan sonraki TÜM Development (primac.tr) ve Production ("DEPLOY MODE" ile acanstr.com/ots) deploy
işlemlerinde aşağıdaki 7 adım sırayla tamamlanmadan deploy "başarılı" sayılmaz:

**Hazırla → Yükle → Doğrula → Migration → Smoke Test → Temizlik → Son Doğrulama**

1. **Otomatik temizlik**: Deploy tamamlandıktan sonra `guncelleme.zip`, test paketleri (`*_test.zip`,
   `*_test.php`), geçici installer dosyaları (`kur.php`, `ac.php`, `bitir.php`,
   `temizle-kurulum.php`, `install_*.php`), migration yardımcı dosyaları ve deploy sırasında
   oluşan tüm geçici dosyalar **otomatik tespit edilip silinir** — kullanıcıdan manuel dosya
   silmesi İSTENMEZ (cPanel File Manager'da elle silme yok).
2. Temizlik işlemi deploy aracının kendisi (`guncelle.php`) tarafından **doğrulanır** (silme
   başarılı mı kontrol edilir, `unlink()` dönüş değeri kontrolsüz bırakılmaz — bkz. bu projede daha
   önce yaşanan "sahte başarı" hatası, `extractTo()`'nun kontrol edilmemesi).
3. Temizlik sonunda üç liste raporlanır: **silinen dosyalar**, **silinemeyen dosyalar**, henüz
   silme kapsamına GİRMEYEN (kalıcı kod/config) dosyalara dokunulmadığı notu.
4. Güvenlik/izin nedeniyle otomatik silinemeyen bir dosya varsa: **tam yol + dosya adı + silinme
   nedeni** açıkça raporlanır (kullanıcı elle müdahale etmek isterse net bilgiyle karar verebilsin).
5. **Smoke Test, Temizlik'ten ÖNCE gelir** — deploy aracı zip'i açıp migration'ı uyguladıktan sonra
   HEMEN kendini silmez; kullanıcı smoke test'i tamamlayıp onay verene kadar (ör. `?cleanup=1`
   linkine tıklayana kadar) zip/araç dosyaları sunucuda kalır — böylece bir sorun çıkarsa tekrar
   extract etmeye gerek kalmadan durum incelenebilir.
6. Bu standart `~/Desktop/PRIMAC-GUNCELLEME/guncelle.php` (DEV) ve "DEPLOY MODE" sırasında
   `~/Desktop/ACANS-GUNCELLEME/guncelle.php` (PROD) için AYNI şablondan uygulanır.

## Sürekli Kalite Denetimi Standardı (2026-07-04'ten itibaren — RESMİ STANDART)
SYSTEM AUDIT MODE (mimari, güvenlik, performans, UX/UI, veri modeli, kod kalitesi — read-only,
kod/DB değiştirmeden) tek seferlik bir denetim değildir. Kullanıcı tekrar belirtmese bile aşağıdaki
durumlarda OTOMATİK olarak yeniden çalıştırılır:
- Her büyük Sprint sonunda.
- Her Release Candidate (RC) öncesinde.
- Her Major sürüm (v1.x, v2.x vb.) öncesinde.
- Production'a ("DEPLOY MODE") çıkmadan hemen önce.

Her denetim sonunda `CHANGELOG.md`, `VERSIONING.md`, `ROADMAP.md`, `KNOWN_BUGS.md`,
`NEXT_SESSION.md` gözden geçirilir ve gerekiyorsa güncellenir — denetimde bulunan güvenlik açıkları
`KNOWN_BUGS.md`'ye, mimari/performans/UX teknik borçları `ROADMAP.md`'ye işlenir. Denetim raporunun
kendisi kod/dosya değiştirmez, commit oluşturmaz — sadece bulguları belgeler; bulguların
düzeltilmesi ayrı, kullanıcı onaylı bir iş turu olarak ele alınır.

## Kavram Standardı (2026-07-13'te güncellendi — "UX TERMINOLOGY FIX")
2026-07-03 kararı "İşlerim" ile "İş Takip"i aynı menüde yakın adlarla bırakmıştı; kullanıcı
karışıklığı bildirdi (sol menüde iki benzer isim). 2026-07-13'te KESİN terminoloji kararıyla
değiştirildi — aşağıdaki isimler artık geçerli, **eskileri ("İşlerim" bu bağlamda, "İş Takip")
bir daha kullanılmaz.** Route/dosya adı/tablo adı/yetki anahtarı DEĞİŞMEDİ, sadece görünen isim:
- **"İş Emirleri"** (eski adı: "İş Takip") = şirketin operasyonel iş kayıtları — müşteri işleri,
  iş emirleri, termin/sorumlu/durum takibi (`jobs.php`, `jobs` tablosu). Alt açıklama: "Müşteri
  işleri ve operasyon takibi".
- **"Görevlerim"** (eski adı: "İşlerim") = kullanıcıya atanmış kişisel görev/hatırlatma listesi,
  çek vadesi gibi kullanıcı aksiyonu bekleyen kayıtlar dahil (`mytasks.php` / `mobile/mytasks.php`).
  Alt açıklama: "Bana atanan görevler ve hatırlatmalar".
- **"İş Ekle"** = başka bir personele iş/görev atama ekranı (`task_new.php` / `mobile/task_new.php`,
  admin veya `tasks` yetkili kullanıcı için).
- **"Kendime İş Ekle"** = kullanıcının kendine iş kaydı oluşturduğu AYRI ekran
  (`mytask_new.php` / `mobile/mytask_new.php`), `tasks` yetkisi istemez.
- **"Notlarım" / "Kendime Not Ekle"** = kişisel not sistemi (`notes.php` web,
  `mobile/mytasks.php` içine gömülü panel mobilde), `tasks` tablosuyla karışmaz.
- `jobs.php`'ye açılan TÜM etiketler (web sidebar, dashboard kartı, mobil kartlar, sayfa başlığı,
  rapor adı) tek isimde birleşti: **"İş Emirleri"**. Eskiden aynı ekrana "İş Takip", "İş Merkezi"
  ve sade "İşler" gibi üç ayrı isimle gidiliyordu — bkz. `memory/features.md` 2026-07-13 kaydı.

## Mobil UX Standardı (2026-07-04'ten itibaren — UX SPRINT-001)
- **Liste ekranı = sadece listeleme.** Bir liste ekranındaki kart/satır tekil bir "Detay" ekranına
  gitmek için TEK bir tıklanabilir alan olmalı (kart tamamı tıklanabilir, "Aç" gibi ayrı bir buton
  zorunlu değil).
- **Tekil aksiyonlar sadece Detay ekranında.** Sil, Paylaş, İlgili Modüle Git, İşaretle gibi
  TEKİL (bir kayda özel) aksiyonlar liste kartının içine gömülmez — sadece o kaydın Detay ekranında
  sunulur. Sayfa-seviyesi TOPLU aksiyonlar (ör. "Okunanları Sil", "Tümünü Sil") bu kuralın DIŞINDA,
  liste ekranında kalabilir.
- Bu ilke ilk olarak Bildirimler modülünde uygulandı (`mobile/notifications.php` liste +
  `mobile/notification_view.php` detay, bkz. `CHANGELOG.md`) ve bundan sonra yazılacak/yeniden
  tasarlanacak TÜM mobil liste ekranları için standarttır.

## Sürüm Takibi (2026-07-03'ten itibaren — RESMİ STANDART)
- `VERSIONING.md` projenin resmi sürüm dokümanıdır (Current Dev/Prod Version, Release Durumu,
  Dağıtım Geçmişi vb.).
- **Her sprint/geliştirme turu sonunda** `VERSIONING.md` VE `CHANGELOG.md` güncellenir.
- **Her Production yayını (DEPLOY MODE) sonrası**: `VERSIONING.md`'deki `Current Production Version`
  güncellenir, Dağıtım Geçmişi'ne yeni satır eklenir.
- **Her Development geliştirmesi sonrası**: `Current Development Version` güncellenir.

## Referanslar
- Teknik kurallar → `CLAUDE.md`
- Sürüm durumu → `VERSIONING.md`
- Özet değişiklik günlüğü → `CHANGELOG.md`
- Açık maddeler/kararlar → `ROADMAP.md`
- Bilinen hatalar (hızlı bakış) → `KNOWN_BUGS.md`, tam geçmiş → `memory/bugs.md`
- Şema envanteri → `DATABASE.md`
- Açık işler (ham) → `memory/backlog.md`
- Deploy adımları → `memory/deploy.md`
