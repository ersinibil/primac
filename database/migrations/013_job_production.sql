-- Migration: 013_job_production
-- Üretim emri → stok bağlama: üretilen ürün + adet + stoğa eklendi mi
ALTER TABLE jobs ADD COLUMN produce_item_id INT NULL;
ALTER TABLE jobs ADD COLUMN produce_qty DECIMAL(12,3) NULL;
ALTER TABLE jobs ADD COLUMN produced TINYINT(1) DEFAULT 0;
