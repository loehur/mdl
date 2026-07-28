<?php

/**
 * Template konfigurasi Env.
 * Salin ke Env.php lalu isi nilai nyata. Jangan commit Env.php berisi secret production.
 */
class Env
{
    // -------------------------------------------------------------------------
    // App
    // -------------------------------------------------------------------------
    // Mode: 'dev' | 'pro' — menentukan set kredensial DB & perilaku auth
    const MODE = 'dev';

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------
    const DB_HOST = 'localhost';

    // Index: 0 main … 7 wadesk, 8 jaggu_school
    // Digabung (merge) dengan default di DBC.php — index baru di DBC tidak hilang.
    const DB_CREDENTIALS = [
        'dev' => [
            0 => ['db' => 'mdl_main', 'user' => 'root', 'pass' => ''],
            1 => ['db' => 'mdl_laundry', 'user' => 'root', 'pass' => ''],
            2 => ['db' => 'mdl_resto', 'user' => 'root', 'pass' => ''],
            3 => ['db' => 'mdl_water', 'user' => 'root', 'pass' => ''],
            4 => ['db' => 'mdl_salon', 'user' => 'root', 'pass' => ''],
            5 => ['db' => 'mdl_investasi', 'user' => 'root', 'pass' => ''],
            6 => ['db' => 'mdl_invoice', 'user' => 'root', 'pass' => ''],
            7 => ['db' => 'mdl_wadesk', 'user' => 'root', 'pass' => ''],
            8 => ['db' => 'mdl_jaggu_school', 'user' => 'root', 'pass' => ''],
        ],
        'pro' => [
            0 => ['db' => 'mdl_main', 'user' => 'mdl_main', 'pass' => 'YOUR_PASSWORD_HERE'],
            1 => ['db' => 'mdl_laundry', 'user' => 'mdl_laundry', 'pass' => 'YOUR_PASSWORD_HERE'],
            2 => ['db' => 'mdl_resto', 'user' => 'mdl_resto', 'pass' => 'YOUR_PASSWORD_HERE'],
            3 => ['db' => 'mdl_water', 'user' => 'mdl_water', 'pass' => 'YOUR_PASSWORD_HERE'],
            4 => ['db' => 'mdl_salon', 'user' => 'mdl_salon', 'pass' => 'YOUR_PASSWORD_HERE'],
            5 => ['db' => 'mdl_investasi', 'user' => 'mdl_investasi', 'pass' => 'YOUR_PASSWORD_HERE'],
            6 => ['db' => 'mdl_invoice', 'user' => 'mdl_invoice', 'pass' => 'YOUR_PASSWORD_HERE'],
            7 => ['db' => 'mdl_wadesk', 'user' => 'mdl_wadesk', 'pass' => 'YOUR_PASSWORD_HERE'],
            8 => ['db' => 'mdl_jaggu_school', 'user' => 'mdl_jaggu_school', 'pass' => 'YOUR_PASSWORD_HERE'],
        ],
    ];

    // -------------------------------------------------------------------------
    // WhatsApp — YCloud (CRM / BSP)
    // -------------------------------------------------------------------------
    const WA_VERIFY_TOKEN = 'change-me-wa-verify-token';   // Webhook verify token
    const WA_API_KEY = 'change-me-ycloud-api-key';          // API key YCloud
    const WA_PHONE_NUMBER = '+628xxxxxxxxxx';              // Nomor WA bisnis (E.164)
    const WA_SERVER_URL = 'http://127.0.0.1:3003/incoming'; // Node wa_server

    // Nomor admin (fitur khusus WA replies)
    const ADMIN_NUMBERS = ['0812xxxxxxxx', '0852xxxxxxxx'];

    // HP yang dipaksa free-form (tidak pakai template WA)
    const FORBID_WA_TEMPLATE_HP = ['811xxxxxxxx', '822xxxxxxxx'];

    // Kata sensitif — last_message / UI disembunyikan (EnvHelper::textContainsPrivateWord)
    const WA_PRIVATE_WORDS = [
        'kode otp',
        'salary slip',
        'gaji cash',
        'gaji tf',
        'gaji transfer',
        'data karyawan',
    ];

    // -------------------------------------------------------------------------
    // WhatsApp — Fonnte
    // -------------------------------------------------------------------------
    const FONNTE_TOKEN = 'change-me-fonnte-token';

    // -------------------------------------------------------------------------
    // WaDesk
    // -------------------------------------------------------------------------
    const WADESK_SERVER_URL = 'http://127.0.0.1:3010/incoming'; // Node wadesk_server
    const WADESK_ENCRYPT_KEY = 'change-me-wadesk-encrypt-key-32b!!'; // Min. 32 char
    const WADESK_VERIFY_TOKEN = 'change-me-wadesk-verify-token';     // Webhook YCloud WaDesk

    // -------------------------------------------------------------------------
    // AI
    // -------------------------------------------------------------------------
    const OPENAI_API_KEY = 'sk-...';           // platform.openai.com
    const OPENAI_MODEL = 'gpt-4o-mini';
    const GROQ_API_KEY = 'change-me-groq-api-key';

    // -------------------------------------------------------------------------
    // Payment — TokoPay (QRIS laundry / invoice / salon)
    // -------------------------------------------------------------------------
    const TOKOPAY_MERCHANT_ID = 'M........';
    const TOKOPAY_SECRET_KEY = 'change-me-tokopay-secret';
    const TOKOPAY_API_URL = 'https://api.tokopay.id';

    // Status TokoPay → lunas / gagal (CleanKas, webhook, dll.)
    const QRIS_STATUS_SUCCESS = ['success', 'completed', 'paid'];
    const QRIS_STATUS_EXPIRED = ['expired', 'cancelled', 'cancel', 'timeout', 'failed', 'fail', 'failure'];

    // -------------------------------------------------------------------------
    // Auth endpoint internal
    // -------------------------------------------------------------------------
    // Cron: ?secret= atau header X-Cron-Secret (node/cron_server)
    const CRON_SECRET = 'change-me-cron-secret';

    // Invoice Subscriptions API: header X-Invoice-Api-Key
    const INVOICE_API_KEY = 'change-me-invoice-api-key';

    // -------------------------------------------------------------------------
    // Exchange rate — freecurrencyapi (invoice USD → IDR)
    // -------------------------------------------------------------------------
    // Daftar: https://app.freecurrencyapi.com/register
    // Docs: https://freecurrencyapi.com/docs/
    const FREECURRENCYAPI_KEY = 'change-me-freecurrencyapi-key';
}
