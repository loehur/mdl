-- WaDesk schema for mdl_wadesk (DB index 7)
-- Create DB first: CREATE DATABASE IF NOT EXISTS mdl_wadesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  admin_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  team_leader_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_teams_tenant (tenant_id),
  CONSTRAINT fk_teams_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS ycloud_keys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  label VARCHAR(150) NOT NULL,
  api_key_enc TEXT NOT NULL,
  phone_number VARCHAR(32) NOT NULL,
  ycloud_phone_id VARCHAR(64) NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_keys_tenant (tenant_id),
  INDEX idx_keys_team (team_id),
  INDEX idx_keys_phone (phone_number),
  INDEX idx_keys_phone_id (ycloud_phone_id),
  CONSTRAINT fk_keys_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_keys_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ycloud_key_id INT UNSIGNED NOT NULL,
  template_name VARCHAR(150) NOT NULL,
  language VARCHAR(16) NOT NULL DEFAULT 'id',
  body_preview TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tpl_key (ycloud_key_id),
  CONSTRAINT fk_tpl_key FOREIGN KEY (ycloud_key_id) REFERENCES ycloud_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_template_params (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  component ENUM('header','body','button') NOT NULL DEFAULT 'body',
  param_index INT UNSIGNED NOT NULL,
  param_name VARCHAR(64) NULL,
  label VARCHAR(150) NOT NULL,
  example_value VARCHAR(255) NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_tpl_param (template_id, component, param_index),
  CONSTRAINT fk_tpl_param FOREIGN KEY (template_id) REFERENCES wa_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  team_id INT UNSIGNED NOT NULL,
  ycloud_key_id INT UNSIGNED NOT NULL,
  phone VARCHAR(32) NOT NULL,
  name VARCHAR(150) NULL,
  last_message TEXT NULL,
  last_in_at DATETIME NULL,
  last_out_at DATETIME NULL,
  last_message_at DATETIME NULL,
  unread INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conv_key_phone (ycloud_key_id, phone),
  INDEX idx_conv_tenant_team (tenant_id, team_id),
  INDEX idx_conv_last (last_message_at),
  CONSTRAINT fk_conv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_key FOREIGN KEY (ycloud_key_id) REFERENCES ycloud_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  direction ENUM('in', 'out') NOT NULL,
  type VARCHAR(32) NOT NULL DEFAULT 'text',
  body TEXT NULL,
  template_name VARCHAR(150) NULL,
  params_json JSON NULL,
  media_url VARCHAR(500) NULL,
  ycloud_msg_id VARCHAR(128) NULL,
  external_id VARCHAR(64) NULL,
  status VARCHAR(32) NULL,
  sent_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_msg_conv (conversation_id, id),
  INDEX idx_msg_ycloud (ycloud_msg_id),
  INDEX idx_msg_external (external_id),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_user FOREIGN KEY (sent_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
