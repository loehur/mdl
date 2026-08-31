ALTER TABLE teams
  ADD COLUMN daily_template_limit INT UNSIGNED NOT NULL DEFAULT 250 AFTER template_category;

CREATE TABLE wa_team_daily_template_stats (
  team_id INT UNSIGNED NOT NULL,
  stat_date DATE NOT NULL,
  template_sent_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (team_id, stat_date),
  INDEX idx_team_daily_template_stats_date (stat_date),
  CONSTRAINT fk_team_daily_template_stats_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
