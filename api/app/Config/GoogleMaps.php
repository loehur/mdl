<?php

namespace App\Config;

/**
 * Google Maps JavaScript API + Places API (New).
 * Key disimpan di Env.php — dipakai CRM, laundry, dll.
 */
class GoogleMaps
{
    public static function getApiKey(): string
    {
        if (!class_exists('Env', false)) {
            return '';
        }
        return trim((string) (\Env::GOOGLE_MAPS_API_KEY ?? ''));
    }

    public static function isConfigured(): bool
    {
        return self::getApiKey() !== '';
    }
}
