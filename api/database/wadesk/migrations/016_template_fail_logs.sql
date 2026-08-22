-- Log failed WhatsApp template sends (Kirimin/Meta rejection after send attempt).

CREATE TABLE IF NOT EXISTS wa_template_fail_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NULL,
  channel_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  conversation_id INT UNSIGNED NULL,
  blast_id INT UNSIGNED NULL,
  blast_recipient_id INT UNSIGNED NULL,
  source ENUM('chat','blast') NOT NULL DEFAULT 'chat',
  phone VARCHAR(32) NOT NULL,
  template_id INT UNSIGNED NULL,
  template_name VARCHAR(150) NOT NULL,
  language VARCHAR(16) NOT NULL DEFAULT 'id',
  device_id VARCHAR(64) NULL,
  preview TEXT NULL,
  error_message TEXT NOT NULL,
  error_code VARCHAR(64) NULL,
  http_code SMALLINT UNSIGNED NULL,
  request_json JSON NULL,
  response_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tpl_fail_tenant_created (tenant_id, created_at),
  INDEX idx_tpl_fail_team (team_id),
  INDEX idx_tpl_fail_source (source),
  CONSTRAINT fk_tpl_fail_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
