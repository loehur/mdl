-- CRM mdl_main (API db 0 / laundry db 100)
-- Rename intent TERKE → KURIR (kembali dari uji rename).
-- Jalankan manual di mdl_main SETELAH deploy kode handleKurir.

UPDATE wa_autoreply_intents
SET code = 'KURIR',
    note = CONCAT(COALESCE(note, ''), ' [rename dari TERKE]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'TERKE';

-- Referensi silang di ai_prompt intent lain
UPDATE wa_autoreply_intents
SET ai_prompt = REPLACE(ai_prompt, 'TERKE', 'KURIR'),
    updated_at = CURRENT_TIMESTAMP
WHERE ai_prompt LIKE '%TERKE%';

-- ai_prompt intent KURIR (blok header lama di teks)
UPDATE wa_autoreply_intents
SET ai_prompt = REPLACE(ai_prompt, '=== TERKE ===', '=== KURIR ==='),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'KURIR'
  AND ai_prompt LIKE '%=== TERKE ===%';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
