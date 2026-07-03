-- Migration: 038_cleanup_orphan_messages2
-- 022'deki hayalet mesaj temizliğinin tekrarı: sender_user_id=NULL olan internal_messages
-- satırları "okunmamış mesaj rozeti var ama açılacak konuşma yok" hissi yaratıyordu (2026-07-03
-- kullanıcı bildirimi, PRIMAC'ta gözlemlendi). Kod tarafında hem rozet sorguları hem de mesajlar
-- ekranı kişi listesi artık bu satırları görmezden geliyor/hiç oluşturmuyor; bu migration birikmiş
-- olabilecek satırları temizler.
DELETE FROM internal_messages WHERE sender_user_id IS NULL;
