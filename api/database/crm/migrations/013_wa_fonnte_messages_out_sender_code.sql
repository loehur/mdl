-- CRM mdl_main (db 0): tandai outbound Fonnte human vs autoreply (AR)
ALTER TABLE wa_fonnte_messages_out
  ADD COLUMN sender_code VARCHAR(32) NULL DEFAULT NULL AFTER source,
  ADD KEY idx_fonnte_out_sender_code (sender_code);
