<?php
/**
 * Rumus ongkir sameday (Portal J kurir, Delivery, WA bot).
 *
 * Jika km < free_km → 0 (gratis)
 * Selain itu → max(min_tarif, round(km × rate_per_km))
 *
 * Sumber tunggal — laundry memanggil GET /Laundry/AntarTarif/config (api.nalju.com).
 * Edit angka di bawah saja; tidak perlu ubah kode helper.
 */
return [
    /** Jarak di bawah ini (km, tidak termasuk batas) gratis */
    'free_km' => 0.2,

    /** Minimal ongkir (Rp) jika di atas free_km */
    'min_tarif' => 5000,

    /** Tarif per kilometer (Rp) */
    'rate_per_km' => 1500,
];
