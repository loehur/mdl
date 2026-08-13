-- CRM mdl_main (API db 0 / laundry db 100): Auto-reply intent keywords + AI prompts
-- Jalankan di database mdl_main. Setelah itu seed via Tools → Auto Reply Keywords → Seed,
-- atau: c:\xampp82\php\php.exe api/database/crm/migrations/014_wa_autoreply_keywords_seed.php

CREATE TABLE IF NOT EXISTS wa_autoreply_intents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  case_value INT NULL DEFAULT NULL,
  notify TINYINT NULL DEFAULT NULL COMMENT 'NULL=unset, 0=false, 1=true',
  ai_prompt MEDIUMTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wa_ar_intent_code (code),
  KEY idx_wa_ar_intent_sort (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_autoreply_patterns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  intent_id INT UNSIGNED NOT NULL,
  pattern TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(255) NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wa_ar_pat_intent (intent_id, sort_order, id),
  CONSTRAINT fk_wa_ar_pat_intent
    FOREIGN KEY (intent_id) REFERENCES wa_autoreply_intents (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wa_autoreply_meta (
  meta_key VARCHAR(64) NOT NULL,
  meta_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (meta_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = meta_value;
