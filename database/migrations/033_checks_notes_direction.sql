-- Migration: 033_checks_notes_direction
-- Çek/Senet kaydına Yön (Alınan/Verilen) alanı ekler. Eskiden "Portföyde" durumu hem bizim
-- verdiğimiz hem bizden alınan çekler için aynı anlama geliyordu, kafa karıştırıyordu.
-- Mevcut kayıtlar varsayılan 'alinan' (tahsilat) olarak işaretlenir — status alanına dokunulmaz,
-- sadece direction'a göre arayüzde farklı etiketlerle gösterilir (checks_notes_lib.php).

ALTER TABLE checks_notes
  ADD COLUMN direction ENUM('alinan','verilen') NOT NULL DEFAULT 'alinan' AFTER type;
