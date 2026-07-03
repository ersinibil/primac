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
