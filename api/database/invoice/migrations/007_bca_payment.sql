-- BCA transfer: simpan nominal asli vs nominal unik transfer
USE mdl_invoice;

ALTER TABLE invoice_payments
  ADD COLUMN base_amount DECIMAL(15, 2) NULL DEFAULT NULL
    COMMENT 'Total tagihan asli sebelum unique nominal BCA'
    AFTER amount;

UPDATE invoice_payments
SET base_amount = amount
WHERE base_amount IS NULL;
