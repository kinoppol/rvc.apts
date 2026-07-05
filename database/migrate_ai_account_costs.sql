-- Idempotent: add monthly_cost and cost_per_slot to ai_accounts
-- Run once against rvc_apts (safe to re-run — checks column existence first)

ALTER TABLE ai_accounts
  ADD COLUMN IF NOT EXISTS monthly_cost  DECIMAL(10,2) NULL DEFAULT NULL AFTER password_reminder,
  ADD COLUMN IF NOT EXISTS cost_per_slot DECIMAL(10,2) NULL DEFAULT NULL AFTER monthly_cost;
