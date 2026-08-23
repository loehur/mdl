<?php

namespace App\Helpers;

use App\Config\GoogleMaps;

/**
 * Google Places API (New) — server-side autocomplete & place details.
 */
class GoogleMapsPlaces
{
    /**
     * @return array{ok:bool,message?:string,items?:array<int,array{place_id:string,label:string}>}
     */
    public static function autocomplete(string $input, ?float $lat = null, ?float $lng = null): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['ok' => false, 'message' => 'Input pencarian kosong'];
        }
        if (mb_strlen($input) < 2) {
            return ['ok' => true, 'items' => []];
        }

        $apiKey = GoogleMaps::getServerApiKey();
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Google Maps API key belum dikonfigurasi di server.'];
        }

        $payload = [
            'input' => $input,
            'includedRegionCodes' => ['id'],
            'languageCode' => 'id',
        ];
        if ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $payload['locationBias'] = [
                'circle' => [
                    'center' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ],
                    'radius' => 50000.0,
                ],
            ];
        }

        $res = self::postJson('https://places.googleapis.com/v1/places:autocomplete', $payload, $apiKey);
        if ($res === null) {
            return ['ok' => false, 'message' => 'Gagal menghubungi Google Places API'];
        }
        if (!empty($res['error']['message'])) {
            return ['ok' => false, 'message' => self::formatGoogleError((string) $res['error']['message'])];
        }

        $items = [];
        $suggestions = is_array($res['suggestions'] ?? null) ? $res['suggestions'] : [];
        foreach ($suggestions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pred = $row['placePrediction'] ?? null;
            if (!is_array($pred)) {
                continue;
            }
            $placeId = trim((string) ($pred['placeId'] ?? ''));
            if ($placeId === '') {
                $placeResource = trim((string) ($pred['place'] ?? ''));
                if (str_starts_with($placeResource, 'places/')) {
                    $placeId = substr($placeResource, 7);
                }
            }
            if ($placeId === '') {
                continue;
            }
            $label = trim((string) ($pred['text']['text'] ?? $pred['structuredFormat']['mainText']['text'] ?? ''));
            if ($label === '') {
                continue;
            }
            $items[] = [
                'place_id' => $placeId,
                'label' => $label,
            ];
            if (count($items) >= 8) {
                break;
            }
        }

        return ['ok' => true, 'items' => $items];
    }

    /**
     * @return array{ok:bool,message?:string,lat?:float,lng?:float,label?:string}
     */
    public static function placeDetails(string $placeId): array
    {
        $placeId = trim($placeId);
        if ($placeId === '') {
            return ['ok' => false, 'message' => 'Place ID kosong'];
        }

        $apiKey = GoogleMaps::getServerApiKey();
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Google Maps API key belum dikonfigurasi di server.'];
        }

        $resource = str_starts_with($placeId, 'places/') ? $placeId : ('places/' . rawurlencode($placeId));
        $url = 'https://places.googleapis.com/v1/' . $resource;

        $res = self::getJson($url, $apiKey, 'location,formattedAddress,displayName');
        if ($res === null) {
            return ['ok' => false, 'message' => 'Gagal menghubungi Google Places API'];
        }
        if (!empty($res['error']['message'])) {
            return ['ok' => false, 'message' => self::formatGoogleError((string) $res['error']['message'])];
        }

        $loc = is_array($res['location'] ?? null) ? $res['location'] : [];
        $lat = (float) ($loc['latitude'] ?? 0);
        $lng = (float) ($loc['longitude'] ?? 0);
        if ($lat == 0.0 && $lng == 0.0) {
            return ['ok' => false, 'message' => 'Koordinat lokasi tidak ditemukan'];
        }

        $label = trim((string) ($res['formattedAddress'] ?? $res['displayName']['text'] ?? ''));

        return [
            'ok' => true,
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'label' => $label,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private static function postJson(string $url, array $payload, string $apiKey): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function getJson(string $url, string $apiKey, string $fieldMask): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'X-Goog-Api-Key: ' . $apiKey,
                'X-Goog-FieldMask: ' . $fieldMask,
            ],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function formatGoogleError(string $message): string
    {
        if (stripos($message, 'referer') !== false && stripos($message, 'blocked') !== false) {
            return 'Autocomplete server ditolak Google. Set GOOGLE_MAPS_SERVER_KEY di Env.php (key tanpa HTTP referrer restriction, aktifkan Places API New).';
        }
        return $message;
    }
}
