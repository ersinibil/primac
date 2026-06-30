-- Migration: 002_contacts_crm
-- CRM: cariler ve müşteri temsilcileri

CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `type` varchar(50) DEFAULT 'Müşteri',
  `phone` varchar(60) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `tax_info` varchar(180) DEFAULT NULL,
  `address` text,
  `opening_balance` decimal(12,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `authorized_person` varchar(160) DEFAULT NULL,
  `tax_office` varchar(160) DEFAULT NULL,
  `tax_number` varchar(80) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `notes` text,
  `representative_mode` varchar(30) DEFAULT 'personel',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_representatives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `personnel_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_contact_personnel` (`contact_id`,`personnel_id`),
  KEY `idx_contact` (`contact_id`),
  KEY `idx_personnel` (`personnel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

