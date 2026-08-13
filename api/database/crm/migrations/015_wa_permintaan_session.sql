-- CRM mdl_main (API db 0 / laundry db 100)
-- Session intent PERMINTAAN: 1 aktif per nomor WA, ringkasan AI, TTL 24 jam.
-- Jangan dijalankan otomatis dari app — jalankan manual di DB production/staging.

CREATE TABLE IF NOT EXISTS wa_permintaan_session (
  phone VARCHAR(32) NOT NULL,
  id_pelanggan INT NULL DEFAULT NULL,
  id_cabang INT NULL DEFAULT NULL,
  status ENUM('open', 'fulfilled', 'rejected') NOT NULL DEFAULT 'open',
  summary TEXT NULL COMMENT 'Ringkasan AI isi permintaan (1 kalimat)',
  raw_log MEDIUMTEXT NULL COMMENT 'Cuplikan chat yang dirangkum',
  reject_reason TEXT NULL,
  reject_alt TEXT NULL,
  reply_text TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (phone),
  KEY idx_permintaan_expires (expires_at),
  KEY idx_permintaan_cabang_status (id_cabang, status),
  KEY idx_permintaan_status_expires (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pastikan intent PERMINTAAN ada (case 3 = merah CRM / notif laundry)
INSERT INTO wa_autoreply_intents (code, sort_order, case_value, notify, ai_prompt, is_active, note)
SELECT
  'PERMINTAAN',
  45,
  3,
  1,
  'Permintaan khusus pelanggan terkait cucian/order yang sudah di laundry (bukan minta kurir jemput/antar ke alamat). Contoh: minta satu item/pakaian diselesaikan/diambil/dulukan dulu, prioritas item tertentu, minta perlakuan khusus pada order. Bukan MINTA_JEMPUT_ANTAR, bukan STATUS, bukan ESTIMASI_SELESAI.',
  1,
  'Session wa_permintaan_session; tanpa autoreply; notif laundry'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM wa_autoreply_intents WHERE code = 'PERMINTAAN'
);

-- Jika intent sudah ada, pastikan case/notify sesuai
UPDATE wa_autoreply_intents
SET case_value = 3,
    notify = 1,
    is_active = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'PERMINTAAN';

-- Bump cache keyword setelah insert/update intent
INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
