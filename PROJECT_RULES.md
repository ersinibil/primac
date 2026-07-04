# PROJECT_RULES.md — OTS Geliştirme Sonrası Dönem Kuralları

Bu proje aktif geliştirme (yeni özellik) aşamasını geçti (2026-07-03 itibarıyla). Bu dosya
bundan sonraki TÜM çalışmalarda `CLAUDE.md`'nin ÜZERİNE eklenen bir öncelik/çalışma-şekli
katmanıdır — `CLAUDE.md`'deki teknik kurallar (PHP 7.2, prepared statement, parite vb.) hâlâ
geçerli, bu dosya onların yerine değil, önüne geçer.

## Öncelik Sırası (yeni özellik DEĞİL)
1. **Stabilite** — mevcut çalışan akışı bozmamak her şeyden önce gelir.
2. **Bug Fix** — bildirilen hatayı kökten çöz, yanına başka iş ekleme.
3. **Tutarlılık** — isimlendirme/kavram/route aynı anlamda kullanılmalı (örn. "İşlerim" vs
   "Görevlerim" karışıklığı — bkz. `memory/bugs.md` 2026-07-03 kaydı).
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

## Kavram Standardı (2026-07-03'te netleşti)
- **"İşlerim"** = bana atanmış işler/görevler listesi (`mytasks.php` / `mobile/mytasks.php`).
  **"Görevlerim" ifadesi artık kullanılmaz.**
- **"İş Ekle"** = başka bir personele iş/görev atama ekranı (`task_new.php` / `mobile/task_new.php`,
  admin veya `tasks` yetkili kullanıcı için).
- **"Kendime İş Ekle"** = kullanıcının kendine iş kaydı oluşturduğu AYRI ekran
  (`mytask_new.php` / `mobile/mytask_new.php`), `tasks` yetkisi istemez.
- **"Notlarım" / "Kendime Not Ekle"** = kişisel not sistemi (`notes.php` web,
  `mobile/mytasks.php` içine gömülü panel mobilde), `tasks` tablosuyla karışmaz.
- **"İşler"** = üretim/iş takip listesi (`jobs.php`, `jobs` tablosu) — "İşlerim" ile KARIŞTIRILMAZ,
  farklı tablo (`jobs` vs `tasks`), farklı ekran.

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
