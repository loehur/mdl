<?php

namespace App\Helpers\Invoice;

/**
 * Konfirmasi pembayaran invoice via transfer BCA (setelah mutasi terikat).
 */
class InvoiceBcaConfirm
{
    /**
     * @param object $invoiceDb api db(6) mdl_invoice
     * @return array{ok:bool,message?:string,payment_ref?:string}
     */
    public static function approve($invoiceDb, string $paymentRef): array
    {
        $paymentRef = trim($paymentRef);
        if ($paymentRef === '') {
            return ['ok' => false, 'message' => 'payment_ref kosong'];
        }

        $payment = $invoiceDb->query(
            "SELECT * FROM invoice_payments
             WHERE payment_ref = ?
               AND payment_method = 'bca'
               AND payment_status IN ('pending', 'failed')
             LIMIT 1",
            [$paymentRef]
        )->row_array();

        if (!is_array($payment) || empty($payment['id'])) {
            return ['ok' => false, 'message' => 'invoice payment BCA pending tidak ditemukan'];
        }

        $now = date('Y-m-d H:i:s');
        $invoiceId = (int) ($payment['invoice_id'] ?? 0);

        $updated = $invoiceDb->query(
            "UPDATE invoice_payments
             SET payment_status = 'success', paid_at = ?
             WHERE payment_ref = ? AND payment_status IN ('pending', 'failed')",
            [$now, $paymentRef]
        );

        if (!$updated || $invoiceDb->affected_rows() < 1) {
            return ['ok' => false, 'message' => 'update payment gagal atau sudah diproses'];
        }

        $invoiceDb->update('invoices', [
            'payment_status' => 'paid',
            'status' => 'paid',
        ], ['id' => $invoiceId]);

        return [
            'ok' => true,
            'payment_ref' => $paymentRef,
            'invoice_id' => $invoiceId,
        ];
    }
}
