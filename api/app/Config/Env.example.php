<?php

class Env
{
    const MODE = 'dev';
    const WA_VERIFY_TOKEN = 'fgsfg';
    const WA_API_KEY = 'fsdgsfdg';
    const WA_PHONE_NUMBER = '+dfsgsfdgsfdg';

    public static function isProduction()
    {
        return self::MODE === 'pro';
    }

    public static function isDev()
    {
        return self::MODE === 'dev';
    }

    CONST operating_hours = [
        'open_hour' => 7,      // Buka jam 07:00
        'open_minute' => 0,
        'close_hour' => 21,    // Tutup jam 21:00
        'close_minute' => 0,
        'working_days' => [1, 2, 3, 4, 5, 6, 7], // Senin - Minggu
        'timezone' => 'Asia/Jakarta',
        'holidays' => [
            '2025-01-01', // Tahun Baru
        ],
    ];

    const DB_HOST = 'localhost';
    const DB_CREDENTIALS = [
        'dev' => [
            0 => ["db" => "mdl_main", "user" => "root", "pass" => ""],
            1 => ["db" => "mdl_laundry", "user" => "root", "pass" => ""],
            2 => ["db" => "mdl_sale", "user" => "root", "pass" => ""],
            3 => ["db" => "mdl_resto", "user" => "root", "pass" => ""],
            4 => ["db" => "mdl_depot", "user" => "root", "pass" => ""],
            5 => ["db" => "mdl_salon", "user" => "root", "pass" => ""]
        ],
         'pro' => [
            0 => ["db" => "mdl_main", "user" => "mdl_main", "pass" => "YOUR_PASSWORD_HERE"],
            1 => ["db" => "mdl_laundry", "user" => "mdl_laundry", "pass" => "YOUR_PASSWORD_HERE"],
            2 => ["db" => "mdl_sale", "user" => "mdl_sale", "pass" => ""],
            3 => ["db" => "mdl_resto", "user" => "mdl_resto", "pass" => ""],
            4 => ["db" => "mdl_depot", "user" => "mdl_depot", "pass" => ""],
            5 => ["db" => "mdl_salon", "user" => "mdl_salon", "pass" => "YOUR_PASSWORD_HERE"]
        ]
    ];

    const OPENAI_API_KEY = '0'; // TODO: Isi dengan API key dari platform.openai.com. Format: sk-...
    const OPENAI_MODEL = '0'; // Optional, default: gpt-4o-mini

    const CMS_USER_ROLES = [
        'admin' => ['DEV', 'AYAH', 'IBU', 'TABLET'],
        'crew' => ['3', '4', '5', '6', '10', '11', '12', '13', '14'],
        'driver' => ['DRIVER1', 'DRIVER2', 'ADI']
    ];
}
