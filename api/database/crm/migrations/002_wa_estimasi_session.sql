-- CRM (mdl_main / API db_index = 0)
-- Session ESTIMASI_SELESAI per nomor WA, TTL 1 jam (expires_at).

CREATE TABLE IF NOT EXISTS wa_estimasi_session (
  phone VARCHAR(32) NOT NULL,
  id_penjualan INT NULL DEFAULT NULL,
  id_cabang INT NULL DEFAULT NULL,
  fase_proses ENUM('antrian', 'pengerjaan', 'selesai') NULL DEFAULT NULL,
  butuh_estimasi TINYINT(1) NOT NULL DEFAULT 0,
  estimasi_tanggal DATE NULL DEFAULT NULL,
  estimasi_jam DECIMAL(5,2) NULL DEFAULT NULL,
  request_text TEXT NULL,
  request_tanggal DATE NULL DEFAULT NULL,
  request_jam DECIMAL(5,2) NULL DEFAULT NULL,
  request_granted TINYINT(1) NULL DEFAULT NULL,
  summary TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (phone),
  KEY idx_expires_at (expires_at),
  KEY idx_id_cabang (id_cabang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
