<?php 
/**
 * Auto Reply Keywords Configuration
 * Format: [handler_name => ['max_length' => int, 'keywords' => [...]]]
 * max_length: Maksimal panjang pesan (dalam karakter) untuk trigger handler ini
 * Set 0 untuk unlimited (tidak ada batasan panjang)
 */
return [    
    'bon' => [
        'max_length' => 100,
        'keywords' => [
            'atas nama', 'ats nama', 'atas nma', 'an.',
            'bon', 'struk', 'nota', 'bill', 'kirim', 'tagihan', 'resi',
            'total laundry', 'total londri', 'total laundri',
            'totl laundry', 'totl londri', 'totl laundri', 'berapa total',
            'brp total', 'brp totl', 'brpa total'
        ]
    ],

    'status' => [
        'max_length' => 100,
        'keywords' => [
            'cek', 'status',
            'dh siap', 'da siap', 'dah siap', 'dh selsesai', 'da selsesai', 'dah selsesai',
            'dh beres', 'da beres', 'dah beres',
            'dh selesai', 'da selesai', 'dah selesai',
            'bs diambil', 'bs di ambil', 'bisa diambil', 'bisa di ambil',
            'bs dijemput', 'bisa dijemput', 'bs di jemput', 'bisa di jemput',
            'kpn siap', 'kapan siap', 'kpn selesai', 'kapan selesai'
        ]
    ],
    
    'buka' => [
        'max_length' => 30,
        'keywords' => [
            // Tutup variations
            'brp tutup', 'berapa tutup', 'jam tutup', 'kapan tutup', 'kpn buka', 'masih tutup',
            'udah tutup', 'dh tutup', 'uda tutup', 'dah tutup', 'sudah tutup', 'da tutup',
            // Buka variations
            'jam buka', 'brp buka', 'berapa buka', 'kapan buka', 
            'udah buka', 'dh buka', 'uda buka', 'dah buka', 'sudah buka', 'da buka',
            'masih buka', 'msh buka', 'masih bukak', 'msh bukak'
        ]
    ],

    'sapa' => [
        'max_length' => 20,
        'keywords' => [
            'ping', 'halo', 'hallo', 'hello', 'tes', 'alaikum', 'assalam', 'aslkm', 'salam', 'pagi', 'sore', 'malam', 'mlm', 'malm', 'mlam'
        ]
    ],

    'penutup' => [
        'max_length' => 20,
        'keywords' => [
            'makasih', 'makasi', 'maksh', 'mksih', 'trimakasih', 'terima kasih', 'terimakasih', 'trimaksih', 'trimaksh', 
            'mksih', 'mksh', 'trmksh', 'tks', 'thx', 'thanks', 'thank you', 
            'tengkyu', 'thks', 'thnx', 'ok', 'oce', 'sip', 'siap', 'baik', 
        ]
    ]
];
