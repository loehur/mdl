<?php

namespace App\Models;

/**
 * KlikQRIS Payment Gateway Model
 * Reference: https://klikqris.com/dokumentasi-api
 */
class KlikQris
{
    private $apiKey;
    private $merchantId;
    private $apiUrl;

    public function __construct()
    {
        $this->apiKey = \Env::KLIKQRIS_API_KEY;
        $this->merchantId = \Env::KLIKQRIS_MERCHANT_ID;
        $this->apiUrl = rtrim(\Env::KLIKQRIS_API_URL, '/');
    }

    /**
     * Create QRIS transaction
     *
     * @param string $orderId
     * @param int $amount
     * @param string|null $keterangan
     * @param string|null $callbackUrl
     * @param bool $sandbox
     * @return array
     */
    public function createTransaction($orderId, $amount, $keterangan = null, $callbackUrl = null, $sandbox = false)
    {
        $baseUrl = $sandbox
            ? 'https://klikqris.com/api/sandbox'
            : $this->apiUrl;

        $payload = [
            'order_id' => $orderId,
            'id_merchant' => $this->merchantId,
            'amount' => (int) $amount,
        ];

        if ($keterangan !== null && $keterangan !== '') {
            $payload['keterangan'] = $keterangan;
        }

        if ($callbackUrl !== null && $callbackUrl !== '') {
            $payload['callback_url'] = $callbackUrl;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . '/qris/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'id_merchant: ' . $this->merchantId,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        $decoded = json_decode($response, true);

        return [
            'http_code' => $httpCode,
            'curl_error' => $error ?: null,
            'endpoint' => $baseUrl . '/qris/create',
            'request' => $payload,
            'raw_response' => $response,
            'response' => $decoded !== null ? $decoded : $response,
        ];
    }
}
