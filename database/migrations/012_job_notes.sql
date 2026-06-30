-- Migration: 012_job_notes
-- İş notları: personel/yönetici işe zaman damgalı not yazabilir.
CREATE TABLE IF NOT EXISTS job_notes (
  id INT NOT NULL AUTO_INCREMENT,
  job_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  note TEXT,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
