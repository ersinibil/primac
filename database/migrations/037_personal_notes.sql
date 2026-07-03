-- Migration: 037_personal_notes
-- Kişisel görev/not alanı — kullanıcı isteği: "görevlerim ekranında kendime de görev-not alanı
-- olsun, bunu personel görmesin, takvime de işlensin, bana kendi numarama ve sistem içi mesaj
-- ile bildirim olsun". Bilerek `tasks` tablosundan AYRI: tasks.php ("Tüm Görevler") personel
-- tarafından görülebiliyor, bu tabloya hiçbir sorgu dışarıdan (user_id filtresi olmadan)
-- dokunmuyor — gizlilik, ayrı tablo ile garanti altına alınıyor.

CREATE TABLE IF NOT EXISTS `personal_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'app_users.id — sadece bu kullanıcı görür',
  `title` varchar(220) NOT NULL,
  `note` text,
  `due_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Açık',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
