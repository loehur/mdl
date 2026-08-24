-- CRM custom quick replies (mdl_main / db index 100)
-- Admin UI: laundry → CRM Setting → Quick Replies
-- Public API: GET /Get/quickReplies (for CRM chat "/" picker)

CREATE TABLE IF NOT EXISTS crm_quick_replies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shortcut VARCHAR(64) NOT NULL COMMENT 'Trigger di chat CRM, wajib diawali /, contoh: /promo',
  title VARCHAR(128) NOT NULL COMMENT 'Judul tampilan di picker',
  message TEXT NOT NULL COMMENT 'Teks pesan yang diisi ke input chat',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_crm_quick_replies_shortcut (shortcut),
  KEY idx_crm_quick_replies_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
