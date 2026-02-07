<?php

namespace App\Config;

/**
 * Fonnte WhatsApp API Configuration
 * @see https://docs.fonnte.com/api-send-message/
 */
class Fonnte
{
    private static $config = null;

    public static function getConfig()
    {
        if (self::$config === null) {
            self::$config = [
                'token' => defined('\Env::FONNTE_TOKEN') ? \Env::FONNTE_TOKEN : '',
                'base_url' => 'https://api.fonnte.com',
                'country_code' => '62', // Default Indonesia
            ];
        }
        return self::$config;
    }

    public static function getToken()
    {
        return self::getConfig()['token'];
    }

    public static function getBaseUrl()
    {
        return rtrim(self::getConfig()['base_url'], '/');
    }

    public static function getCountryCode()
    {
        return self::getConfig()['country_code'];
    }
}
