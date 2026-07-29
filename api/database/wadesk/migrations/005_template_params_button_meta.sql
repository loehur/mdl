-- Store Meta/YCloud button metadata needed when sending templates.
-- Without sub_type + index, YCloud rejects button components after sync.

ALTER TABLE wa_template_params
  ADD COLUMN button_sub_type VARCHAR(32) NULL AFTER component,
  ADD COLUMN button_index INT UNSIGNED NULL AFTER button_sub_type;
