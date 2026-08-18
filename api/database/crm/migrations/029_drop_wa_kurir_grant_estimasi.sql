-- CRM mdl_main (API db 0 / laundry db 100)
-- Hapus grant jam + estimasi waktu dari session kurir.
-- Deploy PHP dulu, baru jalankan ALTER ini.

ALTER TABLE wa_kurir_session
  DROP INDEX idx_request_grant,
  DROP INDEX idx_kurir_butuh_estimasi,
  DROP COLUMN request_text,
  DROP COLUMN request_tanggal,
  DROP COLUMN request_jam,
  DROP COLUMN request_granted,
  DROP COLUMN butuh_estimasi,
  DROP COLUMN estimasi_tanggal,
  DROP COLUMN estimasi_jam,
  DROP COLUMN driver_alt_tanggal,
  DROP COLUMN driver_alt_jam;
