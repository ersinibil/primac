-- P0 SON KAPANIŞ (2026-07-18, Product Owner kararı): CPA'nın asıl ihtiyacı — "tercih edilen
-- tedarikçi" hafızası (045_cpa_preferences) yeterli değildi. Asıl talep: satın alınan miktarın
-- belirli kısmının belirli müşteriye/satışa TAHSİS edilmesi, geri kalanının serbest stok olarak
-- görünmesi. Bu tablo fiziksel stoktan (stock_items.quantity) TAMAMEN AYRI, salt muhasebe/takip
-- katmanıdır — mevcut stok/finans matematiğine (stock_lib.php) hiçbir INSERT/UPDATE eklenmedi.
--
-- purchase_movement_id: finance_movements.id (movement_type='purchase') — stock_movements.id
-- DEĞİL, çünkü stock_update_purchase() bir alış düzenlendiğinde eski stock_movements satırlarını
-- SİLİP yenilerini oluşturuyor (bkz. stock_lib.php::stock_purchase_reverse_lines) — o id'ye
-- bağlansaydı her alış düzenlemesinde tahsisler sessizce öksüz kalırdı. finance_movements.id
-- alış düzenlemesinde SABİT kalır (aynı satır UPDATE edilir), bu yüzden güvenli referans budur.
CREATE TABLE IF NOT EXISTS cpa_allocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_movement_id INT NOT NULL,
  stock_item_id INT NOT NULL,
  customer_id INT NOT NULL,
  allocated_qty DECIMAL(12,3) NOT NULL,
  consumed_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_purchase (purchase_movement_id),
  KEY idx_product (stock_item_id),
  KEY idx_customer_product (customer_id, stock_item_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
