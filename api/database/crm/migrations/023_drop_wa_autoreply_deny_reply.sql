-- CRM mdl_main (API db 0 / laundry db 100)
-- deny_reply tidak dipakai lagi: gerbang gagal = skip intent (tanpa balasan).
-- Deploy PHP dulu, baru jalankan ALTER ini.

ALTER TABLE wa_autoreply_intents
  DROP COLUMN deny_reply;
