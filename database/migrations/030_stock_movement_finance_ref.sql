-- Migration: 030_stock_movement_finance_ref
-- "Satış Sil" özelliği (stock_lib.php stock_reverse_sale()) silinecek satışa ait stok hareketini
-- SADECE "aynı gün + reason=Satış + en son eklenen" gibi belirsiz bir kritere göre buluyordu —
-- aynı gün birden fazla satış olursa YANLIŞ hareketi geri alıp veri bozabiliyordu (2026-07-03
-- güvenlik denetiminde bulundu). Artık satış anında finance_movements.id doğrudan stock_movements'a
-- yazılıyor, silme bu kesin referansla eşleşiyor.
ALTER TABLE stock_movements ADD COLUMN finance_movement_id INT NULL COMMENT 'finance_movements.id — satıştan gelen hareket için kesin referans' AFTER job_id;
