ALTER TABLE wa_channels
  ADD COLUMN inbound_count BIGINT UNSIGNED NOT NULL DEFAULT 0
  AFTER template_sent_count;
