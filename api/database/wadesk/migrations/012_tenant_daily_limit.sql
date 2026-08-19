-- Limit harian nomor unik: per tenant (bukan per channel), nilai di tenants.daily_unique_limit

ALTER TABLE tenants
  ADD COLUMN daily_unique_limit INT UNSIGNED NOT NULL DEFAULT 250
  AFTER kirimin_api_key;

ALTER TABLE wa_key_daily_contacts
  ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id;

UPDATE wa_key_daily_contacts w
INNER JOIN wa_channels c ON c.id = w.channel_id
SET w.tenant_id = c.tenant_id
WHERE w.tenant_id IS NULL;

DELETE w1 FROM wa_key_daily_contacts w1
INNER JOIN wa_key_daily_contacts w2
  ON w1.tenant_id = w2.tenant_id
 AND w1.contact_date = w2.contact_date
 AND w1.phone = w2.phone
 AND w1.id > w2.id;

ALTER TABLE wa_key_daily_contacts
  MODIFY tenant_id INT UNSIGNED NOT NULL;

ALTER TABLE wa_key_daily_contacts
  DROP FOREIGN KEY fk_channel_daily_channel;

ALTER TABLE wa_key_daily_contacts
  DROP INDEX uq_channel_contact_day,
  DROP INDEX idx_channel_day,
  DROP COLUMN channel_id;

ALTER TABLE wa_key_daily_contacts
  ADD UNIQUE KEY uq_tenant_contact_day (tenant_id, contact_date, phone),
  ADD INDEX idx_tenant_day (tenant_id, contact_date),
  ADD CONSTRAINT fk_tenant_daily_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
