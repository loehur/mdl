-- Dedup pesan masuk Fonnte/Baileys by WhatsApp message key id (bukan inboxid lokal).

ALTER TABLE wa_fonnte_messages_in
  ADD COLUMN wa_message_id VARCHAR(128) NULL DEFAULT NULL AFTER inboxid,
  ADD UNIQUE KEY uq_fonnte_in_wamid (wa_message_id);
