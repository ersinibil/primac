-- Migration: 035_finance_accounting_merge
-- Muhasebe (accounting_entries) ile Finans (finance_movements) iki ayrı, birbirinden habersiz
-- defterdi (accounting_entries'de contact_id hiç yoktu, hiçbir raporda finance_movements ile
-- birleşmiyordu). Kullanıcı isteğiyle TEK deftere birleştiriliyor — muhasebe kayıtları artık
-- finance_movements'a yazılır (movement_type='muhasebe'), böylece cari/hesap/genel özet
-- raporlarına otomatik dahil olur.
--
-- accounting_entries tablosu SİLİNMEZ (geri dönüş güvenliği için saklanır, artık okunmaz/yazılmaz).

ALTER TABLE finance_movements
  ADD COLUMN personnel_id INT NULL AFTER contact_id,
  ADD COLUMN payment_type VARCHAR(50) NULL AFTER payment_channel,
  ADD COLUMN vat_mode ENUM('dahil','haric','yok') NULL AFTER vat_amount;

INSERT INTO finance_movements
  (contact_id, category_id, direction, amount, vat_rate, vat_amount, vat_mode,
   account_id, personnel_id, payment_type, status, movement_date, description,
   reference_no, movement_type)
SELECT NULL, category_id, IF(type='gelir','in','out'), amount, vat_rate,
   vat_amount, vat_mode, account_id, personnel_id, payment_type,
   'Aktarıldı (Muhasebe)', entry_date, description, reference_no, 'muhasebe'
FROM accounting_entries;
