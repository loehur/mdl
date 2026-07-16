<?php

namespace App\Models;

/**
 * Doku SNAP Payment Gateway Model
 * Used for QRIS payments via Doku SNAP API
 * Reference: https://developers.doku.com/accept-payments/direct-api/snap/integration-guide/qris
 */
class Doku
{
    private $clientId;
    private $clientSecret;
    private $merchantId;
    private $apiUrl;
    private $tokenUrl;
    private $privateKey;
    private $publicKey;
    private $accessToken;
    private $tokenExpiry;

    public function __construct()
    {
        // Load credentials from Env config class
        $this->clientId = \Env::DOKU_CLIENT_ID;
        $this->clientSecret = \Env::DOKU_CLIENT_SECRET;
        $this->merchantId = \Env::DOKU_MERCHANT_ID;
        $this->apiUrl = \Env::DOKU_API_URL;
        $this->tokenUrl = $this->apiUrl . '/authorization/v1/access-token/b2b';
    }

    /**
     * Generate B2B Access Token
     * Required before calling any SNAP API
     */
    private function getAccessToken()
    {
        // Check if token is still valid
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        // Timestamp in ISO-8601 UTC format (YYYY-MM-DDTHH:mm:ssZ)
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        
        // Generate signature for token request
        // Formula: SHA256withRSA(privateKey, Client-Id|Timestamp)
        $stringToSign = $this->clientId . '|' . $timestamp;
        
        // Load private key
        $privateKeyPath = __DIR__ . '/../../doku_private.key';
        $privateKey = file_get_contents($privateKeyPath);
        $pkeyId = openssl_pkey_get_private($privateKey);
        
        if ($pkeyId === false) {
            return json_encode(['status' => false, 'message' => 'Failed to load private key']);
        }
        
        // Sign with SHA256
        openssl_sign($stringToSign, $signatureBinary, $pkeyId, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signatureBinary);

        $data = [
            'grantType' => 'client_credentials'
        ];

        $headers = [
            'X-CLIENT-KEY: ' . $this->clientId,
            'X-TIMESTAMP: ' . $timestamp,
            'X-SIGNATURE: ' . $signature,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return json_encode(['status' => false, 'message' => 'Failed to get access token: ' . $error]);
        }

        $result = json_decode($response, true);
        
        if (isset($result['accessToken'])) {
            $this->accessToken = $result['accessToken'];
            // Token typically expires in 15 minutes (900 seconds)
            $this->tokenExpiry = time() + (isset($result['expiresIn']) ? intval($result['expiresIn']) : 900);
            return $this->accessToken;
        }

        return json_encode(['status' => false, 'message' => 'Failed to retrieve access token', 'response' => $result]);
    }

    /**
     * Debug method - Get access token with full request/response details
     * Use this to troubleshoot authentication issues
     */
    public function getAccessTokenDebug()
    {
        // Timestamp in ISO-8601 UTC format (YYYY-MM-DDTHH:mm:ssZ)
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        
        // Generate signature for token request
        // Formula: SHA256withRSA(privateKey, Client-Id|Timestamp)
        $stringToSign = $this->clientId . '|' . $timestamp;
        
        // Load private key
        $privateKeyPath = __DIR__ . '/../../doku_private.key';
        $privateKey = file_get_contents($privateKeyPath);
        $pkeyId = openssl_pkey_get_private($privateKey);
        
        if ($pkeyId === false) {
            return ['error' => 'Failed to load private key'];
        }
        
        // Sign with SHA256
        openssl_sign($stringToSign, $signatureBinary, $pkeyId, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signatureBinary);

        $data = [
            'grantType' => 'client_credentials'
        ];

        $headers = [
            'X-CLIENT-KEY: ' . $this->clientId,
            'X-TIMESTAMP: ' . $timestamp,
            'X-SIGNATURE: ' . $signature,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'request' => [
                'url' => $this->tokenUrl,
                'client_id' => $this->clientId,
                'timestamp' => $timestamp,
                'string_to_sign' => $stringToSign,
                'signature' => $signature,
                'body' => $data,
                'headers' => $headers
            ],
            'response' => [
                'http_code' => $httpCode,
                'body' => json_decode($response, true),
                'raw' => $response,
                'error' => $error
            ]
        ];
    }

    /**
     * Generate symmetric signature for SNAP API calls
     * Formula: HMAC_SHA512(clientSecret, stringToSign)
     * stringToSign = HTTPMethod + ":" + EndpointUrl + ":" + AccessToken + ":" + Lowercase(HexEncode(SHA-256(minify(RequestBody)))) + ":" + TimeStamp
     * @see https://developers.doku.com/getting-started-with-doku-api/signature-component/snap/symmetric-signature
     * EndpointUrl = path only (e.g. /snap-adapter/b2b/v1.0/qr/qr-mpm-generate), not full URL
     */
    private function generateSignature($httpMethod, $endpointPath, $accessToken, $requestBody, $timestamp)
    {
        // Minify JSON (remove whitespace) - must match exactly what we send
        $decoded = json_decode($requestBody);
        $minifiedBody = $decoded !== null ? json_encode($decoded) : $requestBody;

        // SHA-256 of minified body, then hex encode, then lowercase
        $hashedBody = hash('sha256', $minifiedBody);
        $lowercaseHash = strtolower($hashedBody);

        // Create string to sign (EndpointUrl = path only per Doku docs)
        $stringToSign = $httpMethod . ':' . $endpointPath . ':' . $accessToken . ':' . $lowercaseHash . ':' . $timestamp;

        // HMAC SHA-512 with clientSecret, raw binary then base64
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret, true));

        return $signature;
    }

    /**
     * Generate QRIS payment
     */
    public function generateQRIS($partnerReferenceNo, $amount, $terminalId = 'k45', $expiredTime = null)
    {
        // Get access token
        $accessToken = $this->getAccessToken();
        if (!is_string($accessToken)) {
            return $accessToken; // Return error
        }

        // Generate request ID (numeric string, unique per day) - doc example: 41807553358950093184162180797837
        $requestId = str_replace('.', '', number_format(microtime(true) * 10000, 0, '', ''));
        if (strlen($requestId) > 32) {
            $requestId = substr($requestId, -32);
        }
        $requestId = str_pad($requestId, 32, '0', STR_PAD_LEFT);

        // X-TIMESTAMP: "Client's current local time" per doc - format YYYY-MM-DDTHH:mm:ss+07:00 (WIB)
        $tz = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);
        $timestamp = $now->format('Y-m-d\TH:i:sP');

        // validityPeriod: ISO 8601, default 30 days. Doc example: 2025-11-30T19:27:15+07:00
        if (!$expiredTime) {
            $expire = (new \DateTime('now', $tz))->modify('+30 days');
            $expiredTime = $expire->format('Y-m-d\TH:i:sP');
        }

        // Request body - doc: additionalInfo requires postalCode and feeType (1 = No Tips)
        $requestBody = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'IDR'
            ],
            'merchantId' => $this->merchantId,
            'terminalId' => $terminalId,
            'validityPeriod' => $expiredTime,
            'additionalInfo' => [
                'postalCode' => '28125',
                'feeType' => '1'   // 1 = No Tips (mandatory per doc)
            ]
        ];

        $requestBodyJson = json_encode($requestBody);

        $path = '/snap-adapter/b2b/v1.0/qr/qr-mpm-generate';
        $url = $this->apiUrl . $path;
        // Signature uses path only, not full URL (per Doku symmetric signature docs)
        $signature = $this->generateSignature('POST', $path, $accessToken, $requestBodyJson, $timestamp);

        // Headers
        $headers = [
            'Content-Type: application/json',
            'X-TIMESTAMP: ' . $timestamp,
            'X-SIGNATURE: ' . $signature,
            'X-PARTNER-ID: ' . $this->clientId,
            'X-EXTERNAL-ID: ' . $requestId,
            'CHANNEL-ID: H2H',
            'Authorization: Bearer ' . $accessToken
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $requestBodyJson,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return json_encode(['status' => false, 'message' => 'Connection Error: ' . $error]);
        }

        return $response;
    }

    /**
     * Query QRIS payment status
     */
    public function queryQRIS($originalPartnerReferenceNo, $originalReferenceNo = null, $serviceCode = '47')
    {
        // Get access token
        $accessToken = $this->getAccessToken();
        if (!is_string($accessToken)) {
            return $accessToken; // Return error
        }

        // Generate request ID (numeric string, unique per day)
        $requestId = str_replace('.', '', number_format(microtime(true) * 10000, 0, '', ''));
        if (strlen($requestId) > 32) {
            $requestId = substr($requestId, -32);
        }
        $requestId = str_pad($requestId, 32, '0', STR_PAD_LEFT);

        // X-TIMESTAMP: "Client's current local time" per doc (same as Generate)
        $tz = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTime('now', $tz);
        $timestamp = $now->format('Y-m-d\TH:i:sP');

        // Request body - doc: originalReferenceNo, originalPartnerReferenceNo, serviceCode, merchantId
        $requestBody = [
            'originalPartnerReferenceNo' => $originalPartnerReferenceNo,
            'serviceCode' => $serviceCode,
            'merchantId' => $this->merchantId
        ];
        if ($originalReferenceNo) {
            $requestBody['originalReferenceNo'] = $originalReferenceNo;
        }

        $requestBodyJson = json_encode($requestBody);

        $path = '/snap-adapter/b2b/v1.0/qr/qr-mpm-query';
        $url = $this->apiUrl . $path;
        $signature = $this->generateSignature('POST', $path, $accessToken, $requestBodyJson, $timestamp);

        // Headers
        $headers = [
            'Content-Type: application/json',
            'X-TIMESTAMP: ' . $timestamp,
            'X-SIGNATURE: ' . $signature,
            'X-PARTNER-ID: ' . $this->clientId,
            'X-EXTERNAL-ID: ' . $requestId,
            'CHANNEL-ID: H2H',
            'Authorization: Bearer ' . $accessToken
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $requestBodyJson,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            return json_encode(['status' => false, 'message' => 'Connection Error: ' . $error]);
        }

        return $response;
    }
}
