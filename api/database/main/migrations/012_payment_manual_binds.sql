USE `mdl_main`;
CREATE TABLE IF NOT EXISTS `payment_manual_binds` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bind_code` VARCHAR(32) NOT NULL,
  `payment_method` ENUM('bca','qris') NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `status` ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
  `requested_by_phone` VARCHAR(32) NOT NULL,
  `bca_mutasi_id` INT UNSIGNED NULL,
  `bca_qris_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `paid_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`bind_code`),
  KEY `idx_pending_method_amount` (`status`,`payment_method`,`amount`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
