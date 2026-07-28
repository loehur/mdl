-- Migrasi: judul invoice (DB: mdl_invoice)
-- Jalankan: mysql -u USER -p mdl_invoice < api/database/invoice/migrations/002_invoice_title.sql

ALTER TABLE invoices
  ADD COLUMN title VARCHAR(255) NULL AFTER customer_phone;
