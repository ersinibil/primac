-- Migration: 032_finance_vat — Satış/Satın Alma hareketlerinde KDV izleme
-- finance_movements.amount HER ZAMAN gerçek toplam (KDV dahil, fiilen tahsil/ödeme) tutarını tutar.
-- vat_rate/vat_amount bilgi/rapor amaçlı — KDV Tahsil Edilen (satış) / KDV Ödenen (alış) ayrımı içindir.
ALTER TABLE finance_movements ADD COLUMN vat_rate DECIMAL(5,2) NULL AFTER amount;
ALTER TABLE finance_movements ADD COLUMN vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER vat_rate;
