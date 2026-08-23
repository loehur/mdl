-- wa_messages_out: identitas pengirim human (crew = sender_code CR + sender_id = id_user laundry)

ALTER TABLE wa_messages_out
  ADD COLUMN sender_id INT UNSIGNED NULL DEFAULT NULL AFTER sender_code,
  ADD KEY idx_wa_out_sender_id (sender_id);
