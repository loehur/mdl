-- CRM mdl_main (API db 0): intent khusus nomor karyawan untuk melihat skema gajinya sendiri.
-- Jalankan sekali pada database production/staging, lalu cache keyword akan diperbarui.

INSERT INTO wa_autoreply_intents
    (code, sort_order, case_value, notify, ai_prompt, is_active, is_admin, is_karyawan, is_pelanggan, note)
SELECT
    'SKEMA_GAJI',
    28,
    NULL,
    0,
    'Karyawan meminta skema gajinya sendiri dengan keyword "skema". Admin dapat meminta skema karyawan tertentu dengan format "skema {id_karyawan}". Berisi fee layanan, target, bonus, fee absensi, dan tunjangan. Bukan slip/total gaji periode tertentu dan bukan pertanyaan pelanggan.',
    1,
    1,
    1,
    0,
    'Skema gaji diri sendiri; admin dapat melihat berdasarkan ID karyawan'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM wa_autoreply_intents WHERE code = 'SKEMA_GAJI');

UPDATE wa_autoreply_intents
SET sort_order = 28,
    case_value = NULL,
    notify = 0,
    ai_prompt = 'Karyawan meminta skema gajinya sendiri dengan keyword "skema". Admin dapat meminta skema karyawan tertentu dengan format "skema {id_karyawan}". Berisi fee layanan, target, bonus, fee absensi, dan tunjangan. Bukan slip/total gaji periode tertentu dan bukan pertanyaan pelanggan.',
    is_active = 1,
    is_admin = 1,
    is_karyawan = 1,
    is_pelanggan = 0,
    note = 'Skema gaji diri sendiri; admin dapat melihat berdasarkan ID karyawan',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'SKEMA_GAJI';

INSERT INTO wa_autoreply_patterns (intent_id, pattern, sort_order, is_active, note)
SELECT i.id, '/^\\s*skema(?:\\s+\\d+)?\\s*$/iu', 10, 1, 'keyword skema; admin boleh skema ID'
FROM wa_autoreply_intents i
WHERE i.code = 'SKEMA_GAJI'
  AND NOT EXISTS (
      SELECT 1 FROM wa_autoreply_patterns p
      WHERE p.intent_id = i.id
        AND p.pattern = '/^\\s*skema(?:\\s+\\d+)?\\s*$/iu'
  );

UPDATE wa_autoreply_patterns p
INNER JOIN wa_autoreply_intents i ON i.id = p.intent_id
SET p.is_active = 0,
    p.updated_at = CURRENT_TIMESTAMP
WHERE i.code = 'SKEMA_GAJI'
  AND p.pattern IN (
      '/^\\s*(?:cek\\s+|info\\s+)?skema\\s+gaji(?:\\s+saya)?\\s*$/iu',
      '/^\\s*(?:cek\\s+|info\\s+)?fee\\s+gaji(?:\\s+saya)?\\s*$/iu'
  );

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
