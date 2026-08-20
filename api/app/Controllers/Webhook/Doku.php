<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\Payment\DokuSignature;
use App\Helpers\Payment\QrisWebhookHandler;

/**
 * Webhook DOKU — HTTP Notification untuk pembayaran QRIS.
 * URL: /Webhook/Doku
 *
 * Daftarkan URL ini di DOKU Back Office sebagai Notification URL.
 * Path harus sama dengan Env::DOKU_WEBHOOK_PATH (default: /Webhook/Doku).
 */
class Doku extends Controller
{
    use QrisWebhookHandler;

    protected $webhookLogChannel = 'Doku';

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

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            $this->logWebhook('Incoming: (empty body) method=' . $method);
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Empty body']);
            return;
        }

        $this->logWebhook('Incoming: ' . $rawBody);

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            $this->logWebhook('Err: Invalid JSON');
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
                $this->logWebhook('Err: Sign — ' . $verify['message']);
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
            $this->logWebhook('Err: Param — missing invoice_number');
            return;
        }

        if ($txStatus === '') {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Missing transaction.status']);
            $this->logWebhook('Err: Param — missing transaction.status ref=' . $invoiceNumber);
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
            $this->logWebhook('TEST: Webhook received — ' . json_encode($logData, JSON_UNESCAPED_SLASHES));
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'TEST webhook logged', 'logged' => true]);
            return;
        }

        $this->logWebhook("Incoming QRIS ref=$invoiceNumber tx=$txStatus mapped=$status");

        if ($parts[0] === 'SALONSUB') {
            $this->handleSalonSubscription($invoiceNumber, $status);
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'Processed SALONSUB']);
            return;
        }
        if ($parts[0] === 'MDLINV') {
            $this->handleInvoicePayment($invoiceNumber, $status);
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'Processed MDLINV']);
            return;
        }
        if ($parts[0] === 'RESTOKAS') {
            $this->handleRestoKas($invoiceNumber, $status);
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => 'Processed RESTOKAS']);
            return;
        }

        $this->handleKasLaundry($invoiceNumber, $status);
    }

    /**
     * Map DOKU transaction.status ke status internal (selaras dengan Tokopay webhook).
     */
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

    /**
     * Coba path alternatif jika nginx menambah/menghilangkan prefix /api.
     */
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
