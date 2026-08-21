<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\BcaScrapper;

/**
 * Transaksi QRIS merchant BCA (QRMS) — sync ke DB + baca dari DB.
 *
 * GET|POST /Laundry/QrisMerchant/transactions
 *   ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
 *
 * Alur: cek bca_qris_hari → pangkas hari sudah sync → scrape sisanya → return dari DB.
 */
class QrisMerchant extends Controller
{
    public function transactions()
    {
        header('Content-Type: application/json; charset=utf-8');

        $body = $this->isPost() ? $this->getBody() : [];
        $startDate = trim((string) ($body['start_date'] ?? $_GET['start_date'] ?? ''));
        $endDate = trim((string) ($body['end_date'] ?? $_GET['end_date'] ?? ''));

        $window = BcaScrapper::qrisScrapeWindow();
        if ($startDate === '') {
            $startDate = $window['start'];
        }
        if ($endDate === '') {
            $endDate = $window['end'];
        }

        $clamped = BcaScrapper::clampQrisDateRange($startDate, $endDate);
        if (empty($clamped['valid'])) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'error' => (string) ($clamped['reason'] ?? 'invalid_date'),
                'message' => 'Rentang tanggal tidak valid (max kemarin–hari ini, 2 hari, lookback max kemarin)',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $db = $this->db(0);
        if (!$db) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Database mdl_main tidak tersedia'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sync = BcaScrapper::syncQrisTransactions($db, (string) $clamped['start'], (string) $clamped['end']);
        if (empty($sync['ok'])) {
            http_response_code(502);
            echo json_encode([
                'ok' => false,
                'error' => (string) ($sync['error'] ?? 'sync_failed'),
                'message' => (string) ($sync['message'] ?? $sync['error'] ?? 'Gagal sync transaksi QRIS'),
                'sync' => $sync,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = BcaScrapper::getQrisRowsFromDb($db, (string) $clamped['start'], (string) $clamped['end']);

        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'start_date' => (string) $clamped['start'],
            'end_date' => (string) $clamped['end'],
            'count' => count($rows),
            'transactions' => $rows,
            'sync' => [
                'skipped_scrape' => !empty($sync['skipped_scrape']),
                'fetched' => (int) ($sync['fetched'] ?? 0),
                'inserted' => (int) ($sync['inserted'] ?? 0),
                'skipped_dup' => (int) ($sync['skipped_dup'] ?? 0),
                'fetch_start' => (string) ($sync['start'] ?? ''),
                'fetch_end' => (string) ($sync['end'] ?? ''),
                'trimmed' => !empty($sync['trimmed']),
                'method' => (string) ($sync['method'] ?? ''),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
