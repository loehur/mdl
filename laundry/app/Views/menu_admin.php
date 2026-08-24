<?php
$menu[1] = [
    [
        'c' => 'AdminApproval/index/Setoran',
        'title' => 'Approval',
        'icon' => 'fas fa-tasks',
        'txt' => 'Approval'
    ],
    [
        'c' => '#',
        'title' => 'Non Tunai',
        'icon' => 'fas fa-credit-card',
        'txt' => 'Non Tunai',
        'submenu' => [
            [
                'c' => '@NonTunaiAdmin/bcaMutasi',
                'title' => 'BCA Mutasi',
                'txt' => 'BCA Mutasi'
            ],
            [
                'c' => '@NonTunaiAdmin/bcaQris',
                'title' => 'BCA QRIS',
                'txt' => 'BCA QRIS'
            ],
        ]
    ],
    [
        'c' => '#',
        'title' => 'Sales Ops',
        'icon' => 'fas fa-shopping-cart',
        'txt' => 'Sales Ops',
        'submenu' => [
            [
                'c' => '@BarangMasuk',
                'title' => 'Barang Masuk',
                'txt' => 'Barang Masuk'
            ],
            [
                'c' => '@Data_List/i/barang',
                'title' => 'Master Barang',
                'txt' => 'Master Barang'
            ],
            [
                'c' => '@Data_List/i/barang_sub',
                'title' => 'Sub Barang',
                'txt' => 'Sub Barang'
            ],
        ]
    ],

    [
        'c' => '#',
        'title' => 'Item',
        'icon' => 'fas fa-list',
        'txt' => 'Data List',
        'submenu' => [
            [
                'c' => '@Cabang_List',
                'title' => 'Data Cabang',
                'txt' => 'Cabang'
            ],
            [
                'c' => '@Data_List/i/item',
                'title' => 'Item Laundry',
                'txt' => 'Item Laundry'
            ],
            [
                'c' => '@Data_List/i/item_pengeluaran',
                'title' => 'Item Pengeluaran',
                'txt' => 'Pengeluaran'
            ],
            [
                'c' => '@Data_List/i/surcas',
                'title' => 'Surcharge',
                'txt' => 'Surcharge'
            ]
        ]
    ],
    [
        'c' => '#',
        'title' => 'CRM Setting',
        'icon' => 'fas fa-headset',
        'txt' => 'CRM Setting',
        'submenu' => [
            [
                'c' => '@CrmQuickReplies',
                'title' => 'Quick Replies',
                'txt' => 'Quick Replies'
            ],
            [
                'c' => '@CrmDevices',
                'title' => 'CRM Devices',
                'txt' => 'CRM Devices'
            ],
        ]
    ],
    [
        'c' => '#',
        'title' => 'Tools',
        'icon' => 'fas fa-tools',
        'txt' => 'Tools',
        'submenu' => [
            [
                'c' => '@MonthlyBill',
                'title' => 'Monthly Bill',
                'txt' => 'Monthly Bill'
            ],
            [
                'c' => '@Reminder',
                'title' => 'Reminder',
                'txt' => 'Reminder'
            ],
            [
                'c' => '@PrepaidList',
                'title' => 'Prepaid',
                'txt' => 'Prepaid'
            ],
            [
                'c' => '@ImportPelanggan',
                'title' => 'Import Pelanggan',
                'txt' => 'Import Pelanggan'
            ],
            [
                'c' => '@IntentLab',
                'title' => 'Intent Lab',
                'txt' => 'Intent Lab'
            ],
            [
                'c' => '@AutoReplyKeywords',
                'title' => 'Auto Reply Keywords',
                'txt' => 'Auto Reply Keywords'
            ],
            [
                'c' => '@WaGateway',
                'title' => 'WhatsApp',
                'txt' => 'WhatsApp'
            ],
        ]
    ],
];

if ($this->id_cabang > 0) {
    $rekapSubmenu = [
        [
            'c' => '@Rekap/i/1',
            'title' => 'Harian Cabang - Rekap',
            'txt' => 'Cabang Harian'
        ],
        [
            'c' => '@Rekap/i/2',
            'title' => 'Bulanan Cabang - Rekap',
            'txt' => 'Cabang Bulanan'
        ]
    ];

    if (count($this->listCabang) > 1) {
        $rekapSubmenu[] = [
            'c' => '@Rekap/i/4',
            'title' => 'Harian Laundry - Rekap',
            'txt' => 'Laundry Harian'
        ];
        $rekapSubmenu[] = [
            'c' => '@Rekap/i/3',
            'title' => 'Bulanan Laundry - Rekap',
            'txt' => 'Laundry Bulanan'
        ];
    }

    $rekapMenu = [
        'c' => '#',
        'title' => 'Rekap',
        'icon' => 'fas fa-chart-line',
        'txt' => 'Rekap',
        'submenu' => $rekapSubmenu
    ];
    
    // Menu Gaji dengan submenu
    $gajiMenu = [
        'c' => '#',
        'title' => 'Gaji',
        'icon' => 'fas fa-money-bill-wave',
        'txt' => 'Gaji',
        'submenu' => [
            [
                'c' => '@Gaji',
                'title' => 'Gaji Bulanan',
                'txt' => 'Gaji Bulanan'
            ],
            [
                'c' => '@GajiPengaturan',
                'title' => 'Pengaturan Gaji',
                'txt' => 'Pengaturan'
            ],
            [
                'c' => '@Payroll',
                'title' => 'Payroll Management',
                'txt' => 'Payroll'
            ]
        ]
    ];
    
    // Insert Rekap menu at index 1 (after Approval)
    array_splice($menu[1], 1, 0, [$rekapMenu]);
    // Insert Gaji menu at index 2 (after Rekap)
    array_splice($menu[1], 2, 0, [$gajiMenu]);
}
