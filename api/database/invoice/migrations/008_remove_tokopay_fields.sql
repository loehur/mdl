-- Jalankan setelah memastikan tidak ada pembayaran gateway lama yang pending.
USE `mdl_invoice`;
ALTER TABLE `invoice_payments` DROP COLUMN `trx_id`;
