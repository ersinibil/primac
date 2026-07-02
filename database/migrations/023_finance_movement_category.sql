-- Migration: 023_finance_movement_category
-- "Ödeme / Gider" (finance_movements) kayıtları şimdiye kadar sadece Cari ile
-- etiketlenebiliyordu. Personel yol gideri, yakıt, vergi, günlük yemek, telefon gibi
-- cari gerektirmeyen giderler için accounting_categories'e bağlı, OPSİYONEL bir
-- category_id kolonu ekleniyor (cari zorunlu değil, sadece kategori de seçilebilir).

ALTER TABLE finance_movements ADD COLUMN category_id INT NULL COMMENT 'accounting_categories.id — opsiyonel, cari yerine/yanında' AFTER contact_id;

-- Kullanıcının örnek verdiği ama hazır kategori setinde eksik olan iki varsayılan gider kategorisi
INSERT IGNORE INTO accounting_categories (name,type,group_name,sort_order) VALUES
('Personel Yol Gideri','gider','Personel',15),
('Günlük Yemek','gider','Personel',16);
