-- Intent FEE: karyawan cek fee Cuci/Malam per cabang; admin boleh menyebut ID karyawan.
INSERT INTO wa_autoreply_intents
  (code, sort_order, case_value, notify, ai_prompt, is_active, is_admin, is_karyawan, is_pelanggan, note)
SELECT 'FEE', 29, NULL, 0,
  'Karyawan meminta fee Cuci atau Jaga Malam pada cabang tertentu. Format karyawan: Fee Cuci KODE atau Fee Malam KODE. Admin boleh: Fee ID_KARYAWAN Cuci KODE atau Fee ID_KARYAWAN Malam KODE.',
  1, 1, 1, 0, 'Fee efektif Cuci/Jaga Malam per cabang'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM wa_autoreply_intents WHERE code = 'FEE');

UPDATE wa_autoreply_intents SET sort_order=29, case_value=NULL, notify=0, is_active=1,
  is_admin=1, is_karyawan=1, is_pelanggan=0,
  ai_prompt='Karyawan meminta fee Cuci atau Jaga Malam pada cabang tertentu. Format karyawan: Fee Cuci KODE atau Fee Malam KODE. Admin boleh: Fee ID_KARYAWAN Cuci KODE atau Fee ID_KARYAWAN Malam KODE.',
  note='Fee efektif Cuci/Jaga Malam per cabang', updated_at=CURRENT_TIMESTAMP WHERE code='FEE';

INSERT INTO wa_autoreply_patterns (intent_id, pattern, sort_order, is_active, note)
SELECT i.id, '/^\\s*fee(?:\\s+\\d+)?(?:\\s+(?:(?:cuci|malam)\\s+[a-z0-9_-]+|layanan))?\\s*$/iu', 10, 1, 'fee layanan atau cuci/malam cabang'
FROM wa_autoreply_intents i WHERE i.code='FEE'
AND NOT EXISTS (SELECT 1 FROM wa_autoreply_patterns p WHERE p.intent_id=i.id AND p.pattern='/^\\s*fee(?:\\s+\\d+)?(?:\\s+(?:(?:cuci|malam)\\s+[a-z0-9_-]+|layanan))?\\s*$/iu');

INSERT INTO wa_autoreply_meta (meta_key, meta_value) VALUES ('cache_version','1')
ON DUPLICATE KEY UPDATE meta_value=CAST(meta_value AS UNSIGNED)+1;
