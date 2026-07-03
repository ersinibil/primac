-- Migration: 034_checks_notes_finance_link
-- Çek/Senet kaydı artık gerçek bir finans hareketi (cari bakiyeyi etkileyen) oluşturur/günceller/siler.
-- Alınan çek/senet = Tahsilat (direction='in'), Verilen çek/senet = Ödeme (direction='out') —
-- finance_new.php'deki Tahsilat/Ödeme ekranıyla BİREBİR aynı mantık, sadece payment_channel='Çek'/'Senet'.
-- Hesap (banka/kasa) bakiyesi GÜNCELLENMEZ (çek henüz tahsil/ödenmemişse fiziken bir hesapta değildir,
-- Veresiye satın almadaki mevcut davranışla tutarlı) — sadece cari bakiyeyi etkiler.

ALTER TABLE checks_notes
  ADD COLUMN finance_movement_id INT NULL AFTER task_id;
