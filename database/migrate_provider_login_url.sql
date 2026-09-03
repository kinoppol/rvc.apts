-- Give each AI type a login page, so the credential card can hand the student a
-- "open the login page" button next to the email and password it just revealed.
-- Stored per type rather than derived from the name, because admins name types freely.
ALTER TABLE ai_providers ADD COLUMN IF NOT EXISTS login_url VARCHAR(255) NULL AFTER name;

-- Sensible defaults for the well-known services, matched loosely on the type name.
-- Only fills rows that have no URL yet, so re-running never overwrites an admin's edit.
UPDATE ai_providers SET login_url = 'https://claude.ai/login'
 WHERE login_url IS NULL AND (name LIKE '%Claude%' OR name LIKE '%claude%');

UPDATE ai_providers SET login_url = 'https://chatgpt.com/'
 WHERE login_url IS NULL AND (name LIKE '%ChatGPT%' OR name LIKE '%chatgpt%' OR name LIKE '%GPT%' OR name LIKE '%OpenAI%');

UPDATE ai_providers SET login_url = 'https://gemini.google.com/app'
 WHERE login_url IS NULL AND (name LIKE '%Gemini%' OR name LIKE '%gemini%');

UPDATE ai_providers SET login_url = 'https://copilot.microsoft.com/'
 WHERE login_url IS NULL AND (name LIKE '%Copilot%' OR name LIKE '%copilot%');

UPDATE ai_providers SET login_url = 'https://www.perplexity.ai/'
 WHERE login_url IS NULL AND (name LIKE '%Perplexity%' OR name LIKE '%perplexity%');

UPDATE ai_providers SET login_url = 'https://grok.com/'
 WHERE login_url IS NULL AND (name LIKE '%Grok%' OR name LIKE '%grok%');
