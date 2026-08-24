-- Blokir invoice: entity_ref = invoice_number (INV-xxxx), bukan payment_ref MDLINV_*
-- Jalankan backfill untuk baris lama yang masih MDLINV_* (sesuaikan prefix DB jika perlu)

USE `mdl_main`;

-- Backfill: payment_ref MDLINV_{invoice_id}_* -> invoices.invoice_number
UPDATE `bca_mutasi_link_block` b
INNER JOIN `mdl_invoice`.`invoice_payments` p ON p.payment_ref = b.entity_ref
INNER JOIN `mdl_invoice`.`invoices` i ON i.id = p.invoice_id
SET b.entity_ref = i.invoice_number
WHERE b.entity_type = 'invoice'
  AND b.entity_ref LIKE 'MDLINV\_%';

-- Fallback jika payment row sudah dihapus tapi pola MDLINV_{id}_ masih ada
UPDATE `bca_mutasi_link_block` b
INNER JOIN `mdl_invoice`.`invoices` i
  ON i.id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(b.entity_ref, '_', 2), '_', -1) AS UNSIGNED)
SET b.entity_ref = i.invoice_number
WHERE b.entity_type = 'invoice'
  AND b.entity_ref LIKE 'MDLINV\_%'
  AND b.entity_ref REGEXP '^MDLINV_[0-9]+_';
