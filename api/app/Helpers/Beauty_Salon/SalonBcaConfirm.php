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

        self::storeSubscriptionSnapshot($salonDb, $paymentRef, $salonId);

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

    /**
     * Simpan snapshot subscription sebelum perpanjangan (untuk unbind admin).
     */
    private static function storeSubscriptionSnapshot($salonDb, string $paymentRef, int $salonId): void
    {
        try {
            $existing = $salonDb->query(
                'SELECT prev_subscription_json FROM subscription_payments WHERE payment_ref = ? LIMIT 1',
                [$paymentRef]
            )->row_array();
            if (is_array($existing) && !empty($existing['prev_subscription_json'])) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $subscription = $salonDb->query(
            'SELECT status, start_date, end_date, last_payment_date, last_payment_amount, payment_ref, reminder_sent
             FROM subscriptions WHERE salon_id = ? LIMIT 1',
            [$salonId]
        )->row_array();

        $salonRow = null;
        try {
            $salonRow = $salonDb->query(
                'SELECT subscription_status, subscription_end_date FROM salon WHERE salon_id = ? LIMIT 1',
                [$salonId]
            )->row_array();
        } catch (\Throwable $e) {
            $salonRow = null;
        }

        if (!is_array($subscription) || empty($subscription)) {
            return;
        }

        $payload = json_encode([
            'subscription' => $subscription,
            'salon' => is_array($salonRow) ? $salonRow : [],
            'saved_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') {
            return;
        }

        try {
            $salonDb->update('subscription_payments', [
                'prev_subscription_json' => $payload,
            ], ['payment_ref' => $paymentRef]);
        } catch (\Throwable $e) {
            // kolom belum dimigrasi
        }
    }
}
