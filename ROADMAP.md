# ROADMAP.md — Açık Maddeler ve Bekleyen Kararlar

## Web Push bildirimi — canlıda VAPID doğrulaması gerekiyor (2026-07-04, SPRINT CLOSE)
Web push bildirimi (`sw.js`, `layout_bottom.php`) yerel ortamda gerçek Chromium ile uçtan uca
doğrulandı (abonelik + teslimat başarılı) — ancak bu test `push_lib.php`'nin fallback (kod içi
sabit) VAPID anahtarlarıyla çalıştı. primac.tr'nin GERÇEK sunucu `config.php`'sinde
`vapid_public`/`vapid_private` tanımlı mı, `gmp`/`bcmath` PHP eklentisi var mı — bunlar sadece canlı
sunucudan doğrulanabilir (bkz. `memory/deploy.md` "EYLEM GEREKİYOR" kaydı, 2026-07-03'ten beri açık).
Kullanıcının primac.tr'de gerçek bir cihazdan bildirim izni verip test etmesi gerekiyor.

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
