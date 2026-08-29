<?php

namespace App\Helpers\Payment;

/**
 * Logika bisnis webhook QRIS terpusat (Tokopay, dll.).
 * Controller webhook hanya handle verifikasi gateway + delegasi ke class ini.
 */
class QrisWebhookProcessor
{
    /** @var object Controller dengan method db() */
    private $controller;

    /** @var string */
    private $logChannel;

    public function __construct(object $controller, string $logChannel = 'QRIS')
    {
        $this->controller = $controller;
        $this->logChannel = $logChannel;
    }

    public function dispatchByPrefix(string $reff_id, string $status): void
    {
        $parts = explode('_', $reff_id);
        if ($parts[0] === 'TEST') {
            return;
        }
        if ($parts[0] === 'SALONSUB') {
            $this->handleSalonSubscription($reff_id, $status);
            return;
        }
        if ($parts[0] === 'MDLINV') {
            $this->handleInvoicePayment($reff_id, $status);
            return;
        }
        if ($parts[0] === 'RESTOKAS') {
            $this->handleRestoKas($reff_id, $status);
            return;
        }

        $this->handleKasLaundry($reff_id, $status);
    }

    public function handleSalonSubscription($payment_ref, $status_gateway): void
    {
        $db = $this->controller->db(4);

        if (!$db) {
            $this->log('Err: DB Salon Not Found');
            return;
        }

        $payment = $db->get_where('subscription_payments', ['payment_ref' => $payment_ref])->row_array();

        if (!$payment) {
            $this->log("Err: Sub Pmt Not Found ref=$payment_ref");
            return;
        }

        $statusLower = strtolower($status_gateway);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);
        $isExpired = in_array($statusLower, \Env::QRIS_STATUS_EXPIRED);
        $isFailed = ($statusLower === 'failed');

        if ($isPaid) {
            $dbStatus = strtolower($payment['payment_status'] ?? '');
            if (!in_array($dbStatus, \Env::QRIS_STATUS_SUCCESS)) {
                $db->update('subscription_payments', [
                    'payment_status' => 'success',
                ], ['payment_ref' => $payment_ref]);

                $salon_id = $payment['salon_id'];

                $db->update('subscriptions', [
                    'status' => 'active',
                    'end_date' => $payment['period_end'],
                    'last_payment_date' => date('Y-m-d'),
                    'last_payment_amount' => $payment['amount'],
                    'payment_ref' => $payment_ref,
                ], ['salon_id' => $salon_id]);

                $this->log("OK: Salon Sub PAID ref=$payment_ref salon=$salon_id");
            }
        } elseif ($isExpired) {
            $db->update('subscription_payments', [
                'payment_status' => 'failed',
            ], ['payment_ref' => $payment_ref]);
            $this->log("OK: Salon Sub EXPIRED ref=$payment_ref");
        } elseif ($isFailed) {
            $db->update('subscription_payments', [
                'payment_status' => 'failed',
            ], ['payment_ref' => $payment_ref]);
            $this->log("OK: Salon Sub FAILED ref=$payment_ref");
        }
    }

    public function handleInvoicePayment($payment_ref, $status_gateway): void
    {
        $db = $this->controller->db(6);

        if (!$db) {
            $this->log('Err: DB Invoice Not Found');
            return;
        }

        $payment = $db->get_where('invoice_payments', ['payment_ref' => $payment_ref])->row_array();

        if (!$payment) {
            $this->log("Err: Invoice Pmt Not Found ref=$payment_ref");
            return;
        }

        $statusLower = strtolower($status_gateway);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);
        $isExpired = in_array($statusLower, \Env::QRIS_STATUS_EXPIRED);

        if ($isPaid) {
            if ($payment['payment_status'] !== 'success') {
                $now = date('Y-m-d H:i:s');
                $db->update('invoice_payments', [
                    'payment_status' => 'success',
                    'paid_at' => $now,
                ], ['payment_ref' => $payment_ref]);

                $db->update('invoices', [
                    'payment_status' => 'paid',
                    'status' => 'paid',
                ], ['id' => $payment['invoice_id']]);

                $this->log("OK: Invoice PAID ref=$payment_ref inv={$payment['invoice_id']}");
            }
        } elseif ($isExpired) {
            $db->update('invoice_payments', [
                'payment_status' => 'expired',
            ], ['payment_ref' => $payment_ref]);

            $db->update('invoices', [
                'payment_status' => 'unpaid',
            ], ['id' => $payment['invoice_id']]);

            $this->log("OK: Invoice EXPIRED ref=$payment_ref");
        }
    }

    public function handleKasLaundry($reff_id, $status): void
    {
        $tokopay_trx_id = $reff_id;

        $ref_finance_extracted = $reff_id;
        if (strpos($reff_id, '_') !== false) {
            $parts = explode('_', $reff_id);
            array_pop($parts);
            $ref_finance_extracted = implode('_', $parts);
        }

        $db_kas = $this->controller->db(1);

        if (!$db_kas) {
            $this->log('Err: DB 1');
            echo json_encode(['status' => false, 'message' => 'DB Error']);
            return;
        }

        $statusLower = strtolower($status);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);
        $isExpired = in_array($statusLower, \Env::QRIS_STATUS_EXPIRED);
        $isFailed = ($statusLower === 'failed');

        $update_kas = $db_kas->update('kas', ['payment_state' => $status], ['payment_trx_id' => $tokopay_trx_id]);
        $affected = $db_kas->affected_rows();

        if (!$update_kas || $affected == 0) {
            $update_kas = $db_kas->update('kas', ['payment_state' => $status], ['ref_finance' => $ref_finance_extracted]);
            $affected = $db_kas->affected_rows();

            if (!$update_kas || $affected == 0) {
                $this->log("Err: Kas payment_state Update Failed trx=$tokopay_trx_id ref=$ref_finance_extracted status=$status (affected=0)");
            } else {
                $this->log("OK: Kas payment_state Updated by ref_finance=$ref_finance_extracted status=$status");
            }
        } else {
            $this->log("OK: Kas payment_state Updated by payment_trx_id=$tokopay_trx_id status=$status");
        }

        if ($isPaid) {
            $cek_kas = $db_kas->get_where('kas', ['payment_trx_id' => $tokopay_trx_id])->row();

            if (!$cek_kas) {
                $cek_kas = $db_kas->get_where('kas', ['ref_finance' => $ref_finance_extracted])->row();
                if ($cek_kas) {
                    $db_kas->update('kas', [
                        'payment_trx_id' => $tokopay_trx_id,
                        'payment_state' => 'paid',
                    ], ['ref_finance' => $ref_finance_extracted]);
                    $this->log("OK: Restored payment_trx_id=$tokopay_trx_id for ref=$ref_finance_extracted");
                }
            }

            if (!$cek_kas) {
                $this->log("Err: Kas Not Found (ORPHAN PAID) trx=$tokopay_trx_id ref=$ref_finance_extracted — kas mungkin sudah dibatalkan/dihapus sebelum webhook");
                echo json_encode(['status' => false, 'message' => 'Kas Not Found']);
                return;
            }

            $ref_finance = $cek_kas->ref_finance;

            $update = $db_kas->update('kas', ['status_mutasi' => 3, 'payment_state' => 'paid'], ['ref_finance' => $ref_finance]);

            if (!$update) {
                $this->log("Err: Upd Kas ref=$ref_finance");
            } else {
                $this->log("OK: Kas PAID ref=$ref_finance");
                $this->notifyQRServer($cek_kas);
            }

            $this->activateInstantKurirIfNeeded($db_kas, $cek_kas);
        } elseif ($isExpired || $isFailed) {
            $kasExp = $db_kas->get_where('kas', ['payment_trx_id' => $tokopay_trx_id])->row();
            if (!$kasExp) {
                $kasExp = $db_kas->get_where('kas', ['ref_finance' => $ref_finance_extracted])->row();
            }
            if ($kasExp) {
                $this->cancelInstantKurirIfNeeded($db_kas, $kasExp);
            }

            $db_kas->query('DELETE FROM kas WHERE payment_trx_id = ? AND status_mutasi != 3', [$tokopay_trx_id]);
            $affected = $db_kas->affected_rows();
            $this->log("OK: Kas EXPIRED trx=$tokopay_trx_id deleted=$affected");
        } else {
            $this->log("Warn: Unknown status=$status trx=$tokopay_trx_id");
        }

        echo json_encode(['status' => true, 'message' => 'Success']);
    }

    public function handleRestoKas($reff_id, $status): void
    {
        $db = $this->controller->db(2);

        if (!$db) {
            $this->log('Err: DB Resto Not Found');
            return;
        }

        $statusLower = strtolower($status);
        $isPaid = in_array($statusLower, \Env::QRIS_STATUS_SUCCESS);

        if (!$isPaid) {
            $this->log("RESTOKAS: Status not paid ($status) - $reff_id");
            return;
        }

        $kas = $db->get_where_row('kas', "payment_trx_id = '" . $reff_id . "'");

        if (!$kas) {
            $this->log("RESTOKAS: Kas not found - $reff_id");
            return;
        }

        if ($kas['status_mutasi'] == 1 && $kas['payment_state'] == 'paid') {
            $this->log("RESTOKAS: Already paid - $reff_id");
            return;
        }

        $update = $db->update('kas', "status_mutasi = 1, payment_state = 'paid'", 'id = ' . $kas['id']);

        if ($update['errno'] != 0) {
            $this->log('RESTOKAS: Update failed - ' . $update['error']);
            return;
        }

        $this->log('RESTOKAS: Paid OK - ' . $reff_id . ', Ref: ' . $kas['ref']);

        $ref = $kas['ref'];

        $order = $db->get_where('pesanan', "ref = '" . $ref . "'");
        $total_tagihan = 0;
        foreach ($order as $o) {
            $subTotal = ($o['harga'] * $o['qty']) - ($o['diskon'] ?? 0);
            $total_tagihan += $subTotal;
        }

        $payments = $db->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $ref . "'");
        $total_dibayar = 0;
        $total_verified = 0;
        $has_pending = false;

        foreach ($payments as $p) {
            $total_dibayar += $p['jumlah'];
            if ($p['status_mutasi'] == 1) {
                $total_verified += $p['jumlah'];
            } else {
                $has_pending = true;
            }
        }

        if ($total_dibayar >= $total_tagihan) {
            if ($total_verified >= $total_tagihan && !$has_pending) {
                $db->update('ref', 'step = 1', "id = '" . $ref . "'");
                $this->log("RESTOKAS: Order closed - $ref");
            } else {
                $db->update('ref', 'step = 4', "id = '" . $ref . "'");
                $this->log("RESTOKAS: Order pending check - $ref");
            }
        }
    }

    private function log(string $message): void
    {
        \Log::write($message, 'webhook', $this->logChannel);
    }

    private function notifyQRServer($cek_kas): void
    {
        try {
            $qrString = isset($cek_kas->payment_qr_string) ? $cek_kas->payment_qr_string : '';

            if ($cek_kas && !empty($qrString)) {
                $kasirId = $cek_kas->id_cabang;

                $url = 'https://qrs.nalju.com/payment-success';
                $postData = [
                    'kasir_id' => (string) $kasirId,
                    'qr_string' => $qrString,
                    'status' => true,
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);

                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                    $this->log("Err: QRS Push $httpCode kasir=$kasirId");
                }
            }
        } catch (\Exception $ex) {
            $this->log('Err: QRS Exc ' . $ex->getMessage());
        }
    }

    private function activateInstantKurirIfNeeded($db, $kas): void
    {
        set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        try {
            if (!class_exists('\\App\\Helpers\\Laundry\\InstantKurir')) {
                require_once __DIR__ . '/../Laundry/InstantKurir.php';
            }
            $jt = is_array($kas)
                ? (int) ($kas['jenis_transaksi'] ?? 0)
                : (int) ($kas->jenis_transaksi ?? 0);
            if ($jt !== \App\Helpers\Laundry\InstantKurir::JENIS_TRANSAKSI) {
                return;
            }
            $result = \App\Helpers\Laundry\InstantKurir::activateAfterPayment($db, $kas);
            $this->log('Instant activate: ' . json_encode($result));
        } catch (\Throwable $e) {
            $this->log(
                'Instant activate err: ' . $e->getMessage()
                . ' @' . basename($e->getFile()) . ':' . $e->getLine()
            );
        } finally {
            restore_error_handler();
        }
    }

    private function cancelInstantKurirIfNeeded($db, $kas): void
    {
        try {
            if (!class_exists('\\App\\Helpers\\Laundry\\InstantKurir')) {
                require_once __DIR__ . '/../Laundry/InstantKurir.php';
            }
            \App\Helpers\Laundry\InstantKurir::cancelUnpaidByKas($db, $kas);
        } catch (\Throwable $e) {
            $this->log('Instant cancel err: ' . $e->getMessage());
        }
    }
}
