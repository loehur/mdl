-- Simpan stateid Fonnte: update delivered/read sering datang tanpa id, hanya stateid.
ALTER TABLE wa_fonnte_messages_out
  ADD COLUMN fonnte_stateid VARCHAR(64) NULL DEFAULT NULL AFTER fonnte_message_id,
  ADD KEY idx_fonnte_out_stateid (fonnte_stateid);
