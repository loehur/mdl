-- Reservasi nominal QRIS lokal lintas modul. Jalankan pada mdl_main.
USE `mdl_main`;

CREATE TABLE IF NOT EXISTS `qris_nominal_reservations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_ref` VARCHAR(100) NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `state` ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
  `active_key` TINYINT UNSIGNED NULL DEFAULT 1 COMMENT 'NULL setelah kadaluarsa agar nominal dapat digunakan kembali',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_active_amount` (`amount`, `active_key`),
  UNIQUE KEY `uk_active_entity` (`entity_ref`, `active_key`),
  KEY `idx_expiry` (`active_key`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
