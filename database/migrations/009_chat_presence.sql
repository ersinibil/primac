-- Migration: 009_chat_presence
-- Mesajlaşma: çevrimiçi/son görülme + "yazıyor..." göstergesi

-- Kullanıcı son görülme zamanı (her poll'da güncellenir)
ALTER TABLE app_users ADD COLUMN last_seen DATETIME NULL;

-- "yazıyor..." sinyali: kim kime, ne zaman yazıyor
CREATE TABLE IF NOT EXISTS chat_typing (
  from_id INT NOT NULL,
  to_id INT NOT NULL,
  at DATETIME NOT NULL,
  PRIMARY KEY (from_id, to_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
