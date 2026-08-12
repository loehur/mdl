<?php

/**
 * Kata sapaan yang dihitung untuk statistik outbound CRM (agent human → customer).
 * Autoreply (sender_code AR) tidak dihitung. Token utuh saja: "butuh" ≠ "bu"; "bu," boleh.
 * Tambahkan entri baru di array ini bila perlu; urutan tidak penting (akan disort panjang ↓ di helper).
 *
 * Disarankan UNIQUE (wa_number, sapaan) di DB agar tidak duplikat baris.
 */
return [
    'keywords' => [
        'pak',
        'bu',
        'buk',
        'kak',
        'kk',
        'bg',
        'bang',
        'mbak',
        'mas',
        'om',
        'nte',
        'ko',
        'ce',
        'cece',
        'tante', // tante
    ],
];
