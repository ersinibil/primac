-- Migration: 022_cleanup_orphan_messages
-- daily_reminder_lib.php eskiden sender_user_id=NULL ile "sistem mesajı" internal_messages'a
-- yazıyordu; bu satırlar Mesajlar ekranının kişi listesi sorgusuyla hiç eşleşmiyordu (hayalet
-- okunmamış mesaj rozeti üretiyordu, açılacak gerçek bir konuşma yoktu). Kod tarafı 2026-07-02'de
-- düzeltildi (artık böyle satır yazılmıyor); bu migration daha önce oluşmuş eski hayalet satırları
-- temizler.
DELETE FROM internal_messages WHERE sender_user_id IS NULL;
