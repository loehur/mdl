<?php

namespace App\Helpers\Beauty_Salon;

/**
 * Konfirmasi pembayaran subscription salon via transfer BCA.
 */
class SalonBcaConfirm
{
    /**
     * @param object $salonDb api db(4) mdl_salon
     * @return array{ok:bool,message?:string,payment_ref?:string,salon_id?:int}
     */
    public static function approve($salonDb, string $paymentRef): array
    {
        $paymentRef = trim($paymentRef);
        if ($paymentRef === '') {
            return ['ok' => false, 'message' => 'payment_ref kosong'];
        }

        $payment = $salonDb->query(
            "SELECT * FROM subscription_payments
             WHERE payment_ref = ?
               AND payment_method = 'bca'
               AND payment_status = 'pending'
             LIMIT 1",
            [$paymentRef]
        )->row_array();

        if (!is_array($payment) || empty($payment['id'])) {
            return ['ok' => false, 'message' => 'subscription payment BCA pending tidak ditemukan'];
        }

        self::activatePayment($salonDb, $payment);

        return [
            'ok' => true,
            'payment_ref' => $paymentRef,
            'salon_id' => (int) ($payment['salon_id'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $payment
     */
    public static function activatePayment($salonDb, array $payment): void
    {
        $paymentRef = (string) ($payment['payment_ref'] ?? '');
        $salonId = (int) ($payment['salon_id'] ?? 0);
        if ($paymentRef === '' || $salonId < 1) {
            return;
        }

        $salonDb->update('subscription_payments', [
            'payment_status' => 'success',
        ], ['payment_ref' => $paymentRef]);

        $salonDb->update('subscriptions', [
            'status' => 'active',
            'start_date' => $payment['period_start'] ?? null,
            'end_date' => $payment['period_end'] ?? null,
            'last_payment_date' => date('Y-m-d'),
            'last_payment_amount' => $payment['amount'] ?? null,
            'payment_ref' => $paymentRef,
            'reminder_sent' => 0,
        ], ['salon_id' => $salonId]);

        try {
            $salonDb->update('salon', [
                'subscription_status' => 'active',
                'subscription_end_date' => $payment['period_end'] ?? null,
            ], ['salon_id' => $salonId]);
        } catch (\Throwable $e) {
            // salon table columns optional
        }
    }
}
