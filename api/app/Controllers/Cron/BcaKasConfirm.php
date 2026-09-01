<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Helpers\Beauty_Salon\SalonBcaConfirm;
use App\Helpers\BcaMutasiMatcher;
use App\Helpers\BcaScrapper;
use App\Helpers\Invoice\InvoiceBcaConfirm;
use App\Helpers\WaDesk\DevFeeBcaConfirm;
use App\Helpers\Laundry\KasMismatchNotify;
use App\Helpers\Laundry\KasNonTunaiConfirm;
use App\Helpers\Payment\BcaMutasiUnbind;
use App\Helpers\Payment\BcaUniqueNominal;

/**
 * Konfirmasi otomatis transfer BCA pending:
 * - kas laundry (± Rp 1.000)
 * - invoice project (± Rp 1.000)
 * - beauty salon subscription (± Rp 1.000)
 * - WaDesk Dev Fee top-up (nominal BCA unik)
 *
 * URL: /Cron/BcaKasConfirm/index?secret=YOUR_CRON_SECRET
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
        $dbSalon = $this->db(4);
        $dbInvoice = $this->db(6);
        $dbWadesk = $this->db(7);

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

        echo 'BcaKasConfirm run at ' . date('Y-m-d H:i:s') . "\n";

        $expired = BcaUniqueNominal::expireStalePending($dbInvoice, $dbSalon);
        if (($expired['invoice'] ?? 0) > 0 || ($expired['salon'] ?? 0) > 0) {
            echo sprintf(
                "EXPIRE stale BCA pending: invoice=%d salon=%d\n",
                (int) ($expired['invoice'] ?? 0),
                (int) ($expired['salon'] ?? 0)
            );
        }

        $crmDb = $this->resolveCrmDb();

        $kasStats = $this->processKasLaundry($dbMain, $dbLaundry, $crmDb);
        $invoiceStats = $this->processInvoiceBca($dbMain, $dbInvoice);
        $salonStats = $this->processSalonBca($dbMain, $dbSalon);
        $devFeeStats = $this->processWadeskDevFeeBca($dbMain, $dbWadesk);

        echo sprintf(
            "\nDone kas: checked=%d matched=%d confirmed=%d scraped=%d skipped=%d errors=%d\n",
            $kasStats['checked'],
            $kasStats['matched'],
            $kasStats['confirmed'],
            $kasStats['scraped'],
            $kasStats['skipped'],
            $kasStats['errors']
        );
        echo sprintf(
            "Done invoice: checked=%d matched=%d confirmed=%d scraped=%d skipped=%d errors=%d\n",
            $invoiceStats['checked'],
            $invoiceStats['matched'],
            $invoiceStats['confirmed'],
            $invoiceStats['scraped'],
            $invoiceStats['skipped'],
            $invoiceStats['errors']
        );
        echo sprintf(
            "Done salon: checked=%d matched=%d confirmed=%d scraped=%d skipped=%d errors=%d\n",
            $salonStats['checked'],
            $salonStats['matched'],
            $salonStats['confirmed'],
            $salonStats['scraped'],
            $salonStats['skipped'],
            $salonStats['errors']
        );
        echo sprintf(
            "Done Dev Fee: checked=%d matched=%d confirmed=%d scraped=%d skipped=%d errors=%d\n",
            $devFeeStats['checked'], $devFeeStats['matched'], $devFeeStats['confirmed'],
            $devFeeStats['scraped'], $devFeeStats['skipped'], $devFeeStats['errors']
        );
    }

    /**
     * @return array{checked:int,matched:int,confirmed:int,scraped:int,skipped:int,errors:int}
     */
    private function processKasLaundry($dbMain, $dbLaundry, $crmDb): array
    {
        $stats = [
            'checked' => 0,
            'matched' => 0,
            'confirmed' => 0,
            'scraped' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $pendingRows = $dbLaundry->query(
            "SELECT ref_finance,
                    SUM(jumlah) AS total,
                    MIN(insertTime) AS insertTime,
                    MAX(note) AS note,
                    MAX(jenis_transaksi) AS jenis_transaksi,
                    MAX(id_client) AS id_client,
                    MAX(id_cabang) AS id_cabang
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
        echo "Pending BCA kas: {$pendingCount}\n";

        if ($pendingCount === 0) {
            return $stats;
        }

        foreach ($pendingRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stats['checked']++;
            $refFinance = trim((string) ($row['ref_finance'] ?? ''));

            $match = BcaMutasiMatcher::matchAndBindForKas($dbMain, $row);
            if (empty($match['ok'])) {
                $stats['errors']++;
                echo "ERR [Kas] {$refFinance}: " . ($match['message'] ?? 'match_failed') . "\n";
                continue;
            }

            if (!empty($match['scraped'])) {
                $stats['scraped']++;
            }

            if (empty($match['matched'])) {
                $stats['skipped']++;
                echo "SKIP [Kas] {$refFinance}: mutasi CR nominal {$row['total']} tidak ditemukan";
                if (!empty($match['range_start']) && !empty($match['range_end'])) {
                    echo " ({$match['range_start']}..{$match['range_end']})";
                }
                echo "\n";
                KasMismatchNotify::send($dbLaundry, $row, 'BCA');
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
                echo "ERR [Kas] {$refFinance}: konfirmasi gagal — " . ($confirm['message'] ?? '') . " (link dibatalkan)\n";
                continue;
            }

            $stats['confirmed']++;
            $jenisLabel = ((int) ($row['jenis_transaksi'] ?? 0) === 2) ? 'Penarikan' : 'Bayar';
            echo "OK [Kas][{$jenisLabel}] {$refFinance}: mutasi#{$mutasiId} nominal {$match['nominal']} range {$match['range_start']}..{$match['range_end']}\n";
        }

        return $stats;
    }

    /**
     * @return array{checked:int,matched:int,confirmed:int,scraped:int,skipped:int,errors:int}
     */
    private function processInvoiceBca($dbMain, $dbInvoice): array
    {
        $stats = [
            'checked' => 0,
            'matched' => 0,
            'confirmed' => 0,
            'scraped' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if (!$dbInvoice) {
            echo "SKIP invoice: db unavailable\n";
            return $stats;
        }

        try {
            $pendingRows = $dbInvoice->query(
                "SELECT payment_ref,
                        amount AS total,
                        created_at AS insertTime
                 FROM invoice_payments
                 WHERE payment_method = 'bca'
                   AND payment_status = 'pending'
                 ORDER BY created_at ASC
                 LIMIT 30"
            )->result_array();
        } catch (\Throwable $e) {
            echo "SKIP invoice: " . $e->getMessage() . "\n";
            return $stats;
        }

        $pendingCount = is_array($pendingRows) ? count($pendingRows) : 0;
        echo "Pending BCA invoice: {$pendingCount}\n";

        foreach ($pendingRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stats['checked']++;
            $paymentRef = trim((string) ($row['payment_ref'] ?? ''));

            if (BcaMutasiUnbind::isBindBlocked($dbMain, BcaScrapper::ENTITY_INVOICE, $paymentRef, $dbInvoice)) {
                $stats['skipped']++;
                $invNo = BcaMutasiUnbind::resolveInvoiceNumber($dbInvoice, $paymentRef);
                echo "SKIP [Invoice] {$paymentRef}: diblokir admin"
                    . ($invNo !== '' ? " ({$invNo})" : '')
                    . "\n";
                continue;
            }

            $match = BcaMutasiMatcher::matchAndBindForEntity(
                $dbMain,
                $row,
                BcaScrapper::ENTITY_INVOICE,
                $paymentRef,
                false
            );

            if (empty($match['ok'])) {
                $stats['errors']++;
                echo "ERR [Invoice] {$paymentRef}: " . ($match['message'] ?? 'match_failed') . "\n";
                continue;
            }

            if (!empty($match['scraped'])) {
                $stats['scraped']++;
            }

            if (empty($match['matched'])) {
                $stats['skipped']++;
                echo "SKIP [Invoice] {$paymentRef}: mutasi CR nominal {$row['total']} (±1000) tidak ditemukan";
                if (!empty($match['range_start']) && !empty($match['range_end'])) {
                    echo " ({$match['range_start']}..{$match['range_end']})";
                }
                echo "\n";
                continue;
            }

            $stats['matched']++;
            $mutasiId = (int) ($match['mutasi_id'] ?? 0);

            $confirm = InvoiceBcaConfirm::approve($dbInvoice, $paymentRef);
            if (empty($confirm['ok'])) {
                BcaMutasiMatcher::unbindEntity(
                    $dbMain,
                    BcaScrapper::ENTITY_INVOICE,
                    $paymentRef
                );
                $stats['errors']++;
                echo "ERR [Invoice] {$paymentRef}: konfirmasi gagal — " . ($confirm['message'] ?? '') . " (link dibatalkan)\n";
                continue;
            }

            $stats['confirmed']++;
            echo "OK [Invoice] {$paymentRef}: mutasi#{$mutasiId} nominal {$match['nominal']} range {$match['range_start']}..{$match['range_end']}\n";
        }

        return $stats;
    }

    /**
     * @return array{checked:int,matched:int,confirmed:int,scraped:int,skipped:int,errors:int}
     */
    private function processSalonBca($dbMain, $dbSalon): array
    {
        $stats = [
            'checked' => 0,
            'matched' => 0,
            'confirmed' => 0,
            'scraped' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if (!$dbSalon) {
            echo "SKIP salon: db unavailable\n";
            return $stats;
        }

        try {
            $pendingRows = $dbSalon->query(
                "SELECT payment_ref,
                        amount AS total,
                        created_at AS insertTime
                 FROM subscription_payments
                 WHERE payment_method = 'bca'
                   AND payment_status = 'pending'
                 ORDER BY created_at ASC
                 LIMIT 30"
            )->result_array();
        } catch (\Throwable $e) {
            echo "SKIP salon: " . $e->getMessage() . "\n";
            return $stats;
        }

        $pendingCount = is_array($pendingRows) ? count($pendingRows) : 0;
        echo "Pending BCA salon: {$pendingCount}\n";

        foreach ($pendingRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stats['checked']++;
            $paymentRef = trim((string) ($row['payment_ref'] ?? ''));

            if (BcaMutasiUnbind::isBindBlocked($dbMain, BcaScrapper::ENTITY_SALON_SUBSCRIPTION, $paymentRef, null, $dbSalon)) {
                $stats['skipped']++;
                $salonId = BcaMutasiUnbind::resolveSalonId($dbSalon, $paymentRef);
                echo "SKIP [Salon] {$paymentRef}: diblokir admin"
                    . ($salonId !== '' ? " (salon#{$salonId})" : '')
                    . "\n";
                continue;
            }

            $match = BcaMutasiMatcher::matchAndBindForEntity(
                $dbMain,
                $row,
                BcaScrapper::ENTITY_SALON_SUBSCRIPTION,
                $paymentRef,
                false
            );

            if (empty($match['ok'])) {
                $stats['errors']++;
                echo "ERR [Salon] {$paymentRef}: " . ($match['message'] ?? 'match_failed') . "\n";
                continue;
            }

            if (!empty($match['scraped'])) {
                $stats['scraped']++;
            }

            if (empty($match['matched'])) {
                $stats['skipped']++;
                echo "SKIP [Salon] {$paymentRef}: mutasi CR nominal {$row['total']} (±1000) tidak ditemukan";
                if (!empty($match['range_start']) && !empty($match['range_end'])) {
                    echo " ({$match['range_start']}..{$match['range_end']})";
                }
                echo "\n";
                continue;
            }

            $stats['matched']++;
            $mutasiId = (int) ($match['mutasi_id'] ?? 0);

            $confirm = SalonBcaConfirm::approve($dbSalon, $paymentRef);
            if (empty($confirm['ok'])) {
                BcaMutasiMatcher::unbindEntity(
                    $dbMain,
                    BcaScrapper::ENTITY_SALON_SUBSCRIPTION,
                    $paymentRef
                );
                $stats['errors']++;
                echo "ERR [Salon] {$paymentRef}: konfirmasi gagal — " . ($confirm['message'] ?? '') . " (link dibatalkan)\n";
                continue;
            }

            $stats['confirmed']++;
            echo "OK [Salon] {$paymentRef}: mutasi#{$mutasiId} nominal {$match['nominal']} range {$match['range_start']}..{$match['range_end']}\n";
        }

        return $stats;
    }

    /** @return array{checked:int,matched:int,confirmed:int,scraped:int,skipped:int,errors:int} */
    private function processWadeskDevFeeBca($dbMain, $dbWadesk): array
    {
        $stats = ['checked' => 0, 'matched' => 0, 'confirmed' => 0, 'scraped' => 0, 'skipped' => 0, 'errors' => 0];
        if (!$dbWadesk) {
            echo "SKIP Dev Fee: db unavailable\n";
            return $stats;
        }
        try {
            $dbWadesk->query(
                "UPDATE wa_tenant_dev_fee_payments SET payment_status = 'expired'
                 WHERE payment_status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            $rows = $dbWadesk->query(
                "SELECT payment_ref, amount AS total, created_at AS insertTime
                 FROM wa_tenant_dev_fee_payments
                 WHERE payment_method = 'bca' AND payment_status = 'pending'
                 ORDER BY created_at ASC LIMIT 30"
            )->result_array();
        } catch (\Throwable $e) {
            echo "SKIP Dev Fee: " . $e->getMessage() . "\n";
            return $stats;
        }
        echo 'Pending BCA Dev Fee: ' . count($rows) . "\n";
        foreach ($rows as $row) {
            $stats['checked']++;
            $ref = trim((string) ($row['payment_ref'] ?? ''));
            $match = BcaMutasiMatcher::matchAndBindForEntity($dbMain, $row, BcaScrapper::ENTITY_WADESK_DEV_FEE, $ref, true);
            if (empty($match['ok'])) {
                $stats['errors']++;
                echo "ERR [Dev Fee] {$ref}: " . ($match['message'] ?? 'match_failed') . "\n";
                continue;
            }
            if (!empty($match['scraped'])) $stats['scraped']++;
            if (empty($match['matched'])) {
                $stats['skipped']++;
                echo "SKIP [Dev Fee] {$ref}: mutasi CR nominal {$row['total']} tidak ditemukan\n";
                continue;
            }
            $stats['matched']++;
            $confirm = DevFeeBcaConfirm::approve($dbWadesk, $ref);
            if (empty($confirm['ok'])) {
                BcaMutasiMatcher::unbindEntity($dbMain, BcaScrapper::ENTITY_WADESK_DEV_FEE, $ref);
                $stats['errors']++;
                echo "ERR [Dev Fee] {$ref}: " . ($confirm['message'] ?? 'konfirmasi gagal') . "\n";
                continue;
            }
            $stats['confirmed']++;
            echo "OK [Dev Fee] {$ref}: +{$confirm['quota_added']} quota\n";
        }
        return $stats;
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
