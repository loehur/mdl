<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Helpers\BcaScrapper;
use App\Helpers\BcaMutasiMatcher;
use App\Helpers\BcaQrisMatcher;
use App\Helpers\Laundry\KasUnmatchedNotification;

class KasUnmatchedNotify extends Controller
{
    private const MIN_PENDING_MINUTES = 15;
    private const STALE_CLAIM_MINUTES = 15;
    private const LIMIT = 50;

    public function index()
    {
        if (!$this->verifyCronSecret()) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(401);
            echo "ERROR: Unauthorized\n";
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        $dbLaundry = $this->db(1);
        $dbMain = $this->db(0);
        if (!$dbLaundry || !$dbMain) {
            echo "ERROR: Database connection failed\n";
            return;
        }

        $deleteStats = $this->deleteAnonymousUnmatched($dbLaundry, $dbMain);
        $checked = 0;
        $claimed = 0;
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $rows = $this->loadCandidates($dbLaundry);

        echo 'KasUnmatchedNotify run at ' . date('Y-m-d H:i:s') . "\n";
        echo sprintf(
            "Anonymous delete: checked=%d deleted_refs=%d deleted_rows=%d skipped_linked=%d skipped_mixed=%d skipped_matched=%d errors=%d\n",
            $deleteStats['checked'],
            $deleteStats['deleted_refs'],
            $deleteStats['deleted_rows'],
            $deleteStats['skipped_linked'],
            $deleteStats['skipped_mixed'],
            $deleteStats['skipped_matched'],
            $deleteStats['errors']
        );
        echo 'Candidates: ' . count($rows) . "\n";

        if ($deleteStats['deleted_refs'] > 0) {
            $rows = $this->loadCandidates($dbLaundry);
        }

        foreach ($rows as $row) {
            $checked++;
            $method = strtoupper(trim((string) ($row['method'] ?? '')));
            $refFinance = trim((string) ($row['ref_finance'] ?? ''));
            if ($method === '' || $refFinance === '' || (int) ($row['id_user'] ?? 0) === 0) {
                $skipped++;
                continue;
            }

            if (!$this->stillUnmatched($dbMain, $row)) {
                $skipped++;
                continue;
            }

            if (!$this->claim($dbLaundry, $method, $refFinance)) {
                $skipped++;
                continue;
            }
            $claimed++;

            $result = KasUnmatchedNotification::send($dbLaundry, $row);
            if (!empty($result['skipped'])) {
                $this->markFailed($dbLaundry, $method, $refFinance, 'skipped');
                $skipped++;
            } elseif (!empty($result['ok'])) {
                $this->markSent($dbLaundry, $method, $refFinance);
                $sent++;
            } else {
                $this->markFailed($dbLaundry, $method, $refFinance, (string) ($result['error'] ?? 'send_failed'));
                $failed++;
            }
        }

        echo sprintf("Done. checked=%d claimed=%d sent=%d skipped=%d failed=%d\n", $checked, $claimed, $sent, $skipped, $failed);
    }

    private function deleteAnonymousUnmatched($dbLaundry, $dbMain): array
    {
        $stats = ['checked' => 0, 'deleted_refs' => 0, 'deleted_rows' => 0, 'skipped_linked' => 0, 'skipped_mixed' => 0, 'skipped_matched' => 0, 'errors' => 0];
        $groups = $dbLaundry->query(
            "SELECT ref_finance, UPPER(MAX(note)) AS method
             FROM kas
             WHERE metode_mutasi = 2 AND status_mutasi = 2 AND id_user = 0
               AND UPPER(IFNULL(note, '')) IN ('BCA', 'QRIS')
               AND ref_finance <> ''
               AND insertTime <= DATE_SUB(NOW(), INTERVAL " . self::MIN_PENDING_MINUTES . " MINUTE)
             GROUP BY ref_finance
             ORDER BY MIN(insertTime) ASC
             LIMIT " . self::LIMIT
        )->result_array();

        foreach ($groups as $group) {
            $stats['checked']++;
            $ref = trim((string) ($group['ref_finance'] ?? ''));
            $method = strtoupper(trim((string) ($group['method'] ?? '')));
            try {
                $rows = $dbLaundry->query(
                    "SELECT id_kas, id_user, metode_mutasi, status_mutasi, note, insertTime, jumlah
                     FROM kas WHERE ref_finance = ?",
                    [$ref]
                )->result_array();
                if (!$rows || !$this->anonymousGroupIsSafe($rows, $method)) {
                    $stats['skipped_mixed']++;
                    continue;
                }
                if ($this->hasKasPaymentLink($dbMain, $ref)) {
                    $stats['skipped_linked']++;
                    continue;
                }
                $candidate = [
                    'method' => $method,
                    'ref_finance' => $ref,
                    'total' => array_sum(array_map(static fn(array $r): float => (float) ($r['jumlah'] ?? 0), $rows)),
                    'insertTime' => $rows[0]['insertTime'] ?? '',
                ];
                if (!$this->stillUnmatched($dbMain, $candidate)) {
                    $stats['skipped_matched']++;
                    continue;
                }
                $where = ['ref_finance' => $ref, 'metode_mutasi' => 2, 'status_mutasi' => 2, 'id_user' => 0, 'note' => $method];
                if (!$dbLaundry->delete('kas', $where)) {
                    $stats['errors']++;
                    continue;
                }
                $deleted = $dbLaundry->affected_rows();
                if ($deleted > 0) {
                    $dbMain->query('DELETE FROM wh_midtrans WHERE ref_id = ?', [$ref]);
                    $stats['deleted_refs']++;
                    $stats['deleted_rows'] += $deleted;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }
        return $stats;
    }

    private function anonymousGroupIsSafe(array $rows, string $method): bool
    {
        if (!in_array($method, ['BCA', 'QRIS'], true) || $rows === []) return false;
        foreach ($rows as $row) {
            if ((int) ($row['id_user'] ?? -1) !== 0 || (int) ($row['metode_mutasi'] ?? 0) !== 2 || (int) ($row['status_mutasi'] ?? 0) !== 2 || strtoupper(trim((string) ($row['note'] ?? ''))) !== $method || strtotime((string) ($row['insertTime'] ?? '')) > time() - (self::MIN_PENDING_MINUTES * 60)) return false;
        }
        return true;
    }

    private function hasKasPaymentLink($dbMain, string $refFinance): bool
    {
        $bca = $dbMain->query('SELECT id FROM bca_mutasi_link WHERE entity_type = ? AND entity_ref = ? LIMIT 1', [BcaScrapper::ENTITY_KAS_LAUNDRY, $refFinance])->row_array();
        if (!empty($bca['id'])) return true;
        $qris = $dbMain->query('SELECT id FROM bca_qris_link WHERE entity_type = ? AND entity_ref = ? LIMIT 1', [BcaScrapper::ENTITY_KAS_LAUNDRY, $refFinance])->row_array();
        return !empty($qris['id']);
    }

    private function loadCandidates($db): array
    {
        $rows = $db->query(
            "SELECT method, ref_finance, MAX(id_cabang) AS id_cabang,
                    MAX(id_user) AS id_user, MAX(id_client) AS id_client,
                    MAX(jenis_transaksi) AS jenis_transaksi, SUM(jumlah) AS total,
                    MIN(insertTime) AS insertTime
             FROM (
                 SELECT 'BCA' AS method, ref_finance, id_cabang, id_user, id_client, jenis_transaksi, jumlah, insertTime
                 FROM kas
                 WHERE metode_mutasi = 2 AND status_mutasi = 2
                   AND UPPER(IFNULL(note, '')) = 'BCA'
                   AND ref_finance <> ''
                   AND insertTime <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 UNION ALL
                 SELECT 'QRIS' AS method, ref_finance, id_cabang, id_user, id_client, jenis_transaksi, jumlah, insertTime
                 FROM kas
                 WHERE metode_mutasi = 2 AND status_mutasi = 2
                   AND UPPER(IFNULL(note, '')) = 'QRIS'
                   AND ref_finance <> ''
                   AND insertTime <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ) pending
             GROUP BY method, ref_finance
             ORDER BY MIN(insertTime) ASC
             LIMIT " . self::LIMIT,
            [self::MIN_PENDING_MINUTES, self::MIN_PENDING_MINUTES]
        )->result_array();

        return is_array($rows) ? $rows : [];
    }

    private function stillUnmatched($dbMain, array $row): bool
    {
        $method = strtoupper((string) ($row['method'] ?? ''));
        $nominal = BcaScrapper::formatNominal($row['total'] ?? 0);
        if ($method === 'BCA') {
            $range = BcaScrapper::computeKasMutasiRange((string) ($row['insertTime'] ?? ''));
            return BcaMutasiMatcher::findUnlinkedMatch(
                $dbMain,
                $nominal,
                $range['start'],
                $range['end'],
                false,
                (string) ($row['insertTime'] ?? '')
            ) === null;
        }

        $range = BcaScrapper::qrisScrapeWindow();
        return BcaQrisMatcher::findUnlinkedMatch(
            $dbMain,
            $nominal,
            $range['start'],
            $range['end'],
            (string) ($row['insertTime'] ?? '')
        ) === null;
    }

    private function claim($db, string $method, string $refFinance): bool
    {
        $db->query(
            "INSERT IGNORE INTO kas_unmatched_notifications
                (method, ref_finance, status, claimed_at, attempts)
             VALUES (?, ?, 'processing', NOW(), 1)",
            [$method, $refFinance]
        );
        if ($db->affected_rows() > 0) {
            return true;
        }

        $db->query(
            "UPDATE kas_unmatched_notifications
             SET status = 'processing', claimed_at = NOW(), attempts = attempts + 1, last_error = NULL
             WHERE method = ? AND ref_finance = ?
               AND (
                 status = 'failed'
                 OR (status = 'processing' AND claimed_at < DATE_SUB(NOW(), INTERVAL " . self::STALE_CLAIM_MINUTES . " MINUTE))
               )",
            [$method, $refFinance]
        );
        return $db->affected_rows() > 0;
    }

    private function markSent($db, string $method, string $refFinance): void
    {
        $db->update('kas_unmatched_notifications', ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'last_error' => null], ['method' => $method, 'ref_finance' => $refFinance]);
    }

    private function markFailed($db, string $method, string $refFinance, string $error): void
    {
        $db->update('kas_unmatched_notifications', ['status' => 'failed', 'last_error' => substr($error, 0, 255)], ['method' => $method, 'ref_finance' => $refFinance]);
    }

    protected function verifyCronSecret(): bool
    {
        $expected = class_exists('Env') && defined('Env::CRON_SECRET') ? (string) \Env::CRON_SECRET : (getenv('CRON_SECRET') ?: '');
        if ($expected === '') return false;
        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        return hash_equals($expected, $provided);
    }
}
