-- Link transaksi QRIS merchant BCA ke entitas bisnis (kas laundry, dll.)
-- Satu transaksi QRIS hanya boleh terikat ke satu entitas.

USE `mdl_main`;

CREATE TABLE IF NOT EXISTS `bca_qris_link` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bca_qris_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(32) NOT NULL COMMENT 'mis. kas_laundry',
  `entity_ref` VARCHAR(64) NOT NULL COMMENT 'mis. ref_finance',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bca_qris` (`bca_qris_id`),
  UNIQUE KEY `uk_entity` (`entity_type`, `entity_ref`),
  KEY `idx_entity_ref` (`entity_ref`),
  CONSTRAINT `fk_bca_qris_link_transaksi`
    FOREIGN KEY (`bca_qris_id`) REFERENCES `bca_qris_transaksi` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
