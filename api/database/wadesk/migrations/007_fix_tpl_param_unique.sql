-- Fix UNIQUE constraint on wa_template_params to include component column.
-- Old constraint (template_id, param_index) blocks header+body with same param_index.
-- Also ensure ENUM allows header/body/button.

-- Prefer running via: GET /WaDesk/Templates/runMigration007 (admin)

ALTER TABLE wa_template_params
  MODIFY COLUMN component ENUM('header','body','button') NOT NULL DEFAULT 'body';

-- Rename old unique then add correct one (if rename fails, use SET FOREIGN_KEY_CHECKS=0)
ALTER TABLE wa_template_params
  RENAME INDEX uq_tpl_param TO uq_tpl_param_old,
  ADD UNIQUE KEY uq_tpl_param (template_id, component, param_index);

ALTER TABLE wa_template_params DROP INDEX uq_tpl_param_old;
