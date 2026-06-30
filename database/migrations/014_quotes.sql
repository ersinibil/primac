-- Migration: 014_quotes — Teklif modülü
CREATE TABLE IF NOT EXISTS `quotes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quote_no` VARCHAR(40) NOT NULL,
  `customer_id` INT NULL,
  `customer_name` VARCHAR(180) NULL,
  `quote_date` DATE NULL,
  `valid_until` DATE NULL,
  `vat_rate` DECIMAL(5,2) DEFAULT 20.00,
  `subtotal` DECIMAL(14,2) DEFAULT 0,
  `vat_amount` DECIMAL(14,2) DEFAULT 0,
  `total` DECIMAL(14,2) DEFAULT 0,
  `notes` TEXT NULL,
  `status` VARCHAR(20) DEFAULT 'Taslak',
  `created_by` INT NULL,
  `created_by_name` VARCHAR(160) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_customer` (`customer_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quote_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `qty` DECIMAL(12,3) DEFAULT 1,
  `unit_price` DECIMAL(14,2) DEFAULT 0,
  `line_total` DECIMAL(14,2) DEFAULT 0,
  KEY `idx_quote` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
