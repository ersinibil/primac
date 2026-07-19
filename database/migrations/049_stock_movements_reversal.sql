-- STOK HAREKETİ GÜVENLİ GERİ ALMA (2026-07-19, pilot öncesi kapanış — Product Owner kararı)
-- Amaç: kaynağı olmayan (finance_movement_id NULL — manuel/orphan) yanlış bir stok hareketini
-- fiziksel DELETE yapmadan, ters yönde yeni bir kayıtla düzeltebilmek (audit trail korunur).
-- reversed_movement_id: bu satırın HANGİ eski stock_movements.id'yi geri aldığını tutar — hem
-- "bu hareket zaten geri alındı mı" kontrolü hem de zaman çizelgesinde iz sürülebilirlik için.
-- Kaynağı bir satış/alışa bağlı (finance_movement_id dolu) hareketler bu mekanizmayı KULLANMAZ —
-- onlar zaten mevcut stock_reverse_sale()/stock_reverse_purchase() üzerinden (sales.php/purchase.php
-- "Sil" akışı) geri alınıyor, bu kolon sadece manuel/orphan kayıtlar için.
ALTER TABLE stock_movements
  ADD COLUMN reversed_movement_id INT NULL COMMENT 'Bu satır hangi eski stock_movements.id kaydını geri alıyor (manuel düzeltme)' AFTER finance_movement_id;

CREATE INDEX idx_stock_movements_reversed ON stock_movements(reversed_movement_id);
