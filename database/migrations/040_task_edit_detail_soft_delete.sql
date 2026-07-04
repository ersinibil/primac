-- Migration: 040_task_edit_detail_soft_delete
-- Kullanıcı isteği (2026-07-04): "İşlerim" ekranına Düzenle/Detay/Sil eklendi.
-- tasks: created_by (oluşturan kullanıcı) / updated_by (son güncelleyen kullanıcı) / deleted_at
-- (soft delete — hiçbir görev fiziksel silinmez, deleted_at IS NULL filtresiyle listelerden
-- gizlenir). Migration runner (migrate.php) idempotent hataları (1050/1060/1061/1091) zaten
-- yok sayıyor, bu repodaki diğer ALTER TABLE ADD COLUMN migration'ları (026, 036) ile aynı desen.
ALTER TABLE tasks ADD COLUMN created_by INT NULL COMMENT 'app_users.id — görevi oluşturan kullanıcı';
ALTER TABLE tasks ADD COLUMN updated_by INT NULL COMMENT 'app_users.id — görevi en son güncelleyen kullanıcı';
ALTER TABLE tasks ADD COLUMN deleted_at DATETIME NULL COMMENT 'NULL değilse görev soft-delete edilmiştir, hiçbir listede/sayaçta görünmez';

-- Görev yorumları (task detay ekranında, web+mobil ortak).
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Göreve eklenen dosyalar — job_files ile aynı desen (uploads/task_files/ altında saklanır, bu
-- tabloda kök-göreli yol tutulur), task'a özel, sadeleştirilmiş (onay/paylaşım alanları yok).
CREATE TABLE IF NOT EXISTS `task_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `uploaded_by` int DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `stored_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
