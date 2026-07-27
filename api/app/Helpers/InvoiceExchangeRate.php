<?php

namespace App\Helpers;

/**
 * Kurs USD→IDR via freecurrencyapi, disimpan harian di tabel exchange_rates.
 * Docs: https://freecurrencyapi.com/docs/latest
 */
class InvoiceExchangeRate
{
    /**
     * @param object $db Database wrapper (index invoice)
     * @return array{rate: float, rate_date: string, cached: bool, source: string}
     */
    public static function getUsdToIdrRate($db): array
    {
        $rateDate = date('Y-m-d');

        $existing = $db->query(
            "SELECT rate, rate_date, source
             FROM exchange_rates
             WHERE base_currency = 'USD' AND quote_currency = 'IDR' AND rate_date = ?
             LIMIT 1",
            [$rateDate]
        )->row_array();

        if ($existing && is_numeric($existing['rate']) && (float) $existing['rate'] > 0) {
            return [
                'rate' => (float) $existing['rate'],
                'rate_date' => (string) $existing['rate_date'],
                'cached' => true,
                'source' => (string) ($existing['source'] ?? 'freecurrencyapi'),
            ];
        }

        $apiKey = self::apiKey();
        if ($apiKey === '') {
            throw new \RuntimeException(
                'FREECURRENCYAPI_KEY belum diisi di Env.php. Daftar gratis di app.freecurrencyapi.com'
            );
        }

        $url = 'https://api.freecurrencyapi.com/v1/latest?' . http_build_query([
            'base_currency' => 'USD',
            'currencies' => 'IDR',
        ]);

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Ekstensi PHP cURL belum aktif');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'apikey: ' . $apiKey,
            ],
        ]);
        $body = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Gagal menghubungi freecurrencyapi: ' . $curlErr);
        }

        $payload = json_decode($body, true);
        if ($code < 200 || $code >= 300 || !is_array($payload)) {
            $msg = is_array($payload)
                ? (string) ($payload['message'] ?? $payload['error'] ?? "HTTP {$code}")
                : "HTTP {$code}";
            if ($code === 401 || $code === 403) {
                $msg = 'API key freecurrencyapi tidak valid';
            } elseif ($code === 429) {
                $msg = 'Kuota freecurrencyapi habis (HTTP 429)';
            }
            throw new \RuntimeException($msg);
        }

        $rate = $payload['data']['IDR'] ?? null;
        if (!is_numeric($rate) || (float) $rate <= 0) {
            throw new \RuntimeException('Response freecurrencyapi tidak berisi data.IDR');
        }

        $rate = round((float) $rate, 6);
        $now = date('Y-m-d H:i:s');

        try {
            $db->query(
                "INSERT INTO exchange_rates
                    (base_currency, quote_currency, rate, rate_date, source, fetched_at)
                 VALUES ('USD', 'IDR', ?, ?, 'freecurrencyapi', ?)
                 ON DUPLICATE KEY UPDATE
                    rate = VALUES(rate),
                    source = VALUES(source),
                    fetched_at = VALUES(fetched_at)",
                [$rate, $rateDate, $now]
            );
        } catch (\Throwable $e) {
            // Rate tetap dipakai meski insert gagal (race / schema)
        }

        return [
            'rate' => $rate,
            'rate_date' => $rateDate,
            'cached' => false,
            'source' => 'freecurrencyapi',
        ];
    }

    public static function apiKey(): string
    {
        if (class_exists('Env', false) && defined('Env::FREECURRENCYAPI_KEY')) {
            $key = trim((string) Env::FREECURRENCYAPI_KEY);
            if ($key !== '' && strpos($key, 'change-me') === false) {
                return $key;
            }
        }
        return '';
    }
}
