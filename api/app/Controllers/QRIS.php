<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SuperFlash as SuperFlashModel;

/**
 * QRIS Controller
 * Handles QRIS payment operations via SuperFlash payment gateway
 */
class QRIS extends Controller
{
    /**
     * Generate QRIS Code
     * Endpoint: /QRIS/generate
     * Method: POST
     * 
     * Request Body:
     * {
     *   "amount": 100000,
     *   "order_id": "ORD-123456",          // maps to external_id
     *   "terminal_id": "TERMINAL-01",      // optional, defaults to order_id/external_id
     *   "customer_name": "John Doe",
     *   "customer_email": "john@example.com",
     *   "customer_phone": "081234567890",
     *   "description": "Payment for Order #123456",
     *   "session_time": 10,                // minutes (preferred)
     *   "expired_at": "2026-01-26 12:00:00"// optional fallback to compute session_time
     * }
     */
    public function generate()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            // Get request body
            $input = $this->getBody();

            // Validate required fields
            if (empty($input['amount'])) {
                $this->error('Amount is required', 400);
            }

            if (empty($input['order_id'])) {
                $this->error('order_id wajib diisi', 400);
            }

            // Initialize SuperFlash model
            $superflash = new SuperFlashModel();
            
            // Generate QRIS
            $result = $superflash->generateQRIS([
                'amount' => $input['amount'],
                'external_id' => $input['order_id'],
                'terminal_id' => $input['terminal_id'] ?? null,
                'customer_name' => $input['customer_name'] ?? null,
                'customer_email' => $input['customer_email'] ?? null,
                'customer_phone' => $input['customer_phone'] ?? null,
                'description' => $input['description'] ?? null,
                'expired_at' => $input['expired_at'] ?? null,
                'session_time' => $input['session_time'] ?? null,
                // Also allow spec field names directly if caller wants
                'fullname' => $input['fullname'] ?? null,
                'email' => $input['email'] ?? null,
                'phone_number' => $input['phone_number'] ?? null,
            ]);

            if ($result['status']) {
                $this->success($result['data'], 'QRIS generated successfully');
            } else {
                $this->error($result['message'], $result['http_code'] ?? 500, $result['data']);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check QRIS Payment Status
     * Endpoint: /QRIS/status/{transaction_id}
     * Method: GET
     * 
     * URL Parameters:
     * - transaction_id: transaction_id dari response SuperFlash (format biasanya: FM-xxxx)
     */
    public function status($transactionId = null)
    {
        $this->handleCors();

        if (!$this->isGet()) {
            $this->error('Method not allowed', 405);
        }

        try {
            // Validate transaction_id
            if (empty($transactionId)) {
                $this->error('Transaction ID is required', 400);
            }

            // Initialize SuperFlash model
            $superflash = new SuperFlashModel();
            
            // Check payment status
            $result = $superflash->checkPaymentStatus($transactionId);

            if ($result['status']) {
                $this->success($result['data'], 'Payment status retrieved successfully');
            } else {
                $this->error($result['message'], $result['http_code'] ?? 500, $result['data']);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Webhook handler for payment notifications
     * Endpoint: /QRIS/webhook
     * Method: POST
     * 
     * This endpoint will be called by SuperFlash when payment status changes
     */
    public function webhook()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            // Get webhook payload
            $payload = $this->getBody();
            
            // TODO: Verify webhook signature/authentication based on SuperFlash docs
            
            // Log webhook for debugging
            error_log('SuperFlash Webhook: ' . json_encode($payload));

            // TODO: Process webhook based on payment status
            // Example: Update order status in database
            
            // Return success response
            $this->success(['received' => true], 'Webhook received');
            
        } catch (\Exception $e) {
            error_log('SuperFlash Webhook Error: ' . $e->getMessage());
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}
