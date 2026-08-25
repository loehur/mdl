<?php

/**
 * Laundry admin BCA mutasi → api.nalju.com/Payment/BcaMutasiAdmin
 */
class BcaMutasiAdminApi
{
    private $apiUrl;

    public function __construct()
    {
        $this->apiUrl = ApiLoopback::baseUrl() . '/Payment/BcaMutasiAdmin';
    }

    /**
     * @return array{ok:bool,message?:string,entity_type?:string,entity_ref?:string,revert?:array}
     */
    public function unbind(int $linkId, string $reason = '', string $blockedBy = ''): array
    {
        if ($linkId < 1) {
            return ['ok' => false, 'message' => 'link_id tidak valid'];
        }

        $res = $this->call('unbind', [
            'link_id' => $linkId,
            'reason' => $reason,
            'blocked_by' => $blockedBy,
        ]);

        return is_array($res) ? $res : ['ok' => false, 'message' => 'Respons API tidak valid'];
    }

    /**
     * @param list<string> $invoiceRefs
     * @param list<string> $salonRefs
     * @return array<string,array{name:string,url:?string,badge:string,jenis_transaksi:int}>
     */
    public function resolvePayers(array $invoiceRefs = [], array $salonRefs = []): array
    {
        if ($invoiceRefs === [] && $salonRefs === []) {
            return [];
        }

        $res = $this->call('payers', [
            'invoice_refs' => array_values($invoiceRefs),
            'salon_refs' => array_values($salonRefs),
        ]);

        if (!is_array($res) || empty($res['ok']) || !is_array($res['payers'] ?? null)) {
            return [];
        }

        return $res['payers'];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function call(string $action, array $payload)
    {
        $secret = $this->resolveCronSecret();
        $url = $this->apiUrl . '/' . $action;
        if ($secret !== '') {
            $url .= '?secret=' . rawurlencode($secret);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return ['ok' => false, 'message' => 'Payload tidak valid'];
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $headers = ApiLoopback::headers($url, $headers);
        if ($secret !== '') {
            $headers[] = 'X-Cron-Secret: ' . $secret;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        if (ApiLoopback::isLoopback($url)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            return [
                'ok' => false,
                'message' => $curlErr !== '' ? $curlErr : 'API tidak merespons',
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Respons API bukan JSON (HTTP ' . $httpCode . ')',
            ];
        }

        if ($httpCode >= 400 && empty($decoded['ok'])) {
            if (empty($decoded['message'])) {
                $decoded['message'] = 'API error HTTP ' . $httpCode;
            }
        }

        return $decoded;
    }

    private function resolveCronSecret(): string
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

        if (class_exists('URL') && defined('URL::API_CRON_SECRET')) {
            return trim((string) URL::API_CRON_SECRET);
        }

        return '';
    }
}
