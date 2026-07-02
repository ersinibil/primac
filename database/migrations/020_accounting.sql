-- Muhasebe modülü: kategori + kayıt tabloları

CREATE TABLE IF NOT EXISTS accounting_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('gelir','gider') NOT NULL,
  group_name VARCHAR(60) NULL,
  sort_order INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  type ENUM('gelir','gider') NOT NULL,
  category_id INT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  description VARCHAR(500) NULL,
  reference_no VARCHAR(80) NULL,
  account_id INT NULL COMMENT 'finance_accounts.id',
  personnel_id INT NULL COMMENT 'Personel ödemesi ise',
  payment_type VARCHAR(50) NULL COMMENT 'maas,avans,prim,sgk,vergi,diger',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan gider kategorileri
INSERT IGNORE INTO accounting_categories (name,type,group_name,sort_order) VALUES
('Maaş','gider','Personel',10),
('Avans','gider','Personel',11),
('Prim / İkramiye','gider','Personel',12),
('SGK Primi (İşveren)','gider','Personel',13),
('SGK Primi (İşçi)','gider','Personel',14),
('Gelir Vergisi (Muhtasar)','gider','Vergi',20),
('KDV Ödemesi','gider','Vergi',21),
('Damga Vergisi','gider','Vergi',22),
('Kira','gider','İşletme',30),
('Elektrik','gider','İşletme',31),
('Su','gider','İşletme',32),
('Doğalgaz','gider','İşletme',33),
('İnternet / Telefon','gider','İşletme',34),
('Yakıt / Araç','gider','İşletme',35),
('Ofis & Kırtasiye','gider','İşletme',36),
('Bakım & Onarım','gider','İşletme',37),
('Reklam & Pazarlama','gider','İşletme',38),
('Sigorta','gider','İşletme',39),
('Kargo & Nakliye','gider','İşletme',40),
('Danışmanlık / Hizmet','gider','İşletme',41),
('Banka Kredisi Taksiti','gider','Mali',50),
('Kredi Kartı Ödemesi','gider','Mali',51),
('Banka Faiz & Komisyon','gider','Mali',52),
('Diğer Gider','gider','Diğer',99),
-- Gelir kategorileri
('Satış Tahsilatı','gelir','Gelir',10),
('Hizmet Geliri','gelir','Gelir',11),
('Avans / Ön Ödeme','gelir','Gelir',12),
('Kira Geliri','gelir','Gelir',13),
('Faiz Geliri','gelir','Gelir',14),
('İade / Düzeltme','gelir','Gelir',15),
('Diğer Gelir','gelir','Gelir',99);
