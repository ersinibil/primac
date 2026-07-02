-- Migration: 029_quote_approval_token — Teklif onay tokeni
ALTER TABLE quotes ADD COLUMN IF NOT EXISTS approval_token VARCHAR(64) NULL;
ALTER TABLE quotes ADD COLUMN IF NOT EXISTS approval_decision_at TIMESTAMP NULL;
CREATE INDEX IF NOT EXISTS idx_approval_token ON quotes(approval_token);
