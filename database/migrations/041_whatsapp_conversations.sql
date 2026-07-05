-- Migration: 041_whatsapp_conversations
-- WhatsApp konuşma geçmişi altyapısı (kullanıcı isteği 2026-07-05). Sadece kişi/cari iletişimi
-- amaçlı gönderimler (wa_send_now.php, "sender scope" allowlist ile share_lib.php'de kontrol
-- edilir) bu tabloya yazılır — OTP/sistem mesajları (sifre_sifirla.php, users.php giriş bilgisi,
-- daily_reminder_lib.php) kapsam dışı, mevcut logsuz davranışlarında kalır.
CREATE TABLE IF NOT EXISTS `wa_conversations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone` varchar(32) NOT NULL COMMENT '_wa_normalize_phone() ile normalize edilmiş (90XXXXXXXXXX)',
  `contact_id` int DEFAULT NULL COMMENT 'contacts.id — telefon eşleşirse doldurulur, yoksa NULL (sadece telefonla konuşma)',
  `last_message_at` datetime DEFAULT NULL,
  `last_message_preview` varchar(255) DEFAULT NULL,
  `last_direction` varchar(10) DEFAULT NULL COMMENT 'outbound | inbound',
  `unread_count` int NOT NULL DEFAULT 0 COMMENT 'okunmamış inbound mesaj sayısı, konuşma açılınca 0''a döner',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_phone` (`phone`),
  KEY `idx_contact` (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wa_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversation_id` int NOT NULL,
  `direction` varchar(10) NOT NULL COMMENT 'outbound | inbound',
  `source` varchar(40) DEFAULT NULL COMMENT 'gönderen modül etiketi — ör. wa_send_now, ileride yeni kaynaklar tek satır allowlist ile eklenir',
  `body` text,
  `media_url` varchar(500) DEFAULT NULL,
  `media_type` varchar(30) DEFAULT NULL,
  `provider_message_id` varchar(120) DEFAULT NULL COMMENT 'UltraMsg mesaj id''si — webhook eşleştirme/tekrar-yazma önleme',
  `status` varchar(30) DEFAULT NULL COMMENT 'gönderim/teslim durumu (ack vb.)',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_provider_msg` (`provider_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
