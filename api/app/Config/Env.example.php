<?php

/**
 * Salin file ini ke Env.php dan sesuaikan credential production.
 * Env.php tidak di-commit ke git (lihat api/.gitignore).
 */
class Env
{
    const MODE = 'pro'; // 'dev' | 'pro'
    const DB_HOST = 'localhost';

    /**
     * Hanya override index yang perlu diubah.
     * Index yang tidak ada di sini tetap pakai default dari DBC.php
     *
     * Index 5 = mdl_investasi (app Investasi PWA)
     * Index 6 = mdl_invoice (app Invoice PWA)
     * Index 7 = mdl_wadesk (app WaDesk)
     */
    const DB_CREDENTIALS = [
        'pro' => [
            0 => ['db' => 'mdl_main', 'user' => 'mdl_main', 'pass' => 'ISI_PASSWORD'],
            1 => ['db' => 'mdl_laundry', 'user' => 'mdl_laundry', 'pass' => 'ISI_PASSWORD'],
            2 => ['db' => 'mdl_resto', 'user' => 'mdl_resto', 'pass' => 'ISI_PASSWORD'],
            3 => ['db' => 'mdl_water', 'user' => 'mdl_water', 'pass' => 'ISI_PASSWORD'],
            4 => ['db' => 'mdl_salon', 'user' => 'mdl_salon', 'pass' => 'ISI_PASSWORD'],
            5 => ['db' => 'mdl_investasi', 'user' => 'mdl_investasi', 'pass' => 'ISI_PASSWORD_INVESTASI'],
            6 => ['db' => 'mdl_invoice', 'user' => 'mdl_invoice', 'pass' => 'ISI_PASSWORD_INVOICE'],
            7 => ['db' => 'mdl_wadesk', 'user' => 'mdl_wadesk', 'pass' => 'ISI_PASSWORD_WADESK'],
        ],
        'dev' => [
            5 => ['db' => 'mdl_investasi', 'user' => 'root', 'pass' => ''],
            6 => ['db' => 'mdl_invoice', 'user' => 'root', 'pass' => ''],
            7 => ['db' => 'mdl_wadesk', 'user' => 'root', 'pass' => ''],
        ],
    ];

    /** Kunci enkripsi API key YCloud di WaDesk (32+ char). Ganti di production. */
    const WADESK_ENCRYPT_KEY = 'change-me-wadesk-encrypt-key-32b!!';

    /** Token verifikasi webhook YCloud untuk WaDesk (boleh beda dari WA_VERIFY_TOKEN CRM). */
    const WADESK_VERIFY_TOKEN = 'wadesk_verify_token_change_me';

    /** Secret untuk endpoint Cron (query ?secret= atau header X-Cron-Secret). */
    const CRON_SECRET = 'change-me-cron-secret';

    /** API key untuk endpoint status subscription (header X-Invoice-Api-Key). */
    const INVOICE_API_KEY = 'change-me-invoice-api-key';
}

// URL internal push ke wa_server (Node.js port 3003, sama VPS dengan API)
// Jangan pakai https://waserver.nalju.com dari PHP — DNS bisa timeout di server.
const WA_SERVER_URL = 'http://127.0.0.1:3003/incoming';

// Push realtime WaDesk (Node wadesk_server, default port 3010)
const WADESK_SERVER_URL = 'http://127.0.0.1:3010/incoming';
