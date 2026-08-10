-- CRM mdl_main: session AMBIL_LEWAT_TUTUP (TTL 1 jam)
-- Customer ambil sendiri lewat jam tutup → tanya jam → grant petugas (≤1 jam setelah tutup)

CREATE TABLE IF NOT EXISTS wa_ambil_tutup_session (
  phone VARCHAR(32) NOT NULL,
  id_penjualan INT NULL DEFAULT NULL,
  id_cabang INT NULL DEFAULT NULL,
  step VARCHAR(40) NOT NULL DEFAULT 'ask_jam',
  request_text TEXT NULL,
  request_tanggal DATE NULL DEFAULT NULL,
  request_jam DECIMAL(5,2) NULL DEFAULT NULL,
  request_granted TINYINT(1) NULL DEFAULT NULL,
  reject_reason TEXT NULL,
  summary TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (phone),
  KEY idx_expires_at (expires_at),
  KEY idx_id_cabang (id_cabang),
  KEY idx_request_grant (request_jam, request_granted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
