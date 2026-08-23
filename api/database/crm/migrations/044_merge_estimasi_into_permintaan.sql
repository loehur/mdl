-- CRM mdl_main (API db 0 / laundry db 100)
-- Gabung intent ESTIMASI_SELESAI → PERMINTAAN (regex pattern saja).
-- ai_prompt PERMINTAAN di-update manual di CRM.
-- Drop wa_estimasi_session (state lama estimasi selesai).
-- Jalankan manual di mdl_main.

UPDATE wa_autoreply_patterns p
INNER JOIN wa_autoreply_intents oldi ON oldi.id = p.intent_id
INNER JOIN wa_autoreply_intents neu ON neu.code = 'PERMINTAAN'
SET p.intent_id = neu.id,
    p.updated_at = CURRENT_TIMESTAMP
WHERE oldi.code = 'ESTIMASI_SELESAI';

UPDATE wa_autoreply_intents
SET note = CONCAT(COALESCE(note, ''), ' [pattern dipindah ke PERMINTAAN]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'PERMINTAAN';

UPDATE wa_autoreply_intents
SET is_active = 0,
    note = CONCAT(COALESCE(note, ''), ' [digabung ke PERMINTAAN, pattern saja]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'ESTIMASI_SELESAI';

DROP TABLE IF EXISTS wa_estimasi_session;

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
