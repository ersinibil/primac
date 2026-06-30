-- Migration: 008_misc
-- Diğer: yönetim talepleri

CREATE TABLE IF NOT EXISTS `management_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_no` varchar(40) DEFAULT NULL,
  `personnel_id` int DEFAULT NULL,
  `related_job_id` int DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `description` text,
  `priority` varchar(30) DEFAULT 'Normal',
  `status` varchar(40) DEFAULT 'Yeni',
  `response_note` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_personnel` (`personnel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

