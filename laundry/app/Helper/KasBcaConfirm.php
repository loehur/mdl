<?php

/**
 * Side effect setelah kas BCA dikonfirmasi (manual / bind).
 */
class KasBcaConfirm
{
    /**
     * @param object $dbLaundry db(0)
     * @param object $dbMain db(100) — wa_conversations
     */
    public static function afterApprove($dbLaundry, $dbMain, string $refFinance, array $kasRows): bool
    {
        $refFinance = trim($refFinance);
        if ($refFinance === '' || empty($kasRows)) {
            return false;
        }

        $seenRef = [];
        foreach ($kasRows as $kasRow) {
            $jt = (int) ($kasRow['jenis_transaksi'] ?? 0);
            $refTransaksi = trim((string) ($kasRow['ref_transaksi'] ?? ''));

            if ($jt === 7 && $refTransaksi !== '' && !isset($seenRef[$refTransaksi])) {
                $seenRef[$refTransaksi] = true;
                self::updateSalesState($dbLaundry, $refTransaksi);
            }
        }

        self::resetWaPaymentPriority($dbLaundry, $dbMain, $kasRows[0]);
        self::pushWebSocketPriorityReset($dbLaundry, $kasRows[0]);

        return true;
    }

    /**
     * @param object $dbLaundry
     */
    private static function updateSalesState($dbLaundry, string $refTransaksi): void
    {
        $refEsc = $dbLaundry->escape($refTransaksi);
        $payments = $dbLaundry->get_where('kas', "ref_transaksi = '$refEsc' AND jenis_transaksi = 7");
        if (!is_array($payments) || empty($payments)) {
            return;
        }

        $items = $dbLaundry->get_where('barang_mutasi', "ref = '$refEsc'");
        if (!is_array($items) || empty($items)) {
            return;
        }

        $totalTagihan = 0.0;
        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0);
            $margin = (float) ($item['margin'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            $totalTagihan += ($price + $margin) * $qty;
        }

        $totalBayar = 0.0;
        $allPaid = true;
        foreach ($payments as $p) {
            $totalBayar += (float) ($p['jumlah'] ?? 0);
            if ((int) ($p['status_mutasi'] ?? 0) !== 3) {
                $allPaid = false;
            }
        }

        if ($totalTagihan > 0 && $allPaid && $totalBayar >= $totalTagihan) {
            $dbLaundry->update('barang_mutasi', ['state' => 1], "ref = '$refEsc'");
        }
    }

    /**
     * @param array<string,mixed> $kasRow
     */
    private static function resetWaPaymentPriority($dbLaundry, $dbMain, array $kasRow): void
    {
        $idClient = (int) ($kasRow['id_client'] ?? 0);
        if ($idClient < 1) {
            return;
        }

        try {
            $pelanggan = $dbLaundry->get_where_row('pelanggan', "id_pelanggan = '$idClient'");
            if (!$pelanggan || empty($pelanggan['nomor_pelanggan'])) {
                return;
            }

            require_once 'app/Helper/PelangganByPhone.php';
            $nomor = PelangganByPhone::key($pelanggan['nomor_pelanggan']);
            if ($nomor === '') {
                return;
            }

            $dbMain->query(
                'UPDATE wa_conversations SET priority = 0 WHERE priority = 2 AND '
                . PelangganByPhone::likeSql($dbMain->escape($nomor), 'wa_number')
            );
        } catch (\Throwable $e) {
            error_log('[KasBcaConfirm] WA priority: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $kasRow
     */
    private static function pushWebSocketPriorityReset($dbLaundry, array $kasRow): void
    {
        $idClient = (int) ($kasRow['id_client'] ?? 0);
        if ($idClient < 1) {
            return;
        }

        try {
            $pelanggan = $dbLaundry->get_where_row('pelanggan', "id_pelanggan = '$idClient'");
            if (!$pelanggan || empty($pelanggan['nomor_pelanggan'])) {
                return;
            }

            require_once 'app/Helper/PelangganByPhone.php';
            $nomor = PelangganByPhone::key($pelanggan['nomor_pelanggan']);
            if ($nomor === '') {
                return;
            }

            $payload = [
                'type' => 'priority_updated',
                'phone' => '62' . $nomor,
                'priority' => 0,
                'target_id' => '0',
                'sender_id' => 'system',
            ];

            $ch = curl_init('http://127.0.0.1:3003/incoming');
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            // non-blocking
        }
    }
}
