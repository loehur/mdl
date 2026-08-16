-- CRM mdl_main (API db 0 / laundry db 100)
-- Tambah cabang DeepSeek ke intent SALDO (setelah 024).
-- Jalankan manual di mdl_main.

UPDATE wa_autoreply_intents
SET ai_prompt = 'Perintah admin cek saldo. IAK jika ada kata iak. Tokopay jika tokopay. YCloud jika ycloud. DeepSeek jika deepseek. Bukan tarik saldo. Bukan pelanggan.',
    note = 'gabungan SALDO_IAK + SALDO_TOKOPAY + SALDO_YCLOUD + DeepSeek; handleSaldo',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'SALDO';

INSERT INTO wa_autoreply_patterns (intent_id, pattern, sort_order, is_active, note)
SELECT neu.id, '/saldo\\s+deep\\s*seek/i', 40, 1, 'saldo deepseek'
FROM wa_autoreply_intents neu
WHERE neu.code = 'SALDO'
  AND NOT EXISTS (
    SELECT 1
    FROM wa_autoreply_patterns p
    WHERE p.intent_id = neu.id
      AND p.pattern LIKE '%deep%seek%'
  );

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
