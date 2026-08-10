<?php

/**
 * CRM mdl_main: petugas update nama pelanggan baru (#NEW#) dari WA jemput.
 * Bell langsung setelah insert pelanggan (tidak menunggu delivery_request).
 */

ALTER TABLE wa_kurir_session
  ADD COLUMN butuh_update_nama TINYINT(1) NOT NULL DEFAULT 0 AFTER estimasi_jam;

ALTER TABLE wa_kurir_session
  ADD KEY idx_kurir_butuh_update_nama (butuh_update_nama, id_cabang);
