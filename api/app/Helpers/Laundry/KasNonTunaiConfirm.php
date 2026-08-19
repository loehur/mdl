<?php

namespace App\Helpers\Laundry;

/**
 * Konfirmasi pembayaran non-tunai (BCA auto-match) + side effect aman.
 */
class KasNonTunaiConfirm
{
    /**
     * Setujui kas BCA pending setelah mutasi terikat.
     *
     * @param object $laundryDb api db(1) mdl_laundry
     * @param object|null $crmDb db yang punya wa_conversations (opsional)
     * @return array{ok:bool,message?:string,updated?:int}
     */
    public static function approveBcaTransfer($laundryDb, string $refFinance, $crmDb = null): array
    {
        $refFinance = trim($refFinance);
        if ($refFinance === '') {
            return ['ok' => false, 'message' => 'ref_finance kosong'];
        }

        $rows = $laundryDb->query(
            'SELECT * FROM kas
             WHERE ref_finance = ?
               AND status_mutasi = 2
               AND metode_mutasi = 2
               AND UPPER(IFNULL(note, \'\')) = ?
             LIMIT 20',
            [$refFinance, 'BCA']
        )->result_array();

        if (!is_array($rows) || empty($rows)) {
            return ['ok' => false, 'message' => 'kas pending BCA tidak ditemukan'];
        }

        foreach ($rows as $kas) {
            if (strtoupper(trim((string) ($kas['note'] ?? ''))) === 'QRIS') {
                return ['ok' => false, 'message' => 'bukan BCA transfer'];
            }
        }

        $updated = $laundryDb->update('kas', ['status_mutasi' => 3], [
            'ref_finance' => $refFinance,
            'status_mutasi' => 2,
            'metode_mutasi' => 2,
        ]);
        $affected = $laundryDb->affected_rows();

        if (!$updated || $affected < 1) {
            return ['ok' => false, 'message' => 'update kas gagal atau sudah diproses'];
        }

        $freshRows = $laundryDb->query(
            'SELECT * FROM kas WHERE ref_finance = ? AND status_mutasi = 3 LIMIT 20',
            [$refFinance]
        )->result_array();

        if (!is_array($freshRows)) {
            $freshRows = $rows;
        }

        $seenRef = [];
        foreach ($freshRows as $kasRow) {
            $jt = (int) ($kasRow['jenis_transaksi'] ?? 0);
            $refTransaksi = trim((string) ($kasRow['ref_transaksi'] ?? ''));

            if ($jt === 7 && $refTransaksi !== '' && !isset($seenRef[$refTransaksi])) {
                $seenRef[$refTransaksi] = true;
                self::updateSalesState($laundryDb, $refTransaksi);
            }
        }

        foreach ($freshRows as $kasRow) {
            if ((int) ($kasRow['jenis_transaksi'] ?? 0) === InstantKurir::JENIS_TRANSAKSI) {
                try {
                    $result = InstantKurir::activateAfterPayment($laundryDb, $kasRow);
                    if (class_exists('\\Log', false)) {
                        \Log::write(
                            'BcaKasConfirm Instant ref=' . $refFinance . ' ' . json_encode($result),
                            'cron',
                            'BcaKasConfirm'
                        );
                    }
                } catch (\Throwable $e) {
                    if (class_exists('\\Log', false)) {
                        \Log::write(
                            'BcaKasConfirm Instant err ref=' . $refFinance . ' ' . $e->getMessage(),
                            'cron',
                            'BcaKasConfirm'
                        );
                    }
                }
                break;
            }
        }

        self::resetWaPaymentPriority($laundryDb, $crmDb, $freshRows[0] ?? $rows[0]);
        self::pushWebSocketPriorityReset($freshRows[0] ?? $rows[0]);

        return [
            'ok' => true,
            'updated' => $affected,
            'ref_finance' => $refFinance,
        ];
    }

    /**
     * Sales jt=7: tandai barang_mutasi lunas jika total bayar cukup & semua kas paid.
     */
    public static function updateSalesState($laundryDb, string $refTransaksi): void
    {
        $refTransaksi = trim($refTransaksi);
        if ($refTransaksi === '') {
            return;
        }

        $payments = $laundryDb->query(
            'SELECT jumlah, status_mutasi FROM kas WHERE ref_transaksi = ? AND jenis_transaksi = 7',
            [$refTransaksi]
        )->result_array();

        if (!is_array($payments) || empty($payments)) {
            return;
        }

        $items = $laundryDb->query(
            'SELECT price, margin, qty FROM barang_mutasi WHERE ref = ?',
            [$refTransaksi]
        )->result_array();

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
            $laundryDb->update('barang_mutasi', ['state' => 1], ['ref' => $refTransaksi]);
        }
    }

    /**
     * @param array<string,mixed> $kasRow
     */
    private static function resetWaPaymentPriority($laundryDb, $crmDb, array $kasRow): void
    {
        if ($crmDb === null) {
            return;
        }

        $idClient = (int) ($kasRow['id_client'] ?? 0);
        if ($idClient < 1) {
            return;
        }

        try {
            $pelanggan = $laundryDb->query(
                'SELECT nomor_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idClient]
            )->row_array();

            $phone = trim((string) ($pelanggan['nomor_pelanggan'] ?? ''));
            if ($phone === '') {
                return;
            }

            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (!is_string($digits) || strlen($digits) < 8) {
                return;
            }

            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            } elseif (str_starts_with($digits, '62') && strlen($digits) >= 11) {
                $digits = substr($digits, 2);
            }

            $like = '%' . $digits;
            $crmDb->query(
                'UPDATE wa_conversations SET priority = 0 WHERE priority = 2 AND wa_number LIKE ?',
                [$like]
            );
        } catch (\Throwable $e) {
            if (class_exists('\\Log', false)) {
                \Log::write('BcaKasConfirm WA priority skip: ' . $e->getMessage(), 'cron', 'BcaKasConfirm');
            }
        }
    }

    /**
     * @param array<string,mixed> $kasRow
     */
    private static function pushWebSocketPriorityReset(array $kasRow): void
    {
        $idClient = (int) ($kasRow['id_client'] ?? 0);
        if ($idClient < 1) {
            return;
        }

        try {
            if (!class_exists('\\App\\Core\\DB', false)) {
                return;
            }
            $laundryDb = \App\Core\DB::getInstance(1);
            $pelanggan = $laundryDb->query(
                'SELECT nomor_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idClient]
            )->row_array();

            $phone = trim((string) ($pelanggan['nomor_pelanggan'] ?? ''));
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (!is_string($digits) || strlen($digits) < 8) {
                return;
            }
            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            } elseif (str_starts_with($digits, '62') && strlen($digits) >= 11) {
                $digits = substr($digits, 2);
            }

            $phonePlus62 = '62' . $digits;
            $payload = [
                'type' => 'priority_updated',
                'phone' => $phonePlus62,
                'priority' => 0,
                'target_id' => '0',
                'sender_id' => 'system',
            ];

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return;
            }

            $ch = curl_init('http://127.0.0.1:3003/incoming');
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            // non-blocking
        }
    }
}
