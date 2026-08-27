-- Tenant-wide Dev Fee template usage. No top-up workflow is included yet.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS wa_tenant_dev_fee_quotas (
  tenant_id INT UNSIGNED NOT NULL,
  quota_total INT UNSIGNED NULL,
  quota_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id),
  CONSTRAINT fk_dev_fee_quota_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_tenant_dev_fee_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NULL,
  template_id INT UNSIGNED NULL,
  template_name VARCHAR(150) NOT NULL,
  user_id INT UNSIGNED NULL,
  team_id INT UNSIGNED NULL,
  channel_id INT UNSIGNED NULL,
  phone VARCHAR(32) NULL,
  source ENUM('chat','blast') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dev_fee_message (message_id),
  INDEX idx_dev_fee_logs_tenant (tenant_id, id),
  CONSTRAINT fk_dev_fee_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_dev_fee_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_dev_fee_logs_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
  CONSTRAINT fk_dev_fee_logs_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
