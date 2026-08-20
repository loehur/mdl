<?php

namespace App\Helpers\Payment;

/**
 * Verifikasi signature HTTP Notification DOKU (Non-SNAP).
 * @see https://developers.doku.com/get-started-with-doku-api/notification/best-practice
 */
class DokuSignature
{
    /**
     * @return array{valid: bool, message: string}
     */
    public static function verify(string $rawBody, string $requestTarget, ?string $clientId = null, ?string $secretKey = null): array
    {
        $clientId = $clientId ?? (class_exists('Env') && defined('Env::DOKU_CLIENT_ID') ? \Env::DOKU_CLIENT_ID : '');
        $secretKey = $secretKey ?? (class_exists('Env') && defined('Env::DOKU_SECRET_KEY') ? \Env::DOKU_SECRET_KEY : '');

        if ($clientId === '' || $secretKey === '') {
            return ['valid' => false, 'message' => 'DOKU credentials not configured'];
        }

        $headerClientId = self::getHeader('Client-Id');
        $requestId = self::getHeader('Request-Id');
        $requestTimestamp = self::getHeader('Request-Timestamp');
        $signatureProvided = self::getHeader('Signature');

        if ($headerClientId === '' || $requestId === '' || $requestTimestamp === '' || $signatureProvided === '') {
            return ['valid' => false, 'message' => 'Missing DOKU signature headers'];
        }

        if (!hash_equals($clientId, $headerClientId)) {
            return ['valid' => false, 'message' => 'Invalid Client-Id'];
        }

        $digest = base64_encode(hash('sha256', $rawBody, true));
        $signatureComponents = implode("\n", [
            'Client-Id:' . $headerClientId,
            'Request-Id:' . $requestId,
            'Request-Timestamp:' . $requestTimestamp,
            'Request-Target:' . $requestTarget,
            'Digest:' . $digest,
        ]);

        $expected = 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $signatureComponents, $secretKey, true));

        if (!hash_equals($expected, $signatureProvided)) {
            return ['valid' => false, 'message' => 'Invalid Signature'];
        }

        return ['valid' => true, 'message' => 'OK'];
    }

    /**
     * Resolve Request-Target path untuk verifikasi signature.
     */
    public static function resolveRequestTarget(): string
    {
        if (class_exists('Env') && defined('Env::DOKU_WEBHOOK_PATH')) {
            $configured = trim((string) \Env::DOKU_WEBHOOK_PATH);
            if ($configured !== '') {
                return $configured;
            }
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/Webhook/Doku', PHP_URL_PATH);
        if (!is_string($uri) || $uri === '') {
            return '/Webhook/Doku';
        }

        return $uri;
    }

    private static function getHeader(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (!empty($_SERVER[$serverKey])) {
            return trim((string) $_SERVER[$serverKey]);
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }
}
