<?php
namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\Payment\QrisWebhookHandler;

class Tokopay extends Controller
{
    use QrisWebhookHandler;

    protected $webhookLogChannel = 'Tokopay';

    public function index()
    {
        $merchant_id = 'M240926BMTGB612';
        $secret = '4aea0ede516df65d88ccb773a443c61b3b3702fe1b9647deb9293cac07fd72bf';

        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data) {
            echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
            return;
        }

        $reff_id = isset($data['reff_id']) ? $data['reff_id'] : '';
        $signature_provided = isset($data['signature']) ? $data['signature'] : '';

        if (empty($reff_id) || empty($signature_provided)) {
            echo json_encode(['status' => false, 'message' => 'Missing parameter']);
            $this->logWebhook('Err: Param');
            return;
        }

        $signature_generated = md5($merchant_id . ':' . $secret . ':' . $reff_id);

        if ($signature_provided !== $signature_generated) {
            echo json_encode(['status' => false, 'message' => 'Invalid Signature']);
            $this->logWebhook('Err: Sign');
            return;
        }

        $status = isset($data['status']) ? $data['status'] : '';

        $parts = explode('_', $reff_id);
        if ($parts[0] === 'TEST') {
            $raw_json = file_get_contents('php://input');
            $log_data = [
                'raw_json' => $raw_json,
                'decoded_data' => $data,
                'reff_id' => $reff_id,
                'status' => $status,
                'signature_provided' => $signature_provided,
                'signature_generated' => md5($merchant_id . ':' . $secret . ':' . $reff_id),
                'signature_valid' => ($signature_provided === md5($merchant_id . ':' . $secret . ':' . $reff_id)),
                'timestamp' => date('Y-m-d H:i:s'),
                'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ];
            $this->logWebhook('TEST: Webhook received - ' . json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo json_encode(['status' => true, 'message' => 'TEST webhook logged', 'logged' => true]);
            return;
        }
        if ($parts[0] === 'SALONSUB') {
            $this->handleSalonSubscription($reff_id, $status);
            echo json_encode(['status' => true, 'message' => 'Processed SALONSUB']);
            return;
        }
        if ($parts[0] === 'MDLINV') {
            $this->handleInvoicePayment($reff_id, $status);
            echo json_encode(['status' => true, 'message' => 'Processed MDLINV']);
            return;
        }
        if ($parts[0] === 'RESTOKAS') {
            $this->handleRestoKas($reff_id, $status);
            echo json_encode(['status' => true, 'message' => 'Processed RESTOKAS']);
            return;
        }

        $this->handleKasLaundry($reff_id, $status);
    }
}
