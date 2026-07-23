<?php

namespace App\Controllers\Beauty_Salon;

use App\Core\Controller;

/**
 * Beauty Salon Subscription Controller
 * Manages monthly subscription for salons - Rp 60.000/month per salon_id
 */
class Subscription extends Controller
{
    private $db_index = 4; // mdl_salon database
    private $monthly_price = 60000; // Rp 60.000 per month
    private $trial_days = 30; // 30 days trial period
    private $grace_period_days = 3; // 3 days grace period after expiry
    private $session_key = 'salon_user_session';

    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * GET - Get current subscription status for logged-in salon
     */
    public function index()
    {
        try {
            $salon_id = $this->getSalonId();
            
            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan. Silakan login ulang.', 401);
            }

            $subscription = $this->getSubscription($salon_id);
            
            // If no subscription exists, create trial
            if (!$subscription) {
                $subscription = $this->createTrialSubscription($salon_id);
            }

            // Calculate days remaining
            $end_date = new \DateTime($subscription['end_date']);
            $today = new \DateTime(date('Y-m-d'));
            $diff = $today->diff($end_date);
            $days_remaining = $end_date >= $today ? (int)$diff->days : -(int)$diff->days;

            // Determine actual status considering grace period
            $effective_status = $subscription['status'];
            if ($subscription['status'] === 'expired' && $days_remaining >= -$this->grace_period_days) {
                $effective_status = 'grace_period';
            }

            $this->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'days_remaining' => $days_remaining,
                    'effective_status' => $effective_status,
                    'is_trial' => $subscription['status'] === 'trial',
                    'is_active' => in_array($effective_status, ['trial', 'active', 'grace_period']),
                    'monthly_price' => $this->monthly_price,
                    'grace_period_days' => $this->grace_period_days,
                    'should_warn' => $days_remaining <= 7 && $days_remaining >= 0,
                    'should_renew' => $days_remaining <= 3
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Subscription index error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Check if subscription is valid (for middleware)
     */
    public function check()
    {
        try {
            $salon_id = $this->getSalonId();
            
            if (!$salon_id) {
                $this->json([
                    'success' => true,
                    'is_valid' => false,
                    'reason' => 'no_session'
                ]);
                return;
            }

            $subscription = $this->getSubscription($salon_id);
            
            // If no subscription, assume trial (will be created on full load)
            if (!$subscription) {
                $this->json([
                    'success' => true,
                    'is_valid' => true,
                    'status' => 'trial',
                    'message' => 'Masa trial'
                ]);
                return;
            }

            $end_date = new \DateTime($subscription['end_date']);
            $today = new \DateTime(date('Y-m-d'));
            $diff = $today->diff($end_date);
            $days_remaining = $end_date >= $today ? (int)$diff->days : -(int)$diff->days;

            // Determine EFFECTIVE status based on dates, not just database status
            // If end_date has passed, treat as expired regardless of DB status
            $effective_status = $subscription['status'];
            if ($days_remaining < 0 && in_array($subscription['status'], ['trial', 'active'])) {
                $effective_status = 'expired';
                
                // Also update the database status
                $this->db($this->db_index)->update('subscriptions', [
                    'status' => 'expired'
                ], ['salon_id' => $salon_id]);
            }
            
            // Determine if in grace period
            if ($effective_status === 'expired' && $days_remaining >= -$this->grace_period_days) {
                $effective_status = 'grace_period';
            }

            // Allow access during trial, active, or grace period
            $is_valid = in_array($effective_status, ['trial', 'active', 'grace_period']);

            $this->json([
                'success' => true,
                'is_valid' => $is_valid,
                'status' => $effective_status,
                'days_remaining' => $days_remaining,
                'end_date' => $subscription['end_date'],
                'message' => $is_valid 
                    ? ($days_remaining <= 3 && $days_remaining >= 0 ? 'Langganan akan segera habis' : ($effective_status === 'grace_period' ? 'Masa tenggang' : 'Aktif'))
                    : 'Langganan telah habis'
            ]);
        } catch (\Exception $e) {
            error_log("Subscription check error: " . $e->getMessage());
            $this->json([
                'success' => false,
                'is_valid' => false,
                'reason' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST - Create payment for subscription renewal with Tokopay QRIS
     */
    public function pay()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $salon_id = $this->getSalonId();

            if (!$salon_id) {
                $this->error('Salon ID tidak ditemukan. Silakan login ulang.', 401);
            }

            // Resolve existing pending first (like laundry: reuse / refresh / mark failed)
            $pending_payment = $this->db($this->db_index)
                ->get_where('subscription_payments', [
                    'salon_id' => $salon_id,
                    'payment_status' => 'pending'
                ], 1)
                ->row_array();

            if ($pending_payment) {
                // On pay: reuse/check pending, but do NOT silently refresh into old invoice.
                // If expired → mark failed and allow creating the newly selected plan.
                $resolved = $this->resolvePendingQris($pending_payment, false);

                if (!empty($resolved['paid'])) {
                    $this->json([
                        'success' => true,
                        'status' => 'paid',
                        'data' => $resolved['data'] ?? null,
                        'message' => $resolved['message'] ?? 'Pembayaran sudah berhasil'
                    ]);
                }

                if (!empty($resolved['qr_string'])) {
                    $this->json([
                        'success' => true,
                        'data' => $resolved['data'],
                        'message' => $resolved['message'] ?? 'Melanjutkan pembayaran tertunda Anda'
                    ]);
                }

                // Expired/failed already marked — continue create new payment below
                if (empty($resolved['allow_new'])) {
                    $this->error(
                        $resolved['message'] ?? 'Anda memiliki pembayaran yang belum selesai. Mohon selesaikan atau batalkan di riwayat.',
                        400
                    );
                }
            }

            $body = $this->getBody();

            $months = isset($body['months']) ? (int)$body['months'] : 1;
            if ($months < 1 || $months > 12) {
                $months = 1;
            }

            $base_amount = $this->monthly_price * $months;
            $discount = 0;
            if ($months >= 12) {
                $discount = $base_amount * 0.15;
            } elseif ($months >= 3) {
                $discount = $base_amount * 0.05;
            }
            $amount = $base_amount - $discount;

            $subscription = $this->getSubscription($salon_id);

            if ($subscription) {
                $end_date_check = new \DateTime($subscription['end_date']);
                $today_check = new \DateTime(date('Y-m-d'));
                if ($end_date_check > $today_check) {
                    $interval = $today_check->diff($end_date_check);
                    $days_remaining = (int)$interval->format('%a');
                    if ($days_remaining > 31) {
                        $this->error('Langganan Anda masih aktif ' . $days_remaining . ' hari lagi. Perpanjangan baru dapat dilakukan jika sisa kurang dari 31 hari.', 400);
                    }
                }
            }

            if (!$subscription) {
                $subscription = $this->createTrialSubscription($salon_id);
            }

            $current_end = new \DateTime($subscription['end_date']);
            $today = new \DateTime(date('Y-m-d'));
            $start_date = $current_end < $today ? $today : $current_end;
            $end_date = clone $start_date;
            $end_date->modify("+{$months} months");

            $payment_ref = 'SALONSUB_' . $salon_id . '_' . time();

            $this->db($this->db_index)->insert('subscription_payments', [
                'salon_id' => $salon_id,
                'subscription_id' => $subscription['id'],
                'amount' => $amount,
                'payment_method' => 'qris',
                'payment_ref' => $payment_ref,
                'payment_status' => 'pending',
                'period_start' => $start_date->format('Y-m-d'),
                'period_end' => $end_date->format('Y-m-d'),
                'notes' => "Perpanjangan {$months} bulan" . ($discount > 0 ? " (Diskon " . ($months >= 12 ? '15%' : '5%') . ")" : ""),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $order = $this->createTokopayOrder((int)$amount, $payment_ref);

            if (empty($order['ok']) || empty($order['qr_string'])) {
                $this->markPaymentFailed($payment_ref);
                error_log('Tokopay createOrder failed for ' . $payment_ref . ': ' . ($order['raw'] ?? ''));
                $this->error($order['message'] ?? 'Gagal membuat QRIS. Silakan coba lagi.', 500);
            }

            $this->json([
                'success' => true,
                'data' => [
                    'payment_ref' => $payment_ref,
                    'amount' => $amount,
                    'months' => $months,
                    'discount' => $discount,
                    'period_start' => $start_date->format('Y-m-d'),
                    'period_end' => $end_date->format('Y-m-d'),
                    'qr_string' => $order['qr_string']
                ],
                'message' => 'Scan QRIS untuk membayar Rp ' . number_format($amount, 0, ',', '.')
            ]);
        } catch (\Exception $e) {
            error_log('Subscription pay error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Resume pending payment (reuse QR if fresh, refresh if expired)
     */
    public function resume()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $salon_id = $this->getSalonId();
            if (!$salon_id) {
                $this->error('Unauthorized', 401);
            }

            $body = $this->getBody();
            if (empty($body['payment_ref'])) {
                $this->error('Reference required', 400);
            }
            $payment_ref = $body['payment_ref'];

            $payment = $this->db($this->db_index)
                ->get_where('subscription_payments', [
                    'payment_ref' => $payment_ref,
                    'salon_id' => $salon_id
                ], 1)
                ->row_array();

            if (!$payment) {
                $this->error('Payment not found', 404);
            }

            if (in_array($payment['payment_status'], ['success', 'paid'], true)) {
                $this->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Pembayaran sudah berhasil'
                ]);
            }

            if ($payment['payment_status'] !== 'pending') {
                $this->json([
                    'success' => false,
                    'expired' => true,
                    'status' => $payment['payment_status'],
                    'message' => 'Pembayaran ini sudah tidak aktif. Silakan buat pembayaran baru.'
                ]);
            }

            $resolved = $this->resolvePendingQris($payment, true);

            if (!empty($resolved['paid'])) {
                $this->json([
                    'success' => true,
                    'status' => 'paid',
                    'data' => $resolved['data'] ?? null,
                    'message' => $resolved['message'] ?? 'Pembayaran berhasil'
                ]);
            }

            if (!empty($resolved['qr_string'])) {
                $this->json([
                    'success' => true,
                    'data' => $resolved['data'],
                    'message' => $resolved['message'] ?? 'Silakan scan QRIS kembali',
                    'refreshed' => !empty($resolved['refreshed'])
                ]);
            }

            // Could not refresh — mark failed so UI status updates
            if (!empty($resolved['expired']) || !empty($resolved['allow_new'])) {
                $this->markPaymentFailed($payment['payment_ref']);
                $this->json([
                    'success' => false,
                    'expired' => true,
                    'status' => 'failed',
                    'message' => $resolved['message'] ?? 'Invoice kadaluarsa. Silakan buat pembayaran baru.'
                ]);
            }

            $this->error($resolved['message'] ?? 'Gagal mengambil data QRIS', 500);
        } catch (\Exception $e) {
            error_log('Subscription resume error: ' . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Cancel pending payment
     */
    public function cancel()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $salon_id = $this->getSalonId();
            if (!$salon_id) {
                $this->error('Unauthorized', 401);
            }
            
            $body = $this->getBody();
            if (empty($body['payment_ref'])) {
                $this->error('Reference required', 400);
            }
            $payment_ref = $body['payment_ref'];

            // Get payment
            $payment = $this->db($this->db_index)
                ->get_where('subscription_payments', [
                    'payment_ref' => $payment_ref,
                    'salon_id' => $salon_id
                ], 1)
                ->row_array();

            if (!$payment) {
                $this->error('Payment not found', 404);
            }

            if ($payment['payment_status'] !== 'pending') {
                $this->error('Hanya pembayaran pending yang dapat dibatalkan', 400);
            }

            // Update status to failed (since cancelled might not be in enum)
            $this->db($this->db_index)->update('subscription_payments', [
                'payment_status' => 'failed'
            ], ['payment_ref' => $payment_ref]);

            $this->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dibatalkan'
            ]);

        } catch (\Exception $e) {
            error_log("Subscription cancel error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }


    /**
     * POST - Confirm payment (manual confirmation or webhook callback)
     */
    public function confirm()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $this->validate($body, ['payment_ref']);

            $payment_ref = $body['payment_ref'];
            
            // Get payment record
            $payment = $this->db($this->db_index)
                ->get_where('subscription_payments', ['payment_ref' => $payment_ref], 1)
                ->row_array();

            if (!$payment) {
                $this->error('Pembayaran tidak ditemukan', 404);
            }

            if ($payment['payment_status'] === 'success') {
                $this->error('Pembayaran sudah dikonfirmasi sebelumnya', 400);
            }

            // Update payment status
            $this->db($this->db_index)->update('subscription_payments', [
                'payment_status' => 'success',
                'payment_method' => $body['payment_method'] ?? $payment['payment_method']
            ], ['id' => $payment['id']]);

            // Update subscription
            $this->db($this->db_index)->update('subscriptions', [
                'status' => 'active',
                'start_date' => $payment['period_start'],
                'end_date' => $payment['period_end'],
                'last_payment_date' => date('Y-m-d H:i:s'),
                'last_payment_amount' => $payment['amount'],
                'payment_ref' => $payment_ref,
                'reminder_sent' => 0
            ], ['salon_id' => $payment['salon_id']]);

            // Update salon table
            $this->db($this->db_index)->update('salon', [
                'subscription_status' => 'active',
                'subscription_end_date' => $payment['period_end']
            ], ['salon_id' => $payment['salon_id']]);

            $this->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dikonfirmasi. Langganan aktif hingga ' . $payment['period_end']
            ]);
        } catch (\Exception $e) {
            error_log("Subscription confirm error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Get payment history
     */
    public function history()
    {
        try {
            $salon_id = $this->getSalonId();
            
            if (!$salon_id) {
                $this->json([
                    'success' => true,
                    'data' => []
                ]);
                return;
            }
            
            // Get payments without order_by (DB class may not support it)
            $payments = $this->db($this->db_index)
                ->get_where('subscription_payments', ['salon_id' => $salon_id], 50)
                ->result_array();
            
            // Sort by created_at desc in PHP
            if ($payments) {
                usort($payments, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
            
                // Limit to last 5 records as requested
                $payments = array_slice($payments, 0, 5);
            }

            $this->json([
                'success' => true,
                'data' => $payments ?: []
            ]);
        } catch (\Exception $e) {
            error_log("Subscription history error: " . $e->getMessage());
            $this->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * GET - Poll payment status from DB only (webhook is source of truth)
     * Pattern from laundry payment_gateway_status_db
     */
    public function pollPayment($payment_ref = null)
    {
        if (!$payment_ref && isset($_GET['payment_ref'])) {
            $payment_ref = $_GET['payment_ref'];
        }

        try {
            if (!$payment_ref) {
                $this->error('Payment reference required', 400);
            }

            $salon_id = $this->getSalonId();
            if (!$salon_id) {
                $this->error('Unauthorized', 401);
            }

            $payment = $this->db($this->db_index)
                ->get_where('subscription_payments', [
                    'payment_ref' => $payment_ref,
                    'salon_id' => $salon_id
                ], 1)
                ->row_array();

            if (!$payment) {
                $this->error('Payment not found', 404);
            }

            $dbStatus = strtolower((string)($payment['payment_status'] ?? ''));

            if (in_array($dbStatus, ['success', 'paid'], true)) {
                $this->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Pembayaran berhasil'
                ]);
            }

            if (in_array($dbStatus, ['failed', 'expired', 'cancelled'], true)) {
                $this->json([
                    'success' => true,
                    'status' => 'expired',
                    'message' => 'Pembayaran kadaluarsa atau gagal'
                ]);
            }

            $this->json([
                'success' => true,
                'status' => 'pending',
                'message' => 'Menunggu pembayaran'
            ]);
        } catch (\Exception $e) {
            error_log('Poll payment error: ' . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Check payment status from Tokopay (manual verify)
     * Updates local status on paid / expired / failed
     */
    public function checkPayment($payment_ref = null)
    {
        if (!$payment_ref && isset($_GET['payment_ref'])) {
            $payment_ref = $_GET['payment_ref'];
        }

        try {
            if (!$payment_ref) {
                $this->error('Payment reference required', 400);
            }

            $salon_id = $this->getSalonId();
            if (!$salon_id) {
                $this->error('Unauthorized', 401);
            }

            $payment = $this->db($this->db_index)
                ->get_where('subscription_payments', [
                    'payment_ref' => $payment_ref,
                    'salon_id' => $salon_id
                ], 1)
                ->row_array();

            if (!$payment) {
                $this->error('Payment not found', 404);
            }

            if (in_array($payment['payment_status'], ['paid', 'success'], true)) {
                $this->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Pembayaran berhasil!'
                ]);
            }

            if (in_array($payment['payment_status'], ['failed', 'expired', 'cancelled'], true)) {
                $this->json([
                    'success' => true,
                    'status' => 'expired',
                    'message' => 'Pembayaran sudah kadaluarsa/gagal. Silakan buat pembayaran baru.'
                ]);
            }

            $amount_int = (int)floatval($payment['amount']);
            $tokopay = new \App\Models\Tokopay();
            $response = $tokopay->checkStatus($payment_ref, $amount_int, 'QRIS');
            $data = json_decode($response, true);
            $parsed = $this->parseTokopayStatus($data);

            if (!empty($parsed['connection_error'])) {
                $this->json([
                    'success' => true,
                    'status' => 'error',
                    'message' => $parsed['message'] ?? 'Gagal terhubung ke payment gateway'
                ]);
            }

            if (!empty($parsed['paid'])) {
                $this->activatePayment($payment);
                $this->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Pembayaran berhasil! Langganan aktif hingga ' . $payment['period_end']
                ]);
            }

            if (!empty($parsed['expired'])) {
                $this->markPaymentFailed($payment_ref);
                $this->json([
                    'success' => true,
                    'status' => 'expired',
                    'message' => 'QRIS sudah kadaluarsa. Silakan buat / perbarui pembayaran.'
                ]);
            }

            $this->json([
                'success' => true,
                'status' => 'pending',
                'message' => 'Menunggu pembayaran...'
            ]);
        } catch (\Exception $e) {
            error_log('Check payment error: ' . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Update expired subscriptions (cron job endpoint)
     * Should be called periodically by cron
     */
    public function cron_update_status()
    {
        try {
            // This should be protected by API key in production
            $today = date('Y-m-d');
            
            // Update subscriptions that have expired
            $this->db($this->db_index)->raw_query(
                "UPDATE subscriptions 
                 SET status = 'expired' 
                 WHERE status IN ('trial', 'active') 
                 AND end_date < ?",
                [$today]
            );

            // Update salon table for expired subscriptions
            $this->db($this->db_index)->raw_query(
                "UPDATE salon s 
                 INNER JOIN subscriptions sub ON s.salon_id = sub.salon_id 
                 SET s.subscription_status = 'expired'
                 WHERE sub.status = 'expired'"
            );

            // Get subscriptions needing reminder (7 days before expiry)
            $reminder_date = date('Y-m-d', strtotime('+7 days'));
            $upcoming = $this->db($this->db_index)
                ->raw_query(
                    "SELECT s.*, u.email, u.name 
                     FROM subscriptions s
                     INNER JOIN users u ON u.salon_id = s.salon_id
                     WHERE s.end_date <= ? 
                     AND s.end_date >= ?
                     AND s.reminder_sent = 0
                     AND s.status IN ('trial', 'active')
                     AND u.role = 'admin'",
                    [$reminder_date, $today]
                )
                ->result_array();

            // Mark reminders as sent
            if (!empty($upcoming)) {
                $salon_ids = array_column($upcoming, 'salon_id');
                $placeholders = implode(',', array_fill(0, count($salon_ids), '?'));
                $this->db($this->db_index)->raw_query(
                    "UPDATE subscriptions SET reminder_sent = 1 WHERE salon_id IN ($placeholders)",
                    $salon_ids
                );
            }

            $this->json([
                'success' => true,
                'message' => 'Subscription status updated',
                'reminders_to_send' => count($upcoming),
                'reminder_emails' => array_column($upcoming, 'email')
            ]);
        } catch (\Exception $e) {
            error_log("Subscription cron error: " . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET - Pricing information
     */
    public function pricing()
    {
        $this->json([
            'success' => true,
            'data' => [
                'monthly' => [
                    'price' => $this->monthly_price,
                    'price_formatted' => 'Rp ' . number_format($this->monthly_price, 0, ',', '.'),
                    'period' => '1 bulan',
                    'discount' => 0
                ],
                'quarterly' => [
                    'price' => $this->monthly_price * 3 * 0.95, // 5% discount
                    'price_formatted' => 'Rp ' . number_format($this->monthly_price * 3 * 0.95, 0, ',', '.'),
                    'period' => '3 bulan',
                    'discount' => 5,
                    'per_month' => 'Rp ' . number_format($this->monthly_price * 0.95, 0, ',', '.')
                ],
                'yearly' => [
                    'price' => $this->monthly_price * 12 * 0.85, // 15% discount
                    'price_formatted' => 'Rp ' . number_format($this->monthly_price * 12 * 0.85, 0, ',', '.'),
                    'period' => '12 bulan',
                    'discount' => 15,
                    'per_month' => 'Rp ' . number_format($this->monthly_price * 0.85, 0, ',', '.')
                ],
                'trial_days' => $this->trial_days,
                'grace_period_days' => $this->grace_period_days
            ]
        ]);
    }

    // ============ PRIVATE HELPERS ============

    /**
     * Resolve pending QRIS payment (laundry-style):
     * - < 5 min: reuse same ref (idempotent createOrder)
     * - >= 5 min: check gateway status first
     *   - paid → activate
     *   - expired/failed → refresh QR with new payment_ref (same row) OR allow new
     *   - pending / unclear / API error → try reuse; if fail and old → refresh
     */
    private function resolvePendingQris(array $payment, $allowRefresh = true)
    {
        $payment_ref = $payment['payment_ref'];
        $amount_int = (int)floatval($payment['amount']);
        $created_at = !empty($payment['created_at']) ? strtotime($payment['created_at']) : 0;
        $age_minutes = $created_at > 0 ? (time() - $created_at) / 60 : 999;

        $payloadBase = [
            'payment_ref' => $payment_ref,
            'amount' => $payment['amount'],
            'period_start' => $payment['period_start'],
            'period_end' => $payment['period_end'],
            'discount' => 0
        ];

        // Fresh QR window: reuse same Tokopay ref
        if ($age_minutes < 5) {
            $order = $this->createTokopayOrder($amount_int, $payment_ref);
            if (!empty($order['ok']) && !empty($order['qr_string'])) {
                return [
                    'qr_string' => $order['qr_string'],
                    'data' => array_merge($payloadBase, ['qr_string' => $order['qr_string']]),
                    'message' => 'Melanjutkan pembayaran tertunda Anda'
                ];
            }

            // Create failed while still "fresh" — check real status before giving up
            $status = $this->fetchTokopayPaymentStatus($payment_ref, $amount_int);
            if (!empty($status['paid'])) {
                $this->activatePayment($payment);
                return [
                    'paid' => true,
                    'data' => $payloadBase,
                    'message' => 'Pembayaran sudah berhasil'
                ];
            }
            if (!empty($status['expired'])) {
                if ($allowRefresh) {
                    return $this->refreshPaymentQr($payment);
                }
                $this->markPaymentFailed($payment_ref);
                return [
                    'expired' => true,
                    'allow_new' => true,
                    'message' => 'Invoice kadaluarsa. Silakan buat pembayaran baru.'
                ];
            }

            return [
                'message' => $order['message'] ?? 'Gagal memuat QRIS. Coba lagi sebentar.'
            ];
        }

        // Older than 5 minutes — confirm status before regenerating (laundry rule)
        $status = $this->fetchTokopayPaymentStatus($payment_ref, $amount_int);

        if (!empty($status['paid'])) {
            $this->activatePayment($payment);
            return [
                'paid' => true,
                'data' => $payloadBase,
                'message' => 'Pembayaran sudah berhasil'
            ];
        }

        if (!empty($status['expired'])) {
            if ($allowRefresh) {
                return $this->refreshPaymentQr($payment);
            }
            $this->markPaymentFailed($payment_ref);
            return [
                'expired' => true,
                'allow_new' => true,
                'message' => 'Invoice kadaluarsa. Silakan buat pembayaran baru.'
            ];
        }

        // Still pending or unclear: try reuse existing ref first
        $order = $this->createTokopayOrder($amount_int, $payment_ref);
        if (!empty($order['ok']) && !empty($order['qr_string'])) {
            return [
                'qr_string' => $order['qr_string'],
                'data' => array_merge($payloadBase, ['qr_string' => $order['qr_string']]),
                'message' => 'Silakan scan QRIS kembali'
            ];
        }

        // Cannot reuse and no confirmed pending — refresh or allow new
        if ($allowRefresh) {
            return $this->refreshPaymentQr($payment);
        }

        $this->markPaymentFailed($payment_ref);
        return [
            'expired' => true,
            'allow_new' => true,
            'message' => 'Invoice tidak valid lagi. Silakan buat pembayaran baru.'
        ];
    }

    /**
     * Regenerate QRIS on same payment row with new payment_ref (Tokopay needs unique ref)
     */
    private function refreshPaymentQr(array $payment)
    {
        $salon_id = $payment['salon_id'];
        $old_ref = $payment['payment_ref'];
        $new_ref = 'SALONSUB_' . $salon_id . '_' . time();
        $amount_int = (int)floatval($payment['amount']);

        $this->db($this->db_index)->update('subscription_payments', [
            'payment_ref' => $new_ref,
            'created_at' => date('Y-m-d H:i:s')
        ], ['id' => $payment['id']]);

        $order = $this->createTokopayOrder($amount_int, $new_ref);
        if (empty($order['ok']) || empty($order['qr_string'])) {
            // Roll back ref if generate failed so history still points to old row identity
            $this->db($this->db_index)->update('subscription_payments', [
                'payment_ref' => $old_ref
            ], ['id' => $payment['id']]);
            $this->markPaymentFailed($old_ref);
            return [
                'expired' => true,
                'allow_new' => true,
                'message' => $order['message'] ?? 'Gagal memperbarui QRIS. Silakan buat pembayaran baru.'
            ];
        }

        return [
            'qr_string' => $order['qr_string'],
            'refreshed' => true,
            'data' => [
                'payment_ref' => $new_ref,
                'amount' => $payment['amount'],
                'period_start' => $payment['period_start'],
                'period_end' => $payment['period_end'],
                'qr_string' => $order['qr_string'],
                'discount' => 0
            ],
            'message' => 'QRIS diperbarui. Silakan scan ulang.'
        ];
    }

    private function createTokopayOrder($amount_int, $payment_ref)
    {
        $tokopay = new \App\Models\Tokopay();
        $response = $tokopay->createOrder((int)$amount_int, $payment_ref, 'QRIS');
        $data = json_decode($response, true);

        $apiOk = false;
        if (isset($data['status'])) {
            $status = is_string($data['status']) ? strtolower($data['status']) : $data['status'];
            if ($status === 'success' || $status === 'true' || $status === true || $status === 1) {
                $apiOk = true;
            }
        }

        $qr_string = $this->extractQrString($data);
        if ($apiOk && !empty($qr_string)) {
            return [
                'ok' => true,
                'qr_string' => $qr_string,
                'raw' => $response
            ];
        }

        $message = 'Gagal membuat QRIS';
        if (!empty($data['message'])) {
            $message = $data['message'];
        } elseif (!empty($data['error_msg'])) {
            $message = $data['error_msg'];
        } elseif ($apiOk && empty($qr_string)) {
            $message = 'QR String tidak ditemukan dari payment gateway';
        }

        return [
            'ok' => false,
            'qr_string' => '',
            'message' => $message,
            'raw' => is_string($response) ? substr($response, 0, 300) : ''
        ];
    }

    private function fetchTokopayPaymentStatus($payment_ref, $amount_int)
    {
        try {
            $tokopay = new \App\Models\Tokopay();
            $response = $tokopay->checkStatus($payment_ref, (int)$amount_int, 'QRIS');
            $data = json_decode($response, true);
            return $this->parseTokopayStatus($data);
        } catch (\Exception $e) {
            return [
                'connection_error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Parse Tokopay/QRIS status response.
     * IMPORTANT: root "status"=Success means API OK, NOT payment paid.
     */
    private function parseTokopayStatus($data)
    {
        if (!$data || !is_array($data)) {
            return [
                'connection_error' => true,
                'message' => 'Respon tidak valid dari payment gateway'
            ];
        }

        if (isset($data['status']) && $data['status'] === false && !empty($data['message'])) {
            return [
                'connection_error' => true,
                'message' => 'Gagal cek ke Tokopay: ' . $data['message']
            ];
        }

        $status_trx = '';
        $payment_status = '';

        if (!empty($data['payment_status']) && is_string($data['payment_status'])) {
            $payment_status = strtolower(trim($data['payment_status']));
        }

        if (isset($data['data']) && is_array($data['data'])) {
            if (!empty($data['data']['status_pembayaran'])) {
                $status_trx = strtolower(trim($data['data']['status_pembayaran']));
            } elseif (!empty($data['data']['status_detail'])) {
                $status_trx = strtolower(trim($data['data']['status_detail']));
            } elseif (!empty($data['data']['status']) && is_string($data['data']['status'])) {
                $status_trx = strtolower(trim($data['data']['status']));
            }
        }

        if ($status_trx === '' && !empty($data['status_pembayaran'])) {
            $status_trx = strtolower(trim($data['status_pembayaran']));
        } elseif ($status_trx === '' && !empty($data['status_detail'])) {
            $status_trx = strtolower(trim($data['status_detail']));
        } elseif ($status_trx === '' && !empty($data['trx_status'])) {
            $status_trx = strtolower(trim($data['trx_status']));
        }

        $successList = ['success', 'paid', 'settlement', 'capture', 'completed', 'berhasil'];
        $expiredList = ['expired', 'cancelled', 'cancel', 'timeout', 'failed', 'fail', 'kadaluarsa', 'gagal'];

        if (class_exists('Env') && defined('Env::QRIS_STATUS_SUCCESS')) {
            $successList = array_map('strtolower', (array)\Env::QRIS_STATUS_SUCCESS);
        }
        if (class_exists('Env') && defined('Env::QRIS_STATUS_EXPIRED')) {
            $expiredList = array_map('strtolower', (array)\Env::QRIS_STATUS_EXPIRED);
        }

        $isPaid = ($payment_status === 'paid') || in_array($status_trx, $successList, true);
        $isExpired = ($payment_status === 'expired') || in_array($status_trx, $expiredList, true);

        return [
            'paid' => $isPaid,
            'expired' => $isExpired && !$isPaid,
            'pending' => !$isPaid && !$isExpired,
            'trx_status' => $status_trx ?: ($payment_status ?: 'unknown')
        ];
    }

    private function extractQrString($data)
    {
        if (!is_array($data)) {
            return '';
        }
        if (!empty($data['data']['qr_string'])) {
            return trim($data['data']['qr_string']);
        }
        if (!empty($data['qr_string'])) {
            return trim($data['qr_string']);
        }
        return '';
    }

    private function markPaymentFailed($payment_ref)
    {
        if (empty($payment_ref)) {
            return;
        }
        $this->db($this->db_index)->update('subscription_payments', [
            'payment_status' => 'failed'
        ], ['payment_ref' => $payment_ref]);
    }

    private function activatePayment(array $payment)
    {
        $payment_ref = $payment['payment_ref'];
        $salon_id = $payment['salon_id'];

        $this->db($this->db_index)->update('subscription_payments', [
            'payment_status' => 'success'
        ], ['payment_ref' => $payment_ref]);

        $this->db($this->db_index)->update('subscriptions', [
            'status' => 'active',
            'start_date' => $payment['period_start'],
            'end_date' => $payment['period_end'],
            'last_payment_date' => date('Y-m-d'),
            'last_payment_amount' => $payment['amount'],
            'payment_ref' => $payment_ref,
            'reminder_sent' => 0
        ], ['salon_id' => $salon_id]);
    }

    private function getSalonId()
    {
        return $_SESSION[$this->session_key]['user']['salon_id'] ?? null;
    }

    private function getSubscription($salon_id)
    {
        return $this->db($this->db_index)
            ->get_where('subscriptions', ['salon_id' => $salon_id], 1)
            ->row_array();
    }

    private function createTrialSubscription($salon_id)
    {
        // Get the user's created_at date to use as start_date
        $user = $this->db($this->db_index)
            ->get_where('users', ['salon_id' => $salon_id, 'role' => 'admin'], 1)
            ->row_array();
        
        // Use user's created_at date if available, otherwise use today
        if ($user && !empty($user['created_at'])) {
            $start_date = date('Y-m-d', strtotime($user['created_at']));
        } else {
            $start_date = date('Y-m-d');
        }
        
        // Calculate end_date based on start_date + trial_days
        $end_date = date('Y-m-d', strtotime($start_date . " +{$this->trial_days} days"));

        $data = [
            'salon_id' => $salon_id,
            'plan' => 'monthly',
            'price' => $this->monthly_price,
            'status' => 'trial',
            'trial_days' => $this->trial_days,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db($this->db_index)->insert('subscriptions', $data);
        $data['id'] = $this->db($this->db_index)->last_id();

        // Also update salon table
        $this->db($this->db_index)->update('salon', [
            'subscription_status' => 'trial',
            'subscription_end_date' => $end_date
        ], ['salon_id' => $salon_id]);

        return $data;
    }
}
