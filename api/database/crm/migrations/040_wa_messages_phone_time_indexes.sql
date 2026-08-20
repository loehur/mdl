-- Index untuk lookup last message per phone (IN exact match, bukan LIKE '%...')
-- Fonnte sudah punya idx_fonnte_in_phone_time / idx_fonnte_out_phone_time (011)
-- Jalankan baris yang belum ada saja jika ada error "Duplicate key name"

ALTER TABLE wa_messages_in
  ADD INDEX idx_wa_in_phone_time (phone, created_at);

ALTER TABLE wa_messages_out
  ADD INDEX idx_wa_out_phone_time (phone, created_at);
