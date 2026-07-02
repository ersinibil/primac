-- Migration: 026_checks_notes_attachment
-- Çek/senet kaydına dosya eki (fotoğraf/taranmış görsel/PDF) desteği.
-- Dosyalar uploads/check_files/ altında saklanır, bu kolonda kök-göreli yol tutulur
-- (örn. 'uploads/check_files/cn_12_20260702_ab12cd34.jpg') — job_files ile aynı desen.

ALTER TABLE checks_notes ADD COLUMN attachment VARCHAR(255) NULL COMMENT 'uploads/check_files altında saklanan dosya yolu' AFTER notes;
