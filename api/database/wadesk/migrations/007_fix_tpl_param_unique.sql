-- Fix UNIQUE constraint on wa_template_params to include component column.
-- The old constraint (template_id, param_index) blocks header+body having same param_index.
-- New constraint (template_id, component, param_index) allows header:1 and body:1 to coexist.

-- Drop old constraint (name may vary — try both common names)
ALTER TABLE wa_template_params DROP INDEX IF EXISTS uq_tpl_param;
ALTER TABLE wa_template_params DROP INDEX IF EXISTS uq_template_param;

-- Recreate with component included
ALTER TABLE wa_template_params
  ADD UNIQUE KEY uq_tpl_param (template_id, component, param_index);
