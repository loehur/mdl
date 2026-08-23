<?php

namespace App\Config;

/**
 * Google Maps JavaScript API + Places API (New).
 * Key disimpan di Env.php — dipakai CRM, laundry, dll.
 *
 * GOOGLE_MAPS_API_KEY        → browser (Maps JS), HTTP referrer restriction
 * GOOGLE_MAPS_SERVER_KEY     → server (Places REST autocomplete/details), tanpa referrer restriction
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

    /** Key untuk panggilan REST dari server (autocomplete, place details). */
    public static function getServerApiKey(): string
    {
        if (!class_exists('Env', false)) {
            return '';
        }
        $server = trim((string) (\Env::GOOGLE_MAPS_SERVER_KEY ?? ''));
        if ($server !== '') {
            return $server;
        }
        return self::getApiKey();
    }

    public static function isConfigured(): bool
    {
        return self::getApiKey() !== '';
    }
}
