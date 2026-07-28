-- Migrasi: log reminder WA jatuh tempo invoice (DB: mdl_invoice)
-- Jalankan: mysql -u USER -p mdl_invoice < api/database/invoice/migrations/004_invoice_wa_reminders.sql

CREATE TABLE IF NOT EXISTS invoice_wa_reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  remind_date DATE NOT NULL,
  days_until_due INT NOT NULL,
  wa_message_id VARCHAR(100) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'sent',
  error_message TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invoice_remind_date (invoice_id, remind_date),
  INDEX idx_remind_date (remind_date)
);
