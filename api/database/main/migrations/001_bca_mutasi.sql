-- Mutasi rekening BCA (sync dari bca_scrapper)
-- Jalankan sekali di database mdl_main

USE `mdl_main`;

CREATE TABLE IF NOT EXISTS `bca_mutasi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal` VARCHAR(16) NOT NULL COMMENT 'Format asli BCA: DD/MM/YYYY atau PEND',
  `tanggal_iso` DATE NULL DEFAULT NULL COMMENT 'Tanggal ter-parse untuk query range; PEND = NULL',
  `keterangan` TEXT NOT NULL,
  `nominal` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
  `mutasi` CHAR(2) NOT NULL COMMENT 'CR atau DB',
  `fingerprint` CHAR(64) NOT NULL COMMENT 'SHA256 dedup: tanggal|keterangan|nominal|mutasi',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fingerprint` (`fingerprint`),
  KEY `idx_tanggal_iso` (`tanggal_iso`),
  KEY `idx_mutasi` (`mutasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
