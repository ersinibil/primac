# PRIMAC OTS — CHAT DEVİR CHECKPOINT (2026-07-18)

> Bu dosya bir önceki konuşmanın context limiti dolduğu için oluşturuldu. Yeni oturum bu dosyayı,
> `CLAUDE.md`'yi ve altta işaret edilen `memory/*.md` dosyalarını okuyup, `git status`/`git log` ile
> gerçek kod durumunu doğruladıktan SONRA kaldığı yerden devam etmeli — sıfırdan başlamamalı,
> tamamlanmış işi tekrar açmamalı.

**Checkpoint anındaki HEAD:** `524b591` (main, origin ile senkron, working tree TEMİZ).

---

## 1. PROJE TEMELİ

- **PRIMAC OTS / Operation OS** — saf PHP 7.2 ERP. Web masaüstü paneli + mobil PWA (`mobile/`).
- **DEV:** primac.tr (DB `u7883898_primactr`) — TÜM geliştirme/test burada.
- **PROD:** acanstr.com/ots/ (DB `u7883898_primacos`) — SADECE "DEPLOY MODE" komutuyla dokunulur.
- **Felsefe: Evolution, not Revolution.** Mevcut DB şeması/route yapısı/backend iş mantığı/finans-
  stok-cari matematiği KORUNUR — büyük refactor yok, yeni paralel sistem icat edilmez.
- **Legacy UI istenmiyor** — Design System (`df-*` sınıflar, `ds_lib.php`, `ds-foundation.css/js`)
  tek görsel dil, web+mobil TEK ÜRÜN hissi vermeli.
- Detaylar: `CLAUDE.md`, `PROJECT_RULES.md`, `DATABASE.md`, `VERSIONING.md`.

## 2. ÇALIŞMA KURALI

- Product Owner komutları KISA/DOĞRUDAN uygulanır — gereksiz audit/uzun rapor istenmedikçe yok.
- **"Kodda var" ≠ "kullanıcı kullanabiliyor."** Gerçek USER TEST (gerçek cihaz/gerçek video) kod
  incelemesinden ÜSTÜNDÜR — kod PASS ile USER TEST PASS ayrımı HER ZAMAN net tutulmalı.
- Standart akış: uygula → test (php -l + mümkünse render/mockup doğrulama) → commit → push →
  `guncelleme.zip`'i tazele → kısa sonuç raporu.
- Finans/mimari kararı gereken (özellikle geçmiş veriyi geriye dönük etkileyecek) noktalarda
  SESSİZCE varsayım yapılmaz — Product Owner'a açıkça sorulur/raporlanır.
- **config.php ve lokal/canlı veritabanına bu ortamdan DOKUNULMAZ** — hiçbir migration bu ortamdan
  çalıştırılmaz (sunucu/DB erişimi yok). Tüm doğrulama STATİK KOD İNCELEMESİ + (mümkünse) headless
  Chrome ile statik HTML/CSS mockup render'ı üzerinden yapılır — gerçek cihaz testi Product Owner'da.

## 3. MASTER/P0 DURUMU

- **Master Kapanış Denetimi** (2026-07-18, bu konuşmanın başında) 17 bölümlük tam denetim yaptı,
  Artifact + PDF olarak teslim edildi (KOŞULLU HAZIR verdisi). Ardından **"PİLOT ÖNCESİ SON
  TOPARLAMA"** adıyla 5 P0 kapatma turu geldi:
  1. Çek/senet cari bakiye işareti — `contact_balance_case_sql()`'e `cek_senet`/`cek_senet_ciro`
     eklendi (commit `275a95f`). **CODE PASS**, canlı DB'de doğrulanmadı.
  2. Migration 048 güvenliği — `checks_notes_lifecycle_ready()` guard'ı (commit `486a873`). **CODE
     PASS.**
  3. Alış/Satış Belgesi düzenle/sil — `trade_core.php` + `trade_document_edit.php` (yeni), kontrollü
     iptal (status='İptal', fiziksel DELETE yok) (commit `2a925a0`). **CODE PASS**, USER TEST YOK.
  4. Mobil "Belge" redirect-trap — 4 dosyaya `web=1` eklendi (commit `bf15fda`). **CODE PASS.**
  5. "6 kırık mobil menü linki" — DOĞRULANDI, kırık DEĞİLDİ (muhtemelen daha önceki DS-migration
     turlarında zaten düzelmişti, denetim raporu o an güncel değildi). Kod değişikliği yapılmadı.
- Bu 5 P0'dan SONRA gelen ek işler (finans UX, mobil shell) aşağıdaki bölümlerde ayrı ayrı.
- **Genel ilke: hiçbir P0/madde "USER TEST PASS" onayı almadan CLOSED sayılmaz** — bu konuşmada
  mobil shell 3 kez "CODE PASS" denip 3 kez gerçek cihazda FAIL verdi (bkz. bölüm 7). Yeni oturum
  bu disiplini SIKI tutmalı: "commit var" = "bitti" DEĞİLDİR.

## 4. ÇEK/SENET — TAM FİNANSAL YAŞAM DÖNGÜSÜ

**Durum makinesi (değişmedi, sağlam):**
- Alınan: Portföyde → Tahsil Et / Ciro Et / Karşılıksız / İptal (final: tahsil_edildi/ciro_edildi/
  karsiliksiz/iptal — hiçbiri geri dönüşsüz düzenlenemez).
- Verilen: Portföyde(Bekliyor) → Öde / İptal.
- `checks_notes_can_delete()`: sadece 'portfoyde' durumundaki (henüz finansal aksiyon almamış)
  kayıt silinebilir — final durumlar silinemez.

**Cari/finans matematiği (2026-07-18'de düzeltildi, commit `275a95f`):**
- Kabul anı (`checks_notes_sync_finance()`, movement_type='cek_senet') ve Ciro (`cek_senet_ciro`)
  artık `contact_balance_case_sql()`'de normal/mobile (Tahsilat/Ödeme) ile AYNI ters-işaret dalında
  — müşteriden 1.000 TL çek ALINDIĞINDA cari borç DOĞRU YÖNDE (-1.000) azalıyor.
- Tahsil Et/Öde (`cek_senet_tahsil`, contact_id=NULL) cariyi İKİNCİ KEZ etkilemiyor — sadece
  seçilen kasa/banka hesabına gerçek hareket.
- Ciro Et: kasa/banka hareketi YOK, sadece ciro edilen tarafın borcu (Ödeme mantığıyla) kapanıyor.
- Formül CANLI SUM() ile türetiliyor (hiçbir yerde saklı bakiye yok) — bu düzeltme deploy edilir
  edilmez TÜM geçmiş kayıtların etkisi otomatik doğru yansır, ayrı bir toplu-düzeltme SCRIPT'i
  YOK/gerekmiyor.

**Tahsilat/Ödeme + Çek/Senet ENTEGRASYONU (2026-07-18, commit `1aafe7d`, CODE PASS/USER TEST YOK):**
- `finance_new.php` (web) + `mobile/collection.php` + `mobile/payment.php`: Yöntem=Çek/Senet
  seçilince artık kasa/banka hesabı SORULMUYOR — kayıt `checks_notes_create()`/
  `checks_notes_endorse()` TEK kaynağına gidiyor (kopya matematik YOK).
- Ödeme+Çek/Senet'te iki gerçek seçenek: A) Kendi çekimizi ver (`direction='verilen'`), B)
  Portföydeki müşteri çekini hedef tedarikçiye ciro et (`checks_notes_endorse()`).
- Tahsilat/Ödeme ekranlarında cari seçim listesi artık filtrelenebiliyor: Tahsilat'ta varsayılan
  Müşteriler, Ödeme'de varsayılan Tedarikçiler, "Tüm Cariler" bir tık uzakta (contacts.type alanı
  kullanıldı, isimden tahmin YOK). Web+mobil aynı davranıyor.

**⚠️ AÇIK/YAPILMADI — "FİNANS UX KAPANIŞ — CARİ FİLTRESİ + ÇEK/SENET DÜZENLE/İPTAL" komutu geldi
ama mobil shell regresyonları araya girdiği için HENÜZ UYGULANMADI:**
- Çek/Senet listesi/detayında (`checks_notes.php`/`check_note_view.php` + mobil) PORTFÖYDE/BEKLİYOR
  durumundaki bir kaydı **Düzenle** (cari/tür/numara/vade/banka/tutar/açıklama, transaction içinde
  eski cari etkisini tersleyip yeniyi uygulayarak) ve **İptal** (status=İptal, fiziksel DELETE değil,
  cari etkisini tersleyerek) aksiyonları — `checks_notes_update()` şu an durum kabul ETMİYOR ve genel
  alan güncellemesi zaten var ama YENİ tutar/cari DEĞİŞİNCE cari etkisini transaction içinde
  TERSLEME mantığı YOK, bu görevin ASIL istediği budur.
- Final durumdaki (tahsil/ciro/ödenmiş) kayıtta edit/delete zaten `checks_notes_can_delete()`/
  `checks_notes_update()`'in `status!=='portfoyde'` kontrolüyle ENGELLİ — bu kısım muhtemelen zaten
  doğru, ama YENİ eklenecek "Düzenle" UI'sinin bu kilide saygı gösterdiği AYRICA doğrulanmalı.
- Test senaryosu (Product Owner'ın verdiği, henüz koşulmadı): Müşteri 5.000 borçlu → 1.000 çek al =
  4.000 → 1.000'i 1.500'e düzenle = 3.500 → iptal et = tekrar 5.000.
- **BU MADDE YENİ OTURUMUN ÖNCELİKLİ AÇIK İŞLERİNDEN BİRİ** (bkz. bölüm 10).

## 5. CPA (Customer Procurement Allocation / "Müşteriye Ayır")

- Migration 046 (`cpa_allocations`) + 047 (`cpa_allocation_consumptions`) — TEK şema otoritesi,
  runtime'da CREATE TABLE YAPILMIYOR (`cpa_alloc_tables_ready()` guard'ı).
- Kullanıcıya HİÇBİR YERDE "CPA" gösterilmiyor — sadece "Müşteriye Ayır"/"Müşteriye Ayrılan".
- Satın alma sırasında opsiyonel satır bazlı tahsis, Alış Belgesi detayında "Müşteriye Ayrılan"
  bölümü + doğrudan "🧾 Sat" kısayolu (contact_view.php/product_view.php'de de var).
- Satış düzenle/sil artık CPA tüketimini doğru geri alıyor/yeniden uyguluyor (P0, commit `1f8a897`,
  `stock_lib.php::stock_update_sale()/stock_reverse_sale()` TEK ortak nokta).
- Alış Belgesi düzenle/sil aktif tahsis varken ENGELLENİYOR (`cpa_alloc_active_remaining_for_
  purchase()` kapısı, P0-3 turunda `stock_lib.php`'nin 6 fonksiyonuna `$viaDocument` parametresiyle
  bağlandı, commit `2a925a0`).
- **⚠️ primac.tr'de migrate.php (046/047 dahil) çalıştırılmadan bu özellik KULLANILAMAZ** — yazma
  fonksiyonları açık hata verir (sessizce "kuruluymuş gibi" davranmaz).
- **USER TEST YOK** — gerçek DB'de 2500 alış→1000 tahsis→400 satış→700'e düzenle→sil senaryosu
  sadece kod izi ile doğrulandı.

## 6. PERSONEL

- `personnel.php` (kartlı ana ekran) + `personnel_edit.php` (sticky kimlik başlığı: avatar+ad+rol+
  Aktif/Pasif+OTS hesap rozeti, sekmeler GENEL→OTS HESABI & YETKİLER→GÖREVLER→PERFORMANS öncelikli).
  `mobile/personnel_view.php` aynı sekmeli yapıya taşındı (commit `3796f3f`).
- Personel detayına `topx($title,$backUrl,$backLabel)` ile "‹ Personel" geri etiketi eklendi (P0
  mobil shell turlarından biri).
- **AÇIK:** sekme çubuğunun (9 sekme) responsive/görsel davranışı — önceki turlarda iki kez
  denendi (`3c2d4ac` scrollIntoView+fade, sonra `0e759f2` flex-wrap ile web'de KESİN çözüldü) ama
  bunun personel özelinde GERÇEK cihazda son hali TEYİT EDİLMEDİ.
- Mobil parity genel olarak tamam görünüyor, gerçek cihaz testi bekliyor.

## 7. MOBİL SHELL / İLETİŞİM — ⚠️ EN ÇOK "FAIL" ALAN ALAN, DİKKATLİ OKU

**Bu konuşmada mobil shell/navigation ile ilgili düzeltme 3 kez "tamamlandı" denip 3 kez gerçek
iPhone testinde (kullanıcı ekran görüntüsü/video ile) FAIL aldı.** Yeni oturum bunu HAFİFE ALMASIN.

**Round 1 (commit `74e797a`):** Kök neden bulundu: `body.mob-compact.chat-mode .df-m-bottomnav
{display:none}` — Sohbet/WhatsApp ekranına girilince global alt bar TAMAMEN gizleniyordu, composer
onun yerini alıyordu. Düzeltme: chat-mode artık nav'ı gizlemiyor, composer nav'ın üstüne oturuyor
(o an JS ile ölçülen `--acans-navh` custom property kullanıldı). Ayrıca `topx($title,$backUrl,
$backLabel)` — geri buton bağlamsal etiket kazandı, mobil tema (Sistem/Açık/Koyu) altyapısı
kuruldu, Launcher/Pin modeli retired edilip web Rail'le AYNI 5-kategori IA'sına geçildi.
→ **Gerçek cihazda FAIL:** route bazlı TUTARSIZ (İşler'de nav var, Menü/İletişim Merkezi'nde yok).

**Round 2 (commit `81de59a`):** Kod akışı satır satır yeniden izlendi, control-flow bugı
BULUNAMADI. Asıl kök neden bulundu: `mobile/sw.js`'nin service worker'ı `assets/css/*.css` ve
`assets/js/*.js`'i CACHE-FIRST alıyor, `sw.js`'nin kendi `CACHE` versiyon sabiti 2026-07-17'den
(bu oturumun TÜM CSS/JS turlarından ÖNCE) beri HİÇ bump edilmemişti — kullanıcının cihazı sunucudaki
kod ne olursa olsun eski CSS/JS'i önbellekten sunmaya devam ediyor olabilirdi. `CACHE='acans-os-
v28'`→`'v29'`. **KURAL: `assets/css`/`assets/js` her değiştiğinde `mobile/sw.js`'nin CACHE sabiti
de AYNI commit'te bump edilmeli** (bkz. `~/.claude/…/memory/feedback_sw_cache_bump.md` — kalıcı
feedback hafızası olarak da kaydedildi).
→ **Gerçek cihazda YİNE FAIL (video ile):** WhatsApp listede nav var, konuşma detayına girince nav
VE composer/gönder alanı YOK; OTS sohbette composer var ama nav yok.

**Round 3 (commit `f2eff0d`, EN SON DURUM):**
- Composer konumlandırma JS-ölçümlü `--acans-navh` yerine SABİT CSS `calc(70px + env(safe-area-
  inset-bottom))`'a geçirildi — timing/CSS-yükleme-sırası bağımlılığı TAMAMEN kaldırıldı (JS
  ölçüm artık YOK, `syncNavHeight()` silindi).
- Geri buton THY-tarzı: Ana/İş/Cari/İletişim(liste)/Menü(kök) — bu 5 KÖK ekranda geri buton
  TAMAMEN gizli (merkezi tek JS kuralı, `common.php::botx()`), detay ekranlarında bağlamsal
  etiket (‹ Sohbetler/‹ WhatsApp/‹ Menü) — TEK kod noktasından yönetiliyor.
- Menü seviye-2'deki gereksiz ikinci "‹ Menü" in-page linki kaldırıldı (topbar'ın kendi geri
  butonu zaten aynı işi yapıyordu).
- **Bu round'un USER TEST sonucu HENÜZ RAPORLANMADI** — checkpoint bu noktada yazıldığı için yeni
  oturum, Product Owner yeni bir test videosu/ekran görüntüsü paylaşırsa ona göre devam etmeli.

**Layout standardı (Product Owner'ın netleştirdiği THY-benzeri model):**
```
Alt bar (Ana/İş/Cari/İletişim/Menü) = ANA navigasyon, normal authenticated her ekranda sabit.
Geri buton = SADECE gerçek hiyerarşi (detay ekranı → bağlamsal üst ekran), kök ekranlarda YOK.
Sohbet/WhatsApp detay: header → mesaj geçmişi → composer → global bottom nav (composer nav'ın
  YERİNE GEÇMEZ, üstüne oturur).
Menü = sade kategori launcher (İşler/Ticaret/Üretim & Stok/Finans/Yönetim + İletişim Merkezi/
  Raporlar/Ayarlar tek-satırlık hub girişleri).
```

**Gerçek cihaz/DOM assertion testi bu ortamdan YAPILAMADI** (sunucu/cihaz erişimi yok) — tüm
doğrulama kod izi + headless Chrome statik mockup render (gerçek common.php/ds-foundation.css
içeriği kopyalanarak 390px iPhone viewport'ta screenshot) ile yapıldı. **primac.tr'ye
`guncelleme.zip`'in GERÇEKTEN yüklenip `guncelle.php`'nin çalıştırılıp çalıştırılmadığı da hiçbir
turda doğrulanamadı** — tekrarlayan "FAIL" raporlarının bir kısmı stale-deploy/stale-cache
kaynaklı olabilir, bir kısmı gerçek koddaki (şimdi düzeltilmiş) buglardı. Yeni oturum ikisini de
gözden uzak tutmamalı.

## 8. MOBİL TEMA / AYARLAR

- **Tema:** Sistem (varsayılan, `prefers-color-scheme` takip eder) / Açık / Koyu — `<html
  data-theme="dark|light">` (sistem seçiliyken attribute YOK), `ds-foundation.css`'teki
  `body.mobile-shell` token'ları artık `@media(prefers-color-scheme:dark)` + `data-theme` açık
  override'a bağlı (önceden HER ZAMAN sabit koyuydu, hiç açık tema YOKTU).
- **Kalıcılık kök nedeni bulundu (commit `f2eff0d`):** `user_preferences` tablosu (migration 044)
  yoksa `user_pref_set()`/`get()` ÖNCEDEN sessizce no-op oluyordu — kullanıcı "Açık" seçince o an
  ekran boyanıyordu ama kayıt hiç DB'ye yazılamıyor, sonraki sayfada varsayılana dönüyordu.
  `user_prefs_table_ready()` guard'ı + `ajax_nav_prefs.php`'nin `set_theme` action'ı artık migration
  eksikse AÇIK hata veriyor, `setMobileTheme()` JS'i fetch yanıtını kontrol edip başarı/hatayı
  görünür gösteriyor (sessiz "kaydedildi" yalanı YOK).
- **⚠️ primac.tr'de migration 044'ün gerçekten çalıştırılıp çalıştırılmadığı HÂLÂ doğrulanmadı** —
  çalıştırılmamışsa artık en azından kullanıcı AÇIK bir hata mesajı görür (önceden sessizdi).
- **Ayarlar:** Menü'nün EN ÜSTÜNDE (arama altında) tek satırlık "⚙️ Ayarlar" girişi
  (`more.php?open=ayarlar`) — içinde Görünüm(tema)/Bildirim Ayarları(Push Kur)/Profil-Şifre/Web
  Sürümü/Çıkış Yap toplu. Dağınık "Genel" bloğu + varsayılan-kapalı `<details>` kaldırıldı — İş
  kategorileriyle (İşler/Ticaret/Üretim & Stok/Finans/Yönetim) artık karışmıyor.
- Bu round'un USER TEST sonucu da HENÜZ RAPORLANMADI (bkz. bölüm 7 son not).

## 9. MOBİL UX/IA REFERANS PASS — ⚠️⚠️ EN ÖNEMLİ AKTİF İŞ (henüz BAŞLANMADI)

Product Owner'ın son komutu bu — checkpoint yazılırken bu iş için HENÜZ TEK SATIR KOD
YAZILMAMIŞTI (bu görev "sadece hafızayı güncelle, kod değiştirme" diye açıkça sınırlandırıldığı
için). **Yeni oturum tam burdan devam etmeli.**

**Görev tanımı (Product Owner'ın kendi sözleriyle özet):**
- TÜM 66 mobil sayfayı BİRDEN değiştirme. ÖNCE sadece **7 referans ekran**:
  Ana, Menü, İş, Cari, İletişim, 1 örnek Liste ekranı, 1 örnek Detay ekranı.
- Yeni mobil ürün modeli:
  - Alt bar = ana "dünyalar" (bölüm 7'deki THY modeliyle aynı).
  - Geri = sadece bağlamsal üst ekran.
  - Menü = sade launcher: **Ticaret / Stok & Üretim / Finans / Raporlar / Yönetim** — İş ve
    İletişim ZATEN alt barda olduğu için Menü'de GEREKSİZ TEKRAR edilmeyecek (yani şu anki
    Menü'deki İletişim Merkezi tile'ı da muhtemelen kaldırılmalı/yeniden değerlendirilmeli —
    bu netleştirilmeli).
  - Arama = tek global bulma mekanizması.
  - Ana = "Operation Center" (komuta merkezi hissi).
  - Liste ekranı = standart mobil liste deseni (henüz TANIMLANMADI — referans ekranda netleşecek).
  - Detay ekranı = özet + ana aksiyon + sade içerik navigasyonu (henüz TANIMLANMADI).
  - "10 aksiyon" gibi anlamsız sayaçlar (şu anki kategori tile'larındaki "N aksiyon" alt metni gibi)
    KALDIRILACAK.
  - İş ekranı (jobs.php) uzun fiil/link listesi OLMAYACAK — yeniden tasarlanacak.
- **KRİTİK KISIT: Referans 7 ekran, Product Owner'ın ekran görüntüsü ile USER TEST ONAYI almadan
  KALAN ekranlara YAYILMAYACAK.** Yani: 7 referans ekranı tasarla/uygula → Product Owner'a göster →
  onay al → SONRA diğer ekranlara yay. Onaysız toplu yayılım YAPILMAMALI.
- Bu iş "Menü"nün bölüm 8'de az önce yapılan halini de muhtemelen ETKİLEYECEK (kategori seti
  değişiyor: 5 kategori → İletişim/Raporlar çıkarılıp sadece Ticaret/Stok & Üretim/Finans/
  Raporlar/Yönetim kalıyor gibi görünüyor — ama "Raporlar" hem burada hem az önce eklenen ayrı
  tile olarak listede, net karar Product Owner'dan referans ekran onayı ile gelecek).

**Yeni oturum ilk adımı:** bu 7 referans ekranın somut tasarımını (IA + wireframe seviyesinde,
gerekirse Artifact ile mockup) hazırlayıp Product Owner'dan görsel onay istemek — kör kod
yazıp yaymamak.

## 10. AÇIK BACKLOG (öncelik sırasıyla)

1. **Mobile UX/IA Reference Pass** (bölüm 9) — az önce başlatıldı, EN YÜKSEK öncelik.
2. **Mobil shell gerçek cihaz doğrulaması** (bölüm 7, Round 3 sonucu) — Product Owner'dan yeni
   test sonucu bekleniyor.
3. **Finans UX kapanışı — Çek/Senet Düzenle/İptal** (bölüm 4, "AÇIK/YAPILMADI" notu) — cari filtresi
   zaten yapıldı, sadece çek/senet edit/cancel UI+matematiği kaldı.
4. **primac.tr deploy doğrulaması** — migrate.php'nin GERÇEKTEN çalıştırılıp çalıştırılmadığı
   (özellikle 044/046/047/048) hiçbir turda teyit edilemedi; `guncelleme.zip`'in fiilen yüklenip
   `guncelle.php`'nin çalıştırıldığı da doğrulanamadı. Bu, tekrarlayan "FAIL" raporlarının bir
   kısmını açıklıyor olabilir — Product Owner'a AÇIKÇA sorulmalı.
5. Home görev mükerrerliği — `8823d18` ile kapatıldığı NOT edilmişti, yeni oturum tekrar açmadan
   önce gerçekten kapalı mı diye git log/kod ile kontrol etsin (muhtemelen KAPALI).
6. Personel mobil parity / sekme UX gerçek cihaz doğrulaması (bölüm 6).
7. Kodda var ama UI'ye hiç bağlanmamış özellik taraması — Master Denetim'in "F. Bağlanmamış
   Özellikler" bölümünden (bu checkpoint'te tekrarlanmadı, gerekirse Master Audit Artifact'ine
   bakılabilir — link için önceki oturumun kendi kaydına bakılmalı, bu dosyada YOK).
8. Legacy UI son temizlik — özellikle `job_view.php` web+mobil parity (Master Audit'te "Legacy UI
   Envanteri" bölümünde bahsi geçmişti, bu checkpoint'te detay tutulmadı).
9. Web/mobil parity genel taraması.
10. Smoke test (P0 kapanış sonrası).
11. **v0.9 PILOT RELEASE** — nihai hedef, yukarıdakiler USER TEST PASS almadan gündeme gelmez.

## 11. SON COMMİTLER (en yeniden en eskiye)

| Hash | İş | Durum |
|---|---|---|
| `524b591` | docs: mobil nav/tema 3. tur memory kaydı | — |
| `f2eff0d` | Mobil shell Round 3: sağlam composer CSS + THY geri buton + tema kalıcılığı kök nedeni + Ayarlar toplanması | CODE PASS, USER TEST PENDING |
| `81de59a` | Mobil shell Round 2: sw.js CACHE version bump (v28→v29) — service worker stale cache kök nedeni | CODE PASS, USER TEST → FAIL (Round 3'ü tetikledi) |
| `1aafe7d` | Finans UX: Tahsilat/Ödeme cari filtresi + çek/senet entegrasyonu (kasa/banka hareketi yok) | CODE PASS, USER TEST PENDING |
| `2dabae1` + `c215360` | Mobil shell Round 1: Launcher/Pin retired, kategori IA, back-target mekanizması | CODE PASS, USER TEST → FAIL (Round 2'yi tetikledi) |
| `bf15fda` | P0-4: mobil "Belge" redirect-trap (`web=1`) | CODE PASS |
| `2a925a0` | P0-3: Alış/Satış Belgesi düzenle/sil (kontrollü iptal) | CODE PASS, USER TEST YOK |
| `486a873` | P0-2: migration 048 schema-readiness guard | CODE PASS |
| `275a95f` | P0-1: çek/senet cari bakiye işareti düzeltmesi | CODE PASS, canlı DB'de doğrulanmadı |
| `8469ab6` | CPA: "Müşteriye Ayrılan" listelerine "🧾 Sat" kısayolu | CODE PASS |
| `0e759f2` | df-tabs web'de kesin taşma çözümü (flex-wrap) | CODE PASS |
| `e04917e` | CPA: Müşteriye Özel Satın Alma gerçek kullanıcı akışı | CODE PASS, USER TEST YOK |
| `8823d18` | Home: mükerrer kritik stok gösterimi kaldırıldı | CODE PASS |
| `15959a2` | (önceki oturumun context handoff noktası) | — |

Daha eski commit'ler için `git log --oneline` — bu checkpoint'ten önceki genel durum
`memory/backlog.md`'deki `🔴 CONTEXT HANDOFF — 2026-07-18 (ÖNCEKİ TUR)` bloğunda (10 maddelik
tablo) hâlâ geçerli referans olarak duruyor, silinmedi.

---

## 12. YENİ CHAT BAŞLATMA KOMUTU

Aşağıdaki metni yeni Claude Code konuşmasının İLK mesajı olarak yapıştır:

```
PRIMAC OTS geliştirmesine kaldığımız yerden devam et.

Önce şunları oku (bu sırayla):
1. memory/NEXT_CHAT_CONTEXT.md (bu konuşmanın tam checkpoint'i)
2. CLAUDE.md ve PROJECT_RULES.md
3. memory/backlog.md (en üstteki context handoff bloğu dahil)

Sonra git status + git log --oneline -20 ile gerçek kod durumunu checkpoint'teki HEAD (524b591
veya sonrası) ile doğrula — checkpoint'ten sonra yeni commit varsa önce onları incele.

Kurallar:
- Checkpoint'te "USER TEST PENDING" veya "FAIL" işaretli hiçbir işi otomatik olarak "tamamlandı"
  sayma — gerçek onay/test sonucu gelmeden CLOSED deme.
- Checkpoint'te tamamlanmış (CODE PASS + USER TEST PASS) olarak işaretli işi TEKRAR yapma.
- Şu an aktif öncelik: Mobile UX/IA Reference Pass (checkpoint bölüm 9) — 7 referans ekranın
  (Ana/Menü/İş/Cari/İletişim/1 Liste/1 Detay) tasarımını hazırla, Product Owner'dan GÖRSEL ONAY
  almadan kalan ekranlara YAYMA.
- Bu onay süreci beklerken veya paralel olarak, açık backlog'daki diğer maddelere (checkpoint
  bölüm 10) Product Owner'ın vereceği sıraya göre geç.
- config.php/lokal veya canlı veritabanına dokunma, migration çalıştırma — bu ortamda erişim yok.
- Bana ne yapacağını sormadan önce checkpoint'i ve gerçek kodu oku; sadece gerçekten belirsiz/
  Product Owner kararı gereken noktalarda sor.

Kısa bir "checkpoint okundu, şu an aktif iş X" özetiyle başla, uzun rapor yazma.
```
