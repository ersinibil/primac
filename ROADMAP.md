# ROADMAP.md — Açık Maddeler ve Bekleyen Kararlar

Bu dosya "yapılacaklar listesi" değil, **karar bekleyen veya kapsamı netleşmemiş** maddelerin
kaydıdır. `PROJECT_RULES.md` gereği proje artık aktif geliştirme aşamasında değil — buradaki
hiçbir madde kullanıcı açıkça istemeden uygulanmaz. Amaç: bir sonraki oturumda "neredeydik"
sorusuna hızlı cevap.

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
