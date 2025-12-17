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
        'title' => 'Sales Ops',
        'icon' => 'fas fa-shopping-cart',
        'txt' => 'Sales Ops',
        'submenu' => [
            [
                'c' => '@Data_List/i/barang',
                'title' => 'Master Barang',
                'txt' => 'Master Barang'
            ],
            [
                'c' => '@Data_List/i/barang_sub',
                'title' => 'Sub Barang',
                'txt' => 'Sub Barang'
            ]
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
        'title' => 'System Ops',
        'icon' => 'fas fa-cogs',
        'txt' => 'System Ops',
        'submenu' => [
            [
                'c' => '@WA_Status',
                'title' => 'WA_Status',
                'txt' => 'Whatsapp Status'
            ],
            [
                'c' => '@Setting',
                'title' => 'Setting',
                'txt' => 'Setting'
            ],
            [
                'c' => '@Troubleshoot',
                'title' => 'Troubleshoot',
                'txt' => 'Troubleshoot'
            ]
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

    $rekapSubmenu[] = [
        'c' => '@Gaji',
        'title' => 'Gaji Bulanan - Rekap',
        'txt' => 'Gaji Bulanan'
    ];

    $rekapMenu = [
        'c' => '#',
        'title' => 'Rekap',
        'icon' => 'fas fa-chart-line',
        'txt' => 'Rekap',
        'submenu' => $rekapSubmenu
    ];
    
    // Insert Rekap menu at index 1 (after Approval)
    array_splice($menu[1], 1, 0, [$rekapMenu]);
}
