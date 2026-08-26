-- Daily unique limit: kembali per tenant (tenants.daily_unique_limit), bukan per WABA.

SET NAMES utf8mb4;

-- Gabungkan hitungan: satu nomor per tenant per hari (meski dikirim dari WABA berbeda).
DELETE w1 FROM wa_key_daily_contacts w1
INNER JOIN wa_key_daily_contacts w2
  ON w1.tenant_id = w2.tenant_id
 AND w1.contact_date = w2.contact_date
 AND w1.phone = w2.phone
 AND w1.id > w2.id;

ALTER TABLE wa_key_daily_contacts
  DROP FOREIGN KEY fk_tenant_daily_tenant;

ALTER TABLE wa_key_daily_contacts
  DROP INDEX uq_waba_contact_day,
  DROP INDEX idx_waba_day,
  DROP INDEX idx_waba_day_status;

-- Kolom waba_id tidak dipakai lagi untuk quota harian.
ALTER TABLE wa_key_daily_contacts
  DROP COLUMN waba_id;

ALTER TABLE wa_key_daily_contacts
  ADD UNIQUE KEY uq_tenant_contact_day (tenant_id, contact_date, phone),
  ADD INDEX idx_tenant_day_status (tenant_id, contact_date, status);

ALTER TABLE wa_key_daily_contacts
  ADD CONSTRAINT fk_tenant_daily_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
