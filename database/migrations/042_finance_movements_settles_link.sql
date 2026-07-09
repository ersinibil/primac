-- Migration: 042_finance_movements_settles_link
-- Finance Core Stabilization — Adım 1 (SADECE altyapı, davranış değişikliği YOK).
-- Bir tahsilat/ödeme satırının, belgesi olmayan (document_id NULL) hangi satış/alış
-- finance_movements satırını kapattığını tutacak tek kolon. Belge kaynaklı satış/alışlar
-- (trade_document_new.php) için bu kolon GEREKMİYOR — onlarda zaten trade_documents.paid_amount
-- + finance_movements.document_id var (bkz. migration 005, 030).
--
-- Bilinçli olarak YAPILMAYANLAR (mimari karar, Finance Core Architecture Review'de netleşti):
-- - paid_amount buraya EKLENMEDİ — o bir ledger satırının değil, bir belgenin özet durumudur,
--   doğru yeri trade_documents (zaten orada var).
-- - Gerçek FOREIGN KEY constraint EKLENMEDİ — proje genelinde ilişkiler uygulama katmanında
--   kontrol ediliyor (bkz. sil.php'deki FOREIGN_KEY_CHECKS deseni), tutarlılık için aynı yaklaşım.
-- - Hiçbir geriye dönük veri (backfill) doldurulmadı — mevcut kayıtlarda bu kolon NULL kalır,
--   eski davranış (status bazlı bakiye hesaplama) birebir korunur.
-- - Bu migration TEK BAŞINA hiçbir kod davranışını değiştirmez — kolon eklenene kadar hiçbir
--   ekran bunu okumuyor/yazmıyor. Kullanılmaya başlaması ayrı, sonraki adımların konusu.
--
-- "Ne kadarı kapandı" burada AYRICA saklanmaz — ihtiyaç anında
-- SUM(amount) WHERE settles_movement_id=<satırın id'si> ile türetilir.

ALTER TABLE finance_movements
  ADD COLUMN settles_movement_id INT NULL
  COMMENT 'finance_movements.id — bu tahsilat/ödemenin kapattığı belgesiz satış/alış satırı (document_id NULL olan kayıtlar için)'
  AFTER document_id;
