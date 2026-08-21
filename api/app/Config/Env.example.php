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
    const WA_PHONE_NUMBER = '+628xxxxxxxxxx';              // Nomor WA bisnis legacy (alias line CS)
    const WA_LINE_ADMIN_PHONE = '+628117686252';           // Line admin (YCloud)
    const WA_LINE_CS_PHONE = '+6281170706611';             // Line CS (YCloud)
    const WA_LINE_ADMIN_LABEL = 'A';                       // Label UI — ganti tanpa migrasi DB
    const WA_LINE_CS_LABEL = 'B';
    const WA_LINE_ADMIN_NAME = 'Admin';
    const WA_LINE_CS_NAME = 'CS';
    const WA_SERVER_URL = 'http://127.0.0.1:3003/incoming'; // Node wa_server

    // Node maps_server — resolve URL Google Maps → lat/lng (KURIR, dll.)
    const MAPS_SERVER_URL = 'http://127.0.0.1:3020/resolve';
    const MAPS_SERVER_TOKEN = ''; // sama dengan MAPS_SERVER_TOKEN di node/maps_server/.env (opsional)

    // Nomor admin (fitur khusus WA replies)
    const ADMIN_NUMBERS = ['0812xxxxxxxx', '0852xxxxxxxx'];

    // HP yang dipaksa free-form (tidak pakai template WA)
    const FORBID_WA_TEMPLATE_HP = ['811xxxxxxxx', '822xxxxxxxx'];

    // Kata sensitif — last_message / UI disembunyikan (EnvHelper::textContainsPrivateWord)
    const WA_PRIVATE_WORDS = [
        'kode otp',
        'access key',
        'salary slip',
        'gaji cash',
        'gaji tf',
        'gaji transfer',
        'data karyawan',
    ];

    // Fonnte: group WA only (fonnte_server POST /send). Kosongkan WEBHOOK_URL di node .env.
    const FONNTE_TOKEN = 'change-me-fonnte-token';
    // API Baileys lokal (POST /send, /device). Production VPS: http://127.0.0.1:3025
    const FONNTE_BASE_URL = 'http://127.0.0.1:3025';
    // Jangan set webhook personal — group send only
    const FONNTE_WEBHOOK_URL = '';

    // -------------------------------------------------------------------------
    // WaDesk
    // -------------------------------------------------------------------------
    const WADESK_SERVER_URL = 'http://127.0.0.1:3010/incoming'; // Node wadesk_server
    const WADESK_ENCRYPT_KEY = 'change-me-wadesk-encrypt-key-32b!!'; // Min. 32 char (legacy)
    const WADESK_VERIFY_TOKEN = 'change-me-wadesk-verify-token';     // Webhook WaDesk (Kirimin)

    // Kirimin.id — base URL opsional (API key per tenant di tabel tenants.kirimin_api_key)
    const KIRIMIN_BASE_URL = 'https://apiapp.kirimin.id';

    // -------------------------------------------------------------------------
    // AI
    // -------------------------------------------------------------------------
    const OPENAI_API_KEY = 'sk-...';           // platform.openai.com
    const OPENAI_MODEL = 'gpt-4o-mini';
    const DEEPSEEK_API_KEY = 'change-me-deepseek-api-key'; // platform.deepseek.com
    const DEEPSEEK_MODEL = 'deepseek-chat';
    // Primary: 'deepseek' | 'openai'
    // deepseek = DeepSeek dulu, OpenAI cadangan (jika OPENAI_API_KEY terisi)
    // openai   = OpenAI dulu, DeepSeek cadangan (jika DEEPSEEK_API_KEY terisi)
    const AI_PRIORITY = 'deepseek';

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
    // Payment — DOKU (QRIS webhook)
    // -------------------------------------------------------------------------
    const DOKU_CLIENT_ID = 'MCH-0001-xxxxxxxxxxxx';
    const DOKU_SECRET_KEY = 'change-me-doku-secret-key';
    // Path notification URL (harus sama dengan yang didaftarkan di DOKU Back Office)
    const DOKU_WEBHOOK_PATH = '/Webhook/Doku';
    // true = skip verifikasi signature (hanya untuk development lokal)
    const DOKU_SKIP_SIGNATURE_VERIFY = false;

    // -------------------------------------------------------------------------
    // Shipping — Biteship (Kurir Instant laundry)
    // -------------------------------------------------------------------------
    const BITESHIP_API_KEY = 'change-me-biteship-api-key';
    const BITESHIP_API_URL = 'https://api.biteship.com';
    // Opsional: verifikasi webhook /Webhook/Biteship (kosongkan untuk skip)
    const BITESHIP_WEBHOOK_SECRET = '';

    // -------------------------------------------------------------------------
    // Auth endpoint internal
    // -------------------------------------------------------------------------
    // Cron: ?secret= atau header X-Cron-Secret (node/cron_server)
    const CRON_SECRET = 'change-me-cron-secret';

    // BCA scrapper (node/bca_scrapper) — header X-Bca-Token; harus sama dengan .env service
    const BCA_SCRAPPER_TOKEN = 'change-me-bca-scrapper-token';
    const BCA_SCRAPPER_MUTASI_URL = 'http://127.0.0.1:3021/mutasi';

    // Invoice Subscriptions API: header X-Invoice-Api-Key
    const INVOICE_API_KEY = 'change-me-invoice-api-key';

    // -------------------------------------------------------------------------
    // Exchange rate — freecurrencyapi (invoice USD → IDR)
    // -------------------------------------------------------------------------
    // Daftar: https://app.freecurrencyapi.com/register
    // Docs: https://freecurrencyapi.com/docs/
    const FREECURRENCYAPI_KEY = 'change-me-freecurrencyapi-key';
}
