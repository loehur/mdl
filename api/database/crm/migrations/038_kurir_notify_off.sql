-- CRM mdl_main: matikan notify intent KURIR (tanpa notif group/kartu CS).
-- Jalankan setelah 037_rename_terke_to_kurir.sql (atau kapan saja jika code sudah KURIR).

UPDATE wa_autoreply_intents
SET notify = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'KURIR';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
