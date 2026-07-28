-- Migration: target & pengeluaran harian (jalankan sekali di mdl_investasi)

USE `mdl_investasi`;

CREATE TABLE IF NOT EXISTS `expense_targets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_expense_targets_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_date` DATE NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `target_id` INT UNSIGNED DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_daily_expenses_date` (`record_date`),
  KEY `idx_daily_expenses_target` (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
