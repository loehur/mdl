-- CRM mdl_main: session multi-turn intent LOKASI (terpisah dari wa_kurir_session)

CREATE TABLE IF NOT EXISTS wa_lokasi_session (
  phone VARCHAR(32) NOT NULL,
  id_pelanggan INT NULL DEFAULT NULL,
  id_lokasi INT NULL DEFAULT NULL,
  step VARCHAR(40) NOT NULL DEFAULT 'ask_nama',
  lokasi_nama VARCHAR(50) NULL DEFAULT NULL,
  lokasi_detail VARCHAR(255) NULL DEFAULT NULL,
  latt DECIMAL(10,7) NULL DEFAULT NULL,
  longt DECIMAL(10,7) NULL DEFAULT NULL,
  last_ask_at DATETIME NULL DEFAULT NULL,
  summary TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (phone),
  KEY idx_lokasi_expires (expires_at),
  KEY idx_lokasi_pelanggan (id_pelanggan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
