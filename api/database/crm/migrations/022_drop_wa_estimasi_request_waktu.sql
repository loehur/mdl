-- CRM mdl_main (API db 0 / laundry db 100)
-- ESTIMASI_SELESAI hanya tanya perkiraan (butuh_estimasi). Request waktu selesai
-- tidak lagi di-record di wa_estimasi_session (nanti intent PERMINTAAN).
-- Jangan drop kolom request_* di wa_kurir_session (grant kurir tetap).
-- Deploy PHP dulu, baru jalankan ALTER ini.

ALTER TABLE wa_estimasi_session
  DROP COLUMN request_text,
  DROP COLUMN request_tanggal,
  DROP COLUMN request_jam,
  DROP COLUMN request_granted;
