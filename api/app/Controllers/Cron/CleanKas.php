<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Helpers\Payment\QrisService;

/**
 * CleanKas Controller
 * Proses kas QRIS pending yang sudah > 5 menit (semua id_user).
 * Hapus hanya jika: id_user = 0 dan payment_qr_string kosong.
 * Cek Tokopay jika sudah ada payment_trx_id:
 *   paid → status_mutasi=3, payment_state=status tokopay
 *   expired/failed/cancel/unpaid/pending → status_mutasi=4 (gagal; >5 menit tidak di-skip)
 * Log setiap cek ke kas_qris_cleanup_log (upsert by ref_finance).
 *
 * URL example:
 * /Cron/CleanKas/index?secret=YOUR_CRON_SECRET
 */
class CleanKas extends Controller
{
    public function index()
    {
        if (!$this->verifyCronSecret()) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(401);
            echo "ERROR: Unauthorized\n";
            return;
        }

        $db = $this->db(1); // kas berada di db(1) - mdl_laundry

        if (!$db) {
            header('Content-Type: text/plain');
            echo "ERROR: Database connection failed\n";
            return;
        }

        $whereBase = "UPPER(note) = 'QRIS'"
            . " AND metode_mutasi = 2"
            . " AND status_mutasi = 2"
            . " AND insertTime < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";

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

            // Before hard-delete: batal unpaid Instant (jt=10)
            $toDelete = $db->query(
                "SELECT id_kas, ref_finance, ref_transaksi, jenis_transaksi FROM kas WHERE $whereDelete"
            )->result_array();
            if (is_array($toDelete) && !empty($toDelete)) {
                if (!class_exists('\\App\\Helpers\\Laundry\\InstantKurir')) {
                    require_once __DIR__ . '/../../Helpers/Laundry/InstantKurir.php';
                }
                foreach ($toDelete as $rowDel) {
                    \App\Helpers\Laundry\InstantKurir::cancelUnpaidByKas($db, $rowDel);
                }
            }

            $stats['deleted'] = (int) ($db->query("SELECT COUNT(*) as cnt FROM kas WHERE $whereDelete")->result_array()[0]['cnt'] ?? 0);
            $db->query("DELETE FROM kas WHERE $whereDelete");

            $rows = $db->query(
                "SELECT ref_finance, payment_trx_id, jumlah, id_client, MIN(insertTime) AS insertTime"
                . " FROM kas WHERE $whereTokopay"
                . " GROUP BY ref_finance, payment_trx_id, jumlah, id_client"
            )->result_array();

            $qris = new QrisService();
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

                $result = $this->applyQrisStatus($db, $qris, $row, $stats);
                if (isset($stats[$result])) {
                    $stats[$result]++;
                }
            }

            // Recover Instant: kas sudah lunas tapi Biteship belum dibuat
            $stats['instant_retry'] = $this->retryPaidInstantWithoutBiteship($db);

            $output = sprintf(
                "OK: CleanKas checked=%d deleted=%d paid=%d failed=%d skipped_pending=%d logged=%d errors=%d instant_retry=%d\n",
                $stats['checked'],
                $stats['deleted'],
                $stats['paid'],
                $stats['failed'],
                $stats['skipped_pending'],
                $stats['logged'],
                $stats['errors'],
                $stats['instant_retry']
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
    private function applyQrisStatus($db, QrisService $qris, array $row, array &$stats)
    {
        $refFinance = trim($row['ref_finance'] ?? '');
        $trxId = trim($row['payment_trx_id'] ?? '');
        $nominal = (int) ($row['jumlah'] ?? 0);
        $idClient = (int) ($row['id_client'] ?? 0);
        $insertTime = $row['insertTime'] ?? date('Y-m-d H:i:s');

        if ($refFinance === '' || $trxId === '' || $nominal <= 0) {
            return 'errors';
        }

        $checked = $qris->checkStatus($trxId, $nominal);

        if ($checked['connection_error'] || !is_array($checked['raw'])) {
            if ($this->upsertCleanupLog($db, $refFinance, $insertTime, 'error', $idClient, [])) {
                $stats['logged']++;
            }
            return 'errors';
        }

        $data = $checked['raw'];
        $statusTrx = $qris->parseTrxStatus($data);
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

        // Load kas for Instant kurir side-effects
        $kasRow = $db->get_where('kas', ['ref_finance' => $refFinance])->row_array();
        if ($kasRow) {
            if (!class_exists('\\App\\Helpers\\Laundry\\InstantKurir')) {
                require_once __DIR__ . '/../../Helpers/Laundry/InstantKurir.php';
            }
            if ($bucket === 'paid') {
                \App\Helpers\Laundry\InstantKurir::activateAfterPayment($db, $kasRow);
            } else {
                \App\Helpers\Laundry\InstantKurir::cancelUnpaidByKas($db, $kasRow);
            }
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

        // Unpaid/pending/not_found: query sudah filter umur > 5 menit → anggap gagal
        if ($statusTrx === 'unpaid' || $statusTrx === 'pending' || $statusTrx === 'not_found') {
            return 'failed';
        }

        return 'failed';
    }

    /**
     * Kas Instant (jt=10) sudah paid tapi delivery_request belum punya biteship_order_id.
     * Retry activateAfterPayment (idempotent).
     * @return int jumlah yang berhasil diaktifkan
     */
    private function retryPaidInstantWithoutBiteship($db): int
    {
        if (!class_exists('\\App\\Helpers\\Laundry\\InstantKurir')) {
            require_once __DIR__ . '/../../Helpers/Laundry/InstantKurir.php';
        }

        $jt = (int) \App\Helpers\Laundry\InstantKurir::JENIS_TRANSAKSI;
        $sql = "SELECT k.*"
            . " FROM kas k"
            . " INNER JOIN delivery_request d ON d.id_request = k.ref_transaksi"
            . " WHERE k.jenis_transaksi = {$jt}"
            . " AND k.status_mutasi = 3"
            . " AND UPPER(IFNULL(k.note,'')) = 'QRIS'"
            . " AND (d.biteship_order_id IS NULL OR d.biteship_order_id = '')"
            . " AND d.delivery_status IN ('menunggu_pembayaran','berjalan')"
            . " LIMIT 50";

        $rows = $db->query($sql)->result_array();
        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        $ok = 0;
        foreach ($rows as $kasRow) {
            $result = \App\Helpers\Laundry\InstantKurir::activateAfterPayment($db, $kasRow);
            if (!empty($result['ok'])) {
                $ok++;
            } else {
                \Log::write(
                    'CleanKas Instant retry fail ref=' . ($kasRow['ref_finance'] ?? '')
                    . ' msg=' . ($result['message'] ?? ''),
                    'cron',
                    'CleanKas'
                );
            }
        }
        return $ok;
    }

    protected function verifyCronSecret(): bool
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = (string) \Env::CRON_SECRET;
        }

        if ($expected === '') {
            $expected = getenv('CRON_SECRET') ?: '';
        }

        if ($expected === '') {
            return false;
        }

        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }

        return hash_equals($expected, $provided);
    }
}
