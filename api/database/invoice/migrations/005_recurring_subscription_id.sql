-- Migrasi: subscription_id pada recurring_bills (DB: mdl_invoice)
-- Jalankan: mysql -u USER -p mdl_invoice < api/database/invoice/migrations/005_recurring_subscription_id.sql

ALTER TABLE recurring_bills
  ADD COLUMN subscription_id VARCHAR(64) NULL AFTER id;

ALTER TABLE recurring_bills
  ADD UNIQUE KEY uq_recurring_user_subscription (user_id, subscription_id);
