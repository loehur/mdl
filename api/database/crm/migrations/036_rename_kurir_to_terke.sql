-- CRM mdl_main (API db 0 / laundry db 100)
-- Rename intent KURIR → TERKE (uji: apakah AI klasifikasi dari nama blok vs isi ai_prompt).
-- Jalankan manual di mdl_main SETELAH deploy kode handleTerke.

UPDATE wa_autoreply_intents
SET code = 'TERKE',
    note = CONCAT(COALESCE(note, ''), ' [rename dari KURIR]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'KURIR';

-- Referensi silang di ai_prompt intent lain
UPDATE wa_autoreply_intents
SET ai_prompt = REPLACE(ai_prompt, 'KURIR', 'TERKE'),
    updated_at = CURRENT_TIMESTAMP
WHERE ai_prompt LIKE '%KURIR%';

-- ai_prompt intent TERKE (blok header lama di teks)
UPDATE wa_autoreply_intents
SET ai_prompt = REPLACE(ai_prompt, '=== KURIR ===', '=== TERKE ==='),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'TERKE'
  AND ai_prompt LIKE '%=== KURIR ===%';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
