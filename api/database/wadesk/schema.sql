-- WaDesk schema for mdl_wadesk (DB index 7)
-- Create DB first: CREATE DATABASE IF NOT EXISTS mdl_wadesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  kirimin_api_key VARCHAR(255) NULL,
  daily_unique_limit INT UNSIGNED NOT NULL DEFAULT 250,
  openai_api_key VARCHAR(255) NULL,
  admin_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  template_category ENUM('UTILITY','MARKETING') NOT NULL DEFAULT 'UTILITY',
  daily_template_limit INT UNSIGNED NOT NULL DEFAULT 250,
  template_access_expires_at DATE NULL,
  mask_phone_numbers TINYINT(1) NOT NULL DEFAULT 0,
  team_leader_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_teams_tenant (tenant_id),
  CONSTRAINT fk_teams_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_team_daily_template_stats (
  team_id INT UNSIGNED NOT NULL,
  stat_date DATE NOT NULL,
  template_sent_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (team_id, stat_date),
  INDEX idx_team_daily_template_stats_date (stat_date),
  CONSTRAINT fk_team_daily_template_stats_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'team_leader', 'agent') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  INDEX idx_users_tenant (tenant_id),
  INDEX idx_users_team (team_id),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_users_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wadesk_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token_hash (token_hash),
  INDEX idx_tokens_user (user_id),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_channels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  label VARCHAR(150) NOT NULL,
  device_id VARCHAR(64) NULL,
  waba_id VARCHAR(64) NULL,
  meta_phone_number_id VARCHAR(64) NULL,
  meta_provider_status VARCHAR(32) NULL,
  meta_verification_status VARCHAR(32) NULL,
  meta_quality_rating VARCHAR(32) NULL,
  meta_platform_type VARCHAR(32) NULL,
  channel_type ENUM('waba','device') NOT NULL DEFAULT 'waba',
  provider ENUM('kirimin','meta') NOT NULL DEFAULT 'kirimin',
  phone_number VARCHAR(32) NOT NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  template_sending_enabled TINYINT(1) NOT NULL DEFAULT 1,
  template_sent_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  inbound_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_channel_device (device_id),
  UNIQUE KEY uq_channel_tenant_meta_phone (tenant_id, meta_phone_number_id),
  INDEX idx_channels_tenant (tenant_id),
  INDEX idx_channels_phone (phone_number),
  INDEX idx_channels_waba (waba_id),
  CONSTRAINT fk_channels_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_channel_daily_stats (
  channel_id INT UNSIGNED NOT NULL,
  stat_date DATE NOT NULL,
  template_sent_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (channel_id, stat_date),
  INDEX idx_channel_daily_stats_date (stat_date),
  CONSTRAINT fk_channel_daily_stats_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  meta_waba_id VARCHAR(64) NULL,
  template_name VARCHAR(150) NOT NULL,
  language VARCHAR(16) NOT NULL DEFAULT 'id',
  meta_template_id VARCHAR(64) NULL,
  body_preview TEXT NULL,
  meta_status VARCHAR(32) NULL,
  meta_quality_rating VARCHAR(32) NULL,
  meta_category VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tpl_tenant (tenant_id),
  UNIQUE KEY uq_tpl_tenant_waba_name_lang (tenant_id, meta_waba_id, template_name, language),
  CONSTRAINT fk_tpl_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_wabas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  meta_waba_id VARCHAR(64) NOT NULL,
  name VARCHAR(150) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  meta_app_subscribed_at DATETIME NULL,
  last_synced_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_waba_tenant_meta (tenant_id, meta_waba_id),
  INDEX idx_waba_tenant (tenant_id),
  CONSTRAINT fk_waba_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_waba_teams (
  waba_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (waba_id, team_id),
  UNIQUE KEY uq_waba_team_once (tenant_id, team_id),
  INDEX idx_waba_teams_tenant_waba (tenant_id, waba_id),
  CONSTRAINT fk_waba_team_waba FOREIGN KEY (waba_id) REFERENCES wa_wabas(id) ON DELETE CASCADE,
  CONSTRAINT fk_waba_team_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_waba_team_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_template_teams (
  template_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (template_id, team_id),
  INDEX idx_template_teams_team (team_id),
  INDEX idx_template_teams_tenant (tenant_id),
  CONSTRAINT fk_template_teams_template FOREIGN KEY (template_id) REFERENCES wa_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_template_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_template_teams_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_template_fail_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NULL,
  channel_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  conversation_id INT UNSIGNED NULL,
  message_id BIGINT UNSIGNED NULL,
  blast_id INT UNSIGNED NULL,
  blast_recipient_id INT UNSIGNED NULL,
  source ENUM('chat','blast','webhook') NOT NULL DEFAULT 'chat',
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
  INDEX idx_tpl_fail_message (message_id),
  CONSTRAINT fk_tpl_fail_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_template_params (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  component ENUM('header','body','button') NOT NULL DEFAULT 'body',
  button_sub_type VARCHAR(32) NULL,
  button_index INT UNSIGNED NULL,
  param_index INT UNSIGNED NOT NULL,
  param_name VARCHAR(64) NULL,
  label VARCHAR(150) NOT NULL,
  example_value VARCHAR(255) NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  maxlength SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  UNIQUE KEY uq_tpl_param (template_id, component, param_index),
  CONSTRAINT fk_tpl_param FOREIGN KEY (template_id) REFERENCES wa_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_template_ai_approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  body TEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tpl_ai_approval_token (token_hash),
  INDEX idx_tpl_ai_approval_lookup (tenant_id, team_id, user_id, expires_at),
  CONSTRAINT fk_tpl_ai_approval_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tpl_ai_approval_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_tpl_ai_approval_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_channel_teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_channel_team_link (channel_id, team_id),
  INDEX idx_channel_teams_team (team_id),
  CONSTRAINT fk_channel_teams_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE,
  CONSTRAINT fk_channel_teams_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  channel_id INT UNSIGNED NOT NULL,
  phone VARCHAR(32) NOT NULL,
  name VARCHAR(150) NULL,
  last_message TEXT NULL,
  last_in_at DATETIME NULL,
  last_out_at DATETIME NULL,
  last_message_at DATETIME NULL,
  unread INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conv_channel_team_phone (channel_id, team_id, phone),
  INDEX idx_conv_tenant_team (tenant_id, team_id),
  INDEX idx_conv_last (last_message_at),
  CONSTRAINT fk_conv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_channel FOREIGN KEY (channel_id) REFERENCES wa_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  direction ENUM('in', 'out') NOT NULL,
  type VARCHAR(32) NOT NULL DEFAULT 'text',
  body TEXT NULL,
  body_raw TEXT NULL,
  template_name VARCHAR(150) NULL,
  params_json JSON NULL,
  media_url VARCHAR(500) NULL,
  provider_msg_id VARCHAR(128) NULL,
  external_id VARCHAR(64) NULL,
  status VARCHAR(32) NULL,
  sent_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_msg_conv (conversation_id, id),
  INDEX idx_msg_provider (provider_msg_id),
  INDEX idx_msg_external (external_id),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_user FOREIGN KEY (sent_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_blasts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  channel_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NULL,
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
  INDEX idx_blast_team (team_id),
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
  tenant_id INT UNSIGNED NOT NULL,
  contact_date DATE NOT NULL,
  phone VARCHAR(32) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'sent',
  first_user_id INT UNSIGNED NULL,
  last_user_id INT UNSIGNED NULL,
  first_source VARCHAR(32) NULL,
  last_source VARCHAR(32) NULL,
  first_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_contact_day (tenant_id, contact_date, phone),
  INDEX idx_tenant_day (tenant_id, contact_date),
  INDEX idx_tenant_day_status (tenant_id, contact_date, status),
  CONSTRAINT fk_tenant_daily_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_channel_daily_first_user FOREIGN KEY (first_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_channel_daily_last_user FOREIGN KEY (last_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
