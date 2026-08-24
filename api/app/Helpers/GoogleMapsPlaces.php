<?php

namespace App\Helpers;

use App\Config\GoogleMaps;

/**
 * Google Places API (New) — server-side autocomplete & place details.
 */
class GoogleMapsPlaces
{
    /**
     * @param array{hard_restrict?:bool,restrict_radius?:float,city_name?:string} $options
     * @return array{ok:bool,message?:string,items?:array<int,array{place_id:string,label:string}>}
     */
    public static function autocomplete(string $input, ?float $lat = null, ?float $lng = null, array $options = []): array
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

        $hardRestrict = !empty($options['hard_restrict']);
        $restrictRadius = (float) ($options['restrict_radius'] ?? 25000.0);
        if ($restrictRadius <= 0) {
            $restrictRadius = 25000.0;
        }
        $restrictRadius = min($restrictRadius, 50000.0);
        $cityName = trim((string) ($options['city_name'] ?? ''));

        $payload = [
            'input' => $input,
            'includedRegionCodes' => ['id'],
            'languageCode' => 'id',
        ];
        if ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $circle = [
                'center' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
                'radius' => $restrictRadius,
            ];
            if ($hardRestrict) {
                $payload['locationRestriction'] = ['circle' => $circle];
            } else {
                $payload['locationBias'] = ['circle' => $circle];
            }
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
            if ($cityName !== '' && !self::labelMatchesCity($label, $cityName)) {
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
     * @param array{restrict_lat?:float,restrict_lng?:float,restrict_radius?:float,city_name?:string} $options
     * @return array{ok:bool,message?:string,lat?:float,lng?:float,label?:string}
     */
    public static function placeDetails(string $placeId, array $options = []): array
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

        $restrictLat = isset($options['restrict_lat']) ? (float) $options['restrict_lat'] : null;
        $restrictLng = isset($options['restrict_lng']) ? (float) $options['restrict_lng'] : null;
        $restrictRadius = (float) ($options['restrict_radius'] ?? 25000.0);
        if ($restrictRadius <= 0) {
            $restrictRadius = 25000.0;
        }
        $cityName = trim((string) ($options['city_name'] ?? ''));

        if ($cityName !== '' && !self::labelMatchesCity($label, $cityName)) {
            return ['ok' => false, 'message' => 'Lokasi di luar kota cabang'];
        }
        if ($restrictLat !== null && $restrictLng !== null) {
            $dist = self::distanceMeters($restrictLat, $restrictLng, $lat, $lng);
            if ($dist > $restrictRadius) {
                $km = (int) round($restrictRadius / 1000);
                return ['ok' => false, 'message' => 'Lokasi terlalu jauh dari pusat kota (maks. ' . $km . ' km)'];
            }
        }

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

    private static function normalizeCityName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = preg_replace('/^(kota|kabupaten|kab\.?|kota\s+administrasi)\s+/u', '', $n) ?? $n;
        return preg_replace('/\s+/u', ' ', $n) ?? $n;
    }

    private static function labelMatchesCity(string $label, string $cityName): bool
    {
        $city = self::normalizeCityName($cityName);
        if ($city === '') {
            return true;
        }
        $hay = mb_strtolower($label);
        if (mb_strpos($hay, $city) !== false) {
            return true;
        }
        $citySpaced = str_replace(' ', '', $city);
        $haySpaced = str_replace(' ', '', $hay);
        return $citySpaced !== '' && mb_strpos($haySpaced, $citySpaced) !== false;
    }

    private static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
