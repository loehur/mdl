<?php

namespace App\Models;

/**
 * Biteship Shipping API client
 * Docs: https://biteship.com/en/docs/api
 */
class Biteship
{
    private $apiKey;
    private $apiUrl;

    public function __construct()
    {
        $this->apiKey = class_exists('Env') ? (string) \Env::BITESHIP_API_KEY : '';
        $base = 'https://api.biteship.com';
        if (class_exists('Env')) {
            $base = (string) \Env::BITESHIP_API_URL;
        }
        $this->apiUrl = rtrim($base !== '' ? $base : 'https://api.biteship.com', '/');
    }

    /**
     * Retrieve courier rates — POST /v1/rates/couriers
     */
    public function getRates(array $payload)
    {
        return $this->request('POST', '/v1/rates/couriers', $payload);
    }

    /**
     * Create order — POST /v1/orders
     */
    public function createOrder(array $payload)
    {
        return $this->request('POST', '/v1/orders', $payload);
    }

    /**
     * Get order — GET /v1/orders/:id
     */
    public function getOrder($orderId)
    {
        return $this->request('GET', '/v1/orders/' . rawurlencode((string) $orderId));
    }

    /**
     * Cancel order — POST /v1/orders/:id/cancel
     */
    public function cancelOrder($orderId, array $payload = [])
    {
        return $this->request(
            'POST',
            '/v1/orders/' . rawurlencode((string) $orderId) . '/cancel',
            $payload
        );
    }

    /**
     * @return array Decoded JSON (+ http_code)
     */
    private function request($method, $path, array $payload = null)
    {
        $url = $this->apiUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        if ($payload !== null && strtoupper($method) !== 'GET') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        curl_setopt_array($curl, $opts);

        $response = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($errno) {
            return [
                'success' => false,
                'message' => 'Connection Error: ' . $error,
                'http_code' => 0,
            ];
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'message' => 'Invalid JSON from Biteship',
                'http_code' => $httpCode,
                'raw' => $response,
            ];
        }
        $data['http_code'] = $httpCode;
        return $data;
    }
}
