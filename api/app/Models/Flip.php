<?php
namespace App\Models;

require_once __DIR__ . '/../Config/Env.php';

/**
 * Flip for Business API Model
 * 
 * Provides integration with Flip.id payment gateway for:
 * - Balance inquiry
 * - Bank list
 * - Bank account inquiry (validation)
 * - Money transfer / Disbursement
 * 
 * @link https://docs.flip.id
 */
class Flip
{
    /**
     * Flip API Secret Key
     */
    private $secretKey;
    
    /**
     * Flip API Base URL
     * Production: https://bigflip.id/api/v3
     * Sandbox: https://bigflip.id/big_sandbox_api/v3
     */
    private $baseUrl;
    
    /**
     * Environment mode: 'production' or 'sandbox'
     */
    private $environment;
    
    /**
     * Validation Token for callback verification
     */
    private $validationToken;

    public function __construct()
    {
        // Read configuration from Env class
        $this->environment = defined('\\Env::FLIP_ENV') ? \Env::FLIP_ENV : 'sandbox';
        $this->secretKey = defined('\\Env::FLIP_SECRET_KEY') ? \Env::FLIP_SECRET_KEY : '';
        $this->validationToken = defined('\\Env::FLIP_VALIDATION_TOKEN') ? \Env::FLIP_VALIDATION_TOKEN : '';
        
        // Set base URL based on environment
        if ($this->environment === 'production') {
            $this->baseUrl = "https://bigflip.id/api/v3";
        } else {
            $this->baseUrl = "https://bigflip.id/big_sandbox_api/v3";
        }
    }

    /**
     * =========================================
     * BALANCE
     * =========================================
     */

    /**
     * Get current Flip deposit balance
     * 
     * @return array Response containing balance info
     *  - balance: int Current balance in IDR
     */
    public function getBalance()
    {
        $url = $this->baseUrl . "/general/balance";
        return $this->sendRequest('GET', $url);
    }

    /**
     * =========================================
     * BANK LIST
     * =========================================
     */

    /**
     * Get list of available banks for disbursement
     * 
     * @param string|null $code Optional - Filter by specific bank code
     * @return array List of banks with their codes, names, and fees
     *  - bank_code: string Bank code (e.g., 'bca', 'mandiri')
     *  - name: string Bank name
     *  - fee: int Transfer fee in IDR
     *  - queue: int Current queue status
     *  - status: string Bank status ('OPERATIONAL', 'DISTURBED', 'HEAVILY_DISTURBED')
     */
    public function getBankList($code = null)
    {
        $url = $this->baseUrl . "/general/banks";
        
        $params = [];
        if ($code !== null) {
            $params['code'] = $code;
        }
        
        return $this->sendRequest('GET', $url, $params);
    }

    /**
     * Get info for specific bank code
     * 
     * @param string $bankCode Bank code (e.g., 'bca', 'mandiri', 'bni')
     * @return array Bank info
     */
    public function getBankInfo($bankCode)
    {
        return $this->getBankList($bankCode);
    }

    /**
     * Check if bank is operational
     * 
     * @param string $bankCode
     * @return bool
     */
    public function isBankOperational($bankCode)
    {
        $bankInfo = $this->getBankInfo($bankCode);
        if (isset($bankInfo['status'])) {
            return $bankInfo['status'] === 'OPERATIONAL';
        }
        return false;
    }

    /**
     * =========================================
     * BANK INQUIRY
     * =========================================
     */

    /**
     * Inquiry bank account to validate and get account holder name
     * 
     * @param string $bankCode Bank code (e.g., 'bca', 'mandiri', 'bni', 'bri')
     * @param string $accountNumber Bank account number
     * @param string|null $inquiryKey Optional - Unique key for idempotency
     * @return array Response containing:
     *  - bank_code: string Bank code
     *  - account_number: string Account number
     *  - account_holder: string Account holder name
     *  - status: string 'SUCCESS', 'PENDING', 'INVALID_ACCOUNT_NUMBER', 'SUSPECTED_ACCOUNT', 'BLACK_LISTED'
     *  - inquiry_key: string Unique inquiry key
     */
    public function bankInquiry($bankCode, $accountNumber, $inquiryKey = null)
    {
        $url = $this->baseUrl . "/disbursement/bank-account-inquiry";
        
        $data = [
            'bank_code' => strtolower($bankCode),
            'account_number' => $accountNumber
        ];
        
        if ($inquiryKey !== null) {
            $data['inquiry_key'] = $inquiryKey;
        } else {
            // Generate unique inquiry key if not provided
            $data['inquiry_key'] = 'INQ-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        }
        
        return $this->sendRequest('POST', $url, $data);
    }

    /**
     * Get bank inquiry result by inquiry key (for async inquiries)
     * 
     * @param string $inquiryKey The inquiry key from initial inquiry
     * @return array Inquiry result
     */
    public function getBankInquiryResult($inquiryKey)
    {
        $url = $this->baseUrl . "/disbursement/bank-account-inquiry/" . urlencode($inquiryKey);
        return $this->sendRequest('GET', $url);
    }

    /**
     * =========================================
     * DISBURSEMENT / MONEY TRANSFER
     * =========================================
     */

    /**
     * Create disbursement (money transfer)
     * 
     * @param array $params Disbursement parameters:
     *  - bank_code: string (required) Bank code
     *  - account_number: string (required) Destination account number
     *  - amount: int (required) Amount in IDR (min 10000)
     *  - remark: string (optional) Remark max 18 chars
     *  - idempotency_key: string (optional) Unique key for idempotency
     *  - beneficiary_email: string (optional) Email for notification
     * @return array Disbursement response
     */
    public function createDisbursement($params)
    {
        $url = $this->baseUrl . "/disbursement";
        
        $requiredFields = ['bank_code', 'account_number', 'amount'];
        foreach ($requiredFields as $field) {
            if (!isset($params[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }
        
        $data = [
            'bank_code' => strtolower($params['bank_code']),
            'account_number' => $params['account_number'],
            'amount' => (int)$params['amount']
        ];
        
        // Optional fields
        if (isset($params['remark'])) {
            $data['remark'] = substr($params['remark'], 0, 18);
        }
        
        if (isset($params['beneficiary_email'])) {
            $data['beneficiary_email'] = $params['beneficiary_email'];
        }
        
        // Idempotency key
        $idempotencyKey = $params['idempotency_key'] ?? 'TRF-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        
        return $this->sendRequest('POST', $url, $data, $idempotencyKey);
    }

    /**
     * Get disbursement status by ID
     * 
     * @param int $disbursementId Flip disbursement ID
     * @return array Disbursement detail and status
     */
    public function getDisbursement($disbursementId)
    {
        $url = $this->baseUrl . "/disbursement/" . (int)$disbursementId;
        return $this->sendRequest('GET', $url);
    }

    /**
     * Get disbursement by idempotency key
     * 
     * @param string $idempotencyKey
     * @return array Disbursement detail
     */
    public function getDisbursementByIdempotencyKey($idempotencyKey)
    {
        $url = $this->baseUrl . "/get-disbursement";
        $data = ['idempotency_key' => $idempotencyKey];
        return $this->sendRequest('POST', $url, $data);
    }

    /**
     * Get list of all disbursements
     * 
     * @param array $params Filter parameters:
     *  - pagination: int Items per page (default 20)
     *  - page: int Page number (default 1)
     *  - sort: string 'asc' or 'desc' by created date
     * @return array List of disbursements
     */
    public function getDisbursementList($params = [])
    {
        $url = $this->baseUrl . "/disbursement";
        return $this->sendRequest('GET', $url, $params);
    }

    /**
     * =========================================
     * CALLBACK / WEBHOOK HANDLING
     * =========================================
     */

    /**
     * Verify callback signature from Flip
     * 
     * @param string $token Token from X-Callback-Token header
     * @return bool True if valid
     */
    public function verifyCallback($token)
    {
        return $token === $this->validationToken;
    }

    /**
     * Parse callback data from Flip
     * 
     * @param string $rawBody Raw POST body (form-urlencoded 'data' field)
     * @return array|null Parsed callback data or null if invalid
     */
    public function parseCallback($rawBody)
    {
        // Flip sends callback as form-urlencoded with 'data' field containing JSON
        parse_str($rawBody, $parsed);
        
        if (isset($parsed['data'])) {
            return json_decode($parsed['data'], true);
        }
        
        return null;
    }

    /**
     * =========================================
     * UTILITY METHODS
     * =========================================
     */

    /**
     * Send HTTP request to Flip API
     * 
     * @param string $method HTTP method (GET, POST)
     * @param string $url API endpoint URL
     * @param array $data Request data
     * @param string|null $idempotencyKey Idempotency key for POST requests
     * @return array Response data
     */
    private function sendRequest($method, $url, $data = [], $idempotencyKey = null)
    {
        $ch = curl_init();
        
        // Build authorization header (Basic Auth with secret key)
        $authHeader = 'Basic ' . base64_encode($this->secretKey . ':');
        
        $headers = [
            'Authorization: ' . $authHeader,
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        // Add idempotency key header if provided
        if ($idempotencyKey !== null) {
            $headers[] = 'idempotency-key: ' . $idempotencyKey;
        }
        
        if ($method === 'GET') {
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $curlError,
                'http_code' => 0
            ];
        }
        
        $response = json_decode($result, true);
        
        if ($response === null) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'http_code' => $httpCode,
                'raw_response' => $result
            ];
        }
        
        // Add HTTP code and success flag
        $response['http_code'] = $httpCode;
        $response['success'] = ($httpCode >= 200 && $httpCode < 300);
        
        return $response;
    }

    /**
     * Get current environment
     * 
     * @return string 'production' or 'sandbox'
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Check if using sandbox mode
     * 
     * @return bool
     */
    public function isSandbox()
    {
        return $this->environment === 'sandbox';
    }

    /**
     * =========================================
     * BANK CODE CONSTANTS
     * =========================================
     */
    
    const BANK_BCA = 'bca';
    const BANK_BNI = 'bni';
    const BANK_BRI = 'bri';
    const BANK_MANDIRI = 'mandiri';
    const BANK_BSI = 'bsi';
    const BANK_CIMB = 'cimb';
    const BANK_PERMATA = 'permata';
    const BANK_DANAMON = 'danamon';
    const BANK_BTN = 'btn';
    const BANK_OCBC = 'ocbc';
    const BANK_MAYBANK = 'maybank';
    const BANK_PANIN = 'panin';
    const BANK_BTPN = 'btpn';
    const BANK_JENIUS = 'jenius';
    const BANK_GOPAY = 'gopay';
    const BANK_OVO = 'ovo';
    const BANK_DANA = 'dana';
    const BANK_LINKAJA = 'linkaja';
    const BANK_SHOPEEPAY = 'shopeepay';
    
    /**
     * =========================================
     * STATUS CONSTANTS
     * =========================================
     */
    
    // Disbursement status
    const STATUS_PENDING = 'PENDING';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_DONE = 'DONE';
    
    // Bank inquiry status
    const INQUIRY_SUCCESS = 'SUCCESS';
    const INQUIRY_PENDING = 'PENDING';
    const INQUIRY_INVALID = 'INVALID_ACCOUNT_NUMBER';
    const INQUIRY_SUSPECTED = 'SUSPECTED_ACCOUNT';
    const INQUIRY_BLACKLISTED = 'BLACK_LISTED';
    
    // Bank operational status
    const BANK_OPERATIONAL = 'OPERATIONAL';
    const BANK_DISTURBED = 'DISTURBED';
    const BANK_HEAVILY_DISTURBED = 'HEAVILY_DISTURBED';
}
