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
