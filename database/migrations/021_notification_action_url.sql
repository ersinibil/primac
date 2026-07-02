-- Migration: 021_notification_action_url — bildirim tıklanınca doğru sayfaya gitsin
ALTER TABLE internal_notifications ADD COLUMN action_url VARCHAR(255) DEFAULT NULL;
