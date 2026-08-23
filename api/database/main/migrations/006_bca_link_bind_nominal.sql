-- Audit nominal saat bind: bill (kas laundry) vs mutasi/QRIS yang di-link
-- Jalankan sekali di database mdl_main

USE `mdl_main`;

ALTER TABLE `bca_mutasi_link`
  ADD COLUMN `bill_nominal` DECIMAL(18, 2) NULL DEFAULT NULL
    COMMENT 'Nominal tagihan/kas laundry saat bind' AFTER `entity_ref`,
  ADD COLUMN `bind_nominal` DECIMAL(18, 2) NULL DEFAULT NULL
    COMMENT 'Nominal mutasi BCA saat bind (snapshot)' AFTER `bill_nominal`;

ALTER TABLE `bca_qris_link`
  ADD COLUMN `bill_nominal` DECIMAL(18, 2) NULL DEFAULT NULL
    COMMENT 'Nominal tagihan/kas laundry saat bind' AFTER `entity_ref`,
  ADD COLUMN `bind_nominal` DECIMAL(18, 2) NULL DEFAULT NULL
    COMMENT 'Nominal transaksi QRIS saat bind (snapshot)' AFTER `bill_nominal`;

-- Backfill bind_nominal dari sumber (bill_nominal historis tidak tersedia)
UPDATE `bca_mutasi_link` l
INNER JOIN `bca_mutasi` m ON m.id = l.bca_mutasi_id
SET l.bind_nominal = m.nominal
WHERE l.bind_nominal IS NULL;

UPDATE `bca_qris_link` l
INNER JOIN `bca_qris_transaksi` t ON t.id = l.bca_qris_id
SET l.bind_nominal = t.nominal
WHERE l.bind_nominal IS NULL;
