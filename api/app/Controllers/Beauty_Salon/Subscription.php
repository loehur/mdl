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
    private $monthly_price = 10000; // Rp 60.000 per month
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
            
            // Check for existing pending payment
            $pending_payment = $this->db($this->db_index)
                ->get_where('subscription_payments', [
                    'salon_id' => $salon_id,
                    'payment_status' => 'pending'
                ], 1)
                ->row_array();

            if ($pending_payment) {
            // Attempt to auto-resume existing pending payment
            $payment_ref = $pending_payment['payment_ref'];
            $tokopay = new \App\Models\Tokopay();
            $amount_int = (int)floatval($pending_payment['amount']);
            $response = $tokopay->createOrder($amount_int, $payment_ref, 'QRIS');
            $data = json_decode($response, true);
            
            $isSuccess = false;
            if (isset($data['status'])) {
                $status = is_string($data['status']) ? strtolower($data['status']) : $data['status'];
                if ($status === 'success' || $status === 'true' || $status === true || $status === 1) {
                    $isSuccess = true;
                }
            }

            if ($isSuccess) {
                 $qr_string = '';
                 if (isset($data['data']['qr_string'])) {
                     $qr_string = $data['data']['qr_string'];
                 } elseif (isset($data['qr_string'])) {
                     $qr_string = $data['qr_string'];
                 }
                 
                 if (!empty($qr_string)) {
                      $this->json([
                        'success' => true,
                        'data' => [
                            'payment_ref' => $payment_ref,
                            'amount' => $pending_payment['amount'],
                            'period_start' => $pending_payment['period_start'],
                            'period_end' => $pending_payment['period_end'],
                            'qr_string' => $qr_string,
                            'discount' => 0
                        ],
                        'message' => 'Melanjutkan pembayaran tertunda Anda'
                    ]);
                    return;
                 }
            }
            
            $this->error('Anda memiliki pembayaran yang belum selesai. Mohon selesaikan atau batalkan pembayaran sebelumnya di riwayat.', 400);
        }

            $body = $this->getBody();
            
            $months = isset($body['months']) ? (int)$body['months'] : 1;
            if ($months < 1 || $months > 12) {
                $months = 1;
            }

            // Apply discount for multi-month plans
            $base_amount = $this->monthly_price * $months;
            $discount = 0;
            if ($months >= 12) {
                $discount = $base_amount * 0.15; // 15% discount for yearly
            } elseif ($months >= 3) {
                $discount = $base_amount * 0.05; // 5% discount for quarterly
            }
            $amount = $base_amount - $discount;

            $subscription = $this->getSubscription($salon_id);

            // Validate remaining days
            if ($subscription) {
                 $end_date = new \DateTime($subscription['end_date']);
                 $today = new \DateTime(date('Y-m-d'));
                 if ($end_date > $today) {
                     $interval = $today->diff($end_date);
                     $days_remaining = (int)$interval->format('%a');
                     if ($days_remaining > 31) {
                         $this->error('Langganan Anda masih aktif ' . $days_remaining . ' hari lagi. Perpanjangan baru dapat dilakukan jika sisa kurang dari 31 hari.', 400);
                     }
                 }
            }

            if (!$subscription) {
                $subscription = $this->createTrialSubscription($salon_id);
            }

            // Determine new period dates
            $current_end = new \DateTime($subscription['end_date']);
            $today = new \DateTime(date('Y-m-d'));
            
            // If expired, start from today. Otherwise extend from current end date
            $start_date = $current_end < $today ? $today : $current_end;
            $end_date = clone $start_date;
            $end_date->modify("+{$months} months");

            // Generate unique payment reference for Tokopay
            // Format: SALONSUB_{salon_id}_{timestamp}
            $payment_ref = 'SALONSUB_' . $salon_id . '_' . time();

            // Create payment record first
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

            // Generate QRIS via Tokopay
            $tokopay = new \App\Models\Tokopay();
            $amount_int = (int)$amount;
            $response = $tokopay->createOrder($amount_int, $payment_ref, 'QRIS');
            
            // Log raw response for debugging
            error_log("Tokopay Raw Response: " . $response);
            
            $data = json_decode($response, true);

            // Check status with multiple possibilities (Success, true, 1, etc)
            $isSuccess = false;
            
            if (isset($data['status'])) {
                $status = is_string($data['status']) ? strtolower($data['status']) : $data['status'];
                if ($status === 'success' || $status === 'true' || $status === true || $status === 1) {
                    $isSuccess = true;
                }
            }

            if ($isSuccess) {
                // ... success handling ...
                // Extract qr_string
                $qr_string = '';
                if (isset($data['data']['qr_string'])) {
                    $qr_string = $data['data']['qr_string'];
                } elseif (isset($data['qr_string'])) {
                    $qr_string = $data['qr_string'];
                }

                if (empty($qr_string)) {
                    error_log("Tokopay: QR String not found in response: " . $response);
                    $this->error('QR String tidak ditemukan dari Tokopay', 500);
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
                        'qr_string' => $qr_string
                    ],
                    'message' => "Scan QRIS untuk membayar Rp " . number_format($amount, 0, ',', '.')
                ]);
                // Tokopay API failed - log and return error
                error_log("Tokopay API Error: " . $response);
                
                $error_msg = isset($data['message']) ? $data['message'] : 'Gagal membuat QRIS';
                if (isset($data['error_msg'])) $error_msg .= ' - ' . $data['error_msg'];
                
                // DEBUG: Tambahkan raw response ke pesa error agar terlihat di frontend
                if (!$data) {
                    $error_msg .= " | Raw: " . substr($response, 0, 200);
                }
                
                $this->error('Tokopay Error: ' . $error_msg, 500);
            }
        } catch (\Exception $e) {
            error_log("Subscription pay error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            $this->error('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Resume pending payment (< 5 minutes)
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
                $this->error('Payment is not pending. Status: ' . $payment['payment_status'], 400);
            }

            $created_at = strtotime($payment['created_at']);
            $now = time();
            $diff = $now - $created_at;

            // Call Tokopay to get QR (idempotent call with same ref_id)
            $tokopay = new \App\Models\Tokopay();
            // Ensure amount is integer
            $amount_int = (int)floatval($payment['amount']);
            $response = $tokopay->createOrder($amount_int, $payment_ref, 'QRIS');
            
             // Log raw response for debugging
             error_log("Tokopay Resume Response: " . $response);
            
            $data = json_decode($response, true);

            // Check status logic
            $isSuccess = false;
            
            // Check status fields
            if (isset($data['status'])) {
                $status = is_string($data['status']) ? strtolower($data['status']) : $data['status'];
                if ($status === 'success' || $status === 'true' || $status === true || $status === 1) {
                    $isSuccess = true;
                }
            }

            if ($isSuccess) {
                // ... (success code remains same) ...
                // Extract qr_string
                $qr_string = '';
                if (isset($data['data']['qr_string'])) {
                    $qr_string = $data['data']['qr_string'];
                } elseif (isset($data['qr_string'])) {
                    $qr_string = $data['qr_string'];
                }

                if (empty($qr_string)) {
                    $this->error('QR String tidak ditemukan dari Tokopay', 500);
                }

                $this->json([
                    'success' => true,
                    'data' => [
                        'payment_ref' => $payment_ref,
                        'amount' => $payment['amount'],
                        'period_start' => $payment['period_start'],
                        'period_end' => $payment['period_end'],
                        'qr_string' => $qr_string,
                        'discount' => 0
                    ],
                    'message' => "Silakan scan QRIS kembali"
                ]);
            } else {
                 // Tokopay Failed. Check if it's likely expired based on time
                 if ($diff > (5 * 60)) {
                     // If failed and > 5 mins, assume expired
                     $this->json([
                        'success' => false,
                        'expired' => true,
                        'message' => 'Invoice kadaluarsa atau tidak valid. Silakan buat pembayaran baru.'
                     ]);
                     return;
                 }
                 
                 // If not expired by time, return actual error
                 $error_msg = isset($data['message']) ? $data['message'] : 'Gagal mengambil data QRIS';
                 if (isset($data['error_msg'])) $error_msg .= ' - ' . $data['error_msg'];
                 
                 // DEBUG: Include raw response
                 if (!$isSuccess) {
                     $error_msg .= " | Raw: " . substr($response, 0, 200);
                 }
                 
                 $this->error('Tokopay Error: ' . $error_msg, 500);
            }

        } catch (\Exception $e) {
            error_log("Subscription resume error: " . $e->getMessage());
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
     * GET - Check payment status from Tokopay
     * Used for polling to check if QRIS payment is completed
     */
    public function checkPayment($payment_ref = null)
    {
        // Fix: Ambil dari query string jika argument null
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

            // Get payment record
            $payment = $this->db($this->db_index)
                ->get_where('subscription_payments', [
                    'payment_ref' => $payment_ref,
                    'salon_id' => $salon_id
                ], 1)
                ->row_array();

            if (!$payment) {
                $this->error('Payment not found', 404);
            }

            // If already paid, return success
            if ($payment['payment_status'] === 'paid' || $payment['payment_status'] === 'success') {
                $this->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Pembayaran berhasil!'
                ]);
                return;
            }

            // Check status from Tokopay
        // Check status from Tokopay using INLINE CURL to avoid Class Loading issues
        $merchantId = 'M240926BMTGB612';
        $secretKey = '4aea0ede516df65d88ccb773a443c61b3b3702fe1b9647deb9293cac07fd72bf';
        
        $amount_int = (int)floatval($payment['amount']); // Fix: Define amount_int
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.tokopay.id/v1/order?merchant=" . $merchantId . "&secret=" . $secretKey . "&ref_id=" . $payment_ref . "&nominal=" . $amount_int . "&metode=QRIS",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
             error_log("Tokopay Curl Error: $err");
             $this->json([
                'success' => true,
                'status' => 'error',
                'message' => 'Connection Error: ' . $err
            ]);
            return;
        }
        
        $data = json_decode($response, true);
        
        // Handle connection/API error
        if (!$data || (isset($data['status']) && $data['status'] === false && isset($data['message']))) {
            $error_msg = isset($data['message']) ? $data['message'] : 'Respon tidak valid dari Payment Gateway';
            $this->json([
                'success' => true, // Still success true to show alert in frontend logic usually, but let's see frontend logic
                'status' => 'error',
                'message' => 'Gagal cek ke Tokopay: ' . $error_msg
            ]);
            return;
        }

        $isPaid = false;
            
            // Check various status fields
            $status_trx = '';
            
            if (isset($data['data'])) {
                // Check inside 'data' object
                if (isset($data['data']['status_pembayaran'])) {
                    $status_trx = $data['data']['status_pembayaran'];
                } elseif (isset($data['data']['status'])) {
                    $status_trx = $data['data']['status'];
                }
            } elseif (isset($data['status_pembayaran'])) {
                // Check at root level (some endpoints return flat)
                 $status_trx = $data['status_pembayaran'];
            }
            
            $status_trx = strtolower($status_trx);
            
            // Log status for debug
            error_log("Tokopay Check Status Ref [$payment_ref]: $status_trx | Full: " . substr($response, 0, 100));
            
            // Tokopay usually uses 'Success' or 'Paid' for paid transactions
            if ($status_trx === 'success' || $status_trx === 'paid' || $status_trx === 'settlement') {
                $isPaid = true;
            }

            if ($isPaid) {
                // Update payment status
                $this->db($this->db_index)->update('subscription_payments', [
                    'payment_status' => 'success'
                ], ['payment_ref' => $payment_ref]);

                // Update subscription
                $subscription = $this->getSubscription($salon_id);
                if ($subscription) {
                    $this->db($this->db_index)->update('subscriptions', [
                        'status' => 'active',
                        'end_date' => $payment['period_end'],
                        'last_payment_date' => date('Y-m-d'),
                        'last_payment_amount' => $payment['amount'],
                        'payment_ref' => $payment_ref
                    ], ['salon_id' => $salon_id]);

                    // Update salon table
                    $this->db($this->db_index)->update('salon', [
                        'subscription_status' => 'active',
                        'subscription_end_date' => $payment['period_end']
                    ], ['salon_id' => $salon_id]);
                }

                $this->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Pembayaran berhasil! Langganan aktif hingga ' . $payment['period_end']
                ]);
            } else {
            $this->json([
                'success' => true,
                'status' => 'pending',
                'message' => 'Menunggu pembayaran...',
                'debug_response' => $data
            ]);
        }
        } catch (\Exception $e) {
            error_log("Check payment error: " . $e->getMessage());
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
