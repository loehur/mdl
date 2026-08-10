-- CRM mdl_main: filter notifikasi estimasi per cabang
-- id_cabang diambil dari sale.id_cabang berdasarkan id_penjualan

ALTER TABLE wa_estimasi_session
  ADD COLUMN id_cabang INT NULL DEFAULT NULL AFTER id_penjualan,
  ADD KEY idx_id_cabang (id_cabang);
