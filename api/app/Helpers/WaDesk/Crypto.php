<?php

namespace App\Helpers\WaDesk;

/**
 * Encrypt/decrypt YCloud API keys for WaDesk storage.
 */
class Crypto
{
    public static function encrypt(string $plain): string
    {
        $key = self::key();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Encrypt failed');
        }
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 17) {
            throw new \RuntimeException('Invalid ciphertext');
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('Decrypt failed');
        }
        return $plain;
    }

    /** Deterministic fingerprint so the same YCloud credential shares templates across team keys. */
    public static function fingerprint(string $plainApiKey): string
    {
        return hash('sha256', trim($plainApiKey));
    }

    private static function key(): string
    {
        $secret = defined('\Env::WADESK_ENCRYPT_KEY')
            ? (string) \Env::WADESK_ENCRYPT_KEY
            : 'change-me-wadesk-encrypt-key-32b!!';
        return hash('sha256', $secret, true);
    }
}
