-- CRM mdl_main: MINTA_JEMPUT_ANTAR — antar + sekalian jemput, plus label notif group Fonnte.
-- Jangan dijalankan otomatis dari app — jalankan manual di DB production/staging.

ALTER TABLE wa_kurir_session
  ADD COLUMN sekalian_jemput TINYINT(1) NOT NULL DEFAULT 0 AFTER jenis,
  ADD COLUMN group_notify_label VARCHAR(32) NULL DEFAULT NULL AFTER summary;
