<?php

namespace App\Controllers\Payment;

use App\Core\Controller;
use App\Helpers\Payment\BcaMutasiUnbind;

/**
 * Admin BCA mutasi bind — dipanggil dari laundry NonTunaiAdmin via api.nalju.com.
 *
 * POST /Payment/BcaMutasiAdmin/unbind   { link_id, reason?, blocked_by? }
 * POST /Payment/BcaMutasiAdmin/payers   { invoice_refs?: string[], salon_refs?: string[] }
 *
 * Auth: ?secret= atau header X-Cron-Secret (Env::CRON_SECRET).
 */
class BcaMutasiAdmin extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * POST — unbind link, blokir entity, revert status pembayaran.
     */
    public function unbind()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->verifyCronSecret()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $body = $this->getBody();
        if (!is_array($body) || $body === []) {
            $body = $_POST;
        }

        $linkId = (int) ($body['link_id'] ?? 0);
        $reason = trim((string) ($body['reason'] ?? ''));
        $blockedBy = trim((string) ($body['blocked_by'] ?? ''));

        if ($linkId < 1) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'link_id tidak valid'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $result = BcaMutasiUnbind::execute(
                $this->db(0),
                $this->db(1),
                $this->db(6),
                $this->db(4),
                $linkId,
                $reason !== '' ? $reason : 'Unbind admin BCA Mutasi',
                $blockedBy !== '' ? $blockedBy : 'admin'
            );

            if (empty($result['ok'])) {
                http_response_code(400);
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[BcaMutasiAdmin::unbind] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Unbind gagal: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST — resolve payer invoice / salon untuk tampilan admin laundry.
     */
    public function payers()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->verifyCronSecret()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $body = $this->getBody();
        if (!is_array($body)) {
            $body = [];
        }

        $invoiceRefs = $body['invoice_refs'] ?? [];
        $salonRefs = $body['salon_refs'] ?? [];

        if (!is_array($invoiceRefs)) {
            $invoiceRefs = [];
        }
        if (!is_array($salonRefs)) {
            $salonRefs = [];
        }

        $payers = [];

        try {
            $invoiceDb = $this->db(6);
            foreach ($invoiceRefs as $ref) {
                $ref = trim((string) $ref);
                if ($ref === '') {
                    continue;
                }

                $payment = $invoiceDb->query(
                    'SELECT p.payment_ref, i.invoice_number, i.public_token
                     FROM invoice_payments p
                     INNER JOIN invoices i ON i.id = p.invoice_id
                     WHERE p.payment_ref = ?
                     LIMIT 1',
                    [$ref]
                )->row_array();

                if (!is_array($payment) || empty($payment['payment_ref'])) {
                    $payers[$ref] = [
                        'name' => $ref,
                        'url' => null,
                        'badge' => 'Invoice',
                        'jenis_transaksi' => 0,
                    ];
                    continue;
                }

                $number = trim((string) ($payment['invoice_number'] ?? ''));
                $token = trim((string) ($payment['public_token'] ?? ''));
                $name = $number !== '' ? $number : $ref;

                $payers[$ref] = [
                    'name' => $name,
                    'url' => $token !== '' ? ('https://ml.nalju.com/invoice/' . rawurlencode($token)) : null,
                    'badge' => 'Invoice',
                    'jenis_transaksi' => 0,
                ];
            }

            $salonDb = $this->db(4);
            foreach ($salonRefs as $ref) {
                $ref = trim((string) $ref);
                if ($ref === '') {
                    continue;
                }

                $payment = $salonDb->query(
                    'SELECT p.payment_ref, p.salon_id, s.salon_name
                     FROM subscription_payments p
                     LEFT JOIN salon s ON s.salon_id = p.salon_id
                     WHERE p.payment_ref = ?
                     LIMIT 1',
                    [$ref]
                )->row_array();

                $salonName = trim((string) ($payment['salon_name'] ?? ''));
                if ($salonName === '') {
                    $salonName = 'Salon #' . (int) ($payment['salon_id'] ?? 0);
                }

                $payers[$ref] = [
                    'name' => $salonName,
                    'url' => null,
                    'badge' => 'Salon',
                    'jenis_transaksi' => 0,
                ];
            }

            echo json_encode(['ok' => true, 'payers' => $payers], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[BcaMutasiAdmin::payers] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function verifyCronSecret(): bool
    {
        $expected = $this->expectedCronSecret();
        if ($expected === '') {
            return true;
        }

        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }

        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function expectedCronSecret(): string
    {
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            return trim((string) \Env::CRON_SECRET);
        }

        return trim((string) (getenv('CRON_SECRET') ?: ''));
    }
}
