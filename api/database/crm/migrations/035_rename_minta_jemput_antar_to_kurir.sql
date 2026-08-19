-- CRM mdl_main (API db 0 / laundry db 100)
-- Rename intent MINTA_JEMPUT_ANTAR → KURIR (handler: handleKurir di WARepliesKurirTrait).
-- Jalankan manual di mdl_main.

UPDATE wa_autoreply_intents
SET code = 'KURIR',
    note = CONCAT(COALESCE(note, ''), ' [rename dari MINTA_JEMPUT_ANTAR]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'MINTA_JEMPUT_ANTAR';

-- Referensi silang di ai_prompt intent lain
UPDATE wa_autoreply_intents
SET ai_prompt = REPLACE(ai_prompt, 'MINTA_JEMPUT_ANTAR', 'KURIR'),
    updated_at = CURRENT_TIMESTAMP
WHERE ai_prompt LIKE '%MINTA_JEMPUT_ANTAR%';

-- ai_prompt intent KURIR sendiri (nama blok lama di teks)
UPDATE wa_autoreply_intents
SET ai_prompt = REPLACE(ai_prompt, '=== MINTA_JEMPUT_ANTAR ===', '=== KURIR ==='),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'KURIR'
  AND ai_prompt LIKE '%=== MINTA_JEMPUT_ANTAR ===%';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
