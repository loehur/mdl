-- mdl_main.wa_messages_out: tambah id_pelanggan (phone tetap nomor WA untuk CRM)
USE mdl_main;

ALTER TABLE wa_messages_out
  ADD COLUMN id_pelanggan INT UNSIGNED NULL AFTER phone;

UPDATE wa_messages_out
SET id_pelanggan = CAST(phone AS UNSIGNED)
WHERE id_pelanggan IS NULL
  AND status IN ('queue', 'processing')
  AND phone REGEXP '^[0-9]+$'
  AND CHAR_LENGTH(phone) BETWEEN 1 AND 10
  AND CAST(phone AS UNSIGNED) > 0;

UPDATE mdl_main.wa_messages_out w
INNER JOIN mdl_laundry.notif n
    ON TRIM(COALESCE(n.text, '')) = TRIM(COALESCE(w.content, ''))
   AND n.id_pelanggan IS NOT NULL
   AND n.id_pelanggan > 0
SET w.id_pelanggan = n.id_pelanggan
WHERE w.id_pelanggan IS NULL
  AND w.status IN ('queue', 'processing');

UPDATE mdl_main.wa_messages_out w
INNER JOIN mdl_laundry.pelanggan p ON p.id_pelanggan = w.id_pelanggan
SET w.phone = p.nomor_pelanggan
WHERE w.id_pelanggan IS NOT NULL
  AND w.status IN ('queue', 'processing')
  AND w.phone REGEXP '^[0-9]+$'
  AND CHAR_LENGTH(w.phone) <= 10;

ALTER TABLE wa_messages_out
  ADD INDEX idx_wa_out_id_pelanggan (id_pelanggan);
