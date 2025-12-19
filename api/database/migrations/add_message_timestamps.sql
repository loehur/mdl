-- Add timestamp columns for message status tracking
-- Run this migration to support whatsapp.message.updated events

ALTER TABLE `wa_messages`
ADD COLUMN `sent_at` datetime DEFAULT NULL COMMENT 'When message was sent' AFTER `status`,
ADD COLUMN `delivered_at` datetime DEFAULT NULL COMMENT 'When message was delivered' AFTER `sent_at`,
ADD COLUMN `read_at` datetime DEFAULT NULL COMMENT 'When message was read' AFTER `delivered_at`,
ADD COLUMN `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Last update time' AFTER `read_at`;

-- Add index for better query performance
CREATE INDEX `idx_status` ON `wa_messages` (`status`);
CREATE INDEX `idx_read_at` ON `wa_messages` (`read_at`);
