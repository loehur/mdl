-- CRM mdl_main (API db 0 / laundry db 100)
-- Hapus alternatif keyword "cek" dari intent SALDO (dan intent saldo legacy sebelum merge 024).
-- Format perintah admin: "saldo iak", "info tokopay", dll — bukan "cek iak" (bentrok STATUS/CEK_QRIS).
-- Jalankan manual di mdl_main.

UPDATE wa_autoreply_patterns p
INNER JOIN wa_autoreply_intents i ON i.id = p.intent_id
SET p.pattern = REPLACE(p.pattern, '(saldo|cek|info)', '(saldo|info)'),
    p.updated_at = CURRENT_TIMESTAMP
WHERE i.code IN ('SALDO', 'SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD')
  AND p.pattern LIKE '%(saldo|cek|info)%';

UPDATE wa_autoreply_patterns p
INNER JOIN wa_autoreply_intents i ON i.id = p.intent_id
SET p.pattern = REPLACE(p.pattern, '(cek|info)', '(saldo|info)'),
    p.updated_at = CURRENT_TIMESTAMP
WHERE i.code IN ('SALDO', 'INFO_FONNTE')
  AND p.pattern LIKE '%(cek|info)%';

UPDATE wa_autoreply_intents
SET ai_prompt = REPLACE(ai_prompt, 'admin cek saldo', 'admin lihat saldo'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'SALDO'
  AND ai_prompt LIKE '%admin cek saldo%';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
