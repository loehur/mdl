-- QRIS lokal memakai ref_finance + status_mutasi + qris_nominal_reservations.
-- Jalankan setelah seluruh pending Tokopay/Midtrans selesai.
USE `mdl_laundry`;
ALTER TABLE `kas`
  DROP COLUMN `payment_gateway`,
  DROP COLUMN `payment_trx_id`,
  DROP COLUMN `payment_state`,
  DROP COLUMN `payment_created_at`;
