<?php

namespace App\Controllers\Laundry;

use App\Config\GoogleMaps;
use App\Core\Controller;

/**
 * Konfigurasi Google Maps untuk client (CRM, laundry kasir, J, dll.).
 * URL: GET /Laundry/MapsConfig/get
 *
 * Key browser-restricted — tetap aman dengan HTTP referrer restriction di Google Cloud.
 */
class MapsConfig extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    public function get()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->setCorsHeaders();

        $apiKey = GoogleMaps::getApiKey();
        if ($apiKey === '') {
            http_response_code(503);
            echo json_encode([
                'ok' => false,
                'status' => false,
                'message' => 'Google Maps API key belum dikonfigurasi di server (GOOGLE_MAPS_API_KEY).',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'status' => true,
            'api_key' => $apiKey,
        ], JSON_UNESCAPED_UNICODE);
    }
}
