-- CRM mdl_main (API db 0)
-- Session HARGA unified per nomor WA, TTL 30 menit (follow-up durasi/paket/layanan).

CREATE TABLE IF NOT EXISTS wa_harga_session (
  phone VARCHAR(32) NOT NULL,
  service VARCHAR(32) NOT NULL DEFAULT 'cuci_setrika' COMMENT 'cuci_setrika|setrika_saja|cuci_pack',
  durasi VARCHAR(16) NOT NULL DEFAULT 'regular' COMMENT 'regular|ekspres|kilat',
  delivery TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=durasi -D (include antar/jemput)',
  paket TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=harga paket/member',
  item_keyword VARCHAR(64) NULL DEFAULT NULL COMMENT 'boneka, gorden, dll.',
  step VARCHAR(32) NOT NULL DEFAULT 'ready' COMMENT 'ready|ask_service|collecting',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (phone),
  KEY idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
