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
        $secret = '';
        // Prefer laundry URL config if present
        if (class_exists('URL') && defined('URL::API_CRON_SECRET')) {
            $secret = (string) URL::API_CRON_SECRET;
        }
        $url = $this->apiUrl . '/activate';
        if ($secret !== '') {
            $url .= '?secret=' . rawurlencode($secret);
        }
        return $this->callApi($url, 'POST', $payload, $secret);
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
