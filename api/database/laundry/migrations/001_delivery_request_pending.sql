-- mdl_laundry (laundry db 0 / API db 1)
-- Status pending: request Antar diparkir dari board Delivery,
-- aktif lagi saat customer chat MINTA_JEMPUT_ANTAR jenis antar.
-- Deploy PHP dulu, baru jalankan ALTER ini.

ALTER TABLE delivery_request
  MODIFY COLUMN delivery_status ENUM(
    'berjalan',
    'menunggu_pembayaran',
    'pending',
    'selesai',
    'batal'
  ) NOT NULL DEFAULT 'berjalan';
