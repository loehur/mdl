-- Request Kurir Sameday (customer) — jalankan di database mdl_laundry
-- Riwayat selesai tetap di delivery_riwayat.

CREATE TABLE IF NOT EXISTS `delivery_request` (
  `id_request` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sumber` ENUM('customer') NOT NULL DEFAULT 'customer',
  `jenis` ENUM('antar','jemput') NOT NULL,
  `layanan` ENUM('sameday') NOT NULL DEFAULT 'sameday',
  `delivery_status` ENUM('berjalan','selesai','batal') NOT NULL DEFAULT 'berjalan',
  `id_pelanggan` INT NOT NULL,
  `phone_tail` VARCHAR(9) NOT NULL,
  `id_cabang` INT NOT NULL,
  `id_lokasi` INT UNSIGNED NULL,
  `lokasi_nama` VARCHAR(50) NULL,
  `lokasi_detail` VARCHAR(255) NULL,
  `lokasi_latt` DECIMAL(10,7) NULL,
  `lokasi_longt` DECIMAL(10,7) NULL,
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
  KEY `idx_lokasi` (`id_lokasi`)
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
