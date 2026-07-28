-- Cleanup WaDesk template params: body only (no header / button)
-- Run on mdl_wadesk

-- 1) Hapus param yang component-nya header atau button
DELETE FROM wa_template_params
WHERE component IN ('header', 'button');

-- 2) Pastikan semua sisa param bertipe body
UPDATE wa_template_params
SET component = 'body'
WHERE component <> 'body';

-- 3) (Opsional) Kunci ENUM hanya 'body' ke depan
-- Uncomment jika ingin enum dipersempit:
-- ALTER TABLE wa_template_params
--   MODIFY COLUMN component ENUM('body') NOT NULL DEFAULT 'body';
