<?php

/**
 * Fonnte helper laundry → https://api.nalju.com/Laundry/Fonnte
 * Token Fonnte hanya di server API (Env::FONNTE_TOKEN).
 *
 * Usage:
 *   $this->helper('FonnteService');
 *   FonnteService::sendToGroup($groupId, $message);
 *   FonnteService::driverGroupId();
 *   FonnteService::cabangGroupId($cabangRow);
 */
class FonnteService
{
    private static $apiUrl;

    private static function base(): string
    {
        return ApiLoopback::baseUrl() . '/Laundry/Fonnte';
    }

    /** Fallback sama App\Config\Fonnte (bukan secret). */
    private static $driverGroupId = '6281268098300-1625376610@g.us';
    private static $estimasiGroupId = '120363024779416973@g.us';

    public static function driverGroupId(): string
    {
        return self::$driverGroupId;
    }

    public static function estimasiGroupId(): string
    {
        return self::$estimasiGroupId;
    }

    /**
     * Group cabang dari row cabang.id_group_fonnte, fallback estimasi group.
     * @param array|null $cabangRow
     */
    public static function cabangGroupId($cabangRow = null): string
    {
        if (is_array($cabangRow)) {
            $fromCabang = trim((string) ($cabangRow['id_group_fonnte'] ?? ''));
            if ($fromCabang !== '' && preg_match('/@g\.us$/i', $fromCabang)) {
                return $fromCabang;
            }
        }
        return self::estimasiGroupId();
    }

    /**
     * @return array{success:bool,data:mixed,error:?string}
     */
    public static function sendToGroup(string $groupId, string $message, array $options = []): array
    {
        $groupId = trim($groupId);
        if ($groupId === '' || !preg_match('/@g\.us$/i', $groupId)) {
            return ['success' => false, 'data' => null, 'error' => 'ID group tidak valid'];
        }
        if (trim($message) === '') {
            return ['success' => false, 'data' => null, 'error' => 'Pesan kosong'];
        }

        $payload = array_merge($options, [
            'group_id' => $groupId,
            'message' => $message,
        ]);

        $res = self::callApi('/sendGroup', $payload);
        if (!is_array($res)) {
            return ['success' => false, 'data' => null, 'error' => 'Respons API tidak valid'];
        }

        $ok = !empty($res['success']) || !empty($res['ok']);
        $err = null;
        if (!$ok) {
            $err = trim((string) ($res['error'] ?? $res['message'] ?? 'Gagal kirim Fonnte'));
            if ($err === '' || strcasecmp($err, 'PHP Error') === 0) {
                $detail = trim((string) ($res['error'] ?? ''));
                if ($detail !== '') {
                    $err = $detail;
                }
            }
        }

        return [
            'success' => $ok,
            'data' => $res['data'] ?? null,
            'error' => $ok ? null : $err,
        ];
    }

    /**
     * @return array{success:bool,data:mixed,error:?string}
     */
    public static function sendMessage(string $phone, string $message, array $options = []): array
    {
        return [
            'success' => false,
            'data' => null,
            'error' => 'sendMessage lewat laundry belum tersedia — gunakan sendToGroup atau API WhatsApp',
        ];
    }

    private static function resolveCronSecret(): string
    {
        if (class_exists('URL') && defined('URL::API_CRON_SECRET')) {
            $s = trim((string) URL::API_CRON_SECRET);
            if ($s !== '' && $s !== 'change-me-cron-secret') {
                return $s;
            }
        }
        foreach (['API_CRON_SECRET', 'CRON_SECRET'] as $envKey) {
            $s = trim((string) (getenv($envKey) ?: ''));
            if ($s !== '') {
                return $s;
            }
        }
        return '';
    }

    private static function callApi(string $path, array $data = []): array
    {
        $secret = self::resolveCronSecret();
        $url = rtrim(self::base(), '/') . $path;
        if ($secret !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'secret=' . rawurlencode($secret);
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $headers = ApiLoopback::headers($url, $headers);
        if ($secret !== '') {
            $headers[] = 'X-Cron-Secret: ' . $secret;
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        $opts = ApiLoopback::curlOpts($url, $opts);
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['ok' => false, 'success' => false, 'message' => 'Connection Error: ' . $curlErr];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'success' => false,
                'message' => 'Invalid JSON (HTTP ' . $httpCode . ')',
                'raw' => substr((string) $raw, 0, 200),
            ];
        }
        return $decoded;
    }
}
