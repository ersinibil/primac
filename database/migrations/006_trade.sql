-- Migration: 006_trade
-- Ticari belgeler: teklif/sipariş/fatura ve kalemleri

CREATE TABLE IF NOT EXISTS `trade_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `document_no` varchar(80) NOT NULL,
  `document_type` varchar(30) NOT NULL,
  `contact_id` int DEFAULT NULL,
  `account_id` int DEFAULT NULL,
  `document_date` date NOT NULL,
  `status` varchar(40) DEFAULT 'Kesinleşti',
  `subtotal` decimal(14,2) DEFAULT '0.00',
  `vat_total` decimal(14,2) DEFAULT '0.00',
  `grand_total` decimal(14,2) DEFAULT '0.00',
  `paid_amount` decimal(14,2) DEFAULT '0.00',
  `description` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact` (`contact_id`),
  KEY `idx_type` (`document_type`),
  KEY `idx_date` (`document_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `trade_document_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `document_id` int NOT NULL,
  `stock_item_id` int DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `unit` varchar(40) DEFAULT 'adet',
  `quantity` decimal(14,3) DEFAULT '1.000',
  `unit_price` decimal(14,2) DEFAULT '0.00',
  `vat_rate` decimal(5,2) DEFAULT '20.00',
  `line_total` decimal(14,2) DEFAULT '0.00',
  `line_vat` decimal(14,2) DEFAULT '0.00',
  `line_grand` decimal(14,2) DEFAULT '0.00',
  `auto_created_product` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_document` (`document_id`),
  KEY `idx_stock` (`stock_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

