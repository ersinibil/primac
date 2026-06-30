-- Migration: 015_quote_firm — teklif firması (ACANS/PRIMAC)
ALTER TABLE quotes ADD COLUMN firm VARCHAR(20) DEFAULT NULL;
