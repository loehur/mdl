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
        $stats = ['checked' => 0, 'matched' => 0, 'confirmed' => 0, 'errors' => 0];
        try {
            $rows = $mainDb->query("SELECT entity_ref, amount FROM qris_nominal_reservations WHERE active_key = 1 AND state = 'pending' AND expires_at >= NOW() ORDER BY created_at ASC LIMIT " . (int) $limit)->result_array();
        } catch (\Throwable $e) {
            // Migration belum dijalankan: cron legacy tetap boleh melanjutkan.
            return ['checked' => 0, 'matched' => 0, 'confirmed' => 0, 'errors' => 1];
        }
        foreach ((array) $rows as $row) {
            $stats['checked']++; $ref = trim((string) ($row['entity_ref'] ?? '')); $amount = (int) ($row['amount'] ?? 0);
            if ($ref === '' || $amount < 1) { $stats['errors']++; continue; }
            $qris = $mainDb->query("SELECT t.id, t.nominal FROM bca_qris_transaksi t LEFT JOIN bca_qris_link l ON l.bca_qris_id = t.id WHERE l.id IS NULL AND t.nominal = ? ORDER BY t.tanggal ASC, t.waktu ASC LIMIT 1", [$amount])->row_array();
            if (!is_array($qris) || empty($qris['id'])) continue;
            $stats['matched']++; $type = self::entityType($ref);
            if (!BcaQrisMatcher::bindQris($mainDb, (int) $qris['id'], $type, $ref, $amount, $qris['nominal'])) { $stats['errors']++; continue; }
            if (!self::applyBusinessPayment($ref, $laundryDb, $invoiceDb, $salonDb, $crmDb)) { BcaQrisMatcher::unbindEntity($mainDb, $type, $ref); $stats['errors']++; continue; }
            $mainDb->update('qris_nominal_reservations', ['state' => 'paid', 'active_key' => null], ['entity_ref' => $ref, 'active_key' => 1]); $stats['confirmed']++;
        }
        return $stats;
    }
    private static function entityType(string $ref): string { if (strpos($ref, 'MDLINV_') === 0) return BcaScrapper::ENTITY_INVOICE; if (strpos($ref, 'SALONSUB_') === 0) return BcaScrapper::ENTITY_SALON_SUBSCRIPTION; return BcaScrapper::ENTITY_KAS_LAUNDRY; }
    private static function applyBusinessPayment(string $ref, $laundryDb, $invoiceDb, $salonDb, $crmDb): bool
    {
        if (strpos($ref, 'MDLINV_') === 0) {
            $p = $invoiceDb->query("SELECT invoice_id FROM invoice_payments WHERE payment_ref = ? AND payment_status = 'pending' LIMIT 1", [$ref])->row_array(); if (!is_array($p)) return false;
            $invoiceDb->update('invoice_payments', ['payment_status' => 'success', 'paid_at' => date('Y-m-d H:i:s')], ['payment_ref' => $ref]); $invoiceDb->update('invoices', ['payment_status' => 'paid', 'status' => 'paid'], ['id' => (int) $p['invoice_id']]); return true;
        }
        if (strpos($ref, 'SALONSUB_') === 0) { $p = $salonDb->query("SELECT * FROM subscription_payments WHERE payment_ref = ? AND payment_status = 'pending' LIMIT 1", [$ref])->row_array(); if (!is_array($p)) return false; SalonBcaConfirm::activatePayment($salonDb, $p); return true; }
        return !empty(KasNonTunaiConfirm::approveQrisMerchant($laundryDb, $ref, $crmDb)['ok']);
    }
}
