CREATE TABLE IF NOT EXISTS wa_channel_daily_stats (
  channel_id INT UNSIGNED NOT NULL,
  stat_date DATE NOT NULL,
  template_sent_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (channel_id, stat_date),
  INDEX idx_channel_daily_stats_date (stat_date),
  CONSTRAINT fk_channel_daily_stats_channel
    FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
