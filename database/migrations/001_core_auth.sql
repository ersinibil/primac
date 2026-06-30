-- Migration: 001_core_auth
-- Çekirdek: kullanıcılar, personel, cihazlar, aktivite log

CREATE TABLE IF NOT EXISTS `app_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(40) DEFAULT 'personel',
  `personnel_id` int DEFAULT NULL,
  `permissions` text,
  `active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `remember_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `role` varchar(120) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT '0.00',
  `daily_wage` decimal(10,2) DEFAULT '0.00',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `login_enabled` tinyint(1) DEFAULT '0',
  `telegram_activation_code` varchar(20) DEFAULT NULL,
  `telegram_bound` tinyint(1) DEFAULT '0',
  `telegram_chat_id` bigint DEFAULT NULL,
  `telegram_user_id` bigint DEFAULT NULL,
  `telegram_username` varchar(120) DEFAULT NULL,
  `telegram_last_seen` datetime DEFAULT NULL,
  `address` text,
  `start_date` date DEFAULT NULL,
  `work_type` varchar(60) DEFAULT NULL,
  `iban` varchar(40) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personnel_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `personnel_id` int NOT NULL,
  `telegram_user_id` bigint DEFAULT NULL,
  `telegram_chat_id` bigint DEFAULT NULL,
  `telegram_username` varchar(120) DEFAULT NULL,
  `telegram_first_name` varchar(120) DEFAULT NULL,
  `telegram_last_name` varchar(120) DEFAULT NULL,
  `device_name` varchar(120) DEFAULT 'Telegram',
  `last_seen` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tg_user` (`telegram_user_id`),
  KEY `idx_personnel` (`personnel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(160) DEFAULT NULL,
  `module` varchar(80) DEFAULT NULL,
  `action` varchar(120) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `icon` varchar(20) DEFAULT '•',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

