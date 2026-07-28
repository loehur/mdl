<?php

namespace App\Helpers\WaDesk;

/**
 * Push events to node/wadesk_server.
 */
class Server
{
    private const DEFAULT_URL = 'http://127.0.0.1:3010/incoming';

    public static function incomingUrl(): string
    {
        if (defined('WADESK_SERVER_URL') && is_string(WADESK_SERVER_URL) && WADESK_SERVER_URL !== '') {
            return WADESK_SERVER_URL;
        }
        if (class_exists('\Env', false)) {
            try {
                $ref = new \ReflectionClass('\Env');
                if ($ref->hasConstant('WADESK_SERVER_URL')) {
                    $url = $ref->getConstant('WADESK_SERVER_URL');
                    if (is_string($url) && $url !== '') {
                        return $url;
                    }
                }
            } catch (\Throwable $e) {
                /* ignore */
            }
        }
        return self::DEFAULT_URL;
    }

    public static function push(array $payload): void
    {
        $url = self::incomingUrl();
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        @curl_exec($ch);
        @curl_close($ch);
    }
}
