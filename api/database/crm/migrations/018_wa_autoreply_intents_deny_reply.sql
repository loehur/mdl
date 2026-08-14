-- CRM mdl_main. Hanya jika 017 sudah dijalankan TANPA kolom deny_reply.

ALTER TABLE wa_autoreply_intents
  ADD COLUMN deny_reply TEXT NULL COMMENT 'Balasan jika gerbang role tidak terpenuhi; kosong=diam' AFTER is_pelanggan;
