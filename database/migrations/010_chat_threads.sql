-- Migration: 010_chat_threads
-- Grup + işe/cariye bağlı sohbet. Mevcut 1-1 internal_messages BOZULMAZ.

-- Konu (thread): grup | iş | cari
CREATE TABLE IF NOT EXISTS chat_threads (
  id INT NOT NULL AUTO_INCREMENT,
  type VARCHAR(20) NOT NULL DEFAULT 'group',   -- group | job | cari
  title VARCHAR(160) NOT NULL,
  ref_id INT DEFAULT NULL,                      -- job_id veya contact_id (job/cari için)
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_type_ref (type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Konu üyeleri + okundu takibi
CREATE TABLE IF NOT EXISTS chat_thread_members (
  thread_id INT NOT NULL,
  user_id INT NOT NULL,
  last_read_id INT NOT NULL DEFAULT 0,
  PRIMARY KEY (thread_id, user_id),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mevcut mesaj tablosuna thread_id (1-1 mesajlarda NULL kalır)
ALTER TABLE internal_messages ADD COLUMN thread_id INT NULL;
