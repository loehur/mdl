<?php
$menu[0] = [
    [
        'c' => 'Antrian/index/1',
        'title' => 'Data Order',
        'icon' => 'far fa-clock',
        'txt' => 'Laundry Order',
    ],
    [
        'c' => 'Sales',
        'title' => 'Sales Order',
        'icon' => 'fas fa-shopping-cart',
        'txt' => 'Sales Order',
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
        'title' => 'Deposit',
        'icon' => 'fas fa-book',
        'txt' => 'Saldo Pelanggan',
        'submenu' =>
        [
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
        'c' => 'Data_List/i/pelanggan',
        'title' => 'Pelanggan',
        'icon' => 'fas fa-address-book',
        'txt' => 'Pelanggan'
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
        ]
    ],
     [
        'c' => 'Filter',
        'title' => 'Order Filter',
        'icon' => 'fas fa-filter',
        'txt' => 'Order Filter',
        'submenu' =>
        [
            [
                'c' => '/i/2',
                'title' => 'Order Filter Pengantaran',
                'txt' => 'Pengantaran',
            ],
            [
                'c' => '/i/1',
                'title' => 'Order Filter Pengambilan',
                'txt' => 'Pengambilan',
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
                'txt' => 'Printer Setting',
            ],
        ]
    ],
];
