-- Migration: 031_accounting_vat — Muhasebe kaydında KDV dahil/hariç desteği
-- amount kolonu HER ZAMAN gerçek toplam (KDV dahil, fiilen ödenen/tahsil edilen) tutarı tutar
-- (hesap bakiyesi mantığıyla tutarlı). vat_mode/vat_rate/vat_amount sadece bilgi/döküm amaçlı.
ALTER TABLE accounting_entries ADD COLUMN vat_mode ENUM('dahil','haric','yok') NOT NULL DEFAULT 'yok' AFTER amount;
ALTER TABLE accounting_entries ADD COLUMN vat_rate DECIMAL(5,2) NULL AFTER vat_mode;
ALTER TABLE accounting_entries ADD COLUMN vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER vat_rate;
