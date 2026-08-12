<?php
/**
 * Rumus ongkir sameday (Portal J kurir, Delivery, WA bot).
 *
 * Tarif = max(min_tarif, round(km × rate_per_km))
 *
 * Sumber tunggal — laundry memanggil GET /Laundry/AntarTarif/config (api.nalju.com).
 * Edit angka di bawah saja; tidak perlu ubah kode helper.
 */
return [
    /** Minimal ongkir (Rp), meski jarak pendek */
    'min_tarif' => 5000,

    /** Tarif per kilometer (Rp) */
    'rate_per_km' => 1000,
];
