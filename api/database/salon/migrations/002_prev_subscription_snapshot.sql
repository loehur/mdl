-- Snapshot subscription sebelum konfirmasi BCA (untuk unbind/revert)
-- Jalankan sekali di database mdl_salon

USE `mdl_salon`;

ALTER TABLE `subscription_payments`
  ADD COLUMN `prev_subscription_json` TEXT NULL DEFAULT NULL
    COMMENT 'Snapshot subscriptions + salon sebelum activatePayment BCA'
    AFTER `notes`;
