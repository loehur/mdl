-- CRM mdl_main (API db 0 / laundry db 100)
-- Kartu notif PERMINTAAN 24 jam, terpisah dari jendela follow-up chat (expires_at 1 jam).
-- Jalankan manual jika tabel wa_permintaan_session sudah ada (hasil 015 lama).

ALTER TABLE wa_permintaan_session
  ADD COLUMN notify_expires_at DATETIME NULL COMMENT 'Kartu notif laundry (24 jam dari buat)' AFTER expires_at;

UPDATE wa_permintaan_session
SET notify_expires_at = DATE_ADD(updated_at, INTERVAL 24 HOUR)
WHERE notify_expires_at IS NULL;

ALTER TABLE wa_permintaan_session
  MODIFY COLUMN notify_expires_at DATETIME NOT NULL COMMENT 'Kartu notif laundry (24 jam dari buat)';

ALTER TABLE wa_permintaan_session
  ADD KEY idx_permintaan_status_notify (status, notify_expires_at);
