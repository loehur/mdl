-- Request Kurir Sameday / Instant (customer) — jalankan di database mdl_laundry
-- Riwayat selesai tetap di delivery_riwayat.
-- kas jenis_transaksi = 10 → ongkir Kurir Instant (QRIS).

CREATE TABLE IF NOT EXISTS `delivery_request` (
  `id_request` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sumber` ENUM('customer') NOT NULL DEFAULT 'customer',
  `jenis` ENUM('antar','jemput') NOT NULL,
  `delivery_status` ENUM('menunggu_pembayaran','berjalan','selesai','batal') NOT NULL DEFAULT 'berjalan',
  `id_pelanggan` INT NOT NULL,
  `phone_tail` VARCHAR(9) NOT NULL,
  `id_cabang` INT NOT NULL,
  `id_lokasi` INT UNSIGNED NULL,
  `lokasi_nama` VARCHAR(50) NULL,
  `lokasi_detail` VARCHAR(255) NULL,
  `lokasi_latt` DECIMAL(10,7) NULL,
  `lokasi_longt` DECIMAL(10,7) NULL,
  `tarif_surcas` INT UNSIGNED NULL DEFAULT NULL,
  `courier_company` VARCHAR(50) NULL DEFAULT NULL,
  `courier_type` VARCHAR(50) NULL DEFAULT NULL,
  `courier_name` VARCHAR(100) NULL DEFAULT NULL,
  `ongkir` INT UNSIGNED NULL DEFAULT NULL,
  `payment_ref_finance` VARCHAR(50) NULL DEFAULT NULL,
  `biteship_order_id` VARCHAR(64) NULL DEFAULT NULL,
  `biteship_status` VARCHAR(50) NULL DEFAULT NULL,
  `waybill_id` VARCHAR(100) NULL DEFAULT NULL,
  `tracking_url` VARCHAR(255) NULL DEFAULT NULL,
  `driver_name` VARCHAR(100) NULL DEFAULT NULL,
  `driver_phone` VARCHAR(30) NULL DEFAULT NULL,
  `id_karyawan` INT NULL,
  `nama_karyawan` VARCHAR(100) NULL,
  `catatan_batal` TEXT NULL,
  `insertTime` DATETIME NOT NULL,
  `selesaiTime` DATETIME NULL,
  PRIMARY KEY (`id_request`),
  KEY `idx_status` (`delivery_status`),
  KEY `idx_pelanggan` (`id_pelanggan`),
  KEY `idx_jenis_status` (`jenis`, `delivery_status`),
  KEY `idx_phone` (`phone_tail`),
  KEY `idx_lokasi` (`id_lokasi`),
  KEY `idx_payment_ref` (`payment_ref_finance`),
  KEY `idx_biteship_order` (`biteship_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `delivery_request_item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_request` INT UNSIGNED NOT NULL,
  `id_penjualan` INT NOT NULL,
  `no_ref` VARCHAR(50) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_req_sale` (`id_request`, `id_penjualan`),
  KEY `idx_request` (`id_request`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
