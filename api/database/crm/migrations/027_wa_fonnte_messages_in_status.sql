-- CRM mdl_main: status baca pesan masuk Fonnte (received / read)
-- Baris lama dianggap sudah dibaca agar tidak membanjiri unread.

ALTER TABLE wa_fonnte_messages_in
  ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'read' AFTER created_at;

UPDATE wa_fonnte_messages_in SET status = 'read' WHERE status = '' OR status IS NULL;

ALTER TABLE wa_fonnte_messages_in
  ADD KEY idx_fonnte_in_status (status);
