-- Migration: 004_stock_products
-- Stok & ürün: marka/kategori/tedarikçi/birim, stok, hareketler

CREATE TABLE IF NOT EXISTS `product_brands` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_brand_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `supplier_sku` varchar(120) DEFAULT NULL,
  `last_purchase_price` decimal(14,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'TRY',
  `lead_time_days` int DEFAULT '0',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_supplier` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `short_name` varchar(30) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_unit_short` (`short_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL DEFAULT 'Genel',
  `name` varchar(180) NOT NULL,
  `unit` varchar(30) DEFAULT 'adet',
  `quantity` decimal(12,3) DEFAULT '0.000',
  `critical_level` decimal(12,3) DEFAULT '0.000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `product_code` varchar(80) DEFAULT NULL,
  `barcode` varchar(120) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `variant_name` varchar(180) DEFAULT NULL,
  `brand` varchar(120) DEFAULT NULL,
  `default_supplier_id` int DEFAULT NULL,
  `purchase_price` decimal(14,2) DEFAULT '0.00',
  `sale_price` decimal(14,2) DEFAULT '0.00',
  `avg_cost` decimal(14,2) DEFAULT '0.00',
  `last_purchase_price` decimal(14,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'TRY',
  `active` tinyint(1) DEFAULT '1',
  `notes` text,
  `brand_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `shelf_code` varchar(80) DEFAULT NULL,
  `warehouse` varchar(120) DEFAULT NULL,
  `max_stock` decimal(14,3) DEFAULT '0.000',
  `vat_rate` decimal(5,2) DEFAULT '20.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stock_item_id` int NOT NULL,
  `job_id` int DEFAULT NULL,
  `direction` varchar(20) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `reason` varchar(120) DEFAULT NULL,
  `note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

