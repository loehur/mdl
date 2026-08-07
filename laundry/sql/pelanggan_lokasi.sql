-- Lokasi customer (terikat id_pelanggan) — jalankan di mdl_laundry
-- Dipakai saat request Antar/Jemput Sameday.

CREATE TABLE IF NOT EXISTS `pelanggan_lokasi` (
  `id_lokasi` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pelanggan` INT NOT NULL,
  `nama` VARCHAR(50) NOT NULL,
  `detail` VARCHAR(255) NOT NULL,
  `latt` DECIMAL(10,7) NOT NULL,
  `longt` DECIMAL(10,7) NOT NULL,
  `insertTime` DATETIME NOT NULL,
  PRIMARY KEY (`id_lokasi`),
  KEY `idx_pelanggan` (`id_pelanggan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snapshot lokasi di request (jalankan sekali; abaikan error jika kolom sudah ada)
ALTER TABLE `delivery_request`
  ADD COLUMN `id_lokasi` INT UNSIGNED NULL AFTER `id_cabang`,
  ADD COLUMN `lokasi_nama` VARCHAR(50) NULL AFTER `id_lokasi`,
  ADD COLUMN `lokasi_detail` VARCHAR(255) NULL AFTER `lokasi_nama`,
  ADD COLUMN `lokasi_latt` DECIMAL(10,7) NULL AFTER `lokasi_detail`,
  ADD COLUMN `lokasi_longt` DECIMAL(10,7) NULL AFTER `lokasi_latt`;
