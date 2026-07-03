-- Migration: 028_audit_log
-- Değişmez (immutable) denetim günlüğü tablosu.
-- Kim-ne-zaman-neyi-nasıl-değiştirdi (eski→yeni değer) kaydını tutar.
-- Sistem kritik finansal işlemler (hesap/hareket düzenle-sil, muhasebe gider-gelir güncelleme) üzerinde.

CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL COMMENT 'app_users.id — işlemi yapan kişi',
  action VARCHAR(20) NOT NULL COMMENT 'create/update/delete',
  table_name VARCHAR(80) NOT NULL COMMENT 'etkilenen tablo adı',
  record_id INT NULL COMMENT 'etkilenen satırın PK',
  old_value LONGTEXT NULL COMMENT 'güncelleme/silme öncesi JSON — null=yeni kayıt',
  new_value LONGTEXT NULL COMMENT 'güncelleme/yeni kayıt sonrası JSON — null=silme',
  ip_address VARCHAR(45) NULL COMMENT 'İstemci IP adresi (IPv4/IPv6)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'işlem zamanı (sunucu saati)',
  KEY idx_user_action (user_id, action),
  KEY idx_table_record (table_name, record_id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
