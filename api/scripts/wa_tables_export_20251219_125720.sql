-- YCloud WhatsApp Tables Export
-- Generated: 2025-12-19 12:57:20

SET FOREIGN_KEY_CHECKS = 0;

-- Table: wa_webhooks
DROP TABLE IF EXISTS `wa_webhooks`;

CREATE TABLE `wa_webhooks` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `provider` varchar(20) DEFAULT 'ycloud',
  `event_type` varchar(50) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `received_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: wa_customers
DROP TABLE IF EXISTS `wa_customers`;

CREATE TABLE `wa_customers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `wa_number` varchar(20) NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `first_contact_at` datetime DEFAULT NULL,
  `total_messages` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wa_number` (`wa_number`),
  KEY `idx_last_message_at` (`last_message_at`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: wa_conversations
DROP TABLE IF EXISTS `wa_conversations`;

CREATE TABLE `wa_conversations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) NOT NULL,
  `wa_number` varchar(20) NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `last_message` text DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `assigned_user_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wa_number` (`wa_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_message_at` (`last_message_at`),
  CONSTRAINT `wa_conversations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `wa_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: wa_messages
DROP TABLE IF EXISTS `wa_messages`;

CREATE TABLE `wa_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) NOT NULL,
  `customer_id` bigint(20) NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `message_type` enum('text','image','document','audio','video','voice','location','contacts','sticker') DEFAULT 'text',
  `text` text DEFAULT NULL,
  `media_id` varchar(100) DEFAULT NULL,
  `media_url` text DEFAULT NULL,
  `media_mime_type` varchar(100) DEFAULT NULL,
  `media_caption` text DEFAULT NULL,
  `provider_message_id` varchar(100) DEFAULT NULL,
  `wamid` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_by_user_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_provider_message_id` (`provider_message_id`),
  KEY `idx_wamid` (`wamid`),
  KEY `idx_direction` (`direction`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `wa_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `wa_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wa_messages_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `wa_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
