<?php

namespace App\Models;

/**
 * SuperFlash Transfer Model
 * Used for Transfer operations (Flash Mobile Transfer Service)
 *
 * Docs (OpenAPI YAML):
 * - Token: POST /auth/v2/access-token (client_key, server_key)
 * - Inquiry: POST /transfer/api/v1/inquiry (bank_code, bank_account, external_id)
 * - Payment: POST /transfer/api/v1/payment (recipient_bank, recipient_account, recipient_name, amount, note, external_id)
 * - Status: GET /transfer/api/v1/status/{external_id} (Bearer token)
 * 
 * Base URL:
 * - Production: https://secure.flashmobile.id/
 * - Sandbox: https://sandbox-secure.flashmobile.id/
 */
class SuperFlash_Transfer
{
    private $clientKey;
    private $serverKey;
    private $apiUrl;
    private $tokenCachePath;

    private static $cachedToken = null;
    private static $cachedTokenExp = 0; // unix timestamp

    public function __construct()
    {
        // Config via Env.php (recommended) or fallback to docs defaults.
        $this->clientKey = defined('\Env::SUPERFLASH_CLIENT_KEY') ? \Env::SUPERFLASH_CLIENT_KEY : null;
        $this->serverKey = defined('\Env::SUPERFLASH_SERVER_KEY') ? \Env::SUPERFLASH_SERVER_KEY : null;

        $this->apiUrl = $this->resolveBaseUrl();
        $this->tokenCachePath = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'superflash_transfer_token.json';
    }

    /**
     * Resolve base URL for Transfer API.
     *
     * Docs:
     * - Production: https://secure.flashmobile.id/
     * - Sandbox: https://sandbox-secure.flashmobile.id/
     */
    private function resolveBaseUrl()
    {
        // Check if custom URL is set
        if (defined('\Env::SUPERFLASH_TRANSFER_API_URL') && \Env::SUPERFLASH_TRANSFER_API_URL) {
            return rtrim(\Env::SUPERFLASH_TRANSFER_API_URL, '/');
        }

        $isProd = false;
        if (class_exists('\Env') && method_exists('\Env', 'isProduction')) {
            $isProd = \Env::isProduction();
        }

        return $isProd ? 'https://secure.flashmobile.id' : 'https://sandbox-secure.flashmobile.id';
    }

    /**
     * Make HTTP Request to SuperFlash Transfer API.
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, etc)
     * @param array|null $data Request body data
     * @param string|null $token OAuth token (Bearer)
     * @return array Response with status, http_code, message, and data
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $token = null)
    {
        $url = $this->apiUrl . $endpoint;
        
        $curl = curl_init();

        $verifySsl = false;
        if (class_exists('\Env') && method_exists('\Env', 'isProduction')) {
            $verifySsl = \Env::isProduction();
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return [
                'status' => false,
                'message' => 'Connection Error: ' . $error,
                'data' => null,
                'http_code' => 0
            ];
        }
        
        $decodedResponse = json_decode($response, true);

        // SuperFlash Transfer API response format:
        // {
        //   "data": {...},
        //   "meta": {...},
        //   "status": {
        //     "success": true/false,
        //     "code": "0001" | "0002" | "0003",
        //     "message": "Success" | "On Process" | "FAILED"
        //   }
        // }
        $gatewaySuccess = null;
        $gatewayCode = null;
        $gatewayMessage = null;
        if (is_array($decodedResponse) && isset($decodedResponse['status'])) {
            $gatewaySuccess = $decodedResponse['status']['success'] ?? null;
            $gatewayCode = $decodedResponse['status']['code'] ?? null;
            $gatewayMessage = $decodedResponse['status']['message'] ?? null;
        }

        $okHttp = $httpCode >= 200 && $httpCode < 300;
        // Transfer API: success=true means success, code 0001=Success, 0002=Pending, 0003=Failed
        $okGateway = ($gatewaySuccess === null) ? $okHttp : ($gatewaySuccess === true || $gatewayCode === '0002');

        return [
            'status' => $okHttp && $okGateway,
            'http_code' => $httpCode,
            'gateway_code' => $gatewayCode,
            'gateway_success' => $gatewaySuccess,
            'message' => $gatewayMessage ?? ($decodedResponse['message'] ?? ($okHttp ? 'Success' : 'Failed')),
            'data' => $decodedResponse,
            'raw' => $response,
        ];
    }

    /**
     * Request a new OAuth token from SuperFlash Transfer API.
     */
    private function requestToken()
    {
        if (!$this->clientKey || !$this->serverKey) {
            return [
                'status' => false,
                'message' => 'SuperFlash client_key/server_key belum dikonfigurasi (Env::SUPERFLASH_CLIENT_KEY / Env::SUPERFLASH_SERVER_KEY)',
                'data' => null,
                'http_code' => 500,
            ];
        }

        return $this->makeRequest('/auth/v2/access-token', 'POST', [
            'client_key' => $this->clientKey,
            'server_key' => $this->serverKey,
        ]);
    }

    /**
     * Decode exp from JWT (without verifying signature).
     */
    private function decodeJwtExp($jwt)
    {
        if (!$jwt || !is_string($jwt)) return null;
        $parts = explode('.', $jwt);
        if (count($parts) < 2) return null;

        $payload = $parts[1];
        $payload = strtr($payload, '-_', '+/');
        $padLen = 4 - (strlen($payload) % 4);
        if ($padLen < 4) $payload .= str_repeat('=', $padLen);

        $decoded = base64_decode($payload, true);
        if ($decoded === false) return null;

        $json = json_decode($decoded, true);
        if (!is_array($json)) return null;

        return isset($json['exp']) ? (int)$json['exp'] : null;
    }

    /**
     * Get OAuth token (cached).
     */
    private function getAccessToken()
    {
        $now = time();
        $minTtl = 60; // refresh if expiring within 60s

        // In-process cache
        if (self::$cachedToken && self::$cachedTokenExp > ($now + $minTtl)) {
            return [
                'status' => true,
                'token' => self::$cachedToken,
                'exp' => self::$cachedTokenExp,
            ];
        }

        // Session cache
        if (isset($_SESSION['superflash_transfer']) && is_array($_SESSION['superflash_transfer'])) {
            $tok = $_SESSION['superflash_transfer']['token'] ?? null;
            $exp = (int)($_SESSION['superflash_transfer']['exp'] ?? 0);
            if ($tok && $exp > ($now + $minTtl)) {
                self::$cachedToken = $tok;
                self::$cachedTokenExp = $exp;
                return ['status' => true, 'token' => $tok, 'exp' => $exp];
            }
        }

        // File cache (shared across sessions)
        if (is_file($this->tokenCachePath)) {
            $raw = @file_get_contents($this->tokenCachePath);
            $parsed = $raw ? json_decode($raw, true) : null;
            if (is_array($parsed)) {
                $tok = $parsed['token'] ?? null;
                $exp = (int)($parsed['exp'] ?? 0);
                if ($tok && $exp > ($now + $minTtl)) {
                    self::$cachedToken = $tok;
                    self::$cachedTokenExp = $exp;
                    $_SESSION['superflash_transfer'] = ['token' => $tok, 'exp' => $exp];
                    return ['status' => true, 'token' => $tok, 'exp' => $exp];
                }
            }
        }

        // Request new token
        $res = $this->requestToken();
        if (!$res['status']) {
            return [
                'status' => false,
                'message' => $res['message'] ?? 'Gagal mendapatkan token SuperFlash',
                'http_code' => $res['http_code'] ?? 500,
                'data' => $res['data'] ?? null,
            ];
        }

        // Transfer API token response format:
        // {
        //   "status": 200,
        //   "message": "success",
        //   "description": "Token received.",
        //   "data": {
        //     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
        //   },
        //   "meta": {}
        // }
        $token = $res['data']['data']['token'] ?? null;
        if (!$token) {
            return [
                'status' => false,
                'message' => 'Token tidak ditemukan pada response SuperFlash',
                'http_code' => 500,
                'data' => $res['data'] ?? null,
            ];
        }

        $exp = $this->decodeJwtExp($token);
        if (!$exp) {
            // Fallback: assume 7 days if exp missing
            $exp = $now + (7 * 24 * 60 * 60);
        }

        self::$cachedToken = $token;
        self::$cachedTokenExp = $exp;
        $_SESSION['superflash_transfer'] = ['token' => $token, 'exp' => $exp];
        @file_put_contents($this->tokenCachePath, json_encode(['token' => $token, 'exp' => $exp]));

        return ['status' => true, 'token' => $token, 'exp' => $exp];
    }

    /**
     * Account Inquiry - Validate beneficiary account name
     *
     * @param array $params {
     *   @var string $bank_code Bank code (3 chars, e.g. "014" for BCA)
     *   @var string $bank_account Account number (max 16 chars)
     *   @var string $external_id Unique merchant transaction ID (max 50 chars)
     * }
     * @return array Response with status, http_code, message, and data
     */
    public function accountInquiry($params)
    {
        $bankCode = trim($params['bank_code'] ?? '');
        $bankAccount = trim($params['bank_account'] ?? '');
        $externalId = trim($params['external_id'] ?? '');

        if (empty($bankCode)) {
            return [
                'status' => false,
                'message' => 'bank_code wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (empty($bankAccount)) {
            return [
                'status' => false,
                'message' => 'bank_account wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (empty($externalId)) {
            return [
                'status' => false,
                'message' => 'external_id wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        // Validate bank_code length (3 chars)
        if (strlen($bankCode) > 3) {
            return [
                'status' => false,
                'message' => 'bank_code maksimal 3 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        // Validate bank_account length (max 16 chars)
        if (strlen($bankAccount) > 16) {
            return [
                'status' => false,
                'message' => 'bank_account maksimal 16 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        // Validate external_id length (max 50 chars)
        if (strlen($externalId) > 50) {
            return [
                'status' => false,
                'message' => 'external_id maksimal 50 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        $tokenRes = $this->getAccessToken();
        if (!$tokenRes['status']) {
            return [
                'status' => false,
                'message' => $tokenRes['message'] ?? 'Gagal mendapatkan token',
                'data' => $tokenRes['data'] ?? null,
                'http_code' => $tokenRes['http_code'] ?? 500,
            ];
        }

        $payload = [
            'bank_code' => $bankCode,
            'bank_account' => $bankAccount,
            'external_id' => $externalId,
        ];

        return $this->makeRequest('/transfer/api/v1/inquiry', 'POST', $payload, $tokenRes['token']);
    }

    /**
     * Fund Transfer - Send money to bank account
     *
     * @param array $params {
     *   @var string $recipient_bank Bank code (3 chars)
     *   @var string $recipient_account Account number (max 16 chars)
     *   @var string $recipient_name Account name (optional, max 45 chars)
     *   @var int $amount Amount (min: 10,000; max: 50,000,000)
     *   @var string $note Transfer note (max 64 chars)
     *   @var string $external_id Merchant transaction ID (max 64 chars, but docs say max 50)
     * }
     * @return array Response with status, http_code, message, and data
     */
    public function fundTransfer($params)
    {
        $recipientBank = trim($params['recipient_bank'] ?? '');
        $recipientAccount = trim($params['recipient_account'] ?? '');
        $recipientName = trim($params['recipient_name'] ?? '');
        $amount = (int)($params['amount'] ?? 0);
        $note = trim($params['note'] ?? '');
        $externalId = trim($params['external_id'] ?? '');

        if (empty($recipientBank)) {
            return [
                'status' => false,
                'message' => 'recipient_bank wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (empty($recipientAccount)) {
            return [
                'status' => false,
                'message' => 'recipient_account wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if ($amount < 10000) {
            return [
                'status' => false,
                'message' => 'amount minimal 10,000',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if ($amount > 50000000) {
            return [
                'status' => false,
                'message' => 'amount maksimal 50,000,000',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (empty($note)) {
            return [
                'status' => false,
                'message' => 'note wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (empty($externalId)) {
            return [
                'status' => false,
                'message' => 'external_id wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        // Validate lengths
        if (strlen($recipientBank) > 3) {
            return [
                'status' => false,
                'message' => 'recipient_bank maksimal 3 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (strlen($recipientAccount) > 16) {
            return [
                'status' => false,
                'message' => 'recipient_account maksimal 16 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (strlen($recipientName) > 45) {
            return [
                'status' => false,
                'message' => 'recipient_name maksimal 45 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (strlen($note) > 64) {
            return [
                'status' => false,
                'message' => 'note maksimal 64 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (strlen($externalId) > 64) {
            return [
                'status' => false,
                'message' => 'external_id maksimal 64 karakter',
                'data' => null,
                'http_code' => 400,
            ];
        }

        $tokenRes = $this->getAccessToken();
        if (!$tokenRes['status']) {
            return [
                'status' => false,
                'message' => $tokenRes['message'] ?? 'Gagal mendapatkan token',
                'data' => $tokenRes['data'] ?? null,
                'http_code' => $tokenRes['http_code'] ?? 500,
            ];
        }

        $payload = [
            'recipient_bank' => $recipientBank,
            'recipient_account' => $recipientAccount,
            'amount' => $amount,
            'note' => $note,
            'external_id' => $externalId,
        ];

        // recipient_name is optional
        if (!empty($recipientName)) {
            $payload['recipient_name'] = $recipientName;
        }

        return $this->makeRequest('/transfer/api/v1/payment', 'POST', $payload, $tokenRes['token']);
    }

    /**
     * Check Transfer Status
     *
     * @param string $externalId Merchant transaction ID (external_id)
     * @return array Response with status, http_code, message, and data
     */
    public function checkTransferStatus($externalId)
    {
        if (empty($externalId)) {
            return [
                'status' => false,
                'message' => 'external_id wajib diisi',
                'data' => null,
                'http_code' => 400
            ];
        }

        $tokenRes = $this->getAccessToken();
        if (!$tokenRes['status']) {
            return [
                'status' => false,
                'message' => $tokenRes['message'] ?? 'Gagal mendapatkan token',
                'data' => $tokenRes['data'] ?? null,
                'http_code' => $tokenRes['http_code'] ?? 500,
            ];
        }

        $endpoint = '/transfer/api/v1/status/' . rawurlencode($externalId);
        return $this->makeRequest($endpoint, 'GET', null, $tokenRes['token']);
    }
}
