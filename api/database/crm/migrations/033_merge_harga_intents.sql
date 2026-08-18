-- CRM mdl_main (API db 0 / laundry db 100)
-- Gabung HARGA + HARGA_PAKET + HARGA_PAKET_D → satu intent HARGA unified.
-- Pattern lama dipindah ke HARGA. Intent lama dinonaktifkan.
-- Jalankan manual di mdl_main SETELAH 032_wa_harga_session.sql.

-- Pastikan intent HARGA ada & aktif
UPDATE wa_autoreply_intents
SET is_active = 1,
    note = COALESCE(NULLIF(TRIM(note), ''), 'unified HARGA per item + paket/member'),
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'HARGA';

-- Prompt AI unified (ganti prompt HARGA lama)
-- Versi ringkas — lihat juga 034_update_harga_ai_prompt.sql untuk revisi terbaru.
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
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'HARGA';

-- Pindahkan semua regex pattern dari HARGA_PAKET & HARGA_PAKET_D ke HARGA
UPDATE wa_autoreply_patterns p
INNER JOIN wa_autoreply_intents oldi ON oldi.id = p.intent_id
INNER JOIN wa_autoreply_intents neu ON neu.code = 'HARGA'
SET p.intent_id = neu.id
WHERE oldi.code IN ('HARGA_PAKET', 'HARGA_PAKET_D');

-- Nonaktifkan intent lama (handler PHP tetap delegasi legacy → HARGA unified)
UPDATE wa_autoreply_intents
SET is_active = 0,
    note = CONCAT(COALESCE(note, ''), ' [digabung ke HARGA unified]'),
    updated_at = CURRENT_TIMESTAMP
WHERE code IN ('HARGA_PAKET', 'HARGA_PAKET_D');

-- Bump cache keyword loader
INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
