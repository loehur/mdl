-- Database: mdl_jaggu_school (API db_index = 8)
-- Jaggu School — jadwal mapel Senin–Sabtu + ceklist anak

CREATE DATABASE IF NOT EXISTS `mdl_jaggu_school`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `mdl_jaggu_school`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('parent','child') NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jaggu_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_jaggu_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- day_of_week: 1=Senin … 6=Sabtu
CREATE TABLE IF NOT EXISTS `schedule_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `day_of_week` TINYINT UNSIGNED NOT NULL,
  `subject_name` VARCHAR(150) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_day_sort` (`day_of_week`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checklist_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `child_user_id` INT UNSIGNED NOT NULL,
  `schedule_item_id` INT UNSIGNED NOT NULL,
  `for_date` DATE NOT NULL,
  `checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_child_item_date` (`child_user_id`, `schedule_item_id`, `for_date`),
  KEY `idx_for_date` (`for_date`),
  CONSTRAINT `fk_checklist_child` FOREIGN KEY (`child_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checklist_item` FOREIGN KEY (`schedule_item_id`) REFERENCES `schedule_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed users (password: parents 123654, child 123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`)
SELECT 'Loehur', 'loehur@gmail.com', '$2y$10$eF8wlMhkegUlHbv2..Tl7e84K3c.oXdWKEXwA0UqhW6dZwMtJxhgW', 'parent', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'loehur@gmail.com' LIMIT 1);

INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`)
SELECT 'Neli', 'neliarnisglory@gmail.com', '$2y$10$eF8wlMhkegUlHbv2..Tl7e84K3c.oXdWKEXwA0UqhW6dZwMtJxhgW', 'parent', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'neliarnisglory@gmail.com' LIMIT 1);

INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`)
SELECT 'Jeysen', 'jeysenjaggu@gmail.com', '$2y$10$MRIiIMLiZUs7lRyK65Y/b.v.VGeMGQAjMewcrI7s1tXW0407ortTO', 'child', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'jeysenjaggu@gmail.com' LIMIT 1);

CREATE TABLE IF NOT EXISTS `jaggu_chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('user','assistant') NOT NULL,
  `content` TEXT NOT NULL,
  `provider` VARCHAR(16) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_jaggu_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jaggu_chat_daily_summaries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `summary_date` DATE NOT NULL,
  `summary_text` VARCHAR(500) NOT NULL DEFAULT '',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `summary_date`),
  CONSTRAINT `fk_jaggu_summary_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
