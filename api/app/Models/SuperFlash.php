<?php

namespace App\Models;

/**
 * SuperFlash Payment Gateway Model
 * Used for QRIS payment operations (Flash Mobile QRIS Service)
 *
 * Docs (OpenAPI YAML):
 * - Token: POST /priv/v1/pg/token  (client_key, server_key)
 * - Create payment: POST /payment/api/v1/qris/payment (Bearer token)
 * - Status: GET /payment/api/v1/qris/payment-status/{transaction_id} (Bearer token)
 */
class SuperFlash
{
    private $clientKey;
    private $serverKey;
    private $apiUrl;
    private $tokenCachePath;

    private static $cachedToken = null;
    private static $cachedTokenExp = 0; // unix timestamp

    /**
     * SuperFlash docs specify terminal_id/external_id as String(16).
     * If caller sends longer IDs, we deterministically hash them to 16 chars.
     */
    private function normalizeId16($id)
    {
        $id = (string)$id;
        $trimmed = trim($id);
        if ($trimmed === '') return '';

        // Keep original if already within limit (do not alter semantics)
        if (strlen($trimmed) <= 16) return $trimmed;

        return substr(hash('sha256', $trimmed), 0, 16);
    }

    public function __construct()
    {
        // Config via Env.php (recommended) or fallback to docs defaults.
        $this->clientKey = defined('\Env::SUPERFLASH_CLIENT_KEY') ? \Env::SUPERFLASH_CLIENT_KEY : null;
        $this->serverKey = defined('\Env::SUPERFLASH_SERVER_KEY') ? \Env::SUPERFLASH_SERVER_KEY : null;

        $this->apiUrl = $this->resolveBaseUrl();
        $this->tokenCachePath = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'superflash_qris_token.json';
    }

    /**
     * Resolve base URL for QRIS API.
     *
     * Docs:
     * - Production: https://app.flashmobile.co.id
     * - Sandbox: https://sandbox-app.flashmobile.co.id
     */
    private function resolveBaseUrl()
    {
        $isProd = false;
        if (class_exists('\Env') && method_exists('\Env', 'isProduction')) {
            $isProd = \Env::isProduction();
        }

        return $isProd ? 'https://app.flashmobile.id' : 'https://sandbox-app.flashmobile.id';
    }

    /**
     * Make HTTP Request to SuperFlash API.
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

        $gatewayCode = null;
        $gatewayMessage = null;
        if (is_array($decodedResponse) && isset($decodedResponse['status'])) {
            $gatewayCode = $decodedResponse['status']['code'] ?? null;
            $gatewayMessage = $decodedResponse['status']['message'] ?? null;
        }

        $okHttp = $httpCode >= 200 && $httpCode < 300;
        $okGateway = ($gatewayCode === null) ? $okHttp : in_array((int)$gatewayCode, [0, 200], true);

        return [
            'status' => $okHttp && $okGateway,
            'http_code' => $httpCode,
            'gateway_code' => $gatewayCode,
            'message' => $gatewayMessage ?? ($decodedResponse['message'] ?? ($okHttp ? 'Success' : 'Failed')),
            'data' => $decodedResponse,
            'raw' => $response,
        ];
    }

    /**
     * Request a new OAuth token from SuperFlash.
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

        return $this->makeRequest('/priv/v1/pg/token', 'POST', [
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
        if (isset($_SESSION['superflash_qris']) && is_array($_SESSION['superflash_qris'])) {
            $tok = $_SESSION['superflash_qris']['token'] ?? null;
            $exp = (int)($_SESSION['superflash_qris']['exp'] ?? 0);
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
                    $_SESSION['superflash_qris'] = ['token' => $tok, 'exp' => $exp];
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
        $_SESSION['superflash_qris'] = ['token' => $token, 'exp' => $exp];
        @file_put_contents($this->tokenCachePath, json_encode(['token' => $token, 'exp' => $exp]));

        return ['status' => true, 'token' => $token, 'exp' => $exp];
    }

    /**
     * Generate QRIS Code (Dynamic QR).
     *
     * Accepts both the new spec keys and legacy keys for convenience.
     *
     * Required (spec):
     * - terminal_id
     * - external_id
     * - amount
     * - session_time (minutes)
     * - fullname, email, phone_number (can be empty strings)
     */
    public function generateQRIS($params)
    {
        $amount = (int)($params['amount'] ?? 0);
        $externalId = $params['external_id'];
        $terminalId = $params['terminal_id'] ?? ($params['terminal'] ?? $externalId);

        $description = (string)($params['description'] ?? '');
        $fullname = (string)($params['fullname'] ?? ($params['customer_name'] ?? ''));
        $email = (string)($params['email'] ?? ($params['customer_email'] ?? ''));
        $phone = (string)($params['phone_number'] ?? ($params['customer_phone'] ?? ''));

        $sessionTime = (int)($params['session_time'] ?? 0);
        if ($sessionTime <= 0 && !empty($params['expired_at'])) {
            $expiredAt = strtotime($params['expired_at']);
            if ($expiredAt) {
                $diffSec = $expiredAt - time();
                $sessionTime = (int)ceil($diffSec / 60);
            }
        }
        if ($sessionTime <= 0) $sessionTime = 10; // sensible default

        if (!$externalId) {
            return [
                'status' => false,
                'message' => 'external_id (atau order_id) wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if (!$terminalId) {
            return [
                'status' => false,
                'message' => 'terminal_id wajib diisi',
                'data' => null,
                'http_code' => 400,
            ];
        }

        $originalExternalId = (string)$externalId;
        $originalTerminalId = (string)$terminalId;
        $gatewayExternalId = $this->normalizeId16($originalExternalId);
        $gatewayTerminalId = $this->normalizeId16($originalTerminalId);

        if ($gatewayExternalId === '' || $gatewayTerminalId === '') {
            return [
                'status' => false,
                'message' => 'external_id/terminal_id tidak valid',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if ($amount < 1000) {
            return [
                'status' => false,
                'message' => 'amount minimal 1000',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if ($amount > 10000000) {
            return [
                'status' => false,
                'message' => 'amount maksimal 10000000',
                'data' => null,
                'http_code' => 400,
            ];
        }

        if ($sessionTime < 1) {
            return [
                'status' => false,
                'message' => 'session_time minimal 1 menit',
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
            'terminal_id' => $gatewayTerminalId,
            'external_id' => $gatewayExternalId,
            'amount' => $amount,
            'description' => $description,
            'session_time' => $sessionTime,
            'fullname' => $fullname,
            'email' => $email,
            'phone_number' => $phone,
        ];

        $res = $this->makeRequest('/payment/api/v1/qris/payment', 'POST', $payload, $tokenRes['token']);

        // Attach mapping info so caller can reconcile their original order_id.
        if (is_array($res) && isset($res['data']) && is_array($res['data'])) {
            if (!isset($res['data']['meta']) || !is_array($res['data']['meta'])) {
                $res['data']['meta'] = [];
            }
            $res['data']['meta']['original_external_id'] = $originalExternalId;
            $res['data']['meta']['gateway_external_id'] = $gatewayExternalId;
            $res['data']['meta']['original_terminal_id'] = $originalTerminalId;
            $res['data']['meta']['gateway_terminal_id'] = $gatewayTerminalId;
        }

        return $res;
    }

    /**
     * Check QRIS Payment Status (by transaction_id from SuperFlash response).
     */
    public function checkPaymentStatus($transactionId)
    {
        if (empty($transactionId)) {
            return [
                'status' => false,
                'message' => 'transaction_id wajib diisi',
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

        $endpoint = '/payment/api/v1/qris/payment-status/' . rawurlencode($transactionId);
        return $this->makeRequest($endpoint, 'GET', null, $tokenRes['token']);
    }
}
