-- Link mutasi BCA ke entitas bisnis (kas laundry, dll.)
-- Satu mutasi hanya boleh terikat ke satu entitas.

USE `mdl_main`;

CREATE TABLE IF NOT EXISTS `bca_mutasi_link` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bca_mutasi_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(32) NOT NULL COMMENT 'mis. kas_laundry',
  `entity_ref` VARCHAR(64) NOT NULL COMMENT 'mis. ref_finance',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bca_mutasi` (`bca_mutasi_id`),
  UNIQUE KEY `uk_entity` (`entity_type`, `entity_ref`),
  KEY `idx_entity_ref` (`entity_ref`),
  CONSTRAINT `fk_bca_mutasi_link_mutasi`
    FOREIGN KEY (`bca_mutasi_id`) REFERENCES `bca_mutasi` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
