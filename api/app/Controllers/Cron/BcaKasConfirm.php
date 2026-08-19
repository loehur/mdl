<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Helpers\BcaMutasiMatcher;
use App\Helpers\BcaScrapper;
use App\Helpers\Laundry\KasNonTunaiConfirm;

/**
 * Konfirmasi otomatis kas BCA pending jika mutasi CR cocok ditemukan.
 * Tidak scrape kecuali ada kas pending BCA.
 *
 * URL:
 * /Cron/BcaKasConfirm/index?secret=YOUR_CRON_SECRET
 */
class BcaKasConfirm extends Controller
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
            $dbMain->query('SELECT 1 FROM bca_mutasi LIMIT 1');
            $dbMain->query('SELECT 1 FROM bca_mutasi_link LIMIT 1');
        } catch (\Throwable $e) {
            echo "ERROR: Tabel bca_mutasi / bca_mutasi_link belum ada. Jalankan migration main.\n";
            return;
        }

        $pendingRows = $dbLaundry->query(
            "SELECT ref_finance,
                    SUM(jumlah) AS total,
                    MIN(insertTime) AS insertTime,
                    MAX(note) AS note
             FROM kas
             WHERE metode_mutasi = 2
               AND status_mutasi = 2
               AND UPPER(IFNULL(note, '')) = 'BCA'
               AND ref_finance <> ''
             GROUP BY ref_finance
             ORDER BY insertTime ASC
             LIMIT 30"
        )->result_array();

        $pendingCount = is_array($pendingRows) ? count($pendingRows) : 0;
        echo 'BcaKasConfirm run at ' . date('Y-m-d H:i:s') . "\n";

        if ($pendingCount === 0) {
            echo "SKIP: no pending BCA kas\n";
            return;
        }

        echo "Pending BCA kas: {$pendingCount}\n";

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

            $match = BcaMutasiMatcher::matchAndBindForKas($dbMain, $row);
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
                echo "SKIP {$refFinance}: mutasi CR nominal {$row['total']} tidak ditemukan";
                if (!empty($match['range_start']) && !empty($match['range_end'])) {
                    echo " ({$match['range_start']}..{$match['range_end']})";
                }
                echo "\n";
                continue;
            }

            $stats['matched']++;
            $mutasiId = (int) ($match['mutasi_id'] ?? 0);

            $confirm = KasNonTunaiConfirm::approveBcaTransfer($dbLaundry, $refFinance, $crmDb);
            if (empty($confirm['ok'])) {
                BcaMutasiMatcher::unbindEntity(
                    $dbMain,
                    BcaScrapper::ENTITY_KAS_LAUNDRY,
                    $refFinance
                );
                $stats['errors']++;
                echo "ERR {$refFinance}: konfirmasi kas gagal — " . ($confirm['message'] ?? '') . " (link dibatalkan)\n";
                continue;
            }

            $stats['confirmed']++;
            echo "OK {$refFinance}: mutasi#{$mutasiId} nominal {$match['nominal']} range {$match['range_start']}..{$match['range_end']}\n";
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
     * CRM db untuk wa_conversations — best effort (db(0) di setup API).
     *
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
