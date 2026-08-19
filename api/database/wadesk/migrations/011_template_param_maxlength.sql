-- WaDesk: maxlength per template param (default 20 chars)

SET NAMES utf8mb4;

ALTER TABLE wa_template_params
  ADD COLUMN maxlength SMALLINT UNSIGNED NOT NULL DEFAULT 20 AFTER is_required;

UPDATE wa_template_params SET maxlength = 20 WHERE maxlength IS NULL OR maxlength = 0;
