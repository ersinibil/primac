-- Migration: 005_finance
-- Finans: hesaplar ve hareketler

CREATE TABLE IF NOT EXISTS `finance_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `account_type` varchar(60) NOT NULL DEFAULT 'Banka',
  `bank_name` varchar(180) DEFAULT NULL,
  `iban` varchar(80) DEFAULT NULL,
  `card_last4` varchar(10) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'TRY',
  `opening_balance` decimal(14,2) DEFAULT '0.00',
  `current_balance` decimal(14,2) DEFAULT '0.00',
  `active` tinyint(1) DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contact_id` int DEFAULT NULL,
  `job_id` int DEFAULT NULL,
  `direction` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_channel` varchar(50) DEFAULT 'Banka',
  `status` varchar(50) DEFAULT 'Bekliyor',
  `movement_date` date DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `account_id` int DEFAULT NULL,
  `target_account_id` int DEFAULT NULL,
  `method_id` int DEFAULT NULL,
  `movement_type` varchar(40) DEFAULT 'normal',
  `reference_no` varchar(120) DEFAULT NULL,
  `document_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

