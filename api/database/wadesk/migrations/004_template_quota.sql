-- WaDesk template quota (per team / team leader) for mdl_wadesk (DB index 7)
-- Admin top-up; TL + agents on the same team share balance.
-- Deduct only after successful YCloud template send.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS wa_team_template_quotas (
  team_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  balance INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (team_id),
  INDEX idx_quota_tenant (tenant_id),
  CONSTRAINT fk_quota_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_quota_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_team_template_quota_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  type ENUM('topup','consume','adjust') NOT NULL,
  amount INT NOT NULL,
  balance_after INT UNSIGNED NOT NULL DEFAULT 0,
  user_id INT UNSIGNED NULL,
  source VARCHAR(32) NULL,
  ref_type VARCHAR(32) NULL,
  ref_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_quota_logs_team (team_id, id),
  INDEX idx_quota_logs_tenant (tenant_id, id),
  CONSTRAINT fk_quota_logs_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_quota_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_quota_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
