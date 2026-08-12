-- Tambah assignment cabang ke wa_fonnte_conversations (jika 011 sudah dijalankan versi lama)
-- Jalankan baris yang belum ada saja jika ada error "Duplicate column name"

ALTER TABLE wa_fonnte_conversations
  ADD COLUMN assigned_user_id INT NULL DEFAULT NULL AFTER contact_name;

ALTER TABLE wa_fonnte_conversations
  ADD COLUMN code VARCHAR(16) NULL DEFAULT NULL AFTER assigned_user_id;

ALTER TABLE wa_fonnte_conversations
  ADD COLUMN cust_id INT NULL DEFAULT NULL AFTER code;

ALTER TABLE wa_fonnte_conversations
  ADD COLUMN last_message_at DATETIME NULL DEFAULT NULL AFTER last_out_at;

ALTER TABLE wa_fonnte_conversations
  ADD INDEX idx_fonnte_conv_assigned (assigned_user_id);

ALTER TABLE wa_fonnte_conversations
  ADD INDEX idx_fonnte_conv_last_msg (last_message_at);
