-- Aturan bypass Mutasi BCA CR. Jalankan sekali pada database mdl_main.
USE `mdl_main`;

CREATE TABLE IF NOT EXISTS `bca_mutasi_bypass_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `keyword` VARCHAR(191) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_keyword` (`keyword`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `bca_mutasi`
  ADD COLUMN `is_bypassed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = diabaikan dari pencocokan pembayaran' AFTER `mutasi`,
  ADD COLUMN `bypass_rule_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Aturan bypass yang cocok' AFTER `is_bypassed`,
  ADD KEY `idx_bypass_cr` (`mutasi`, `is_bypassed`),
  ADD KEY `idx_bypass_rule` (`bypass_rule_id`);
