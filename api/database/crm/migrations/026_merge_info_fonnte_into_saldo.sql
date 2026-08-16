-- CRM mdl_main (API db 0 / laundry db 100)
-- Gabung INFO_FONNTE ke intent SALDO (setelah 024/025).
-- Pattern lama INFO_FONNTE dipindah. Intent lama dinonaktifkan.
-- Jalankan manual di mdl_main.

UPDATE wa_autoreply_intents
SET ai_prompt = 'Perintah admin cek saldo/kuota. IAK jika iak. Tokopay jika tokopay. YCloud jika ycloud. DeepSeek jika deepseek. Fonnte jika fonnte (profil + kuota). Bukan tarik saldo. Bukan pelanggan.',
    note = 'gabungan SALDO_IAK + SALDO_TOKOPAY + SALDO_YCLOUD + DeepSeek + INFO_FONNTE; handleSaldo',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'SALDO';

UPDATE wa_autoreply_patterns p
INNER JOIN wa_autoreply_intents oldi ON oldi.id = p.intent_id
INNER JOIN wa_autoreply_intents neu ON neu.code = 'SALDO'
SET p.intent_id = neu.id
WHERE oldi.code = 'INFO_FONNTE';

INSERT INTO wa_autoreply_patterns (intent_id, pattern, sort_order, is_active, note)
SELECT neu.id, '/saldo\\s+fonnte/i', 50, 1, 'saldo fonnte'
FROM wa_autoreply_intents neu
WHERE neu.code = 'SALDO'
  AND NOT EXISTS (
    SELECT 1
    FROM wa_autoreply_patterns p
    WHERE p.intent_id = neu.id
      AND p.pattern LIKE '%saldo%fonnte%'
  );

UPDATE wa_autoreply_intents
SET is_active = 0,
    note = CONCAT(COALESCE(note, ''), ' [digabung ke SALDO]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'INFO_FONNTE';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
