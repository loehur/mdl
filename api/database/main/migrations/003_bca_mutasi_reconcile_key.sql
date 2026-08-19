-- reconcile_key: SHA256(keterangan|nominal|mutasi) — upgrade baris PEND saat BCA posting tanggal
-- Jalankan sekali di mdl_main

USE `mdl_main`;

ALTER TABLE `bca_mutasi`
  ADD COLUMN `reconcile_key` CHAR(64) NULL DEFAULT NULL
    COMMENT 'SHA256 keterangan|nominal|mutasi; match PEND→posted'
    AFTER `fingerprint`,
  ADD KEY `idx_reconcile_key` (`reconcile_key`);

-- Backfill baris existing (harus match format PHP reconcileKey())
UPDATE `bca_mutasi`
SET `reconcile_key` = SHA2(
  CONCAT(
    TRIM(`keterangan`), '|',
    CAST(`nominal` AS DECIMAL(18, 2)), '|',
    `mutasi`
  ),
  256
)
WHERE `reconcile_key` IS NULL OR `reconcile_key` = '';

-- Opsional: hapus PEND orphan jika sudah ada baris posted dengan reconcile_key sama (belum ter-link)
-- DELETE p
-- FROM `bca_mutasi` p
-- INNER JOIN `bca_mutasi` d
--   ON d.`reconcile_key` = p.`reconcile_key`
--  AND UPPER(d.`tanggal`) <> 'PEND'
-- LEFT JOIN `bca_mutasi_link` l ON l.`bca_mutasi_id` = p.`id`
-- WHERE UPPER(p.`tanggal`) = 'PEND'
--   AND l.`id` IS NULL;
