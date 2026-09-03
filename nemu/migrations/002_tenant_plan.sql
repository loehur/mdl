ALTER TABLE tenants ADD COLUMN IF NOT EXISTS plan text NOT NULL DEFAULT 'free' CHECK (plan IN ('free', 'personal', 'pro'));
