<?php

namespace App\Helpers;

/**
 * URL push ke wa_server (Node.js).
 * Default localhost — PHP & Node biasanya di VPS yang sama (/www/wwwroot/mdl).
 * Hindari waserver.nalju.com dari dalam server (DNS timeout).
 */
class WaServer
{
    public static function incomingUrl(): string
    {
        if (class_exists('\Env', false) && defined('\Env::WA_SERVER_URL') && \Env::WA_SERVER_URL !== '') {
            return \Env::WA_SERVER_URL;
        }

        return 'http://127.0.0.1:3003/incoming';
    }
}
