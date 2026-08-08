<?php

/**
 * Biteship API helper (laundry → api.nalju.com)
 */
class BiteshipApi
{
    private $apiUrl = 'https://api.nalju.com/Laundry/Biteship';

    public function rates(array $payload)
    {
        return $this->callApi($this->apiUrl . '/rates', 'POST', $payload);
    }

    public function activate(array $payload)
    {
        $secret = $this->resolveCronSecret();
        $url = $this->apiUrl . '/activate';
        if ($secret !== '') {
            $url .= '?secret=' . rawurlencode($secret);
        }
        $res = $this->callApi($url, 'POST', $payload, $secret);
        return $this->normalizeActivateResponse($res);
    }

    private function resolveCronSecret(): string
    {
        if (class_exists('URL') && defined('URL::API_CRON_SECRET')) {
            $s = trim((string) URL::API_CRON_SECRET);
            if ($s !== '') {
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

    /**
     * API global handler kadang mengembalikan message="PHP Error" + detail di "error".
     */
    private function normalizeActivateResponse($res): array
    {
        if (!is_array($res)) {
            return ['ok' => false, 'message' => 'Respons aktivasi tidak valid'];
        }
        if (isset($res['ok'])) {
            if (empty($res['ok'])) {
                $detail = trim((string) ($res['error'] ?? ''));
                $msg = trim((string) ($res['message'] ?? ''));
                if ($msg === '' || strcasecmp($msg, 'PHP Error') === 0 || strcasecmp($msg, 'Exception') === 0) {
                    if ($detail !== '') {
                        $res['message'] = $detail;
                    }
                }
            }
            return $res;
        }
        // Bentuk error global API: {status:false, message:"PHP Error", error:"..."}
        if (array_key_exists('status', $res) && $res['status'] === false) {
            $detail = trim((string) ($res['error'] ?? ''));
            $msg = trim((string) ($res['message'] ?? 'Gagal aktivasi'));
            if ($detail !== '' && ($msg === '' || strcasecmp($msg, 'PHP Error') === 0 || strcasecmp($msg, 'Exception') === 0)) {
                $msg = $detail;
            }
            return ['ok' => false, 'message' => $msg, 'error' => $detail];
        }
        return $res;
    }

    private function callApi($url, $method = 'GET', $data = [], $cronSecret = '')
    {
        $curl = curl_init();
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($cronSecret !== '') {
            $headers[] = 'X-Cron-Secret: ' . $cronSecret;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return ['ok' => false, 'message' => 'Connection Error: ' . $error];
        }
        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : ['ok' => false, 'message' => 'Invalid JSON', 'raw' => $response];
    }
}
