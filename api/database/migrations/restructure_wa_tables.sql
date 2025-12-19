-- WhatsApp Tables Restructure
-- Split wa_messages into wa_messages_in and wa_messages_out
-- Run on SERVER database

SET FOREIGN_KEY_CHECKS = 0;

-- Backup existing data (optional - comment out if not needed)
-- CREATE TABLE wa_messages_backup AS SELECT * FROM wa_messages;

-- DROP old wa_messages table
DROP TABLE IF EXISTS `wa_messages`;

-- CREATE wa_messages_out (untuk pesan yang kita kirim)
CREATE TABLE `wa_messages_out` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT 'Customer phone number',
  `wamid` varchar(255) DEFAULT NULL COMMENT 'WhatsApp Message ID from API',
  `message_id` varchar(100) DEFAULT NULL COMMENT 'Provider message ID',
  `type` enum('text','template','image','document','video','audio') NOT NULL DEFAULT 'text',
  `content` text DEFAULT NULL COMMENT 'Message content or template name',
  `template_params` text DEFAULT NULL COMMENT 'Template parameters JSON',
  `media_url` text DEFAULT NULL COMMENT 'Media URL if type is media',
  `status` enum('pending','accepted','sent','delivered','read','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'When we initiated send',
  `sent_at` datetime DEFAULT NULL COMMENT 'When API accepted (from webhook)',
  `delivered_at` datetime DEFAULT NULL COMMENT 'When delivered to customer',
  `read_at` datetime DEFAULT NULL COMMENT 'When customer read',
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_wamid` (`wamid`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `wa_messages_out_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `wa_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbound messages (yang kita kirim)';

-- CREATE wa_messages_in (untuk pesan yang masuk dari customer)
CREATE TABLE `wa_messages_in` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) NOT NULL,
  `customer_id` bigint(20) NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT 'Customer phone number',
  `wamid` varchar(255) DEFAULT NULL COMMENT 'WhatsApp Message ID',
  `message_id` varchar(100) DEFAULT NULL COMMENT 'Provider message ID',
  `type` enum('text','image','document','audio','video','voice','location','contacts','sticker') DEFAULT 'text',
  `text` text DEFAULT NULL COMMENT 'Text content',
  `media_id` varchar(100) DEFAULT NULL,
  `media_url` text DEFAULT NULL,
  `media_mime_type` varchar(100) DEFAULT NULL,
  `media_caption` text DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL COMMENT 'Customer name from message',
  `status` varchar(50) DEFAULT 'received' COMMENT 'Status: received, read by us, etc',
  `received_at` datetime DEFAULT current_timestamp() COMMENT 'When we received',
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_wamid` (`wamid`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_received_at` (`received_at`),
  CONSTRAINT `wa_messages_in_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `wa_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wa_messages_in_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `wa_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound messages (dari customer)';

-- ALTER wa_conversations - remove last_message fields, add last_in_at and last_out_at
ALTER TABLE `wa_conversations`
DROP COLUMN IF EXISTS `last_message`,
DROP COLUMN IF EXISTS `last_message_at`,
ADD COLUMN `last_in_at` datetime DEFAULT NULL COMMENT 'Last inbound message time' AFTER `status`,
ADD COLUMN `last_out_at` datetime DEFAULT NULL COMMENT 'Last outbound message time' AFTER `last_in_at`,
ADD INDEX `idx_last_in_at` (`last_in_at`),
ADD INDEX `idx_last_out_at` (`last_out_at`);

SET FOREIGN_KEY_CHECKS = 1;

-- Verification queries (run after migration)
-- SHOW TABLES LIKE 'wa_messages%';
-- SHOW COLUMNS FROM wa_messages_out;
-- SHOW COLUMNS FROM wa_messages_in;
-- SHOW COLUMNS FROM wa_conversations;
