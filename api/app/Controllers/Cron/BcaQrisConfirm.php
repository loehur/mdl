<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Helpers\BcaQrisMatcher;
use App\Helpers\BcaScrapper;
use App\Helpers\Laundry\KasNonTunaiConfirm;
use App\Helpers\Payment\QrisLocalConfirm;

/**
 * Sync transaksi QRIS merchant BCA + konfirmasi kas QRIS static pending (exact, atau ± Rp 1.000).
 *
 * URL:
 * /Cron/BcaQrisConfirm/index?secret=YOUR_CRON_SECRET
 */
class BcaQrisConfirm extends Controller
{
    public function index()
    {
        if (!$this->verifyCronSecret()) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(401);
            echo "ERROR: Unauthorized\n";
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');

        $dbMain = $this->db(0);
        $dbLaundry = $this->db(1);
        if (!$dbMain || !$dbLaundry) {
            echo "ERROR: Database connection failed\n";
            return;
        }

        try {
            $dbMain->query('SELECT 1 FROM bca_qris_transaksi LIMIT 1');
            $dbMain->query('SELECT 1 FROM bca_qris_hari LIMIT 1');
            $dbMain->query('SELECT 1 FROM bca_qris_link LIMIT 1');
        } catch (\Throwable $e) {
            echo "ERROR: Tabel bca_qris belum ada. Jalankan migration 004 + 005.\n";
            return;
        }

        echo 'BcaQrisConfirm run at ' . date('Y-m-d H:i:s') . "\n";

        $scrapeWindow = BcaScrapper::qrisScrapeWindow();
        $sync = BcaScrapper::syncQrisTransactions(
            $dbMain,
            $scrapeWindow['start'],
            $scrapeWindow['end']
        );

        if (empty($sync['ok'])) {
            echo 'WARN sync: ' . ($sync['message'] ?? $sync['error'] ?? 'sync_failed') . "\n";
        } else {
            echo sprintf(
                "SYNC fetched=%d inserted=%d skipped_scrape=%s auth=%s scrape=%s..%s\n",
                (int) ($sync['fetched'] ?? 0),
                (int) ($sync['inserted'] ?? 0),
                !empty($sync['skipped_scrape']) ? 'yes' : 'no',
                !empty($sync['skipped_scrape'])
                    ? 'skip'
                    : (trim((string) ($sync['auth_method'] ?? '')) !== ''
                        ? strtolower((string) $sync['auth_method'])
                        : (!empty($sync['from_cache']) ? 'cache' : 'puppeteer')),
                (string) ($sync['start'] ?? $scrapeWindow['start']),
                (string) ($sync['end'] ?? $scrapeWindow['end'])
            );
        }

        $local = QrisLocalConfirm::confirmPending($dbMain, $dbLaundry, $this->db(6), $this->db(4), $this->resolveCrmDb());
        echo sprintf("Local QRIS checked=%d matched=%d confirmed=%d errors=%d\n", $local['checked'], $local['matched'], $local['confirmed'], $local['errors']);

        $pendingRows = $dbLaundry->query(
            "SELECT ref_finance,
                    SUM(jumlah) AS total,
                    MIN(insertTime) AS insertTime,
                    MAX(note) AS note,
                    MAX(jenis_transaksi) AS jenis_transaksi
             FROM kas
             WHERE metode_mutasi = 2
               AND status_mutasi = 2
               AND UPPER(IFNULL(note, '')) = 'QRIS'
               AND ref_finance <> ''
             GROUP BY ref_finance
             ORDER BY insertTime ASC
             LIMIT 30"
        )->result_array();

        $pendingCount = is_array($pendingRows) ? count($pendingRows) : 0;

        if ($pendingCount === 0) {
            echo "SKIP: no pending static QRIS kas\n";
            return;
        }

        echo "Pending static QRIS kas: {$pendingCount}\n";

        $stats = [
            'checked' => 0,
            'matched' => 0,
            'confirmed' => 0,
            'scraped' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $crmDb = $this->resolveCrmDb();

        foreach ($pendingRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stats['checked']++;
            $refFinance = trim((string) ($row['ref_finance'] ?? ''));

            $match = BcaQrisMatcher::matchAndBindForKas($dbMain, $row);
            if (empty($match['ok'])) {
                $stats['errors']++;
                echo "ERR {$refFinance}: " . ($match['message'] ?? 'match_failed') . "\n";
                continue;
            }

            if (!empty($match['scraped'])) {
                $stats['scraped']++;
            }

            if (empty($match['matched'])) {
                $stats['skipped']++;
                echo "SKIP {$refFinance}: transaksi QRIS nominal {$row['total']} tidak ditemukan";
                if (!empty($match['range_start']) && !empty($match['range_end'])) {
                    echo " ({$match['range_start']}..{$match['range_end']})";
                }
                echo "\n";
                continue;
            }

            $stats['matched']++;
            $qrisId = (int) ($match['qris_id'] ?? 0);

            $confirm = KasNonTunaiConfirm::approveQrisMerchant($dbLaundry, $refFinance, $crmDb);
            if (empty($confirm['ok'])) {
                BcaQrisMatcher::unbindEntity(
                    $dbMain,
                    BcaScrapper::ENTITY_KAS_LAUNDRY,
                    $refFinance
                );
                $stats['errors']++;
                echo "ERR {$refFinance}: konfirmasi kas gagal — " . ($confirm['message'] ?? '') . " (link dibatalkan)\n";
                continue;
            }

            $stats['confirmed']++;
            $rrn = (string) ($match['rrn'] ?? '');
            $jenisLabel = ((int) ($row['jenis_transaksi'] ?? 0) === 2) ? 'Penarikan' : 'Bayar';
            echo "OK [{$jenisLabel}] {$refFinance}: qris#{$qrisId} rrn={$rrn} nominal {$match['nominal']} range {$match['range_start']}..{$match['range_end']}\n";
        }

        echo sprintf(
            "\nDone. checked=%d matched=%d confirmed=%d scraped=%d skipped=%d errors=%d\n",
            $stats['checked'],
            $stats['matched'],
            $stats['confirmed'],
            $stats['scraped'],
            $stats['skipped'],
            $stats['errors']
        );
    }

    /**
     * @return object|null
     */
    private function resolveCrmDb()
    {
        try {
            $db = $this->db(0);
            $db->query('SELECT 1 FROM wa_conversations LIMIT 1');
            return $db;
        } catch (\Throwable $e) {
            return null;
        }
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
