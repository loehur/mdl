<?php

namespace App\Helpers;

use App\Config\Fonnte;

/**
 * Fonnte WhatsApp API Service
 * @see https://docs.fonnte.com/api-send-message/
 */
class FonnteService
{
    private $apiUrl;
    private $token;

    public function __construct()
    {
        $this->apiUrl = Fonnte::getBaseUrl() . '/send';
        $this->token = Fonnte::getToken();
    }

    /**
     * Send message via Fonnte API
     * @param string $phone Target phone (0812xxx or 62812xxx)
     * @param string $message Message text
     * @param array $options Optional: url, filename, etc.
     * @return array ['success' => bool, 'data' => array, 'error' => string|null]
     */
    public function sendMessage($phone, $message, array $options = [])
    {
        if (empty($this->token)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Fonnte token not configured',
            ];
        }

        $target = $this->formatPhone($phone);
        if (empty($target)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Invalid phone number',
            ];
        }

        $postFields = [
            'target' => $target,
            'message' => (string) $message,
            'typing' => true,
            'delay' => $options['delay'] ?? '3-20',
        ];

        if (!empty($options['url'])) {
            $postFields['url'] = (string) $options['url'];
        }
        if (!empty($options['filename'])) {
            $postFields['filename'] = (string) $options['filename'];
        }
        if (isset($options['countryCode'])) {
            $postFields['countryCode'] = (string) $options['countryCode'];
        }

        $response = $this->callApi($postFields);

        $ok = isset($response['status']) && $response['status'] === true;
        return [
            'success' => $ok,
            'data' => $response,
            'error' => $ok ? null : ($response['reason'] ?? 'Unknown error'),
        ];
    }

    /**
     * Format phone for Fonnte (62xxx)
     */
    private function formatPhone($phone)
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 8) {
            return '';
        }
        $countryCode = Fonnte::getCountryCode();
        if ($countryCode !== '0') {
            if (substr($digits, 0, 1) === '0') {
                $digits = $countryCode . substr($digits, 1);
            } elseif (strlen($digits) <= 10 && substr($digits, 0, 2) !== $countryCode) {
                $digits = $countryCode . $digits;
            }
        }
        return $digits;
    }

    /**
     * Call Fonnte Send API
     */
    private function callApi(array $postFields)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->token,
            ],
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['status' => false, 'reason' => 'cURL error: ' . $curlError];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => false, 'reason' => 'Invalid JSON response', 'raw' => substr($raw, 0, 200)];
        }

        if (isset($decoded['Status'])) {
            $decoded['status'] = $decoded['Status'];
        }

        return $decoded;
    }
}
