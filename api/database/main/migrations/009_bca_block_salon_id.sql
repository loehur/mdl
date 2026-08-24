-- Blokir salon: entity_ref = salon_id, bukan payment_ref SALONSUB_*
-- Jalankan backfill untuk baris lama yang masih SALONSUB_* (sesuaikan prefix DB jika perlu)

USE `mdl_main`;

UPDATE `bca_mutasi_link_block` b
INNER JOIN `mdl_salon`.`subscription_payments` p ON p.payment_ref = b.entity_ref
SET b.entity_ref = CAST(p.salon_id AS CHAR)
WHERE b.entity_type = 'salon_subscription'
  AND b.entity_ref LIKE 'SALONSUB\_%';

UPDATE `bca_mutasi_link_block` b
SET b.entity_ref = SUBSTRING_INDEX(SUBSTRING_INDEX(b.entity_ref, '_', 2), '_', -1)
WHERE b.entity_type = 'salon_subscription'
  AND b.entity_ref LIKE 'SALONSUB\_%'
  AND b.entity_ref REGEXP '^SALONSUB_[0-9]+_';
