-- Per-team customer phone masking. Disabled by default for existing teams.
SET NAMES utf8mb4;

ALTER TABLE teams
  ADD COLUMN mask_phone_numbers TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default;
