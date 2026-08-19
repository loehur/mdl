-- WaDesk: YCloud per-key credentials → Kirimin global config + wa_channels mapping
-- Run on mdl_wadesk after backup. Re-assign devices to teams via Admin UI after migration.

SET NAMES utf8mb4;

-- Rename keys table to channels (FK references follow the rename in MySQL/InnoDB)
RENAME TABLE ycloud_keys TO wa_channels;

ALTER TABLE wa_channels
  ADD COLUMN device_id VARCHAR(64) NULL AFTER label,
  ADD COLUMN channel_type ENUM('waba','device') NOT NULL DEFAULT 'waba' AFTER device_id;

-- Drop per-team encrypted YCloud credentials (now in Env.php)
ALTER TABLE wa_channels
  DROP COLUMN api_key_enc,
  DROP COLUMN api_key_hash,
  DROP COLUMN ycloud_phone_id;

-- One team = one channel; one device = one team
ALTER TABLE wa_channels
  ADD UNIQUE KEY uq_channel_team (team_id),
  ADD UNIQUE KEY uq_channel_device (device_id);

-- Templates: tenant-wide (all teams share synced templates)
ALTER TABLE wa_templates
  ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id;

UPDATE wa_templates t
INNER JOIN wa_channels c ON c.id = t.ycloud_key_id
SET t.tenant_id = c.tenant_id
WHERE t.tenant_id IS NULL;

-- Drop old hash-based sharing
ALTER TABLE wa_templates DROP INDEX uq_tpl_hash_name_lang;

ALTER TABLE wa_templates
  DROP COLUMN api_key_hash;

ALTER TABLE wa_templates
  MODIFY ycloud_key_id INT UNSIGNED NULL;

ALTER TABLE wa_templates
  ADD UNIQUE KEY uq_tpl_tenant_name_lang (tenant_id, template_name, language);
