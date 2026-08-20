<?php

namespace App\Helpers\Payment;

use App\Models\Tokopay;

/**
 * Pintu tunggal generate & cek status QRIS (tahap 1: Tokopay).
 * Gagal generate = timeout/koneksi error ATAU tidak ada qr_string.
 */
class QrisService
{
    public const GATEWAY_TOKOPAY = 'tokopay';

    /** @var Tokopay|null */
    private $tokopay;

    public function __construct(?Tokopay $tokopay = null)
    {
        $this->tokopay = $tokopay ?? new Tokopay();
    }

    /**
     * Generate QRIS.
     *
     * @param int    $nominal
     * @param string $ref_id        Referensi bisnis (ref_finance, MDLINV_*, SALONSUB_*, dll.)
     * @param bool   $unique_trx    true = append _timestamp ke order id (flow laundry)
     * @return array{status: bool, failed: bool, message: string, gateway: string, trx_id: string, ref_id: string, qr_string: string, raw: array|null}
     */
    public function generate(int $nominal, string $ref_id, bool $unique_trx = false): array
    {
        $ref_id = trim($ref_id);
        $trx_id = $unique_trx ? $this->buildUniqueTrxId($ref_id) : $ref_id;

        $response = $this->tokopay->createOrder($nominal, $trx_id, 'QRIS');
        $data = is_string($response) ? json_decode($response, true) : null;

        if (!is_array($data)) {
            return $this->failGenerate($ref_id, $trx_id, 'Respon tidak valid dari payment gateway', null);
        }

        if ($this->isConnectionError($data)) {
            $msg = (string) ($data['message'] ?? 'Koneksi ke payment gateway gagal');
            return $this->failGenerate($ref_id, $trx_id, $msg, $data);
        }

        $qr_string = $this->extractQrString($data);
        if ($qr_string === '') {
            $msg = (string) ($data['message'] ?? 'QR String tidak ditemukan dari payment gateway');
            return $this->failGenerate($ref_id, $trx_id, $msg, $data);
        }

        if (!$this->isApiSuccess($data)) {
            $msg = (string) ($data['message'] ?? $data['error_msg'] ?? 'Gagal generate QRIS');
            return $this->failGenerate($ref_id, $trx_id, $msg, $data);
        }

        return [
            'status' => true,
            'failed' => false,
            'message' => 'OK',
            'gateway' => self::GATEWAY_TOKOPAY,
            'trx_id' => $trx_id,
            'ref_id' => $ref_id,
            'qr_string' => $qr_string,
            'raw' => $data,
        ];
    }

    /**
     * Cek status pembayaran QRIS.
     *
     * @return array{
     *   ok: bool,
     *   connection_error: bool,
     *   message: string,
     *   gateway: string,
     *   trx_id: string,
     *   payment_status: string,
     *   trx_status: string,
     *   raw: array|null
     * }
     */
    public function checkStatus(string $trx_id, int $nominal): array
    {
        $trx_id = trim($trx_id);
        $base = [
            'ok' => false,
            'connection_error' => false,
            'message' => '',
            'gateway' => self::GATEWAY_TOKOPAY,
            'trx_id' => $trx_id,
            'payment_status' => 'pending',
            'trx_status' => 'pending',
            'raw' => null,
        ];

        $response = $this->tokopay->checkStatus($trx_id, $nominal, 'QRIS');
        $data = is_string($response) ? json_decode($response, true) : null;

        if (!is_array($data)) {
            return array_merge($base, [
                'connection_error' => true,
                'message' => 'Respon tidak valid dari payment gateway',
            ]);
        }

        if ($this->isConnectionError($data)) {
            return array_merge($base, [
                'connection_error' => true,
                'message' => (string) ($data['message'] ?? 'Koneksi ke payment gateway gagal'),
                'raw' => $data,
            ]);
        }

        if (empty($data) || (isset($data['data']) && !is_array($data['data']))) {
            return array_merge($base, [
                'ok' => true,
                'message' => 'Order tidak ditemukan',
                'payment_status' => 'pending',
                'trx_status' => 'not_found',
                'raw' => $data,
            ]);
        }

        $trx_status = $this->parseTrxStatus($data);
        $payment_status = $this->normalizePaymentStatus($trx_status);

        return array_merge($base, [
            'ok' => true,
            'message' => 'OK',
            'payment_status' => $payment_status,
            'trx_status' => $trx_status,
            'raw' => $data,
        ]);
    }

    /**
     * Parser status mentah (untuk cron / legacy).
     */
    public function parseTrxStatus(array $data): string
    {
        $statusTrx = '';

        if (isset($data['data']) && is_array($data['data'])) {
            if (!empty($data['data']['status']) && is_string($data['data']['status'])) {
                $statusTrx = strtolower(trim($data['data']['status']));
            } elseif (!empty($data['data']['status_pembayaran'])) {
                $statusTrx = strtolower(trim($data['data']['status_pembayaran']));
            } elseif (!empty($data['data']['status_detail'])) {
                $statusTrx = strtolower(trim($data['data']['status_detail']));
            }
        }

        if ($statusTrx === '') {
            if (!empty($data['trx_status'])) {
                $statusTrx = strtolower(trim((string) $data['trx_status']));
            } elseif (!empty($data['status_pembayaran'])) {
                $statusTrx = strtolower(trim($data['status_pembayaran']));
            } elseif (!empty($data['status_detail'])) {
                $statusTrx = strtolower(trim($data['status_detail']));
            } elseif (!empty($data['payment_status'])) {
                $statusTrx = strtolower(trim($data['payment_status']));
            }
        }

        $trxStatus = isset($data['trx_status']) ? strtolower(trim((string) $data['trx_status'])) : '';
        if ($trxStatus === 'unpaid' && ($statusTrx === '' || $statusTrx === 'pending')) {
            $statusTrx = 'unpaid';
        }

        return $statusTrx !== '' ? $statusTrx : 'pending';
    }

    public function extractQrString(?array $data): string
    {
        if (!is_array($data)) {
            return '';
        }
        if (!empty($data['data']['qr_string'])) {
            return trim((string) $data['data']['qr_string']);
        }
        if (!empty($data['qr_string'])) {
            return trim((string) $data['qr_string']);
        }

        return '';
    }

    public function buildUniqueTrxId(string $ref_id): string
    {
        $clean = $this->cleanRefId($ref_id);

        return $clean . '_' . time();
    }

    public function cleanRefId(string $ref_id): string
    {
        $ref_id = trim($ref_id);
        if (strpos($ref_id, '_') === false) {
            return $ref_id;
        }

        $parts = explode('_', $ref_id);
        $last = end($parts);
        if (is_numeric($last) && strlen((string) $last) === 10) {
            array_pop($parts);

            return implode('_', $parts);
        }

        return $ref_id;
    }

    private function failGenerate(string $ref_id, string $trx_id, string $message, ?array $raw): array
    {
        return [
            'status' => false,
            'failed' => true,
            'message' => $message,
            'gateway' => self::GATEWAY_TOKOPAY,
            'trx_id' => $trx_id,
            'ref_id' => $ref_id,
            'qr_string' => '',
            'raw' => $raw,
        ];
    }

    private function isConnectionError(array $data): bool
    {
        if (($data['status'] ?? null) === false && !empty($data['message'])) {
            $msg = strtolower((string) $data['message']);
            if (strpos($msg, 'connection error') !== false || strpos($msg, 'timeout') !== false) {
                return true;
            }
        }

        return !empty($data['error_msg']);
    }

    /**
     * Root status=Success dari Tokopay = API OK, bukan pembayaran lunas.
     */
    private function isApiSuccess(array $data): bool
    {
        if (!isset($data['status'])) {
            return false;
        }

        $status = $data['status'];
        if (is_string($status)) {
            $status = strtolower(trim($status));

            return in_array($status, ['success', 'true', '1'], true);
        }

        return $status === true || $status === 1;
    }

    private function normalizePaymentStatus(string $trx_status): string
    {
        if (in_array($trx_status, \Env::QRIS_STATUS_SUCCESS, true)) {
            return 'paid';
        }
        if (in_array($trx_status, \Env::QRIS_STATUS_EXPIRED, true)) {
            return 'expired';
        }

        return 'pending';
    }
}
