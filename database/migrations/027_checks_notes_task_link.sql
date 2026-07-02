-- Migration: 027_checks_notes_task_link
-- Her çek/senet kaydı oluşturulduğunda (vade tarihi girilmişse) muhasebe/yönetim iş ekranına
-- (tasks tablosu) otomatik bir hatırlatma görevi düşer. Bu kolon, hangi görevin hangi çek/senede
-- ait olduğunu tutar — durum "Tahsil Edildi/Ciro Edildi/İptal" olunca ilgili görevi otomatik
-- tamamlanmış işaretlemek için kullanılır (checks_notes_lib.php).

ALTER TABLE checks_notes ADD COLUMN task_id INT NULL COMMENT 'tasks.id — otomatik oluşturulan vade hatırlatma görevi' AFTER attachment;
