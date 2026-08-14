-- CRM mdl_main (API db 0 / laundry db 100)
-- Gerbang role per intent + teks jika gerbang tidak terpenuhi.
-- Jalankan manual di mdl_main sebelum pakai form AutoReplyKeywords.
-- is_* = 1 → pengirim wajib role itu. Semua 0 = publik.
-- deny_reply: balasan WA jika gerbang gagal. NULL/kosong = diam (jangan bocor intent admin).
-- Runtime filter belum aktif; isi dulu via laundry Admin → Tools → Auto Reply Keywords.

ALTER TABLE wa_autoreply_intents
  ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=wajib pengirim admin' AFTER notify,
  ADD COLUMN is_karyawan TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=wajib pengirim karyawan' AFTER is_admin,
  ADD COLUMN is_pelanggan TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=wajib pengirim pelanggan' AFTER is_karyawan,
  ADD COLUMN deny_reply TEXT NULL COMMENT 'Balasan jika gerbang role tidak terpenuhi; kosong=diam' AFTER is_pelanggan;
