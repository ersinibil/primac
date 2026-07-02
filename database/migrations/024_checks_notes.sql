-- Migration: 024_checks_notes
-- Çek / Senet takip modülü: her çek/senet için ayrı bir kayıt kartı (tür, numara, tutar,
-- vade tarihi, opsiyonel cari/banka, durum, not). Ödeme yöntemi olarak "Çek"/"Senet" seçenekleri
-- finance_movements.payment_channel / accounting_entries.payment_type alanlarına ayrıca eklendi
-- (bu tablo ondan bağımsız — sadece takip kartı istenirse kullanılır, zorunlu değil).

CREATE TABLE IF NOT EXISTS checks_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('cek','senet') NOT NULL DEFAULT 'cek',
  number VARCHAR(80) NULL COMMENT 'Çek/senet numarası',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_date DATE NULL COMMENT 'Vade tarihi',
  contact_id INT NULL COMMENT 'contacts.id — kimden alındı / kime verildi, opsiyonel',
  bank_name VARCHAR(120) NULL COMMENT 'Çek ise banka adı',
  status ENUM('portfoyde','tahsil_edildi','ciro_edildi','karsiliksiz','iptal') NOT NULL DEFAULT 'portfoyde',
  notes VARCHAR(500) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
