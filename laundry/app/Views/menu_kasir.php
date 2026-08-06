<?php
$menu[0] = [
    [
        'c' => '',
        'title' => 'Data Order',
        'icon' => 'far fa-clock',
        'txt' => 'Data Order',
        'submenu' =>
        [
            [
                'c' => 'Antrian/index/1',
                'title' => 'Data Order',
                'txt' => 'Laundry',
            ],
            [
                'c' => 'Sales',
                'title' => 'Sales Order',
                'txt' => 'Sales',
            ],
        ]
    ],
    [
        'c' => '',
        'title' => 'Data Pelanggan',
        'icon' => 'fas fa-book',
        'txt' => 'Data Pelanggan',
        'submenu' =>
        [
            [
                'c' => 'Data_List/i/pelanggan',
                'title' => 'Data Pelanggan',
                'txt' => 'Pelanggan',
            ],
            [
                'c' => 'Member/tampil_rekap',
                'title' => 'List Deposit Member',
                'txt' => 'List Saldo Paket',
            ],
            [
                'c' => 'Member/tambah_paket/0',
                'title' => '(+) Deposit Member',
                'txt' => 'Topup Saldo Paket',
            ],
            [
                'c' => 'SaldoTunai/tampil_rekap',
                'title' => 'List Deposit Tunai',
                'txt' => 'List Saldo Deposit',
            ],
            [
                'c' => 'SaldoTunai/tambah',
                'title' => '(+) Deposit Tunai',
                'txt' => 'Topup Saldo Deposit',
            ],
        ]
    ],
    [
        'c' => '',
        'title' => 'Karyawan',
        'icon' => 'fas fa-users-cog',
        'txt' => 'Karyawan',
        'submenu' =>
        [
            [
                'c' => 'Absen',
                'title' => 'Karyawan Absen',
                'txt' => 'Absen Harian',
            ],
            [
                'c' => 'Kinerja/index/0',
                'title' => 'Karyawan - Kinerja Harian',
                'txt' => 'Kinerja Harian',
            ],
            [
                'c' => 'Kinerja/index/1',
                'title' => 'Karyawan - Kinerja Bulanan',
                'txt' => 'Kinerja Bulanan',
            ],
            [
                'c' => 'Pindah_Outlet',
                'title' => 'Karyawan Pindah Outlet',
                'txt' => 'Pindah Outlet',
            ],
            [
                'c' => 'Karyawan/data',
                'title' => 'Data Karyawan',
                'txt' => 'Data Rekening',
            ],
        ]
    ],
    [
        'c' => '#',
        'title' => 'Tiket',
        'icon' => 'fas fa-ticket-alt',
        'txt' => 'Tiket',
        'submenu' => [
            [
                'c' => '@Tiket/i/0',
                'title' => 'Tiket Proses',
                'txt' => 'Proses',
            ],
            [
                'c' => '@Tiket/i/1',
                'title' => 'Tiket Selesai',
                'txt' => 'Selesai',
            ],
        ]
    ],
    [
        'c' => 'Operan',
        'title' => 'Operan',
        'icon' => 'fas fa-random',
        'txt' => 'Operan',
        'show_if_multi_cabang' => true, // Only show if listCabang > 1
        'hide_if_training' => true,
    ],
    [
        'c' => 'Kas',
        'title' => 'Kas Kasir',
        'icon' => 'fas fa-wallet',
        'txt' => 'Kas Kasir',
    ],
    [
        'c' => 'Operasi',
        'title' => 'Operasi Order',
        'icon' => 'fas fa-tasks',
        'txt' => 'Laundry Operasi',
        'submenu' =>
        [
            [
                'c' => '/i/0/0',
                'title' => 'Operasi Order Proses',
                'txt' => 'Proses',
            ],
            [
                'c' => '/i/1/0',
                'title' => 'Operasi Order Tuntas',
                'txt' => 'Tuntas',
            ],
            [
                'c' => '@Antrian/p/100',
                'title' => 'Data Piutang',
                'txt' => 'Piutang',
            ],
        ]
    ],
     [
        'c' => '',
        'title' => 'Sales Operasi',
        'icon' => 'fas fa-file-invoice',
        'txt' => 'Sales Operasi',
        'submenu' =>
        [
            [
                'c' => 'Stok',
                'title' => 'Stok Barang',
                'txt' => 'Stok',
            ],
            [
                'c' => 'Sales/operasi_pakai',
                'title' => 'Barang Dipakai',
                'txt' => 'Pakai',
            ],
            [
                'c' => 'Sales/operasi_transfer',
                'title' => 'Transfer Barang',
                'txt' => 'Transfer',
            ],
            [
                'c' => 'Sales/operasi_piutang',
                'title' => 'Daftar Piutang',
                'txt' => 'Piutang',
            ],
            [
                'c' => 'Sales/operasi_tuntas',
                'title' => 'Order Selesai',
                'txt' => 'Tuntas',
            ],
        ]
    ],
    [
        'c' => 'Filter',
        'title' => 'Data Filter',
        'icon' => 'fas fa-filter',
        'txt' => 'Data Filter',
        'submenu' =>
        [
            [
                'c' => '/i/2',
                'title' => 'Data Filter Pengantaran',
                'txt' => 'Pengantaran',
            ],
            [
                'c' => '/i/1',
                'title' => 'Data Filter Pengambilan',
                'txt' => 'Pengambilan',
            ],
            [
                'c' => '@Prepaid',
                'title' => 'Pre/Post Paid',
                'txt' => 'Pre/Post Paid',
            ],
        ]
    ],
    [
        'c' => '',
        'title' => 'Setting',
        'icon' => 'fas fa-cog',
        'txt' => 'Setting',
        'submenu' =>
        [
            [
                'c' => 'Setting/printer',
                'title' => 'Printer Setting',
                'txt' => 'Printer',
            ],
            [
                'c' => 'Setting/android',
                'title' => 'Android',
                'txt' => 'Android',
            ],
        ]
    ],
];
