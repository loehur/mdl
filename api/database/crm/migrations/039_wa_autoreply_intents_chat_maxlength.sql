-- CRM mdl_main (API db 0 / laundry db 100)
-- Max karakter chat per intent: pesan lebih panjang → intent di-skip saat klasifikasi.
-- NULL/0 = tanpa batas. Kelola lewat laundry Admin → Tools → Auto Reply Keywords.

ALTER TABLE wa_autoreply_intents
  ADD COLUMN chat_maxlength INT UNSIGNED NULL DEFAULT NULL
  COMMENT 'NULL/0=tanpa batas; pesan lebih panjang → intent ini di-skip'
  AFTER note;

-- Migrasi hardcode PENUTUP 50 char → DB
UPDATE wa_autoreply_intents
SET chat_maxlength = 50,
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'PENUTUP';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
