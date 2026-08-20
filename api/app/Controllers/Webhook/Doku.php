<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\Payment\DokuSignature;
use App\Helpers\Payment\QrisWebhookProcessor;

/**
 * Webhook DOKU — HTTP Notification untuk pembayaran QRIS.
 * URL: /Webhook/Doku
 */
class Doku extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'OPTIONS') {
            http_response_code(200);
            echo json_encode(['status' => true]);
            return;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => false, 'message' => 'Method not allowed']);
            return;
        }

        $processor = new QrisWebhookProcessor($this, 'Doku');

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            \Log::write('Incoming: (empty body) method=' . $method, 'webhook', 'Doku');
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Empty body']);
            return;
        }

        \Log::write('Incoming: ' . $rawBody, 'webhook', 'Doku');

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            \Log::write('Err: Invalid JSON', 'webhook', 'Doku');
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
            return;
        }

        $skipVerify = class_exists('Env')
            && defined('Env::DOKU_SKIP_SIGNATURE_VERIFY')
            && \Env::DOKU_SKIP_SIGNATURE_VERIFY === true;

        if (!$skipVerify) {
            $requestTarget = DokuSignature::resolveRequestTarget();
            $verify = DokuSignature::verify($rawBody, $requestTarget);

            if (!$verify['valid']) {
                $altTarget = $this->alternateRequestTarget($requestTarget);
                if ($altTarget !== null) {
                    $verify = DokuSignature::verify($rawBody, $altTarget);
                }
            }

            if (!$verify['valid']) {
                http_response_code(401);
                echo json_encode(['status' => false, 'message' => $verify['message']]);
                \Log::write('Err: Sign — ' . $verify['message'], 'webhook', 'Doku');
                return;
            }
        }

        $serviceId = strtoupper((string) ($data['service']['id'] ?? ''));
        if ($serviceId !== '' && $serviceId !== 'QRIS') {
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'Ignored non-QRIS service']);
            return;
        }

        $invoiceNumber = trim((string) ($data['order']['invoice_number'] ?? ''));
        $txStatus = strtoupper(trim((string) ($data['transaction']['status'] ?? '')));

        if ($invoiceNumber === '') {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Missing order.invoice_number']);
            \Log::write('Err: Param — missing invoice_number', 'webhook', 'Doku');
            return;
        }

        if ($txStatus === '') {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Missing transaction.status']);
            \Log::write('Err: Param — missing transaction.status ref=' . $invoiceNumber, 'webhook', 'Doku');
            return;
        }

        $status = $this->mapDokuStatus($txStatus);

        $parts = explode('_', $invoiceNumber);
        if ($parts[0] === 'TEST') {
            $logData = [
                'invoice_number' => $invoiceNumber,
                'transaction_status' => $txStatus,
                'mapped_status' => $status,
                'amount' => $data['order']['amount'] ?? null,
                'channel' => $data['channel']['id'] ?? null,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            \Log::write('TEST: Webhook received — ' . json_encode($logData, JSON_UNESCAPED_SLASHES), 'webhook', 'Doku');
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'TEST webhook logged', 'logged' => true]);
            return;
        }

        \Log::write("Incoming QRIS ref=$invoiceNumber tx=$txStatus mapped=$status", 'webhook', 'Doku');

        if ($parts[0] === 'SALONSUB') {
            $processor->handleSalonSubscription($invoiceNumber, $status);
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'Processed SALONSUB']);
            return;
        }
        if ($parts[0] === 'MDLINV') {
            $processor->handleInvoicePayment($invoiceNumber, $status);
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'Processed MDLINV']);
            return;
        }
        if ($parts[0] === 'RESTOKAS') {
            $processor->handleRestoKas($invoiceNumber, $status);
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'Processed RESTOKAS']);
            return;
        }

        $processor->handleKasLaundry($invoiceNumber, $status);
    }

    private function mapDokuStatus(string $dokuStatus): string
    {
        $s = strtoupper(trim($dokuStatus));
        if ($s === 'SUCCESS') {
            return 'paid';
        }
        if ($s === 'FAILED') {
            return 'failed';
        }

        return strtolower($dokuStatus);
    }

    private function alternateRequestTarget(string $current): ?string
    {
        if (strpos($current, '/api/') === 0) {
            return substr($current, 4) ?: null;
        }
        if ($current !== '' && $current[0] === '/') {
            return '/api' . $current;
        }

        return null;
    }
}
