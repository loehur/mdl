ALTER TABLE wa_channels
  ADD COLUMN meta_platform_type VARCHAR(32) NULL AFTER meta_quality_rating,
  ADD COLUMN is_coexistence TINYINT(1) NOT NULL DEFAULT 0 AFTER meta_platform_type;
