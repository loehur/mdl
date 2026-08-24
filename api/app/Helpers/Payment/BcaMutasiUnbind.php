<?php

namespace App\Helpers\Payment;

use App\Helpers\BcaScrapper;
use App\Helpers\Laundry\KasNonTunaiConfirm;

/**
 * Unbind mutasi BCA + blokir entity + kembalikan status pembayaran.
 */
class BcaMutasiUnbind
{
    /**
     * @param object $mainDb api db(0) mdl_main
     * @param object $laundryDb api db(1) mdl_laundry
     * @param object $invoiceDb api db(6) mdl_invoice
     * @param object $salonDb api db(4) mdl_salon
     * @return array{ok:bool,message?:string,entity_type?:string,entity_ref?:string}
     */
    public static function execute(
        $mainDb,
        $laundryDb,
        $invoiceDb,
        $salonDb,
        int $linkId,
        string $reason = '',
        string $blockedBy = ''
    ): array {
        $linkId = (int) $linkId;
        if ($linkId < 1) {
            return ['ok' => false, 'message' => 'link_id tidak valid'];
        }

        $link = $mainDb->query(
            'SELECT * FROM bca_mutasi_link WHERE id = ? LIMIT 1',
            [$linkId]
        )->row_array();

        if (!is_array($link) || empty($link['id'])) {
            return ['ok' => false, 'message' => 'Binding tidak ditemukan'];
        }

        $entityType = trim((string) ($link['entity_type'] ?? ''));
        $entityRef = trim((string) ($link['entity_ref'] ?? ''));
        $mutasiId = (int) ($link['bca_mutasi_id'] ?? 0);

        if ($entityType === '' || $entityRef === '') {
            return ['ok' => false, 'message' => 'Data entity bind tidak valid'];
        }

        if (self::isBlocked($mainDb, $entityType, $entityRef)) {
            return ['ok' => false, 'message' => 'Entity sudah diblokir sebelumnya'];
        }

        $revert = self::revertEntityState($laundryDb, $invoiceDb, $salonDb, $entityType, $entityRef);
        if (empty($revert['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($revert['message'] ?? 'Gagal mengembalikan status entity'),
            ];
        }

        if (!$mainDb->beginTransaction()) {
            return ['ok' => false, 'message' => 'Gagal memulai transaksi'];
        }

        try {
            $deleted = $mainDb->delete('bca_mutasi_link', ['id' => $linkId]);
            if (!$deleted) {
                $mainDb->rollback();
                return ['ok' => false, 'message' => 'Gagal menghapus binding'];
            }

            $insertId = $mainDb->insert('bca_mutasi_link_block', [
                'entity_type' => $entityType,
                'entity_ref' => $entityRef,
                'bca_mutasi_id' => $mutasiId > 0 ? $mutasiId : null,
                'link_id' => $linkId,
                'reason' => $reason !== '' ? $reason : null,
                'blocked_by' => $blockedBy !== '' ? $blockedBy : null,
            ]);

            if (!$insertId) {
                $mainDb->rollback();
                return ['ok' => false, 'message' => 'Gagal menyimpan blokir entity'];
            }

            $mainDb->commit();
        } catch (\Throwable $e) {
            $mainDb->rollback();
            error_log('[BcaMutasiUnbind] ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Unbind gagal: ' . $e->getMessage()];
        }

        return [
            'ok' => true,
            'entity_type' => $entityType,
            'entity_ref' => $entityRef,
            'revert' => $revert,
        ];
    }

    /**
     * @param object $mainDb
     */
    public static function isBlocked($mainDb, string $entityType, string $entityRef): bool
    {
        $entityType = trim($entityType);
        $entityRef = trim($entityRef);
        if ($entityType === '' || $entityRef === '') {
            return false;
        }

        try {
            $row = $mainDb->query(
                'SELECT id FROM bca_mutasi_link_block WHERE entity_type = ? AND entity_ref = ? LIMIT 1',
                [$entityType, $entityRef]
            )->row_array();
        } catch (\Throwable $e) {
            return false;
        }

        return is_array($row) && !empty($row['id']);
    }

    /**
     * @param object $laundryDb
     * @param object $invoiceDb
     * @param object $salonDb
     * @return array{ok:bool,message?:string,detail?:array<string,mixed>}
     */
    public static function revertEntityState(
        $laundryDb,
        $invoiceDb,
        $salonDb,
        string $entityType,
        string $entityRef
    ): array {
        switch ($entityType) {
            case BcaScrapper::ENTITY_KAS_LAUNDRY:
                return self::revertKasLaundry($laundryDb, $entityRef);
            case BcaScrapper::ENTITY_INVOICE:
                return self::revertInvoice($invoiceDb, $entityRef);
            case BcaScrapper::ENTITY_SALON_SUBSCRIPTION:
                return self::revertSalon($salonDb, $entityRef);
            default:
                return ['ok' => false, 'message' => 'entity_type tidak didukung: ' . $entityType];
        }
    }

    /**
     * Kembalikan kas laundry ke tidak tuntas (status_mutasi = 2).
     *
     * @param object $laundryDb
     * @return array{ok:bool,message?:string,detail?:array<string,mixed>}
     */
    public static function revertKasLaundry($laundryDb, string $refFinance): array
    {
        $refFinance = trim($refFinance);
        if ($refFinance === '') {
            return ['ok' => false, 'message' => 'ref_finance kosong'];
        }

        $rows = $laundryDb->query(
            'SELECT * FROM kas
             WHERE ref_finance = ?
               AND metode_mutasi = 2
               AND UPPER(IFNULL(note, \'\')) = ?
             LIMIT 20',
            [$refFinance, 'BCA']
        )->result_array();

        if (!is_array($rows) || $rows === []) {
            return ['ok' => false, 'message' => 'Kas BCA tidak ditemukan'];
        }

        $updated = 0;
        $seenSalesRef = [];

        foreach ($rows as $kasRow) {
            $status = (int) ($kasRow['status_mutasi'] ?? 0);
            if ($status === 3) {
                $laundryDb->update('kas', ['status_mutasi' => 2], [
                    'id_kas' => (int) ($kasRow['id_kas'] ?? 0),
                    'status_mutasi' => 3,
                ]);
                if ($laundryDb->affected_rows() > 0) {
                    $updated++;
                }
            }

            $jt = (int) ($kasRow['jenis_transaksi'] ?? 0);
            $refTransaksi = trim((string) ($kasRow['ref_transaksi'] ?? ''));
            if ($jt === 7 && $refTransaksi !== '' && !isset($seenSalesRef[$refTransaksi])) {
                $seenSalesRef[$refTransaksi] = true;
                self::revertSalesState($laundryDb, $refTransaksi);
            }
        }

        return [
            'ok' => true,
            'detail' => [
                'ref_finance' => $refFinance,
                'kas_reverted' => $updated,
                'sales_refs' => array_keys($seenSalesRef),
            ],
        ];
    }

    /**
     * @param object $laundryDb
     */
    private static function revertSalesState($laundryDb, string $refTransaksi): void
    {
        $refTransaksi = trim($refTransaksi);
        if ($refTransaksi === '') {
            return;
        }

        $payments = $laundryDb->query(
            'SELECT status_mutasi FROM kas WHERE ref_transaksi = ? AND jenis_transaksi = 7',
            [$refTransaksi]
        )->result_array();

        if (!is_array($payments) || $payments === []) {
            return;
        }

        foreach ($payments as $payment) {
            if ((int) ($payment['status_mutasi'] ?? 0) === 3) {
                KasNonTunaiConfirm::updateSalesState($laundryDb, $refTransaksi);
                return;
            }
        }

        $laundryDb->update('barang_mutasi', ['state' => 0], ['ref' => $refTransaksi]);
    }

    /**
     * Gagal bayar + invoice belum bayar.
     *
     * @param object $invoiceDb
     * @return array{ok:bool,message?:string,detail?:array<string,mixed>}
     */
    public static function revertInvoice($invoiceDb, string $paymentRef): array
    {
        $paymentRef = trim($paymentRef);
        if ($paymentRef === '') {
            return ['ok' => false, 'message' => 'payment_ref kosong'];
        }

        $payment = $invoiceDb->query(
            "SELECT * FROM invoice_payments
             WHERE payment_ref = ?
               AND payment_method = 'bca'
             LIMIT 1",
            [$paymentRef]
        )->row_array();

        if (!is_array($payment) || empty($payment['id'])) {
            return ['ok' => false, 'message' => 'Pembayaran invoice BCA tidak ditemukan'];
        }

        $status = (string) ($payment['payment_status'] ?? '');
        $invoiceId = (int) ($payment['invoice_id'] ?? 0);
        if ($invoiceId < 1) {
            return ['ok' => false, 'message' => 'invoice_id tidak valid'];
        }

        // Sudah gagal/expired (mis. unbind sebelumnya gagal setelah update payment) — lanjut sync + unbind link
        if (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
            self::syncInvoiceAfterPaymentFailed($invoiceDb, $invoiceId, (int) $payment['id']);

            return [
                'ok' => true,
                'detail' => [
                    'payment_ref' => $paymentRef,
                    'invoice_id' => $invoiceId,
                    'payment_already_failed' => true,
                ],
            ];
        }

        if (!in_array($status, ['pending', 'success'], true)) {
            return ['ok' => false, 'message' => 'Status pembayaran tidak dapat di-unbind (' . $status . ')'];
        }

        $invoiceDb->update('invoice_payments', [
            'payment_status' => 'failed',
            'paid_at' => null,
        ], ['id' => (int) $payment['id']]);

        self::syncInvoiceAfterPaymentFailed($invoiceDb, $invoiceId, (int) $payment['id']);

        return [
            'ok' => true,
            'detail' => [
                'payment_ref' => $paymentRef,
                'invoice_id' => $invoiceId,
            ],
        ];
    }

    /**
     * Sinkronkan invoices setelah payment BCA gagal / di-unbind.
     *
     * @param object $invoiceDb
     */
    private static function syncInvoiceAfterPaymentFailed($invoiceDb, int $invoiceId, int $excludePaymentId = 0): void
    {
        $invoice = $invoiceDb->query(
            'SELECT status, payment_status FROM invoices WHERE id = ? LIMIT 1',
            [$invoiceId]
        )->row_array();

        if (!is_array($invoice) || empty($invoice)) {
            return;
        }

        $invoiceUpdate = [];

        $stillPending = $invoiceDb->query(
            "SELECT id FROM invoice_payments
             WHERE invoice_id = ? AND payment_status = 'pending'"
             . ($excludePaymentId > 0 ? ' AND id <> ?' : '')
             . ' LIMIT 1',
            $excludePaymentId > 0 ? [$invoiceId, $excludePaymentId] : [$invoiceId]
        )->row_array();

        if (empty($stillPending['id'])) {
            $invoiceUpdate['payment_status'] = 'unpaid';
        }

        // invoices.status ENUM: draft|sent|paid|cancelled — bukan 'unpaid'
        if (strtolower((string) ($invoice['status'] ?? '')) === 'paid') {
            $invoiceUpdate['status'] = 'sent';
        }

        if ($invoiceUpdate !== []) {
            $invoiceDb->update('invoices', $invoiceUpdate, ['id' => $invoiceId]);
        }
    }

    /**
     * Gagal bayar + kembalikan subscription ke snapshot / periode sebelumnya.
     *
     * @param object $salonDb
     * @return array{ok:bool,message?:string,detail?:array<string,mixed>}
     */
    public static function revertSalon($salonDb, string $paymentRef): array
    {
        $paymentRef = trim($paymentRef);
        if ($paymentRef === '') {
            return ['ok' => false, 'message' => 'payment_ref kosong'];
        }

        $payment = $salonDb->query(
            "SELECT * FROM subscription_payments
             WHERE payment_ref = ?
               AND payment_method = 'bca'
             LIMIT 1",
            [$paymentRef]
        )->row_array();

        if (!is_array($payment) || empty($payment['id'])) {
            return ['ok' => false, 'message' => 'Pembayaran subscription BCA tidak ditemukan'];
        }

        $status = (string) ($payment['payment_status'] ?? '');
        $salonId = (int) ($payment['salon_id'] ?? 0);
        if ($salonId < 1) {
            return ['ok' => false, 'message' => 'salon_id tidak valid'];
        }

        if (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
            return [
                'ok' => true,
                'detail' => [
                    'payment_ref' => $paymentRef,
                    'salon_id' => $salonId,
                    'payment_already_failed' => true,
                ],
            ];
        }

        if (!in_array($status, ['pending', 'success'], true)) {
            return ['ok' => false, 'message' => 'Status pembayaran tidak dapat di-unbind (' . $status . ')'];
        }

        $salonDb->update('subscription_payments', [
            'payment_status' => 'failed',
        ], ['id' => (int) $payment['id']]);

        if ($status === 'success') {
            $snapshot = self::decodeSubscriptionSnapshot($payment['prev_subscription_json'] ?? null);
            if ($snapshot !== null) {
                self::applySubscriptionSnapshot($salonDb, $salonId, $snapshot);
            } else {
                self::revertSalonFallback($salonDb, $salonId, $payment);
            }
        }

        return [
            'ok' => true,
            'detail' => [
                'payment_ref' => $paymentRef,
                'salon_id' => $salonId,
                'subscription_reverted' => $status === 'success',
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function decodeSubscriptionSnapshot($raw): ?array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private static function applySubscriptionSnapshot($salonDb, int $salonId, array $snapshot): void
    {
        $sub = is_array($snapshot['subscription'] ?? null) ? $snapshot['subscription'] : [];
        if ($sub !== []) {
            $update = [];
            foreach (['status', 'start_date', 'end_date', 'last_payment_date', 'last_payment_amount', 'payment_ref', 'reminder_sent'] as $col) {
                if (array_key_exists($col, $sub)) {
                    $update[$col] = $sub[$col];
                }
            }
            if ($update !== []) {
                $salonDb->update('subscriptions', $update, ['salon_id' => $salonId]);
            }
        }

        $salon = is_array($snapshot['salon'] ?? null) ? $snapshot['salon'] : [];
        if ($salon !== []) {
            $salonUpdate = [];
            foreach (['subscription_status', 'subscription_end_date'] as $col) {
                if (array_key_exists($col, $salon)) {
                    $salonUpdate[$col] = $salon[$col];
                }
            }
            if ($salonUpdate !== []) {
                try {
                    $salonDb->update('salon', $salonUpdate, ['salon_id' => $salonId]);
                } catch (\Throwable $e) {
                    // kolom salon opsional
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $payment
     */
    private static function revertSalonFallback($salonDb, int $salonId, array $payment): void
    {
        $prev = $salonDb->query(
            "SELECT * FROM subscription_payments
             WHERE salon_id = ?
               AND payment_status = 'success'
               AND payment_ref <> ?
             ORDER BY created_at DESC
             LIMIT 1",
            [$salonId, (string) ($payment['payment_ref'] ?? '')]
        )->row_array();

        if (is_array($prev) && !empty($prev['id'])) {
            $salonDb->update('subscriptions', [
                'status' => 'active',
                'start_date' => $prev['period_start'] ?? null,
                'end_date' => $prev['period_end'] ?? null,
                'last_payment_date' => !empty($prev['created_at']) ? date('Y-m-d', strtotime((string) $prev['created_at'])) : null,
                'last_payment_amount' => $prev['amount'] ?? null,
                'payment_ref' => $prev['payment_ref'] ?? null,
                'reminder_sent' => 0,
            ], ['salon_id' => $salonId]);

            try {
                $salonDb->update('salon', [
                    'subscription_status' => 'active',
                    'subscription_end_date' => $prev['period_end'] ?? null,
                ], ['salon_id' => $salonId]);
            } catch (\Throwable $e) {
                // optional columns
            }

            return;
        }

        $periodStart = trim((string) ($payment['period_start'] ?? ''));
        $today = date('Y-m-d');
        $endDate = $periodStart !== '' ? $periodStart : $today;
        $status = ($endDate >= $today) ? 'active' : 'expired';

        if ($periodStart !== '' && $periodStart === $today) {
            $sub = $salonDb->query(
                'SELECT end_date FROM subscriptions WHERE salon_id = ? LIMIT 1',
                [$salonId]
            )->row_array();
            $currentEnd = trim((string) ($sub['end_date'] ?? ''));
            if ($currentEnd !== '' && $currentEnd < $today) {
                $endDate = $currentEnd;
                $status = 'expired';
            }
        }

        $salonDb->update('subscriptions', [
            'status' => $status,
            'end_date' => $endDate,
            'payment_ref' => null,
            'reminder_sent' => 0,
        ], ['salon_id' => $salonId]);

        try {
            $salonDb->update('salon', [
                'subscription_status' => $status,
                'subscription_end_date' => $endDate,
            ], ['salon_id' => $salonId]);
        } catch (\Throwable $e) {
            // optional columns
        }
    }
}
