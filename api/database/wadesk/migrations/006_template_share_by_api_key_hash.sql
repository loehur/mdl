-- Share synced templates across all WaDesk keys that use the same YCloud API credential.
-- Templates are owned by api_key_hash (fingerprint of plaintext API key), not a single team key row.

ALTER TABLE ycloud_keys
  ADD COLUMN api_key_hash CHAR(64) NULL AFTER api_key_enc,
  ADD INDEX idx_keys_hash (api_key_hash);

ALTER TABLE wa_templates
  ADD COLUMN api_key_hash CHAR(64) NULL AFTER ycloud_key_id,
  ADD INDEX idx_tpl_hash (api_key_hash);

-- Soften ownership FK: deleting one key must not wipe shared templates
ALTER TABLE wa_templates DROP FOREIGN KEY fk_tpl_key;
ALTER TABLE wa_templates MODIFY ycloud_key_id INT UNSIGNED NULL;
ALTER TABLE wa_templates
  ADD CONSTRAINT fk_tpl_key FOREIGN KEY (ycloud_key_id) REFERENCES ycloud_keys(id) ON DELETE SET NULL;

-- Unique per credential + name + language (NULLs ignored until backfilled)
ALTER TABLE wa_templates
  ADD UNIQUE KEY uq_tpl_hash_name_lang (api_key_hash, template_name, language);
