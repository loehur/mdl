-- CRM mdl_main: jam/tanggal yang DIMINTA customer (grant request), terpisah dari estimasi petugas

ALTER TABLE wa_estimasi_session
  ADD COLUMN request_tanggal DATE NULL DEFAULT NULL AFTER request_text,
  ADD COLUMN request_jam DECIMAL(5,2) NULL DEFAULT NULL AFTER request_tanggal;
