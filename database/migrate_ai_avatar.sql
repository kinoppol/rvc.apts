-- Add avatar_emoji column to ai_accounts for per-account icon customisation
ALTER TABLE ai_accounts ADD COLUMN IF NOT EXISTS avatar_emoji VARCHAR(8) NULL AFTER name;
