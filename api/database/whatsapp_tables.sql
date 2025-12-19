-- WhatsApp Messages Log Table
-- Optional: Untuk tracking semua pesan WhatsApp yang dikirim

CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL COMMENT 'Customer phone number',
  `message_type` enum('text','template','media','button') NOT NULL COMMENT 'Type of message',
  `message_mode` enum('free','template') DEFAULT NULL COMMENT 'CSW mode used',
  `message_content` text DEFAULT NULL COMMENT 'Text message content',
  `template_name` varchar(100) DEFAULT NULL COMMENT 'Template name if template mode',
  `template_params` text DEFAULT NULL COMMENT 'Template parameters JSON',
  `media_url` varchar(500) DEFAULT NULL COMMENT 'Media URL if media type',
  `media_type` varchar(20) DEFAULT NULL COMMENT 'Media type: image|video|document|audio',
  `status` enum('pending','sent','delivered','read','failed') DEFAULT 'pending' COMMENT 'Message status',
  `ycloud_message_id` varchar(100) DEFAULT NULL COMMENT 'yCloud message ID',
  `last_customer_message_at` datetime DEFAULT NULL COMMENT 'Last time customer sent message',
  `csw_hours_elapsed` decimal(10,2) DEFAULT NULL COMMENT 'Hours since last customer message',
  `is_within_csw` tinyint(1) DEFAULT 0 COMMENT '1=within CSW, 0=outside CSW',
  `error_message` text DEFAULT NULL COMMENT 'Error message if failed',
  `api_response` text DEFAULT NULL COMMENT 'Full API response JSON',
  `sent_by` int(11) DEFAULT NULL COMMENT 'User ID who sent the message',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ycloud_message_id` (`ycloud_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='WhatsApp messages log via yCloud API';

-- Customer WhatsApp Tracking
-- Untuk menyimpan last_message_at setiap customer

CREATE TABLE IF NOT EXISTS `customer_whatsapp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL COMMENT 'Reference to customer table',
  `phone` varchar(20) NOT NULL COMMENT 'WhatsApp phone number',
  `last_message_at` datetime DEFAULT NULL COMMENT 'Last time customer sent message to us',
  `last_sent_at` datetime DEFAULT NULL COMMENT 'Last time we sent message to customer',
  `total_messages_sent` int(11) DEFAULT 0 COMMENT 'Total messages sent to this customer',
  `total_messages_received` int(11) DEFAULT 0 COMMENT 'Total messages received from customer',
  `is_csw_active` tinyint(1) GENERATED ALWAYS AS (
    CASE 
      WHEN `last_message_at` IS NULL THEN 0
      WHEN TIMESTAMPDIFF(HOUR, `last_message_at`, NOW()) <= 24 THEN 1
      ELSE 0
    END
  ) STORED COMMENT 'Auto-calculated: 1=CSW active, 0=expired',
  `csw_expires_at` datetime GENERATED ALWAYS AS (
    DATE_ADD(`last_message_at`, INTERVAL 24 HOUR)
  ) STORED COMMENT 'Auto-calculated: When CSW will expire',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_phone` (`phone`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_is_csw_active` (`is_csw_active`),
  KEY `idx_last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Customer WhatsApp tracking for CSW management';

-- Insert sample data
-- INSERT INTO `customer_whatsapp` (`customer_id`, `phone`, `last_message_at`) 
-- VALUES (1, '+6281234567890', NOW() - INTERVAL 2 HOUR);
