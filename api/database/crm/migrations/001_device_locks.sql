-- CRM device locks (mdl_main / API db_index = 0)
-- Bind username ke satu device_id. Unlock hanya via logout (atau admin unlock nanti).

CREATE TABLE IF NOT EXISTS crm_device_locks (
  username VARCHAR(64) NOT NULL,
  device_id VARCHAR(64) NOT NULL,
  locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (username),
  KEY idx_device_id (device_id),
  KEY idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
