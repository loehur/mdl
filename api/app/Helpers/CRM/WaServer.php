<?php

namespace App\Helpers\CRM;

/**
 * URL push ke wa_server (Node.js).
 * PHP & Node di VPS yang sama → pakai 127.0.0.1 (hindari DNS waserver.nalju.com dari dalam server).
 */
class WaServer
{
    private const DEFAULT_URL = 'http://127.0.0.1:3003/incoming';

    public static function incomingUrl(): string
    {
        if (!class_exists('\Env', false)) {
            return self::DEFAULT_URL;
        }

        try {
            $ref = new \ReflectionClass('\Env');
            if (!$ref->hasConstant('WA_SERVER_URL')) {
                return self::DEFAULT_URL;
            }

            $url = $ref->getConstant('WA_SERVER_URL');
            if (!is_string($url) || $url === '') {
                return self::DEFAULT_URL;
            }

            // waserver.nalju.com dari dalam VPS sering DNS timeout — pakai localhost
            if (stripos($url, 'waserver.nalju.com') !== false) {
                return self::DEFAULT_URL;
            }

            return $url;
        } catch (\Throwable $e) {
            return self::DEFAULT_URL;
        }
    }
}
