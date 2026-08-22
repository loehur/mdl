-- Webhook-delivered template failures + dedupe by message_id.

ALTER TABLE wa_template_fail_logs
  MODIFY source ENUM('chat','blast','webhook') NOT NULL DEFAULT 'chat';

ALTER TABLE wa_template_fail_logs
  ADD COLUMN message_id BIGINT UNSIGNED NULL AFTER conversation_id,
  ADD INDEX idx_tpl_fail_message (message_id);
