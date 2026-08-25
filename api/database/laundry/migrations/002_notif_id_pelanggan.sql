-- mdl_laundry.notif: phone → id_pelanggan
-- Jalankan di VPS setelah backup. Verifikasi query di bawah sebelum DROP phone.

USE mdl_laundry;

ALTER TABLE notif
  ADD COLUMN id_pelanggan INT UNSIGNED NULL AFTER phone;

-- tipe 1 = Nota (no_ref = sale.no_ref)
UPDATE notif n
INNER JOIN (
    SELECT no_ref, id_cabang, MIN(id_pelanggan) AS id_pelanggan
    FROM sale
    WHERE bin = 0
    GROUP BY no_ref, id_cabang
) s ON s.no_ref = n.no_ref AND s.id_cabang = n.id_cabang
SET n.id_pelanggan = s.id_pelanggan
WHERE n.tipe = 1 AND n.id_pelanggan IS NULL;

-- tipe 2 = Selesai (no_ref = id_penjualan)
UPDATE notif n
INNER JOIN sale s
    ON s.id_penjualan = n.no_ref
   AND s.id_cabang = n.id_cabang
   AND s.bin = 0
SET n.id_pelanggan = s.id_pelanggan
WHERE n.tipe = 2 AND n.id_pelanggan IS NULL;

-- tipe 3 = Member (no_ref = id_member)
UPDATE notif n
INNER JOIN member m ON m.id_member = n.no_ref
SET n.id_pelanggan = m.id_pelanggan
WHERE n.tipe = 3 AND n.id_pelanggan IS NULL;

-- tipe 4 = Saldo tunai (no_ref = id_kas)
UPDATE notif n
INNER JOIN kas k ON k.id_kas = n.no_ref
SET n.id_pelanggan = k.id_client
WHERE n.tipe = 4 AND n.id_pelanggan IS NULL;

-- tipe 6 = OTP (fallback nomor → pelanggan)
UPDATE notif n
INNER JOIN pelanggan p
    ON REPLACE(REPLACE(REPLACE(REPLACE(TRIM(p.nomor_pelanggan), '+', ''), '-', ''), ' ', ''), '.', '')
       LIKE CONCAT(
           '%',
           RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(n.phone), '+', ''), '-', ''), ' ', ''), '.', ''), 9)
       )
SET n.id_pelanggan = p.id_pelanggan
WHERE n.tipe = 6 AND n.id_pelanggan IS NULL AND TRIM(COALESCE(n.phone, '')) <> '';

-- Fallback: phone sudah berisi id_pelanggan (angka pendek)
UPDATE notif
SET id_pelanggan = CAST(phone AS UNSIGNED)
WHERE id_pelanggan IS NULL
  AND phone REGEXP '^[0-9]+$'
  AND CHAR_LENGTH(phone) BETWEEN 1 AND 10
  AND CAST(phone AS UNSIGNED) > 0;

-- VERIFIKASI (harus 0 baris bermasalah sebelum lanjut):
-- SELECT tipe, COUNT(*) FROM notif WHERE id_pelanggan IS NULL OR id_pelanggan = 0 GROUP BY tipe;

ALTER TABLE notif DROP COLUMN phone;

ALTER TABLE notif
  ADD INDEX idx_notif_id_pelanggan (id_pelanggan),
  ADD INDEX idx_notif_pending (state, insertTime);
