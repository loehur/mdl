-- CRM mdl_main (API db 0 / laundry db 100)
-- Intent PEMBERITAHUAN: ack singkat info customer (tanpa pertanyaan / tanpa minta aksi).
-- Jalankan manual di DB production/staging. Runtime baca dari wa_autoreply_intents.

INSERT INTO wa_autoreply_intents (code, sort_order, case_value, notify, ai_prompt, is_active, note)
SELECT
  'PEMBERITAHUAN',
  16,
  NULL,
  0,
  'Pemberitahuan/info dari customer TANPA pertanyaan dan TANPA minta aksi CS. Contoh: otw, daftar item laundry, sudah diantar suami/saya, belum diambil, janji nanti transfer/bayar, kami yang antar/jemput sendiri, jadwal yg tadi sore besok di ambil. Bukan PENUTUP (PENUTUP hanya terima kasih / sudah bayar / ack murni ok-sip). Bukan PERMINTAAN. Bukan MINTA_JEMPUT_ANTAR.',
  1,
  'Ack Baik/Ok/Oke + sapaan + emote; handlePemberitahuan'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM wa_autoreply_intents WHERE code = 'PEMBERITAHUAN'
);

UPDATE wa_autoreply_intents
SET is_active = 1,
    notify = 0,
    ai_prompt = 'Pemberitahuan/info dari customer TANPA pertanyaan dan TANPA minta aksi CS. Contoh: otw, daftar item laundry, sudah diantar suami/saya, belum diambil, janji nanti transfer/bayar, kami yang antar/jemput sendiri, jadwal yg tadi sore besok di ambil. Bukan PENUTUP (PENUTUP hanya terima kasih / sudah bayar / ack murni ok-sip). Bukan PERMINTAAN. Bukan MINTA_JEMPUT_ANTAR.',
    note = 'Ack Baik/Ok/Oke + sapaan + emote; handlePemberitahuan',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'PEMBERITAHUAN';

INSERT INTO wa_autoreply_meta (meta_key, meta_value)
VALUES ('cache_version', '1')
ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1;
