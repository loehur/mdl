<?php

namespace App\Helpers\Payment;

use App\Helpers\BcaQrisMatcher;
use App\Helpers\BcaScrapper;
use App\Helpers\Beauty_Salon\SalonBcaConfirm;
use App\Helpers\Laundry\KasNonTunaiConfirm;

/** Konfirmasi semua reservasi QRIS lokal dari transaksi QRIS merchant BCA. */
class QrisLocalConfirm
{
    public static function confirmPending($mainDb, $laundryDb, $invoiceDb, $salonDb, $crmDb = null, int $limit = 100): array
    {
        $stats = ['checked' => 0, 'matched' => 0, 'confirmed' => 0, 'errors' => 0, 'details' => []];
        try {
            $rows = $mainDb->query("SELECT entity_ref, amount FROM qris_nominal_reservations WHERE active_key = 1 AND state = 'pending' AND expires_at >= NOW() ORDER BY created_at ASC LIMIT " . (int) $limit)->result_array();
        } catch (\Throwable $e) {
            // Migration belum dijalankan: cron legacy tetap boleh melanjutkan.
            return ['checked' => 0, 'matched' => 0, 'confirmed' => 0, 'errors' => 1, 'details' => ['ERROR reservasi QRIS tidak dapat dibaca: ' . $e->getMessage()]];
        }
        foreach ((array) $rows as $row) {
            $stats['checked']++; $ref = trim((string) ($row['entity_ref'] ?? '')); $amount = (int) ($row['amount'] ?? 0);
            if ($ref === '' || $amount < 1) { $stats['errors']++; $stats['details'][] = 'ERROR reservasi tidak valid'; continue; }
            $type = self::entityType($ref);
            // Bind dapat dibuat lebih dahulu melalui Admin Approval. Tetap
            // jalankan efek bisnisnya; jangan menunggu transaksi menjadi unlinked.
            $existing = $mainDb->query(
                'SELECT bca_qris_id FROM bca_qris_link WHERE entity_type = ? AND entity_ref = ? LIMIT 1',
                [$type, $ref]
            )->row_array();
            if (is_array($existing) && !empty($existing['bca_qris_id'])) {
                if (!self::applyBusinessPayment($ref, $laundryDb, $invoiceDb, $salonDb, $crmDb)) { $stats['errors']++; $stats['details'][] = "ERROR {$ref}: bind ada, tetapi kas/payment pending tidak dapat dikonfirmasi"; continue; }
                $mainDb->update('qris_nominal_reservations', ['state' => 'paid', 'active_key' => null], ['entity_ref' => $ref, 'active_key' => 1]);
                $stats['confirmed']++; $stats['details'][] = "OK {$ref}: bind QRIS #{$existing['bca_qris_id']} dikonfirmasi"; continue;
            }
            $qris = $mainDb->query("SELECT t.id, t.nominal FROM bca_qris_transaksi t LEFT JOIN bca_qris_link l ON l.bca_qris_id = t.id WHERE l.id IS NULL AND t.nominal = ? ORDER BY t.tanggal ASC, t.waktu ASC LIMIT 1", [$amount])->row_array();
            if (!is_array($qris) || empty($qris['id'])) { $stats['details'][] = "WAIT {$ref}: mutasi QRIS exact Rp{$amount} belum tersedia/unlinked"; continue; }
            $stats['matched']++;
            if (!BcaQrisMatcher::bindQris($mainDb, (int) $qris['id'], $type, $ref, $amount, $qris['nominal'])) { $stats['errors']++; $stats['details'][] = "ERROR {$ref}: gagal bind QRIS #{$qris['id']}"; continue; }
            if (!self::applyBusinessPayment($ref, $laundryDb, $invoiceDb, $salonDb, $crmDb)) { BcaQrisMatcher::unbindEntity($mainDb, $type, $ref); $stats['errors']++; $stats['details'][] = "ERROR {$ref}: kas/payment pending tidak dapat dikonfirmasi"; continue; }
            $mainDb->update('qris_nominal_reservations', ['state' => 'paid', 'active_key' => null], ['entity_ref' => $ref, 'active_key' => 1]); $stats['confirmed']++; $stats['details'][] = "OK {$ref}: QRIS #{$qris['id']} dikonfirmasi";
        }
        return $stats;
    }
    private static function entityType(string $ref): string { if (strpos($ref, 'MDLINV_') === 0) return BcaScrapper::ENTITY_INVOICE; if (strpos($ref, 'SALONSUB_') === 0) return BcaScrapper::ENTITY_SALON_SUBSCRIPTION; return BcaScrapper::ENTITY_KAS_LAUNDRY; }
    private static function applyBusinessPayment(string $ref, $laundryDb, $invoiceDb, $salonDb, $crmDb): bool
    {
        if (strpos($ref, 'MDLINV_') === 0) {
            // QR yang telah dibuat tetap dapat dibayar selama reservasi aktif.
            // Status failed/expired dapat berasal dari pembatalan UI, bukan dari
            // kegagalan pembayaran; transaksi QRIS exact yang sudah masuk tetap
            // harus dicatat lunas.
            $p = $invoiceDb->query("SELECT invoice_id FROM invoice_payments WHERE payment_ref = ? AND payment_status IN ('pending', 'failed', 'expired') LIMIT 1", [$ref])->row_array(); if (!is_array($p)) return false;
            $invoiceDb->update('invoice_payments', ['payment_status' => 'success', 'paid_at' => date('Y-m-d H:i:s')], ['payment_ref' => $ref]); $invoiceDb->update('invoices', ['payment_status' => 'paid', 'status' => 'paid'], ['id' => (int) $p['invoice_id']]); return true;
        }
        if (strpos($ref, 'SALONSUB_') === 0) {
            // Sama seperti Invoice: pembatalan UI tidak membatalkan uang yang
            // sudah benar-benar diterima oleh QRIS selama reservasi masih aktif.
            $p = $salonDb->query("SELECT * FROM subscription_payments WHERE payment_ref = ? AND payment_status IN ('pending', 'failed', 'expired') LIMIT 1", [$ref])->row_array();
            if (!is_array($p)) return false;
            SalonBcaConfirm::activatePayment($salonDb, $p);
            return true;
        }
        return !empty(KasNonTunaiConfirm::approveQrisMerchant($laundryDb, $ref, $crmDb)['ok']);
    }
}
