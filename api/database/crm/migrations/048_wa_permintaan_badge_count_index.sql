-- CRM mdl_main: percepat angka badge notifikasi PERMINTAAN per cabang.
-- Jalankan sekali secara manual pada database CRM.
ALTER TABLE wa_permintaan_session
  ADD KEY idx_permintaan_cabang_status_notify (id_cabang, status, notify_expires_at);
