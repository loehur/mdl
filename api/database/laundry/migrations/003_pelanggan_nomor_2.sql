-- mdl_laundry.pelanggan: nomor WA alternatif
-- Jalankan di VPS setelah backup. Verifikasi query di bawah sebelum lanjut.

USE mdl_laundry;

ALTER TABLE pelanggan
  ADD COLUMN nomor_pelanggan_2 VARCHAR(30) NULL DEFAULT NULL AFTER nomor_pelanggan;

-- Verifikasi:
-- SHOW COLUMNS FROM pelanggan LIKE 'nomor_pelanggan%';