-- Simpan draf asli free text sebelum AI polish (body = versi terkirim)

ALTER TABLE messages
  ADD COLUMN body_raw TEXT NULL
  AFTER body;
