-- BCA transfer subscription salon
USE mdl_salon;

ALTER TABLE subscription_payments
  ADD COLUMN base_amount DECIMAL(15, 2) NULL DEFAULT NULL
    COMMENT 'Total tagihan asli sebelum unique nominal BCA'
    AFTER amount;

UPDATE subscription_payments
SET base_amount = amount
WHERE base_amount IS NULL;
