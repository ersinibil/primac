-- Migration: 039_user_notification_status
-- Sprint-001 (Bildirimler modülü): genel (target_user_id=NULL) bildirimler artık FİZİKSEL
-- silinmiyor — bir kullanıcının "sil"/"okundu" işlemi başka kullanıcıyı etkilemesin diye,
-- kullanıcı bazlı okunma/gizleme durumu bu AYRI tabloda tutulur. Kişisel (target_user_id dolu)
-- bildirimler bu tabloyu KULLANMAZ, mevcut internal_notifications.is_read yeterli (tek sahibi var).
--
-- İdempotentlik: CREATE TABLE IF NOT EXISTS kullanılır, tablo zaten varsa hiçbir şey yapmaz —
-- ikinci kez çalıştırıldığında hata üretmez. Bu migration mevcut hiçbir tabloya ALTER uygulamıyor
-- (yeni, bağımsız bir tablo), bu yüzden kolon/index çakışma riski yok.
--
-- Kapsam: sadece Development (primac.tr) ortamına uygulanır — Production (acanstr.com/ots)
-- ayrıca "DEPLOY MODE" komutuyla, DEV'de onaylandıktan sonra alır (bkz. PROJECT_RULES.md).
CREATE TABLE IF NOT EXISTS user_notification_status (
  id INT NOT NULL AUTO_INCREMENT,
  notification_id INT NOT NULL,
  user_id INT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notif_user (notification_id, user_id),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
