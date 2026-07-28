-- Migration: named template params + component (header/body/button)
-- Run on mdl_wadesk if columns belum ada

ALTER TABLE wa_template_params
  ADD COLUMN component ENUM('header','body','button') NOT NULL DEFAULT 'body' AFTER template_id,
  ADD COLUMN param_name VARCHAR(64) NULL AFTER param_index;

-- Optional: backfill param_name from index for existing rows
UPDATE wa_template_params
SET param_name = CONCAT('p', param_index)
WHERE param_name IS NULL OR param_name = '';
