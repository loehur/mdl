-- WaDesk Blast tables for mdl_wadesk (DB index 7)
-- Run after api/database/wadesk/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS wa_blasts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  channel_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  campaign_name VARCHAR(150) NOT NULL,
  status ENUM('pending','processing','done','cancelled') NOT NULL DEFAULT 'pending',
  total INT UNSIGNED NOT NULL DEFAULT 0,
  sent INT UNSIGNED NOT NULL DEFAULT 0,
  failed INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  INDEX idx_blast_tenant (tenant_id),
  INDEX idx_blast_status (status),
  INDEX idx_blast_campaign (tenant_id, campaign_name),
  CONSTRAINT fk_blast_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_blast_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE,
  CONSTRAINT fk_blast_template FOREIGN KEY (template_id) REFERENCES wa_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_blast_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_blast_recipients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blast_id INT UNSIGNED NOT NULL,
  phone VARCHAR(32) NOT NULL,
  params_json JSON NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  error TEXT NULL,
  conversation_id INT UNSIGNED NULL,
  message_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  INDEX idx_recip_blast_status (blast_id, status),
  CONSTRAINT fk_recip_blast FOREIGN KEY (blast_id) REFERENCES wa_blasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_key_daily_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel_id INT UNSIGNED NOT NULL,
  contact_date DATE NOT NULL,
  phone VARCHAR(32) NOT NULL,
  first_user_id INT UNSIGNED NULL,
  last_user_id INT UNSIGNED NULL,
  first_source VARCHAR(32) NULL,
  last_source VARCHAR(32) NULL,
  first_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_channel_contact_day (channel_id, contact_date, phone),
  INDEX idx_channel_day (channel_id, contact_date),
  CONSTRAINT fk_channel_daily_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE,
  CONSTRAINT fk_key_daily_first_user FOREIGN KEY (first_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_key_daily_last_user FOREIGN KEY (last_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
