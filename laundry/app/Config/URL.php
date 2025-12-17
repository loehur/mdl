<?php

class URL
{
    const BASE_URL = '/mdl/laundry/';
    const EX_ASSETS = '/assets/';
    const IN_ASSETS = '/mdl/laundry/in_assets/';
    const HOST_URL = '/mdl/laundry';

    const NON_TUNAI = ['QRIS', 'BCA'];
    const NON_TUNAI_GUIDE = [
        'BCA' => [
            'label' => "BCA (BANK CENTRAL ASIA)",
            'number' => '8455103793',
            'name' => 'LUHUR GUNAWAN'
        ],
    ];
    const MOOTA_BANK_ID = [
        'BCA' => 'ANEzlZMVWm9',
    ];
    const MOOTA_TOKEN = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiJucWllNHN3OGxsdyIsImp0aSI6Ijk3ZWVkY2M2OWYyMTk4ZDc1NjQxZWJmNTkwM2JiNzZjYWMwZDY1MGU5Njg0ZTFjMmQ5ODQxZWNkM2E4YjNjNmVhMjI3MDcxYzBhZTY0YjcyIiwiaWF0IjoxNzY1NTE5MzAzLjEzMzMxMywibmJmIjoxNzY1NTE5MzAzLjEzMzMxNywiZXhwIjoxNzk3MDU1MzAzLjEzMDcxOSwic3ViIjoiMjc3NCIsInNjb3BlcyI6WyJhcGkiLCJtdXRhdGlvbl9yZWFkIiwibXV0YXRpb24iXX0.aTijgtb6NHJMTaxHaiUkBo7VF9PZxuO_f_PtqARRt3XzsW1ZAbYQciD8_EFVhc-BSpB9dBGn38tC-gZTVtKdEAsglyZk3eWR03NGAtgfAzKuxfy6SKWFINjqGqmXt6kLtk-Y395V9soIEQe6r3y5xzEKc4CpHN38LTeoZRPEzSJR2s99B0QMzJStnva_yXVPa_eq5BlEJpDov5l9s0fAwI0CZ03-Pm1ujXvcCTiSfGQQpUOWAL0qfDQTSFuzQJkMaEuA1qgjTeTZbOf_jkdNGLwNurJWzvg4hnKhDX4FCzcFtdtvw7AekSeaRp3M6L9ezhcGYJmgYUEmbGo1Li_zfTH_hppM67rphSWRGA8wSUfN5PwKLqdavRXxA-oul7tfhfnT9IvJ2KJimWJ-l8bhSyugF3-SfSak3cDbP-nvLVeSQrzWRgcZkixqwRD8CogMCkmjKoR1zPQZimUFmKx8RBrwH8We4XV2cYrWsNMAM7wpPL1TfUFCYtk_GSTYJOHU0it7pO4__S8jP4O9zwyIdlDzcECLAaJK4lzPeTCagIlGUUtYV8RS7mNiMLro4H_nqO_CSTLz54zCxeE6g7IW8MEwj0LkhQdNZWwp1y5yHs4lY3hle-m_-sg5jikYCsXxHBTsH6ctyvlrqT4Sn3P3xn5ZzGc95nlRmN1_OZQ8iSQ";

    const SESSID = 'MDLSESSID';
    const FIRST_YEAR = 2021;
    const WA_USER = 1; // 1 PEMILIK SERVER, 0 NUMPANG
    const WA_PRIVATE = [
        0 => '081268098300',
        1 => '085278114125'
    ];
    const WA_PUBLIC = false;
    const WA_TOKEN = [
        0 => "",
        1 => "M2tCJhb_mcr5tHFo5r4B"
    ];

    const WA_API = [
        0 => 'WA_Local',
        1 => 'WA_Fonnte'
    ];

    const PAYMENT_GATEWAY = "tokopay";
    const DB_START = 2024;

    const PACK_ROWS = '<br><br><br><br><br><br><br>';
}
