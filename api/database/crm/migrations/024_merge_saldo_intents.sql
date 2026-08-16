-- CRM mdl_main (API db 0 / laundry db 100)
-- Gabung SALDO_IAK + SALDO_TOKOPAY + SALDO_YCLOUD → satu intent SALDO.
-- Pattern lama dipindah (tidak dibuat baru). Intent lama dinonaktifkan.
-- Jalankan manual di mdl_main.

INSERT INTO wa_autoreply_intents (
  code, sort_order, case_value, notify, ai_prompt, is_active,
  is_admin, is_karyawan, is_pelanggan, note
)
SELECT
  'SALDO',
  COALESCE((
    SELECT MIN(sort_order) FROM wa_autoreply_intents
    WHERE code IN ('SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD')
  ), 90),
  NULL,
  NULL,
  'Perintah admin cek saldo. IAK jika ada kata iak. Tokopay jika tokopay. YCloud jika ycloud. DeepSeek jika deepseek. Bukan tarik saldo. Bukan pelanggan.',
  1,
  1,
  0,
  0,
  'gabungan SALDO_IAK + SALDO_TOKOPAY + SALDO_YCLOUD'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM wa_autoreply_intents WHERE code = 'SALDO'
);

UPDATE wa_autoreply_intents
SET is_active = 1,
    is_admin = 1,
    is_karyawan = 0,
    is_pelanggan = 0,
    note = 'gabungan SALDO_IAK + SALDO_TOKOPAY + SALDO_YCLOUD; handleSaldo',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'SALDO';

UPDATE wa_autoreply_intents neu
LEFT JOIN (
  SELECT TRIM(BOTH '\n' FROM GROUP_CONCAT(ai_prompt SEPARATOR '\n')) AS prompts
  FROM wa_autoreply_intents
  WHERE code IN ('SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD')
    AND ai_prompt IS NOT NULL
    AND TRIM(ai_prompt) <> ''
) oldp ON 1 = 1
SET neu.ai_prompt = COALESCE(
  NULLIF(TRIM(neu.ai_prompt), ''),
  NULLIF(TRIM(oldp.prompts), ''),
  'Perintah admin cek saldo. IAK jika ada kata iak. Tokopay jika tokopay. YCloud jika ycloud. DeepSeek jika deepseek. Bukan tarik saldo. Bukan pelanggan.'
)
WHERE neu.code = 'SALDO';

UPDATE wa_autoreply_patterns p
INNER JOIN wa_autoreply_intents oldi ON oldi.id = p.intent_id
INNER JOIN wa_autoreply_intents neu ON neu.code = 'SALDO'
SET p.intent_id = neu.id
WHERE oldi.code IN ('SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD');

UPDATE wa_autoreply_intents
SET is_active = 0,
    note = CONCAT(COALESCE(note, ''), ' [digabung ke SALDO]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code IN ('SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD');

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
