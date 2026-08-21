-- Transaksi QRIS merchant BCA (sync dari bca_scrapper / QRMS)
-- Jalankan sekali di database mdl_main

USE `mdl_main`;

CREATE TABLE IF NOT EXISTS `bca_qris_hari` (
  `tanggal` DATE NOT NULL COMMENT 'Hari sudah di-sync (termasuk 0 transaksi)',
  `tx_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bca_qris_transaksi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal` DATE NOT NULL,
  `waktu` VARCHAR(16) NULL DEFAULT NULL COMMENT 'HH:MM atau HH:MM:SS',
  `rrn` VARCHAR(64) NOT NULL,
  `nominal` DECIMAL(18, 2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(32) NULL DEFAULT NULL,
  `keterangan` TEXT NULL,
  `mid` VARCHAR(32) NULL DEFAULT NULL,
  `outlet_name` VARCHAR(128) NULL DEFAULT NULL,
  `fingerprint` CHAR(64) NOT NULL COMMENT 'SHA256 dedup',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fingerprint` (`fingerprint`),
  UNIQUE KEY `uk_rrn` (`rrn`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_nominal` (`nominal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
