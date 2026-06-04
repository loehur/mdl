<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Models\Tokopay;

/**
 * CleanKas Controller
 * Proses kas QRIS pending yang sudah > 1 jam (semua id_user).
 * Hapus hanya jika: id_user = 0 dan payment_qr_string kosong.
 * Cek Tokopay jika sudah ada payment_trx_id:
 *   paid → status_mutasi=3, payment_state=status tokopay
 *   expired/failed/cancel → status_mutasi=4 (gagal, QR sudah tidak valid)
 *   unpaid/pending → tidak ubah kas (pelanggan masih bisa bayar)
 * Log setiap cek ke kas_qris_cleanup_log (upsert by ref_finance).
 */
class CleanKas extends Controller
{
    public function index()
    {
        $db = $this->db(1); // kas berada di db(1) - mdl_laundry

        if (!$db) {
            header('Content-Type: text/plain');
            echo "ERROR: Database connection failed\n";
            return;
        }

        $whereBase = "UPPER(note) = 'QRIS'"
            . " AND metode_mutasi = 2"
            . " AND status_mutasi = 2"
            . " AND insertTime < DATE_SUB(NOW(), INTERVAL 1 HOUR)";

        $whereDelete = $whereBase
            . " AND id_user = 0"
            . " AND (payment_qr_string IS NULL OR payment_qr_string = '')";

        $whereTokopay = $whereBase
            . " AND payment_trx_id IS NOT NULL"
            . " AND payment_trx_id <> ''";

        $stats = [
            'checked' => 0,
            'deleted' => 0,
            'paid' => 0,
            'failed' => 0,
            'skipped_pending' => 0,
            'errors' => 0,
            'logged' => 0,
        ];

        try {
            $stats['checked'] = (int) ($db->query("SELECT COUNT(*) as cnt FROM kas WHERE $whereBase")->result_array()[0]['cnt'] ?? 0);

            $stats['deleted'] = (int) ($db->query("SELECT COUNT(*) as cnt FROM kas WHERE $whereDelete")->result_array()[0]['cnt'] ?? 0);
            $db->query("DELETE FROM kas WHERE $whereDelete");

            $rows = $db->query(
                "SELECT ref_finance, payment_trx_id, jumlah, id_client, MIN(insertTime) AS insertTime"
                . " FROM kas WHERE $whereTokopay"
                . " GROUP BY ref_finance, payment_trx_id, jumlah, id_client"
            )->result_array();

            $tokopay = new Tokopay();
            $processed = [];

            foreach ($rows as $row) {
                $refFinance = trim($row['ref_finance'] ?? '');
                if ($refFinance === '' || isset($processed[$refFinance])) {
                    continue;
                }
                $processed[$refFinance] = true;

                $trxId = trim($row['payment_trx_id'] ?? '');
                $nominal = (int) ($row['jumlah'] ?? 0);
                if ($trxId === '' || $nominal <= 0) {
                    $stats['errors']++;
                    continue;
                }

                $result = $this->applyTokopayStatus($db, $tokopay, $row, $stats);
                if (isset($stats[$result])) {
                    $stats[$result]++;
                }
            }

            $output = sprintf(
                "OK: CleanKas checked=%d deleted=%d paid=%d failed=%d skipped_pending=%d logged=%d errors=%d\n",
                $stats['checked'],
                $stats['deleted'],
                $stats['paid'],
                $stats['failed'],
                $stats['skipped_pending'],
                $stats['logged'],
                $stats['errors']
            );
        } catch (\Exception $e) {
            $output = "ERROR: " . $e->getMessage() . "\n";
        }

        header('Content-Type: text/plain');
        echo $output;
    }

    /**
     * @return string paid|failed|skipped_pending|errors
     */
    private function applyTokopayStatus($db, Tokopay $tokopay, array $row, array &$stats)
    {
        $refFinance = trim($row['ref_finance'] ?? '');
        $trxId = trim($row['payment_trx_id'] ?? '');
        $nominal = (int) ($row['jumlah'] ?? 0);
        $idClient = (int) ($row['id_client'] ?? 0);
        $insertTime = $row['insertTime'] ?? date('Y-m-d H:i:s');

        if ($refFinance === '' || $trxId === '' || $nominal <= 0) {
            return 'errors';
        }

        $response = $tokopay->checkStatus($trxId, $nominal, 'QRIS');
        $data = json_decode($response, true);

        if (!is_array($data) || (isset($data['status']) && $data['status'] === false)) {
            $rawData = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : [];
            if ($this->upsertCleanupLog($db, $refFinance, $insertTime, 'error', $idClient, $rawData)) {
                $stats['logged']++;
            }
            return 'errors';
        }

        $statusTrx = $this->parseTokopayStatus($data);
        $bucket = $this->classifyStatus($statusTrx);
        $rawData = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];

        if (!$this->upsertCleanupLog($db, $refFinance, $insertTime, $statusTrx, $idClient, $rawData)) {
            return 'errors';
        }
        $stats['logged']++;

        if ($bucket === 'pending') {
            return 'skipped_pending';
        }

        $update = [
            'status_mutasi' => ($bucket === 'paid') ? 3 : 4,
            'payment_state' => $statusTrx,
        ];

        $ok = $db->update('kas', $update, [
            'ref_finance' => $refFinance,
            'status_mutasi' => 2,
        ]);

        if (!$ok) {
            return 'errors';
        }

        return $bucket === 'paid' ? 'paid' : 'failed';
    }

    /**
     * Upsert log per ref_finance. jumlah diambil dari data.total_bayar respons Tokopay.
     */
    private function upsertCleanupLog($db, $refFinance, $insertTime, $state, $idClient, $rawData)
    {
        $rawDataArray = is_array($rawData) ? $rawData : [];
        $jumlah = isset($rawDataArray['total_bayar']) ? (int) $rawDataArray['total_bayar'] : 0;

        $rawJson = is_string($rawData)
            ? $rawData
            : json_encode($rawDataArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($rawJson === false) {
            $rawJson = '{}';
        }

        $sql = "INSERT INTO kas_qris_cleanup_log (ref_finance, `date`, state, jumlah, id_client, raw_json)"
            . " VALUES (?, ?, ?, ?, ?, ?)"
            . " ON DUPLICATE KEY UPDATE"
            . " `date` = VALUES(`date`),"
            . " state = VALUES(state),"
            . " jumlah = VALUES(jumlah),"
            . " id_client = VALUES(id_client),"
            . " raw_json = VALUES(raw_json)";

        return (bool) $db->query($sql, [
            $refFinance,
            $insertTime,
            (string) $state,
            (int) $jumlah,
            (int) $idClient,
            $rawJson,
        ]);
    }

    private function parseTokopayStatus(array $data)
    {
        $statusTrx = '';

        // Prioritas: data.status (Unpaid/Paid/Expired) — jangan tertimpa status_pembayaran "pending"
        if (isset($data['data']) && is_array($data['data'])) {
            if (!empty($data['data']['status']) && is_string($data['data']['status'])) {
                $statusTrx = strtolower(trim($data['data']['status']));
            } elseif (!empty($data['data']['status_pembayaran'])) {
                $statusTrx = strtolower(trim($data['data']['status_pembayaran']));
            } elseif (!empty($data['data']['status_detail'])) {
                $statusTrx = strtolower(trim($data['data']['status_detail']));
            }
        }

        if ($statusTrx === '') {
            if (!empty($data['trx_status'])) {
                $statusTrx = strtolower(trim($data['trx_status']));
            } elseif (!empty($data['status_pembayaran'])) {
                $statusTrx = strtolower(trim($data['status_pembayaran']));
            } elseif (!empty($data['status_detail'])) {
                $statusTrx = strtolower(trim($data['status_detail']));
            } elseif (!empty($data['payment_status'])) {
                $statusTrx = strtolower(trim($data['payment_status']));
            }
        }

        // trx_status unpaid lebih spesifik daripada payment_status pending (format API wrapper)
        $trxStatus = isset($data['trx_status']) ? strtolower(trim((string) $data['trx_status'])) : '';
        if ($trxStatus === 'unpaid' && ($statusTrx === '' || $statusTrx === 'pending')) {
            $statusTrx = 'unpaid';
        }

        if ($statusTrx === '') {
            $statusTrx = 'pending';
        }

        return $statusTrx;
    }

    /**
     * @return string paid|failed|pending
     */
    private function classifyStatus($statusTrx)
    {
        if (in_array($statusTrx, \Env::QRIS_STATUS_SUCCESS, true)) {
            return 'paid';
        }

        // Expired/failed/cancel = terminal, mark gagal
        if (in_array($statusTrx, \Env::QRIS_STATUS_EXPIRED, true)) {
            return 'failed';
        }

        // Unpaid = belum bayar, masih bisa lunas nanti — jangan ubah kas
        if ($statusTrx === 'unpaid' || $statusTrx === 'pending' || $statusTrx === 'not_found') {
            return 'pending';
        }

        return 'failed';
    }
}
