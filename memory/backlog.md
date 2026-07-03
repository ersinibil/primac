# Backlog & Yapılacaklar

<!-- Açık geliştirme görevleri. Kapanan madde buradan silinip memory/features.md'ye taşınır. -->

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
