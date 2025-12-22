<?php 
/**
 * Auto Reply Keywords Configuration
 * Format: [handler_name => ['max_length' => int, 'keywords' => [...]]]
 * max_length: Maksimal panjang pesan (dalam karakter) untuk trigger handler ini
 * Set 0 untuk unlimited (tidak ada batasan panjang)
 */
return [    
    'status' => [
        'max_length' => 50,
        'keywords' => [
            'cek', 'status',
            'udh siap', 'dh siap', 'uda siap', 'dah siap', 'udah siap', 'sudah siap',
            'udh beres', 'dh beres', 'uda beres', 'dah beres', 'udah beres', 'sudah beres',
            'udh selesai', 'dh selesai', 'uda selesai', 'dah selesai', 'udah selesai', 'sudah selesai',
            'bs diambil', 'bs di ambil', 'bisa diambil', 'bisa di ambil',
            'bs dijemput', 'bisa dijemput', 'bs di jemput', 'bisa di jemput',
            'kpn siap', 'kapan siap', 'kpn selesai', 'kapan selesai'
        ]
    ],

    'bon' => [
        'max_length' => 50,
        'keywords' => [
            'atas nama', 'ats nama', 'atas nma',
            'bon', 'struk', 'nota', 'bill', 'kirim', 'tagihan', 'resi',
            'total laundry', 'total londri', 'total laundri',
            'totl laundry', 'totl londri', 'totl laundri', 'berapa total',
            'brp total', 'brp totl'
        ]
    ],
    
    'buka' => [
        'max_length' => 30,
        'keywords' => [
            'jam brp tutup', 'jam berapa tutup',
            'udah tutup', 'dh tutup', 'uda tutup', 'dah tutup', 'sudah tutup', 'da tutup',
            'jam brp buka', 'jam berapa buka',
            'udah buka', 'dh buka', 'uda buka', 'dah buka', 'sudah buka', 'da buka',
            'masih buka', 'msh buka', 'masih bukak', 'msh bukak'
        ]
    ],

    'sapa' => [
        'max_length' => 12,
        'keywords' => [
            'ping', 'halo', 'tes', 'alaikum', 'assalam', 'aslkm', 'salam', 'kak'
        ]
    ],

    'penutup' => [
        'max_length' => 12,
        'keywords' => [
            'makasih', 'makasi', 'trimakasih', 'terima kasih', 'terimakasih', 'trimaksih', 'trimaksh', 
            'mksih', 'mksh', 'trmksh', 'tks', 'thx', 'thanks', 'thank you', 
            'tengkyu', 'ty', 'tq', 'thks', 'thnx', 'ok', 'oke', 'okey', 'okay', 
            'oce', 'okeh', 'sip', 'siap', 'baik'
        ]
    ]
];
