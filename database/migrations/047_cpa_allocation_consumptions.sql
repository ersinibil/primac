-- P0 CPA VERİ BÜTÜNLÜĞÜ KAPANIŞI (2026-07-18, Product Owner kararı) — satış tamamlandığında
-- cpa_allocations.consumed_qty'yi artıran her dilim burada KAYIT ALTINA alınır (allocation_id +
-- sale_movement_id + o dilimde ne kadar düştüğü). Bu defter olmadan bir satış düzenlenince/
-- silinince HANGİ tahsis(ler)den ne kadar geri alınacağı bilinemez — tüketim kalıcı "asılı" kalırdı.
--
-- Kullanım: cpa_allocation_lib.php::cpa_alloc_consume_for_sale() satır ekler,
-- cpa_alloc_reverse_for_sale() aynı sale_movement_id'ye ait satırları okuyup geri alır ve SİLER
-- (bu silme, fonksiyonun idempotent olmasını sağlar — aynı satış için ikinci "reverse" çağrısı
-- artık geri alınacak bir şey bulamaz, no-op olur, ÇİFT İADE oluşmaz).
--
-- 046_cpa_allocations.sql ile birlikte CPA tahsis özelliğinin TEK şema otoritesidir — bu iki
-- migration çalıştırılmadan cpa_allocation_lib.php runtime'da kendi şemasını ARTIK kurmaz
-- (bkz. cpa_alloc_tables_ready()/cpa_alloc_require_tables()) — özellik migrate.php uygulanmadan
-- "aktifmiş gibi" davranmaz.
CREATE TABLE IF NOT EXISTS cpa_allocation_consumptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  allocation_id INT NOT NULL,
  sale_movement_id INT NOT NULL,
  consumed_qty DECIMAL(12,3) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_allocation (allocation_id),
  KEY idx_sale (sale_movement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
