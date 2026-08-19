-- WaDesk: remove YCloud naming + store Kirimin API key per tenant
-- Run on mdl_wadesk (after 008/009 or fresh schema)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE tenants
  ADD COLUMN kirimin_api_key VARCHAR(255) NULL AFTER name;

-- Templates are tenant-wide; drop legacy channel link on template rows
ALTER TABLE wa_templates DROP FOREIGN KEY fk_tpl_key;
ALTER TABLE wa_templates DROP INDEX idx_tpl_key;
ALTER TABLE wa_templates DROP COLUMN ycloud_key_id;

ALTER TABLE conversations DROP FOREIGN KEY fk_conv_key;
ALTER TABLE conversations DROP INDEX uq_conv_key_phone;
ALTER TABLE conversations CHANGE COLUMN ycloud_key_id channel_id INT UNSIGNED NOT NULL;
ALTER TABLE conversations
  ADD UNIQUE KEY uq_conv_channel_phone (channel_id, phone),
  ADD CONSTRAINT fk_conv_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE;

ALTER TABLE wa_blasts DROP FOREIGN KEY fk_blast_key;
ALTER TABLE wa_blasts CHANGE COLUMN ycloud_key_id channel_id INT UNSIGNED NOT NULL;
ALTER TABLE wa_blasts
  ADD CONSTRAINT fk_blast_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE;

ALTER TABLE wa_key_daily_contacts DROP FOREIGN KEY fk_key_daily_key;
ALTER TABLE wa_key_daily_contacts DROP INDEX uq_key_contact_day;
ALTER TABLE wa_key_daily_contacts DROP INDEX idx_key_day;
ALTER TABLE wa_key_daily_contacts CHANGE COLUMN ycloud_key_id channel_id INT UNSIGNED NOT NULL;
ALTER TABLE wa_key_daily_contacts
  ADD UNIQUE KEY uq_channel_contact_day (channel_id, contact_date, phone),
  ADD INDEX idx_channel_day (channel_id, contact_date),
  ADD CONSTRAINT fk_channel_daily_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE;

ALTER TABLE messages DROP INDEX idx_msg_ycloud;
ALTER TABLE messages CHANGE COLUMN ycloud_msg_id provider_msg_id VARCHAR(128) NULL;
ALTER TABLE messages ADD INDEX idx_msg_provider (provider_msg_id);

SET FOREIGN_KEY_CHECKS = 1;
