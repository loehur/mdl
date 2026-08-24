-- Blokir entity agar tidak bisa di-bind ulang setelah unbind admin
-- Jalankan sekali di database mdl_main

USE `mdl_main`;

CREATE TABLE IF NOT EXISTS `bca_mutasi_link_block` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(32) NOT NULL,
  `entity_ref` VARCHAR(64) NOT NULL COMMENT 'kas: ref_finance; invoice: invoice_number INV-xxxx; salon: salon_id',
  `bca_mutasi_id` INT UNSIGNED NULL DEFAULT NULL,
  `link_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'bca_mutasi_link.id saat diblokir',
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `blocked_by` VARCHAR(64) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_entity_block` (`entity_type`, `entity_ref`),
  KEY `idx_mutasi` (`bca_mutasi_id`),
  KEY `idx_link` (`link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
