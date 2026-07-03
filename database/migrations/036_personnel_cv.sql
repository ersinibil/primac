-- Migration: 036_personnel_cv
-- Personel kartına opsiyonel CV/özgeçmiş dosyası yükleme desteği.
-- Dosyalar uploads/personnel_cv/ altında saklanır, bu kolonda kök-göreli yol tutulur
-- (örn. 'uploads/personnel_cv/p_12_20260703_ab12cd34.pdf') — checks_notes.attachment ile aynı desen.

ALTER TABLE personnel ADD COLUMN cv_path VARCHAR(255) NULL COMMENT 'uploads/personnel_cv altında saklanan CV/özgeçmiş dosya yolu' AFTER notes;
