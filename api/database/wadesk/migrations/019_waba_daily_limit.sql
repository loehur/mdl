-- Daily unique limit: per WABA ID (bukan per tenant)
-- Default limit di tenants.daily_unique_limit; override per WABA di wa_waba_daily_limits

SET NAMES utf8mb4;

ALTER TABLE wa_channels
  ADD COLUMN waba_id VARCHAR(64) NULL AFTER device_id,
  ADD INDEX idx_channels_waba (waba_id);

CREATE TABLE IF NOT EXISTS wa_waba_daily_limits (
  waba_id VARCHAR(64) NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  daily_unique_limit INT UNSIGNED NOT NULL DEFAULT 250,
  label VARCHAR(150) NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (waba_id),
  INDEX idx_waba_limit_tenant (tenant_id),
  CONSTRAINT fk_waba_limit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE wa_key_daily_contacts
  ADD COLUMN waba_id VARCHAR(64) NULL AFTER tenant_id;

-- Backfill waba_id dari conversation + channel (nomor yang pernah chat)
UPDATE wa_key_daily_contacts w
INNER JOIN (
  SELECT wdc.id,
    (
      SELECT ch.waba_id
      FROM conversations c
      INNER JOIN wa_channels ch ON ch.id = c.channel_id
      WHERE c.tenant_id = wdc.tenant_id
        AND c.phone = wdc.phone
        AND ch.waba_id IS NOT NULL
        AND TRIM(ch.waba_id) <> ''
      ORDER BY COALESCE(c.last_out_at, c.last_message_at, c.updated_at) DESC, c.id DESC
      LIMIT 1
    ) AS mapped_waba
  FROM wa_key_daily_contacts wdc
) x ON x.id = w.id AND x.mapped_waba IS NOT NULL AND TRIM(x.mapped_waba) <> ''
SET w.waba_id = TRIM(x.mapped_waba)
WHERE w.waba_id IS NULL OR TRIM(w.waba_id) = '';

-- Tenant dengan satu WABA saja: sisa baris ikut WABA itu
UPDATE wa_key_daily_contacts w
INNER JOIN (
  SELECT c.tenant_id, MIN(TRIM(c.waba_id)) AS waba_id
  FROM wa_channels c
  WHERE c.waba_id IS NOT NULL AND TRIM(c.waba_id) <> ''
  GROUP BY c.tenant_id
  HAVING COUNT(DISTINCT TRIM(c.waba_id)) = 1
) s ON s.tenant_id = w.tenant_id
SET w.waba_id = s.waba_id
WHERE w.waba_id IS NULL OR TRIM(w.waba_id) = '';

-- Seed limit row per WABA dari tenant default
INSERT INTO wa_waba_daily_limits (waba_id, tenant_id, daily_unique_limit, label)
SELECT DISTINCT TRIM(c.waba_id), c.tenant_id, t.daily_unique_limit, MIN(c.label)
FROM wa_channels c
INNER JOIN tenants t ON t.id = c.tenant_id
WHERE c.waba_id IS NOT NULL AND TRIM(c.waba_id) <> ''
GROUP BY TRIM(c.waba_id), c.tenant_id, t.daily_unique_limit
ON DUPLICATE KEY UPDATE
  tenant_id = VALUES(tenant_id),
  label = COALESCE(wa_waba_daily_limits.label, VALUES(label));

-- Dedupe setelah waba_id terisi
DELETE w1 FROM wa_key_daily_contacts w1
INNER JOIN wa_key_daily_contacts w2
  ON w1.waba_id = w2.waba_id
 AND w1.contact_date = w2.contact_date
 AND w1.phone = w2.phone
 AND w1.id > w2.id
WHERE w1.waba_id IS NOT NULL AND TRIM(w1.waba_id) <> '';

-- Baris tenant-level lama yang tidak terpetakan ke WABA (tidak dipakai lagi)
DELETE FROM wa_key_daily_contacts
WHERE waba_id IS NULL OR TRIM(waba_id) = '';

ALTER TABLE wa_key_daily_contacts
  MODIFY waba_id VARCHAR(64) NOT NULL;

-- FK tenant_id terikat ke uq_tenant_contact_day — drop FK dulu baru ganti index
ALTER TABLE wa_key_daily_contacts
  DROP FOREIGN KEY fk_tenant_daily_tenant;

ALTER TABLE wa_key_daily_contacts
  DROP INDEX uq_tenant_contact_day,
  DROP INDEX idx_tenant_day,
  DROP INDEX idx_tenant_day_status;

ALTER TABLE wa_key_daily_contacts
  ADD UNIQUE KEY uq_waba_contact_day (waba_id, contact_date, phone),
  ADD INDEX idx_waba_day (waba_id, contact_date),
  ADD INDEX idx_waba_day_status (waba_id, contact_date, status),
  ADD INDEX idx_tenant_day (tenant_id, contact_date);

ALTER TABLE wa_key_daily_contacts
  ADD CONSTRAINT fk_tenant_daily_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
