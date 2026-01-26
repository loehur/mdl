<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SuperFlash_Transfer as SuperFlashTransferModel;

/**
 * Transfer Controller
 * Handles bank transfer operations via SuperFlash Transfer Service
 */
class Transfer extends Controller
{
    /**
     * Account Inquiry - Validate beneficiary account name
     * Endpoint: /Transfer/inquiry
     * Method: POST
     * 
     * Request Body:
     * {
     *   "bank_code": "014",
     *   "bank_account": "1234567890",
     *   "external_id": "INQ-123456"
     * }
     */
    public function inquiry()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            // Get request body
            $input = $this->getBody();

            // Validate required fields
            if (empty($input['bank_code'])) {
                $this->error('bank_code is required', 400);
            }

            if (empty($input['bank_account'])) {
                $this->error('bank_account is required', 400);
            }

            if (empty($input['external_id'])) {
                $this->error('external_id is required', 400);
            }

            // Initialize SuperFlash Transfer model
            $superflash = new SuperFlashTransferModel();
            
            // Account inquiry
            $result = $superflash->accountInquiry([
                'bank_code' => $input['bank_code'],
                'bank_account' => $input['bank_account'],
                'external_id' => $input['external_id'],
            ]);

            if ($result['status']) {
                $this->success($result['data'], 'Account inquiry successful');
            } else {
                $this->error($result['message'], $result['http_code'] ?? 500, $result['data']);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fund Transfer - Send money to bank account
     * Endpoint: /Transfer/payment
     * Method: POST
     * 
     * Request Body:
     * {
     *   "recipient_bank": "014",
     *   "recipient_account": "1234567890",
     *   "recipient_name": "John Doe",
     *   "amount": 100000,
     *   "note": "Transfer payment",
     *   "external_id": "TRF-123456"
     * }
     */
    public function payment()
    {
        $this->handleCors();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            // Get request body
            $input = $this->getBody();

            // Validate required fields
            if (empty($input['recipient_bank'])) {
                $this->error('recipient_bank is required', 400);
            }

            if (empty($input['recipient_account'])) {
                $this->error('recipient_account is required', 400);
            }

            if (empty($input['amount'])) {
                $this->error('amount is required', 400);
            }

            if (empty($input['note'])) {
                $this->error('note is required', 400);
            }

            if (empty($input['external_id'])) {
                $this->error('external_id is required', 400);
            }

            // Initialize SuperFlash Transfer model
            $superflash = new SuperFlashTransferModel();
            
            // Fund transfer
            $result = $superflash->fundTransfer([
                'recipient_bank' => $input['recipient_bank'],
                'recipient_account' => $input['recipient_account'],
                'recipient_name' => $input['recipient_name'] ?? null,
                'amount' => $input['amount'],
                'note' => $input['note'],
                'external_id' => $input['external_id'],
            ]);

            if ($result['status']) {
                $this->success($result['data'], 'Transfer initiated successfully');
            } else {
                $this->error($result['message'], $result['http_code'] ?? 500, $result['data']);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check Transfer Status
     * Endpoint: /Transfer/status/{external_id}
     * Method: GET
     * 
     * URL Parameters:
     * - external_id: Merchant transaction ID (external_id)
     */
    public function status($externalId = null)
    {
        $this->handleCors();

        if (!$this->isGet()) {
            $this->error('Method not allowed', 405);
        }

        try {
            // Validate external_id
            if (empty($externalId)) {
                $this->error('external_id is required', 400);
            }

            // Initialize SuperFlash Transfer model
            $superflash = new SuperFlashTransferModel();
            
            // Check transfer status
            $result = $superflash->checkTransferStatus($externalId);

            if ($result['status']) {
                $this->success($result['data'], 'Transfer status retrieved successfully');
            } else {
                $this->error($result['message'], $result['http_code'] ?? 500, $result['data']);
            }
        } catch (\Exception $e) {
            $this->error('Internal Server Error: ' . $e->getMessage(), 500);
        }
    }
}
