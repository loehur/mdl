<?php

namespace App\Config;

/**
 * Fonnte WhatsApp API Configuration
 * Self-hosted: node/fonnte_server (Baileys). Legacy cloud: https://api.fonnte.com
 *
 * @see node/fonnte_server/README.md
 */
class Fonnte
{
    /** Default lokal — sama PORT node/fonnte_server */
    private const DEFAULT_BASE_URL = 'http://127.0.0.1:3025';

    private static $config = null;

    public static function getConfig()
    {
        if (self::$config === null) {
            $baseUrl = self::DEFAULT_BASE_URL;
            if (defined('Env::FONNTE_BASE_URL')) {
                $fromEnv = trim((string) constant('Env::FONNTE_BASE_URL'));
                if ($fromEnv !== '') {
                    $baseUrl = rtrim($fromEnv, '/');
                }
            }

            self::$config = [
                'token' => defined('Env::FONNTE_TOKEN') ? (string) constant('Env::FONNTE_TOKEN') : '',
                'base_url' => $baseUrl,
                'country_code' => '62', // Default Indonesia
            ];
        }
        return self::$config;
    }

    public static function getToken()
    {
        $token = (string) (self::getConfig()['token'] ?? '');
        if ($token === '' && defined('Env::FONNTE_TOKEN')) {
            $token = trim((string) constant('Env::FONNTE_TOKEN'));
            if (self::$config !== null) {
                self::$config['token'] = $token;
            }
        }
        return $token;
    }

    public static function getBaseUrl()
    {
        return rtrim(self::getConfig()['base_url'], '/');
    }

    public static function getCountryCode()
    {
        return self::getConfig()['country_code'];
    }

    /**
     * Fallback group WhatsApp bila cabang belum punya id_group_fonnte.
     * Prefer isi per-cabang di mdl_laundry.cabang.id_group_fonnte.
     */
    public static function getEstimasiGroupId(): string
    {
        return '120363024779416973@g.us';
    }

    /**
     * Group WhatsApp driver (semua cabang) — konfirmasi jam jemput/antar.
     */
    public static function getDriverGroupId(): string
    {
        return '6281268098300-1625376610@g.us';
    }
}
