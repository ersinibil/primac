-- Migration: 025_job_logs
-- job_view.php'nin "Zaman Çizelgesi" bölümü ve add_log() fonksiyonu bu tabloyu hep varsaymıştı
-- (INSERT/SELECT job_view.php'de zaten vardı) ama hiçbir migration dosyası tabloyu oluşturmuyordu —
-- ACANS'ta muhtemelen elle/geçmişte oluşturulmuş, PRIMAC'a (ayrı DB) hiç gitmemiş, bu yüzden
-- "Table 'job_logs' doesn't exist" hatası veriyordu. temizle_veri.php de bu tabloyu zaten biliyordu.
CREATE TABLE IF NOT EXISTS `job_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `log_type` varchar(50) DEFAULT 'Sistem',
  `message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
