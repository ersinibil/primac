-- Migration: 003_jobs_tasks
-- İş yönetimi: işler, aşamalar, dosyalar, görevler, iş motoru

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_no` varchar(40) NOT NULL,
  `title` varchar(220) NOT NULL,
  `job_type` varchar(60) NOT NULL DEFAULT 'karma',
  `customer_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `responsible_personnel_id` int DEFAULT NULL,
  `description` text,
  `due_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Yeni',
  `sale_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cost_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `work_status` varchar(80) DEFAULT 'Planlama',
  `work_progress` int DEFAULT '0',
  `external_status` varchar(80) DEFAULT NULL,
  `delivery_status` varchar(80) DEFAULT NULL,
  `collection_status` varchar(80) DEFAULT 'Bekliyor',
  `priority` varchar(30) DEFAULT 'Normal',
  `channel` varchar(60) DEFAULT NULL,
  `delivery_address` text,
  `file_link` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_stages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `stage_name` varchar(160) NOT NULL,
  `sort_order` int DEFAULT '0',
  `status` varchar(50) DEFAULT 'Bekliyor',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `note` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `uploaded_by` int DEFAULT NULL,
  `file_type` varchar(60) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `stored_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `share_token` varchar(80) DEFAULT NULL,
  `approval_status` varchar(60) DEFAULT 'Taslak',
  `approval_note` text,
  `customer_note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job` (`job_id`),
  KEY `idx_approval` (`approval_status`),
  KEY `idx_token` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int DEFAULT NULL,
  `personnel_id` int DEFAULT NULL,
  `title` varchar(220) NOT NULL,
  `description` text,
  `due_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Açık',
  `priority` varchar(40) DEFAULT 'Normal',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_checklists` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `title` varchar(220) NOT NULL,
  `owner_type` varchar(40) DEFAULT 'personel',
  `owner_id` int DEFAULT NULL,
  `status` varchar(80) DEFAULT 'Bekliyor',
  `sort_order` int DEFAULT '0',
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `event_type` varchar(80) DEFAULT 'İş',
  `title` varchar(220) NOT NULL,
  `description` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

