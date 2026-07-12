-- Migration: 044_user_preferences
-- WEB UI ALIGNMENT & NAVIGATION SPRINT 001 — Faz A: kullanıcı bazlı Komuta Merkezi kart sırası.
-- Genel amaçlı key/value tablo (ileride başka tercihlere de açık olacak şekilde tasarlandı), ama
-- bu sprintte SADECE 'dashboard_tile_order' anahtarı kullanılıyor — ek tercih alanı/özelliği
-- eklenmedi (kapsam disiplini, kullanıcı kararı).

CREATE TABLE IF NOT EXISTS user_preferences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  pref_key VARCHAR(64) NOT NULL,
  pref_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_pref (user_id, pref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
