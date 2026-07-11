# Backlog & Yapılacaklar

<!-- Açık geliştirme görevleri. Kapanan madde buradan silinip memory/features.md'ye taşınır. -->

## Migration 042 (settles_movement_id) ve 043 (satır bazlı fiyat/KDV) DEV PASS takibi açık
- İkisi de primac.tr'ye deploy edilen kod tabanında hazır (sale-edit özelliği migration 043'e
  bağımlı) ama kullanıcının DEV'de `migrate.php`'yi çalıştırıp teknik doğrulama yapması Finance
  Core Stabilization commit'inden (`d02665b`, migration İÇERMİYOR) BAĞIMSIZ ayrı ayrı takip
  ediliyor. Her ikisi de yerel MariaDB sandbox'ta idempotency + fonksiyonel senaryolarla test
  edildi (bkz. features.md), ama DEV üzerinde kullanıcı PASS'ı gelmeden CLOSED yazılmayacak.

## Mobile Regression Sprint — Finance Core Stabilization mobil doğrulaması (2026-07-11)
- Finance Core Stabilization (satış/alış artık ödeme yapmıyor, cari bakiye formülü düzeltmesi —
  bkz. features.md, bugs.md "Çözüldü") WEB tarafında kullanıcı PASS aldı ve CLOSED (WEB)
  işaretlendi. Kod incelemesinde (Elif/ots-parity-auditor) web/mobil parite PASS aldığı için bu
  karar bloklanmadı, ama kullanıcı henüz mobilde fiilen test etmedi. Ayrı bir sprint olarak:
  `mobile/sales.php` + `mobile/purchase.php`'de aynı 13 zorunlu senaryo (satış→tahsilat,
  alış→ödeme, eski satışı veresiyeye çevirme, alış miktar düzenleme, avg_cost güvenlik kapısı)
  mobil arayüzde elle doğrulanmalı.

## tasks.deleted_at soft-delete filtresi bazı sayaç/rapor sorgularına eklenmedi (2026-07-04)
- "İşlerim" Düzenle/Detay/Sil turu (bkz. features.md) `tasks` tablosuna soft-delete (`deleted_at`)
  ekledi ve `mytasks.php`/`mobile/mytasks.php`/`tasks.php`/`mobile/tasks.php`/`task_view.php`
  (web+mobil) sorgularının hepsine `deleted_at IS NULL` filtresi eklendi. Ama şu dosyalardaki
  `tasks` sayaç/rapor sorguları bilinçli olarak DOKUNULMADI (kapsam disiplini + paralel ajan
  çakışma riski): `jobs.php`, `personnel_view.php` (web+mobil), `dashboard.php`, `kpi.php`,
  `mobile/kpi.php`, `report_lib.php`, `gunluk_rapor.php`, `mobile/gunluk_rapor.php`,
  `daily_reminder_lib.php`, `takvim.php`, `mobile/calendar.php`, `personnel.php`,
  `mobile/personnel.php`, `mobile/profile.php`, `personnel_edit.php`. Soft-silinen bir görev bu
  ekranlardaki "açık görev" sayaçlarına/raporlara hâlâ dahil olabilir. Kalıcı çözüm: bu dosyaların
  tamamına tek tek `AND deleted_at IS NULL` eklenmesi — küçük ama çok dosyayı aynı anda değiştiren
  bir iş, ayrı bir tur olarak ele alınmalı (bu turun kapsamı sadece "İşlerim" akışıydı).
- Ayrıca `sil.php`'nin `'job'` dalı bir işi silerken bağlı `tasks` satırlarını hâlâ FİZİKSEL siliyor
  (children temizliği, job silme akışının kendi kapsamı) — task soft-delete kuralı bu senaryoyu
  kapsamıyor. Job silme akışına dokunmak ayrı bir karar gerektirir.

## VAPID push anahtarı sunucu config.php'lerine elle eklenmeli (2026-07-03)
- `push_lib.php` artık `app_config()` (config.php) üzerinden `vapid_public`/`vapid_private`/
  `vapid_subject` okuyor, tanımlı değilse koddaki ESKİ sabit değerlere düşüyor (geri uyumlu, acil
  değil, hiçbir şey bozulmaz). Ama kalıcı çözüm için ACANS ve PRIMAC sunucularındaki gerçek
  `config.php` dosyalarına (cPanel File Manager, repo dışı, buradan erişim yok) şu 3 satır elle
  eklenmeli:
  ```php
  'vapid_public'=>'BKEqJl3sOt2lxHVBXjtCu_nFTCgH42b7NVTjE4BsGq5xC81cdwF1llwIiAmXMbDieoC74QLHZOhZ1dSkgQjLP3c',
  'vapid_private'=>'lEr2og5nZs8UfiLd3EJeWAsT0NeSoj9aseWYJtxlusw',
  'vapid_subject'=>'mailto:admin@acanstr.com',
  ```
  Bunlar mevcut anahtarlar (taşıma, rotasyon değil) — repo artık GitHub'a bağlı olduğu için kodda
  sabit durması sızıntı riski (bkz. [[deploy]]). Kullanıcı seyahatte, dönünce elle eklenecek.

## Web'de "Görevlerim" sayfası yok — mobilde var (mytasks.php)
- 2026-07-03 (takvim/tasks incelemesi sırasında bulundu): mobilde personel kendi görevlerini
  `mobile/mytasks.php`'de görüyor (yetki gerektirmez, sadece kendi `personnel_id`'sine bakar).
  Web'de karşılığı YOK — web'de görevler sadece `tasks.php` üzerinden görünüyor ve bu sayfa
  `page_module_map()`'te `'tasks'` modülüne bağlı, yani `tasks` yetkisi verilmemiş bir personel
  kendine atanan görevi web'den göremiyor (dashboard'daki "Açık Görev" kartı ve takvimdeki 🎯
  madde bu kullanıcılar için tıklanamaz/düz metin bırakıldı, bkz. features.md). Kalıcı çözüm:
  web'e de `mytasks.php` benzeri, yetki gerektirmeyen bir "Görevlerim" sayfası eklenmeli (CLAUDE.md
  kural 7 — parite). Kapsam/tasarım henüz netleşmedi.

## Mobil parite eksiği — work_center.php, trade_documents.php, design.php sadece web'de
- 2026-07-03 (commit `1ff6f1e`): bu üç sayfa web sol menüsüne ve `boot.php` yetki eşlemesine
  eklendi (bkz. features.md). Ama mobilde hiç karşılığı yok (`mobile/work_center.php`,
  `mobile/trade_documents.php`, `mobile/design.php` gibi dosyalar yok). CLAUDE.md kural 7 ("Yeni
  özellik hem web hem mobilde olmalı") açısından açık kalan tek madde bu — henüz kapsam belirlenmedi
  (mobilde ayrı sayfa mı, yoksa mevcut jobs.php/contacts.php içine filtre olarak mı gömülecek).

## ÇÖZÜLDÜ (yanlış çıktı) — Web tarafı rol kısıtı
- RAPOR.md'nin (2026-06-28) "web'de rol kısıtı yok" iddiası 2026-07-02 denetiminde YANLIŞ bulundu:
  `boot.php`'de `page_module_map()`, `user_can()`, `require_permission()` ile gerçek modül-bazlı yetki
  sistemi var ve `$__pmap` üzerinden sayfa başına otomatik koruma uygulanıyor. Madde kaldırıldı.

## Diagnostik dosyalar prod sunucuda kalmış olabilir
- `temizle.php` (kökte, admin girişi veya `?key=acans-migrate-2026` ile çalışır) install_*.php,
  kontrol.php, iz.php, bak.php, fix_login.php, ac_extract.php, dev_check.php, ac.php, eski not
  dosyalarını siler ve kendini de siler. NOT (2026-07-02 düzeltmesi): asıl deploy aracı `guncelle.php`
  (Masaüstü'nde, repo dışı — bkz. [[deploy]]) her deploy'da KENDİ yardımcı dosyalarını zaten siliyor;
  `temizle.php` bundan bağımsız, daha eski legacy artıkları (install_*.php vb.) temizlemek için ayrı
  bir araç — deploy'un zorunlu bir adımı değil, gerektiğinde manuel çalıştırılır.
