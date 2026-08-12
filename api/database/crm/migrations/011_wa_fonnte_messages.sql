-- CRM mdl_main (db 0): riwayat chat Fonnte terpisah dari wa_messages_in/out (yCloud)

CREATE TABLE IF NOT EXISTS wa_fonnte_conversations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone VARCHAR(32) NOT NULL,
  contact_name VARCHAR(255) NULL DEFAULT NULL,
  assigned_user_id INT NULL DEFAULT NULL,
  code VARCHAR(16) NULL DEFAULT NULL,
  cust_id INT NULL DEFAULT NULL,
  last_message VARCHAR(255) NULL DEFAULT NULL,
  last_in_at DATETIME NULL DEFAULT NULL,
  last_out_at DATETIME NULL DEFAULT NULL,
  last_message_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fonnte_conv_phone (phone),
  KEY idx_fonnte_conv_assigned (assigned_user_id),
  KEY idx_fonnte_conv_last_in (last_in_at),
  KEY idx_fonnte_conv_last_out (last_out_at),
  KEY idx_fonnte_conv_last_msg (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_fonnte_messages_in (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone VARCHAR(32) NOT NULL,
  contact_name VARCHAR(255) NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL DEFAULT 'text',
  text TEXT NULL,
  media_url VARCHAR(512) NULL DEFAULT NULL,
  media_filename VARCHAR(255) NULL DEFAULT NULL,
  media_extension VARCHAR(32) NULL DEFAULT NULL,
  location VARCHAR(64) NULL DEFAULT NULL,
  inboxid BIGINT UNSIGNED NULL DEFAULT NULL,
  fonnte_device VARCHAR(32) NULL DEFAULT NULL,
  member VARCHAR(64) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fonnte_in_inboxid (inboxid),
  KEY idx_fonnte_in_phone_time (phone, created_at),
  KEY idx_fonnte_in_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_fonnte_messages_out (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone VARCHAR(32) NOT NULL,
  type VARCHAR(32) NOT NULL DEFAULT 'text',
  text TEXT NULL,
  media_url VARCHAR(512) NULL DEFAULT NULL,
  fonnte_message_id VARCHAR(64) NULL DEFAULT NULL,
  reply_inboxid BIGINT UNSIGNED NULL DEFAULT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'autoreply',
  sender_code VARCHAR(32) NULL DEFAULT NULL,
  handler VARCHAR(64) NULL DEFAULT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'sent',
  error_text VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fonnte_out_phone_time (phone, created_at),
  KEY idx_fonnte_out_fonnte_id (fonnte_message_id),
  KEY idx_fonnte_out_sender_code (sender_code),
  KEY idx_fonnte_out_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
