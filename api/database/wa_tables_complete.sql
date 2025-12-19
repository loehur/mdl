-- =====================================================
-- WhatsApp Integration Tables - Complete Schema
-- YCloud API Integration
-- Version: 2.0 (Restructured)
-- Date: 2025-12-20
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =====================================================
-- Table 1: wa_webhooks
-- Raw webhook logs dari YCloud
-- =====================================================

DROP TABLE IF EXISTS `wa_webhooks`;

CREATE TABLE `wa_webhooks` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `provider` varchar(20) DEFAULT 'ycloud' COMMENT 'Provider name',
  `event_type` varchar(50) DEFAULT NULL COMMENT 'Event type dari webhook',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `received_at` datetime DEFAULT current_timestamp() COMMENT 'Waktu webhook diterima',
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Raw webhook logs dari YCloud API';

-- =====================================================
-- Table 2: wa_customers
-- Customer WhatsApp tracking
-- =====================================================

DROP TABLE IF EXISTS `wa_customers`;

CREATE TABLE `wa_customers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `wa_number` varchar(20) NOT NULL COMMENT 'WhatsApp number (+62xxx)',
  `contact_name` varchar(255) DEFAULT NULL COMMENT 'Customer name',
  `last_message_at` datetime DEFAULT NULL COMMENT 'Last time customer sent message (for 24h window)',
  `first_contact_at` datetime DEFAULT NULL COMMENT 'First contact time',
  `total_messages` int(11) DEFAULT 0 COMMENT 'Total messages from customer',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Active status',
  `notes` text DEFAULT NULL COMMENT 'Internal notes',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wa_number` (`wa_number`),
  KEY `idx_last_message_at` (`last_message_at`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Customer WhatsApp data - untuk tracking 24h window';

-- =====================================================
-- Table 3: wa_conversations
-- Conversation threads with customers
-- =====================================================

DROP TABLE IF EXISTS `wa_conversations`;

CREATE TABLE `wa_conversations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) NOT NULL COMMENT 'FK to wa_customers',
  `wa_number` varchar(20) NOT NULL COMMENT 'WhatsApp number',
  `contact_name` varchar(255) DEFAULT NULL COMMENT 'Customer name',
  `status` enum('open','closed') DEFAULT 'open' COMMENT 'Conversation status',
  `last_in_at` datetime DEFAULT NULL COMMENT 'Last inbound message time',
  `last_out_at` datetime DEFAULT NULL COMMENT 'Last outbound message time',
  `assigned_user_id` bigint(20) DEFAULT NULL COMMENT 'Assigned CS user (optional)',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wa_number` (`wa_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_in_at` (`last_in_at`),
  KEY `idx_last_out_at` (`last_out_at`),
  CONSTRAINT `wa_conversations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `wa_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Conversation threads dengan customer';

-- =====================================================
-- Table 4: wa_messages_in
-- Inbound messages (dari customer ke kita)
-- =====================================================

DROP TABLE IF EXISTS `wa_messages_in`;

CREATE TABLE `wa_messages_in` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) NOT NULL COMMENT 'FK to wa_conversations',
  `customer_id` bigint(20) NOT NULL COMMENT 'FK to wa_customers',
  `phone` varchar(20) NOT NULL COMMENT 'Customer phone number',
  `wamid` varchar(255) DEFAULT NULL COMMENT 'WhatsApp Message ID dari API',
  `message_id` varchar(100) DEFAULT NULL COMMENT 'Provider message ID',
  `type` enum('text','image','document','audio','video','voice','location','contacts','sticker') DEFAULT 'text' COMMENT 'Message type',
  `text` text DEFAULT NULL COMMENT 'Text content',
  `media_id` varchar(100) DEFAULT NULL COMMENT 'Media ID dari WhatsApp',
  `media_url` text DEFAULT NULL COMMENT 'Media URL',
  `media_mime_type` varchar(100) DEFAULT NULL COMMENT 'Media MIME type',
  `media_caption` text DEFAULT NULL COMMENT 'Media caption',
  `contact_name` varchar(255) DEFAULT NULL COMMENT 'Customer name from message',
  `status` varchar(50) DEFAULT 'received' COMMENT 'Status: received, read',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Inbound messages - pesan masuk dari customer';

-- =====================================================
-- Table 5: wa_messages_out
-- Outbound messages (dari kita ke customer)
-- =====================================================

DROP TABLE IF EXISTS `wa_messages_out`;

CREATE TABLE `wa_messages_out` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) NOT NULL COMMENT 'FK to wa_conversations',
  `phone` varchar(20) NOT NULL COMMENT 'Customer phone number (+62xxx)',
  `wamid` varchar(255) DEFAULT NULL COMMENT 'WhatsApp Message ID dari API',
  `message_id` varchar(100) DEFAULT NULL COMMENT 'Provider message ID',
  `type` enum('text','template','image','document','video','audio') NOT NULL DEFAULT 'text' COMMENT 'Message type',
  `content` text DEFAULT NULL COMMENT 'Message text / template name',
  `template_params` text DEFAULT NULL COMMENT 'Template parameters (JSON)',
  `media_url` text DEFAULT NULL COMMENT 'Media URL jika type = media',
  `status` enum('pending','accepted','sent','delivered','read','failed') DEFAULT 'pending' COMMENT 'Message status',
  `error_message` text DEFAULT NULL COMMENT 'Error message if failed',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'When we initiated send',
  `sent_at` datetime DEFAULT NULL COMMENT 'When API accepted (from webhook)',
  `delivered_at` datetime DEFAULT NULL COMMENT 'When delivered to customer',
  `read_at` datetime DEFAULT NULL COMMENT 'When customer read message',
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_wamid` (`wamid`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `wa_messages_out_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `wa_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Outbound messages - pesan yang kita kirim ke customer';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Sample Queries
-- =====================================================

-- View all conversations with latest message
/*
SELECT 
    c.id,
    c.wa_number,
    c.contact_name,
    c.last_in_at,
    c.last_out_at,
    c.status,
    (SELECT COUNT(*) FROM wa_messages_in WHERE conversation_id = c.id) as total_in,
    (SELECT COUNT(*) FROM wa_messages_out WHERE conversation_id = c.id) as total_out
FROM wa_conversations c
ORDER BY GREATEST(COALESCE(c.last_in_at, '2000-01-01'), COALESCE(c.last_out_at, '2000-01-01')) DESC
LIMIT 20;
*/

-- View conversation timeline (merged inbound + outbound)
/*
SELECT 
    'IN' as direction,
    id,
    phone,
    type,
    text as message,
    NULL as content,
    status,
    received_at as time
FROM wa_messages_in
WHERE conversation_id = 1

UNION ALL

SELECT 
    'OUT' as direction,
    id,
    phone,
    type,
    NULL as message,
    content,
    status,
    created_at as time
FROM wa_messages_out
WHERE conversation_id = 1

ORDER BY time DESC;
*/

-- Check message delivery status
/*
SELECT 
    id,
    phone,
    content,
    status,
    created_at,
    sent_at,
    delivered_at,
    read_at,
    TIMESTAMPDIFF(SECOND, created_at, sent_at) as send_delay_sec,
    TIMESTAMPDIFF(SECOND, sent_at, delivered_at) as delivery_delay_sec,
    TIMESTAMPDIFF(SECOND, delivered_at, read_at) as read_delay_sec
FROM wa_messages_out
WHERE status IN ('sent','delivered','read')
ORDER BY id DESC
LIMIT 10;
*/
