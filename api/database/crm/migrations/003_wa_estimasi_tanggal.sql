-- CRM mdl_main: tambah tanggal estimasi (petugas isi tanggal + jam)
-- Jalankan jika tabel wa_estimasi_session sudah ada tanpa kolom ini.

ALTER TABLE wa_estimasi_session
  ADD COLUMN estimasi_tanggal DATE NULL DEFAULT NULL AFTER butuh_estimasi;
