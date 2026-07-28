-- Migrasi: tagihan berulang (DB: mdl_invoice)
-- Jalankan: mysql -u USER -p mdl_invoice < api/database/invoice/migrations/003_recurring_bills.sql

CREATE TABLE IF NOT EXISTS recurring_bills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  customer_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  items_json JSON NOT NULL,
  period VARCHAR(20) NOT NULL,
  next_issue_date DATE NOT NULL,
  due_days INT NULL,
  source_invoice_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_recurring_user (user_id),
  INDEX idx_recurring_next (is_active, next_issue_date),
  INDEX idx_recurring_customer (customer_id)
);

ALTER TABLE invoices
  ADD COLUMN recurring_bill_id INT NULL AFTER customer_id;

ALTER TABLE invoices
  ADD INDEX idx_invoices_recurring (recurring_bill_id);
