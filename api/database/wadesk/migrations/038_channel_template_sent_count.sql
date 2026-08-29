ALTER TABLE wa_channels
  ADD COLUMN template_sent_count BIGINT UNSIGNED NOT NULL DEFAULT 0
  AFTER template_sending_enabled;
