<?php

namespace App\Controllers\Laundry;

use App\Config\GoogleMaps;
use App\Core\Controller;
use App\Helpers\GoogleMapsPlaces;

/**
 * Konfigurasi Google Maps untuk client (CRM, laundry kasir, J, dll.).
 * URL:
 * - GET  /Laundry/MapsConfig/get
 * - POST /Laundry/MapsConfig/autocomplete
 * - POST /Laundry/MapsConfig/placeDetails
 * - GET  /Laundry/MapsConfig/diagnose
 */
class MapsConfig extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    public function get()
    {
        $this->jsonHeader();
        $apiKey = GoogleMaps::getApiKey();
        if ($apiKey === '') {
            $this->fail('Google Maps API key belum dikonfigurasi di server (GOOGLE_MAPS_API_KEY).', 503);
            return;
        }

        echo json_encode([
            'ok' => true,
            'status' => true,
            'api_key' => $apiKey,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function autocomplete()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }

        $body = $this->mergedInput();
        $input = trim((string) ($body['input'] ?? ''));
        $lat = isset($body['lat']) ? (float) $body['lat'] : null;
        $lng = isset($body['lng']) ? (float) $body['lng'] : null;
        $this->reply(GoogleMapsPlaces::autocomplete($input, $lat, $lng));
    }

    public function placeDetails()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }

        $body = $this->mergedInput();
        $placeId = trim((string) ($body['place_id'] ?? ''));
        $this->reply(GoogleMapsPlaces::placeDetails($placeId));
    }

    /** GET — cek konfigurasi key browser/server (admin/debug). */
    public function diagnose()
    {
        $this->jsonHeader();

        $browserKey = GoogleMaps::getApiKey();
        $serverKey = GoogleMaps::getServerApiKey();
        if ($browserKey === '') {
            $this->fail('GOOGLE_MAPS_API_KEY kosong di Env.php', 503);
            return;
        }

        $auto = GoogleMapsPlaces::autocomplete('fullhouse', -6.2, 106.8);
        $mapsJs = $this->probeMapsJs($browserKey);

        echo json_encode([
            'ok' => true,
            'status' => true,
            'browser_key_suffix' => substr($browserKey, -6),
            'server_key_suffix' => $serverKey !== '' ? substr($serverKey, -6) : '',
            'keys_are_same' => $browserKey === $serverKey,
            'crm_referrer' => 'https://api.nalju.com/public/crm/',
            'maps_js_probe' => $mapsJs,
            'autocomplete_probe' => [
                'ok' => !empty($auto['ok']),
                'items' => isset($auto['items']) ? count($auto['items']) : 0,
                'message' => (string) ($auto['message'] ?? ''),
            ],
            'checklist_browser_key' => [
                'Application restriction: HTTP referrers → https://api.nalju.com/* dan https://*.nalju.com/*',
                'API restrictions: Maps JavaScript API + Map Tiles API (atau sementara None untuk uji)',
                'Library enabled di project key yang sama: Maps JavaScript API, Map Tiles API',
                'Billing account aktif di project tersebut',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string,mixed>
     */
    private function probeMapsJs(string $apiKey): array
    {
        $url = 'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($apiKey) . '&v=weekly';
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'curl init gagal'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Referer: https://api.nalju.com/public/crm/',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $body === '') {
            return ['ok' => false, 'http' => $code, 'message' => 'response kosong'];
        }
        foreach (['ApiNotActivatedMapError', 'RefererNotAllowedMapError', 'InvalidKeyMapError'] as $err) {
            if (stripos($body, $err) !== false) {
                return ['ok' => false, 'http' => $code, 'message' => $err];
            }
        }
        return [
            'ok' => stripos($body, 'google.maps') !== false,
            'http' => $code,
            'bytes' => strlen($body),
        ];
    }

    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->setCorsHeaders();
    }

    /**
     * @param array<string,mixed> $res
     */
    private function reply(array $res): void
    {
        $ok = !empty($res['ok']);
        $res['status'] = $ok;
        if (!$ok) {
            http_response_code(isset($res['message']) ? 400 : 500);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    private function fail(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode([
            'ok' => false,
            'status' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string,mixed>
     */
    private function mergedInput(): array
    {
        $json = $this->getBody();
        if (!is_array($json)) {
            $json = [];
        }
        return array_merge($_GET, $_POST, $json);
    }
}
