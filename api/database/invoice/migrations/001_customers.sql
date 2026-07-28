-- Migrasi: master pelanggan untuk Invoice PWA (DB: mdl_invoice)
-- Jalankan: mysql -u root mdl_invoice < api/database/invoice/migrations/001_customers.sql

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  email VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_customers_user (user_id)
);

-- Tambah customer_id ke invoices (jalankan hanya jika tabel invoices sudah ada).
-- Jika kolom sudah ada, MySQL akan error — abaikan.
ALTER TABLE invoices
  ADD COLUMN customer_id INT NULL AFTER user_id;

ALTER TABLE invoices
  ADD INDEX idx_invoices_customer (customer_id);
