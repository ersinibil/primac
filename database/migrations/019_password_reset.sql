-- 019: Şifre sıfırlama token kolonları
ALTER TABLE app_users ADD COLUMN reset_token VARCHAR(80) NULL DEFAULT NULL;
ALTER TABLE app_users ADD COLUMN reset_expires DATETIME NULL DEFAULT NULL;
