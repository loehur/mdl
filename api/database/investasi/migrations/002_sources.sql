-- Migration: sumber pemasukan (jalankan sekali di mdl_investasi)

USE `mdl_investasi`;

CREATE TABLE IF NOT EXISTS `income_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_income_sources_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abaikan error jika kolom source_id sudah ada
ALTER TABLE `daily_incomes`
  ADD COLUMN `source_id` INT UNSIGNED DEFAULT NULL AFTER `amount`;

ALTER TABLE `daily_incomes`
  ADD KEY `idx_daily_incomes_source` (`source_id`);
