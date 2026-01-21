<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Flip as FlipModel;

require_once __DIR__ . '/../Helpers/Log.php';

/**
 * Flip API Controller
 * 
 * Endpoints for Flip.id payment gateway integration
 * 
 * Available endpoints:
 * - GET  /Flip/balance     - Get current balance
 * - GET  /Flip/banks       - Get list of available banks
 * - GET  /Flip/banks/{code} - Get specific bank info
 * - POST /Flip/inquiry     - Bank account inquiry
 * - GET  /Flip/inquiry/{key} - Get inquiry result by key
 * - POST /Flip/callback    - Handle Flip callback/webhook
 */
class Flip extends Controller
{
    private $flip;

    public function __construct()
    {
        $this->flip = new FlipModel();
    }

    /**
     * Default route - API info
     * GET /Flip
     */
    public function index()
    {
        $this->handleCors();
        
        $this->success([
            'service' => 'Flip Payment Gateway',
            'environment' => $this->flip->getEnvironment(),
            'endpoints' => [
                'GET /Flip/balance' => 'Get current deposit balance',
                'GET /Flip/banks' => 'Get list of available banks',
                'GET /Flip/banks/{code}' => 'Get specific bank info',
                'POST /Flip/inquiry' => 'Bank account inquiry',
                'GET /Flip/inquiry/{key}' => 'Get inquiry result by key',
                'POST /Flip/callback' => 'Webhook handler from Flip',
            ]
        ], 'Flip API is running');
    }

    /**
     * Get current Flip deposit balance
     * GET /Flip/balance
     * 
     * @return JSON { status, message, data: { balance } }
     */
    public function balance()
    {
        $this->handleCors();
        
        if (!$this->isGet()) {
            $this->error('Method not allowed', 405);
        }
        
        $result = $this->flip->getBalance();
        
        if (isset($result['success']) && $result['success']) {
            $this->success([
                'balance' => $result['balance'] ?? 0
            ], 'Balance retrieved successfully');
        } else {
            $this->error(
                $result['error'] ?? 'Failed to get balance',
                $result['http_code'] ?? 500,
                $result
            );
        }
    }

    /**
     * Get list of available banks
     * GET /Flip/banks
     * GET /Flip/banks/{code}
     * 
     * @param string|null $code Optional bank code filter
     * @return JSON { status, message, data: [...banks] }
     */
    public function banks($code = null)
    {
        $this->handleCors();
        
        if (!$this->isGet()) {
            $this->error('Method not allowed', 405);
        }
        
        $result = $this->flip->getBankList($code);
        
        if (isset($result['success']) && $result['success']) {
            // If single bank requested
            if ($code !== null) {
                $this->success($result, 'Bank info retrieved successfully');
            } else {
                $this->success($result, 'Bank list retrieved successfully');
            }
        } else {
            $this->error(
                $result['error'] ?? 'Failed to get bank list',
                $result['http_code'] ?? 500,
                $result
            );
        }
    }

    /**
     * Bank account inquiry
     * POST /Flip/inquiry
     * 
     * Request body:
     * {
     *   "bank_code": "bca",
     *   "account_number": "1234567890",
     *   "inquiry_key": "optional-unique-key"
     * }
     * 
     * @return JSON { status, message, data: { bank_code, account_number, account_holder, status, inquiry_key } }
     */
    public function inquiry($key = null)
    {
        $this->handleCors();
        
        // GET request - retrieve inquiry result by key
        if ($this->isGet()) {
            if ($key === null) {
                $this->error('Inquiry key is required for GET request', 400);
            }
            
            $result = $this->flip->getBankInquiryResult($key);
            
            if (isset($result['success']) && $result['success']) {
                $this->success([
                    'bank_code' => $result['bank_code'] ?? null,
                    'account_number' => $result['account_number'] ?? null,
                    'account_holder' => $result['account_holder'] ?? null,
                    'status' => $result['status'] ?? null,
                    'inquiry_key' => $result['inquiry_key'] ?? $key
                ], 'Inquiry result retrieved');
            } else {
                $this->error(
                    $result['error'] ?? 'Failed to get inquiry result',
                    $result['http_code'] ?? 500,
                    $result
                );
            }
            return;
        }
        
        // POST request - create new inquiry
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }
        
        $body = $this->getBody();
        
        // Validate required fields
        $this->validate($body, ['bank_code', 'account_number']);
        
        $bankCode = $body['bank_code'];
        $accountNumber = $body['account_number'];
        $inquiryKey = $body['inquiry_key'] ?? null;
        
        // Validate bank code format
        if (strlen($bankCode) < 2) {
            $this->error('Invalid bank code', 400);
        }
        
        // Validate account number (numeric, reasonable length)
        if (!preg_match('/^\d{5,20}$/', $accountNumber)) {
            $this->error('Invalid account number format', 400);
        }
        
        $result = $this->flip->bankInquiry($bankCode, $accountNumber, $inquiryKey);
        
        if (isset($result['success']) && $result['success']) {
            $this->success([
                'bank_code' => $result['bank_code'] ?? $bankCode,
                'account_number' => $result['account_number'] ?? $accountNumber,
                'account_holder' => $result['account_holder'] ?? null,
                'status' => $result['status'] ?? 'PENDING',
                'inquiry_key' => $result['inquiry_key'] ?? null
            ], $this->getInquiryMessage($result['status'] ?? 'PENDING'));
        } else {
            $this->error(
                $result['error'] ?? 'Failed to perform bank inquiry',
                $result['http_code'] ?? 500,
                $result
            );
        }
    }

    /**
     * Handle Flip callback/webhook
     * POST /Flip/callback
     * 
     * Flip sends callbacks for:
     * - Bank inquiry results
     * - Disbursement status updates
     * 
     * All callbacks are logged to: logs/{date}/flip_callback.log
     */
    public function callback()
    {
        // Don't handle CORS for callbacks (server-to-server)
        
        if (!$this->isPost()) {
            \Log::write("Invalid method: " . $this->method(), 'flip', 'callback');
            $this->error('Method not allowed', 405);
        }
        
        // Get raw body first for logging
        $rawBody = file_get_contents('php://input');
        
        // Log incoming request
        \Log::write("=== INCOMING CALLBACK ===", 'flip', 'callback');
        \Log::write("Raw Body: " . $rawBody, 'flip', 'callback');
        
        // Verify callback token
        $token = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
        \Log::write("Token: " . ($token ? substr($token, 0, 10) . '...' : 'EMPTY'), 'flip', 'callback');
        
        if (!$this->flip->verifyCallback($token)) {
            \Log::write("ERROR: Invalid callback token", 'flip', 'callback');
            $this->error('Invalid callback token', 401);
        }
        
        // Parse callback data
        $data = $this->flip->parseCallback($rawBody);
        
        if ($data === null) {
            \Log::write("ERROR: Failed to parse callback body", 'flip', 'callback');
            $this->error('Invalid callback data', 400);
        }
        
        // Log parsed data
        \Log::write("Parsed Data: " . json_encode($data, JSON_PRETTY_PRINT), 'flip', 'callback');
        
        // Determine callback type and log accordingly
        $callbackType = $this->detectCallbackType($data);
        \Log::write("Callback Type: " . $callbackType, 'flip', 'callback');
        
        // Process based on callback type
        switch ($callbackType) {
            case 'inquiry':
                $this->handleInquiryCallback($data);
                break;
            case 'disbursement':
                $this->handleDisbursementCallback($data);
                break;
            default:
                \Log::write("Unknown callback type, data stored for review", 'flip', 'callback');
        }
        
        \Log::write("=== CALLBACK PROCESSED ===", 'flip', 'callback');
        
        // Acknowledge callback
        $this->success(null, 'Callback received');
    }

    /**
     * Detect callback type from data
     * @param array $data
     * @return string 'inquiry', 'disbursement', or 'unknown'
     */
    private function detectCallbackType($data)
    {
        if (isset($data['inquiry_key'])) {
            return 'inquiry';
        }
        if (isset($data['id']) && isset($data['status']) && isset($data['amount'])) {
            return 'disbursement';
        }
        return 'unknown';
    }

    /**
     * Handle bank inquiry callback
     * @param array $data Callback data
     */
    private function handleInquiryCallback($data)
    {
        $logData = [
            'type' => 'BANK_INQUIRY',
            'inquiry_key' => $data['inquiry_key'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'status' => $data['status'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        \Log::write("INQUIRY RESULT: " . json_encode($logData), 'flip', 'inquiry');
        
        // Status-specific logging
        $status = $data['status'] ?? 'UNKNOWN';
        switch ($status) {
            case 'SUCCESS':
                \Log::write("SUCCESS: Account verified - " . ($data['account_holder'] ?? 'N/A'), 'flip', 'inquiry');
                break;
            case 'INVALID_ACCOUNT_NUMBER':
                \Log::write("INVALID: Account number not found", 'flip', 'inquiry');
                break;
            case 'SUSPECTED_ACCOUNT':
                \Log::write("WARNING: Suspected account detected", 'flip', 'inquiry');
                break;
            case 'BLACK_LISTED':
                \Log::write("BLOCKED: Account is blacklisted", 'flip', 'inquiry');
                break;
        }
    }

    /**
     * Handle disbursement callback
     * @param array $data Callback data
     */
    private function handleDisbursementCallback($data)
    {
        $logData = [
            'type' => 'DISBURSEMENT',
            'id' => $data['id'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'beneficiary_name' => $data['beneficiary_name'] ?? null,
            'amount' => $data['amount'] ?? null,
            'status' => $data['status'] ?? null,
            'receipt' => $data['receipt'] ?? null,
            'time_served' => $data['time_served'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        \Log::write("DISBURSEMENT UPDATE: " . json_encode($logData), 'flip', 'disbursement');
        
        // Status-specific logging
        $status = $data['status'] ?? 'UNKNOWN';
        $amount = number_format($data['amount'] ?? 0, 0, ',', '.');
        $beneficiary = $data['beneficiary_name'] ?? 'N/A';
        
        switch ($status) {
            case 'DONE':
                \Log::write("SUCCESS: Transfer Rp{$amount} to {$beneficiary} completed", 'flip', 'disbursement');
                break;
            case 'CANCELLED':
                \Log::write("CANCELLED: Transfer Rp{$amount} to {$beneficiary} was cancelled", 'flip', 'disbursement');
                break;
            case 'PENDING':
                \Log::write("PENDING: Transfer Rp{$amount} to {$beneficiary} is processing", 'flip', 'disbursement');
                break;
        }
    }

    /**
     * Get human-readable message for inquiry status
     * @param string $status
     * @return string
     */
    private function getInquiryMessage($status)
    {
        $messages = [
            'SUCCESS' => 'Account inquiry successful',
            'PENDING' => 'Account inquiry is being processed',
            'INVALID_ACCOUNT_NUMBER' => 'Invalid account number',
            'SUSPECTED_ACCOUNT' => 'Account is suspected/flagged',
            'BLACK_LISTED' => 'Account is blacklisted'
        ];
        
        return $messages[$status] ?? 'Inquiry completed';
    }
}

