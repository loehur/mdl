-- Simpan status koneksi asli phone number dari Meta (CONNECTED, PENDING,
-- DISCONNECTED, BANNED/RESTRICTED, dll). `wa_channels.status` tetap
-- active/inactive — active hanya ketika Meta melaporkan CONNECTED.
ALTER TABLE wa_channels
  ADD COLUMN meta_provider_status VARCHAR(32) NULL AFTER meta_phone_number_id;
