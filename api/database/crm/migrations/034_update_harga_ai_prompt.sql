-- CRM mdl_main — perbarui ai_prompt intent HARGA (versi ringkas, unified).
-- Jalankan SETELAH 033_merge_harga_intents.sql (atau kapan saja untuk replace prompt lama).
--
-- CEK DULU (copy-paste di phpMyAdmin / mysql CLI):
--
-- SELECT code, is_active, CHAR_LENGTH(ai_prompt) AS prompt_len, LEFT(ai_prompt, 120) AS preview
-- FROM wa_autoreply_intents
-- WHERE code IN ('HARGA', 'HARGA_PAKET', 'HARGA_PAKET_D')
-- ORDER BY code;
--
-- SELECT code, COUNT(p.id) AS pattern_count
-- FROM wa_autoreply_intents i
-- LEFT JOIN wa_autoreply_patterns p ON p.intent_id = i.id AND p.is_active = 1
-- WHERE i.code IN ('HARGA', 'HARGA_PAKET', 'HARGA_PAKET_D')
-- GROUP BY i.code;

UPDATE wa_autoreply_intents
SET ai_prompt = '=== HARGA ===

Pertanyaan TARIF / HARGA / BIAYA / ONGKOS laundry (semua varian masuk intent ini).

TRUE:
- Per item atau per kilo (baju, boneka, bedcover, per kg, dll.)
- Pricelist / daftar harga / list harga
- Paket, member, bulanan, langganan, deposit
- Ongkos + durasi atau tier layanan (regular, ekspres/1 hari, kilat) = tarif SLA, bukan minta kurir
- Sebut antar/jemput/delivery dalam konteks tanya harga = tetap HARGA

FALSE:
- Barang ritel: parfum, plastik, pewangi, hanger, tissue
- Minta kurir jemput/antar ke alamat tanpa tanya tarif
- Tanya ongkir antar-jemput saja (tanpa regular/ekspres/kilat/durasi hari)
- Tanya berat/kilo order tanpa kata harga/biaya
- Instruksi treatment order ("setrika aja") tanpa kata harga/paket/member

Catatan: Filter layanan, durasi, delivery (-D), paket vs per-item di-handle backend — AI cukup klasifikasi HARGA.',
    note = 'ai_prompt ringkas unified HARGA',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'HARGA';

-- Kosongkan prompt intent lama (sudah nonaktif) agar tidak ikut terbaca saat rebuild prompt
UPDATE wa_autoreply_intents
SET ai_prompt = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE code IN ('HARGA_PAKET', 'HARGA_PAKET_D')
  AND is_active = 0;

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
