-- Alias identitas → wa_conversations.id
-- Conversation tetap di-key wa_number (untuk kirim/display).
-- Lookup inbound: ycloud_user_id / lid / wa_username / phone → conversation yang sama.
-- Jalankan di mdl_main (db CRM). PHP sudah aman jika tabel belum ada.

CREATE TABLE IF NOT EXISTS wa_conversation_aliases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id INT UNSIGNED NOT NULL,
  alias_type VARCHAR(32) NOT NULL,
  alias_value VARCHAR(128) NOT NULL,
  source VARCHAR(16) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conv_alias_type_value (alias_type, alias_value),
  KEY idx_conv_alias_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill phone dari conversation yang sudah ada (aman diulang).
INSERT IGNORE INTO wa_conversation_aliases (conversation_id, alias_type, alias_value, source)
SELECT id, 'phone', REPLACE(wa_number, '+', ''), 'backfill'
FROM wa_conversations
WHERE wa_number IS NOT NULL AND wa_number <> ''
  AND wa_number NOT LIKE '%lid%'
  AND REPLACE(wa_number, '+', '') REGEXP '^[0-9]{8,}$';
