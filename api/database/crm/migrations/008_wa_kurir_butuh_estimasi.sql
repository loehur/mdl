-- CRM mdl_main: kurir "hari ini/besok jam berapa?" → petugas isi perkiraan (mirip ESTIMASI_SELESAI)

ALTER TABLE wa_kurir_session
  ADD COLUMN butuh_estimasi TINYINT(1) NOT NULL DEFAULT 0 AFTER request_granted,
  ADD COLUMN estimasi_tanggal DATE NULL DEFAULT NULL AFTER butuh_estimasi,
  ADD COLUMN estimasi_jam DECIMAL(5,2) NULL DEFAULT NULL AFTER estimasi_tanggal;

ALTER TABLE wa_kurir_session
  ADD KEY idx_kurir_butuh_estimasi (butuh_estimasi, estimasi_jam);
