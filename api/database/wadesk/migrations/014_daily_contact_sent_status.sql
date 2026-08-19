-- Daily contact hanya dihitung jika minimal status sent (gagal tidak dihitung)

ALTER TABLE wa_key_daily_contacts
  ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'sent'
  AFTER phone;

UPDATE wa_key_daily_contacts SET status = 'sent' WHERE status = '' OR status IS NULL;

ALTER TABLE wa_key_daily_contacts
  ADD INDEX idx_tenant_day_status (tenant_id, contact_date, status);
