-- ÇEK / SENET — GERÇEK FİNANSAL YAŞAM DÖNGÜSÜ (2026-07-18, Product Owner kararı)
-- Mevcut checks_notes.finance_movement_id SADECE "çek/senet kabul edilince cari borç/alacağın
-- kapandığı" hareketi tutuyordu (contact_id dolu, account_id HER ZAMAN NULL — fiziken hiçbir kasa/
-- bankada değil). Bu migration, çekin/senedin KENDİ yaşam döngüsünü (fiilen tahsil/ödeme/ciro)
-- AYRI kolonlarda izler — cari zaten kapandığı için tahsil/ödeme anında cariye İKİNCİ KEZ
-- dokunulmaz, sadece seçilen kasa/banka hesabı (settle_account_id) gerçek nakit hareketi alır.
ALTER TABLE checks_notes
  ADD COLUMN settle_date DATE NULL COMMENT 'Fiili tahsil/ödeme tarihi' AFTER finance_movement_id,
  ADD COLUMN settle_account_id INT NULL COMMENT 'finance_accounts.id — tahsil edilen/ödenen kasa/banka' AFTER settle_date,
  ADD COLUMN settle_finance_movement_id INT NULL COMMENT 'gerçek kasa/banka hareketi (finance_movements.id, account_id dolu, contact_id NULL — cari ikinci kez etkilenmesin diye)' AFTER settle_account_id,
  ADD COLUMN ciro_contact_id INT NULL COMMENT 'contacts.id — çek ciro edilerek borcu kapatılan tedarikçi/cari' AFTER settle_finance_movement_id,
  ADD COLUMN ciro_finance_movement_id INT NULL COMMENT 'ciro edilince oluşan tedarikçi borç kapama hareketi (contact_id=ciro_contact_id, account_id NULL — kasa/banka hareketi yok)' AFTER ciro_contact_id,
  ADD COLUMN settle_notes VARCHAR(255) NULL COMMENT 'Tahsil/ödeme/ciro/karşılıksız/iptal serbest açıklama' AFTER ciro_finance_movement_id;
