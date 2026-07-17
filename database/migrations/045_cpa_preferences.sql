-- Migration: 045_cpa_preferences
-- P1 — CUSTOMER PROCUREMENT ALLOCATION (CPA) / Müşteriye Özel Tedarik Takibi (2026-07-18,
-- Product Owner kararı). Amaç: her müşteri+ürün kombinasyonu için tercih edilen tedarikçi(ler)i
-- öncelik sırasıyla saklamak ve satın alma sırasında "akıllı öneri" (zorunlu değil) olarak
-- kullanmak. Kayıtlar SİLİNMEZ — kaldırma işlemi status='Pasif' ile yapılır, değişiklik geçmişi
-- audit_log tablosunda (028_audit_log.sql, audit_lib.php::audit_log()) tutulur.
-- Aynı müşteri+ürün için birden fazla tedarikçi tanımlanabilir (priority ile sıralanır),
-- UNIQUE KEY tekrar eden (müşteri,ürün,tedarikçi) üçlüsünü engeller — yeni tercih değil, var olan
-- kaydın güncellenmesi (öncelik/varsayılan/durum) beklenir.
-- Genişletilebilirlik (Product Owner madde 5 — fiyat analizi/performans puanı/kalite/teslim
-- süresi): bu alanlar İLERİDE ayrı nullable kolonlar olarak eklenecek, bu turda icat edilmedi.

CREATE TABLE IF NOT EXISTS cpa_preferences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL COMMENT 'contacts.id — bu tercihin ait olduğu müşteri',
  stock_item_id INT NOT NULL COMMENT 'stock_items.id — ürün/stok kartı',
  supplier_id INT NOT NULL COMMENT 'contacts.id — tercih edilen tedarikçi',
  priority INT NOT NULL DEFAULT 1 COMMENT 'aynı müşteri+ürün için sıralama, 1=en öncelikli',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif' COMMENT 'Aktif/Pasif — satır asla silinmez',
  notes TEXT NULL,
  created_by INT NULL COMMENT 'app_users.id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_customer_product_supplier (customer_id, stock_item_id, supplier_id),
  KEY idx_customer_product (customer_id, stock_item_id),
  KEY idx_supplier (supplier_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
