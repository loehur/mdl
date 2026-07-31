<?php

namespace App\Controllers\Cron;

use App\Core\Controller;

/**
 * Snapshot rekap laundry bulanan untuk bulan lalu — per cabang operasional (mode 2).
 * Skip diam-diam jika snapshot periode sudah ada.
 *
 * URL:
 * /Cron/RekapSnapshot/index?secret=YOUR_CRON_SECRET
 */
class RekapSnapshot extends Controller
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

        $dbLaundry = $this->db(1);
        $dbMain = $this->db(0);
        if (!$dbLaundry || !$dbMain) {
            echo "ERROR: Database connection failed\n";
            return;
        }

        $periode = date('Y-m', strtotime('first day of last month'));
        if ($periode >= date('Y-m')) {
            echo "SKIP: periode bukan bulan lalu ($periode)\n";
            return;
        }

        echo "RekapSnapshot run at " . date('Y-m-d H:i:s') . " periode=$periode\n";

        $cabangs = $this->getCabangOperasional($dbLaundry);
        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        // Hanya per cabang operasional (mode 2) — dipakai gaji jaga malam
        foreach ($cabangs as $c) {
            $idCabang = (int) ($c['id_cabang'] ?? 0);
            if ($idCabang < 1) {
                continue;
            }
            $this->processOne($dbLaundry, $dbMain, $periode, 2, $idCabang, [$c], $stats);
        }

        echo "\nDone. created={$stats['created']} skipped={$stats['skipped']} errors={$stats['errors']}\n";
    }

    private function processOne($dbLaundry, $dbMain, $periode, $mode, $idCabang, array $listCabang, array &$stats)
    {
        $label = "mode=$mode cabang=$idCabang";
        try {
            if ($this->snapshotExists($dbLaundry, $periode, $mode, $idCabang)) {
                $stats['skipped']++;
                echo "SKIP $label (sudah ada)\n";
                return;
            }

            $agg = $this->hitungRekapBulanan($dbLaundry, $dbMain, $periode, $mode, $idCabang, $listCabang);
            $payload = [
                'periode' => $periode,
                'mode' => (int) $mode,
                'id_cabang' => (int) $idCabang,
                'kas_laundry' => (int) $agg['kas_laundry'],
                'kas_member' => (int) $agg['kas_member'],
                'margin_penjualan' => (int) $agg['margin_penjualan'],
                'total_pendapatan' => (int) $agg['total_pendapatan'],
                'kas_keluar_json' => json_encode($agg['kas_keluar'], JSON_UNESCAPED_UNICODE),
                'gaji' => (int) $agg['gaji'],
                'prepost_cost' => (int) $agg['prepost_cost'],
                'barang_pakai' => (int) $agg['barang_pakai'],
                'total_pengeluaran' => (int) $agg['total_pengeluaran'],
                'laba_rugi' => (int) $agg['laba_rugi'],
                'qty_json' => json_encode($agg['qty'], JSON_UNESCAPED_UNICODE),
                'id_user' => 0,
            ];

            $insertId = $dbLaundry->insert('rekap_snapshot', $payload);
            if (!$insertId) {
                // Race / unique key: treat as skip
                if ($this->snapshotExists($dbLaundry, $periode, $mode, $idCabang)) {
                    $stats['skipped']++;
                    echo "SKIP $label (sudah ada)\n";
                    return;
                }
                $stats['errors']++;
                echo "ERR $label: insert gagal\n";
                return;
            }

            $stats['created']++;
            echo "OK $label id=$insertId laba_rugi={$payload['laba_rugi']}\n";
        } catch (\Throwable $e) {
            $stats['errors']++;
            echo "ERR $label: " . $e->getMessage() . "\n";
        }
    }

    private function snapshotExists($db, $periode, $mode, $idCabang): bool
    {
        $row = $db->query(
            "SELECT id FROM rekap_snapshot WHERE periode = ? AND mode = ? AND id_cabang = ? LIMIT 1",
            [$periode, (int) $mode, (int) $idCabang]
        )->row_array();

        return is_array($row) && !empty($row['id']);
    }

    private function getCabangOperasional($db): array
    {
        $rows = $db->query("SELECT * FROM cabang")->result_array();
        $out = [];
        foreach ($rows as $c) {
            if (!empty($c['is_training'])) {
                continue;
            }
            $out[] = $c;
        }
        return $out;
    }

    /**
     * Hitung rekap bulanan — selaras dengan laundry Rekap::hitungRekapBulananSnapshot.
     */
    private function hitungRekapBulanan($dbLaundry, $dbMain, $periode, $mode, $idCabang, array $listCabang): array
    {
        $useCabang = ((int) $mode === 2);
        $whereCabangSql = $useCabang ? 'id_cabang = ' . (int) $idCabang . ' AND ' : '';

        $this->ensureRentKas($dbLaundry, $periode, $listCabang);

        $kasSql = "SELECT jenis_transaksi, ref_transaksi, note_primary, SUM(jumlah) as total
                   FROM kas
                   WHERE {$whereCabangSql} status_mutasi <> 4 AND DATE_FORMAT(insertTime, '%Y-%m') = ?
                   GROUP BY jenis_transaksi, ref_transaksi, note_primary";
        $kasResult = $dbLaundry->query($kasSql, [$periode])->result_array();
        if (!is_array($kasResult)) {
            $kasResult = [];
        }

        $nonExpenseIds = [];
        $neRows = $dbLaundry->query("SELECT id_item_pengeluaran FROM item_pengeluaran WHERE is_expense = 0")->result_array();
        foreach ($neRows as $r) {
            $idNe = (int) ($r['id_item_pengeluaran'] ?? 0);
            if ($idNe > 0) {
                $nonExpenseIds[$idNe] = true;
            }
        }

        $kas_laundry = 0;
        $kas_member = 0;
        $kas_keluar = [];
        $rent_total = 0;

        foreach ($kasResult as $row) {
            $jenis = (int) ($row['jenis_transaksi'] ?? 0);
            switch ($jenis) {
                case 1:
                    $kas_laundry += (int) $row['total'];
                    break;
                case 3:
                    $kas_member += (int) $row['total'];
                    break;
                case 4:
                    $ref4 = (int) ($row['ref_transaksi'] ?? 0);
                    if ($ref4 > 0 && isset($nonExpenseIds[$ref4])) {
                        break;
                    }
                    $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => (int) $row['total']];
                    break;
                case 8:
                    $ref = (int) ($row['ref_transaksi'] ?? 0);
                    if ($ref > 0 && isset($nonExpenseIds[$ref])) {
                        break;
                    }
                    if ($ref === 102) {
                        $rent_total += (int) $row['total'];
                    } else {
                        $kas_keluar[] = ['note_primary' => $row['note_primary'], 'total' => (int) $row['total']];
                    }
                    break;
            }
        }

        if ($rent_total > 0) {
            $itemRent = $dbLaundry->query(
                "SELECT item_pengeluaran FROM item_pengeluaran WHERE id_item_pengeluaran = 102 LIMIT 1"
            )->row_array();
            $rent_nama = $itemRent['item_pengeluaran'] ?? 'Rekap Bulanan';
            $kas_keluar[] = ['note_primary' => $rent_nama, 'total' => (int) $rent_total];
        } elseif (!isset($nonExpenseIds[102])) {
            $total_rent = 0;
            foreach ($listCabang as $c) {
                $total_rent += (int) ($c['rent'] ?? 0);
            }
            if ($total_rent > 0) {
                $itemRent = $dbLaundry->query(
                    "SELECT item_pengeluaran FROM item_pengeluaran WHERE id_item_pengeluaran = 102 LIMIT 1"
                )->row_array();
                $rent_nama = $itemRent['item_pengeluaran'] ?? 'Rekap Bulanan';
                $kas_keluar[] = ['note_primary' => $rent_nama, 'total' => (int) $total_rent];
            }
        }

        $wherePrepost = $whereCabangSql . "tr_status = 1 AND bisnis = 'laundry' AND DATE_FORMAT(insertTime, '%Y-%m') = ?";
        $costPre = (int) ($dbMain->query(
            "SELECT COALESCE(SUM(price), 0) AS total FROM prepaid WHERE $wherePrepost",
            [$periode]
        )->row_array()['total'] ?? 0);
        $costPost = (int) ($dbMain->query(
            "SELECT COALESCE(SUM(price), 0) AS total FROM postpaid WHERE $wherePrepost",
            [$periode]
        )->row_array()['total'] ?? 0);
        $prepost_cost = $costPre + $costPost;

        if ($useCabang) {
            $gajiRow = $dbLaundry->query(
                "SELECT COALESCE(SUM(gr.jumlah), 0) AS total
                 FROM gaji_result gr
                 INNER JOIN user u ON gr.id_karyawan = u.id_user
                 WHERE gr.tipe = 1 AND gr.tgl = ? AND u.id_cabang = ?",
                [$periode, (int) $idCabang]
            )->row_array();
        } else {
            $gajiRow = $dbLaundry->query(
                "SELECT COALESCE(SUM(jumlah), 0) AS total FROM gaji_result WHERE tipe = 1 AND tgl = ?",
                [$periode]
            )->row_array();
        }
        $gaji = (int) ($gajiRow['total'] ?? 0);

        $barangWhere = "type = 3 AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $barangParams = [$periode];
        if ($useCabang) {
            $barangWhere = 'source_id = ? AND ' . $barangWhere;
            array_unshift($barangParams, (int) $idCabang);
        }
        $barang_pakai = (int) ($dbLaundry->query(
            "SELECT COALESCE(SUM(price * qty), 0) AS total FROM barang_mutasi WHERE $barangWhere",
            $barangParams
        )->row_array()['total'] ?? 0);

        $marginWhere = "type = 1 AND state = 1 AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $marginParams = [$periode];
        if ($useCabang) {
            $marginWhere = 'source_id = ? AND ' . $marginWhere;
            array_unshift($marginParams, (int) $idCabang);
        }
        $margin_penjualan = (int) round((float) ($dbLaundry->query(
            "SELECT COALESCE(SUM(margin * qty), 0) AS total FROM barang_mutasi WHERE $marginWhere",
            $marginParams
        )->row_array()['total'] ?? 0));

        $saleWhere = $whereCabangSql . "bin = 0 AND DATE_FORMAT(insertTime, '%Y-%m') = ?";
        $sales = $dbLaundry->query(
            "SELECT id_penjualan_jenis, list_layanan, qty FROM sale WHERE $saleWhere",
            [$periode]
        )->result_array();

        $qty = $this->buildQtyJson($dbLaundry, is_array($sales) ? $sales : []);

        $total_keluar = 0;
        foreach ($kas_keluar as $a) {
            $total_keluar += (int) $a['total'];
        }
        $total_keluar += $gaji + $prepost_cost + $barang_pakai;
        $total_pendapatan = (int) $kas_laundry + (int) $kas_member + (int) $margin_penjualan;

        return [
            'kas_laundry' => (int) $kas_laundry,
            'kas_member' => (int) $kas_member,
            'margin_penjualan' => (int) $margin_penjualan,
            'total_pendapatan' => $total_pendapatan,
            'kas_keluar' => $kas_keluar,
            'gaji' => $gaji,
            'prepost_cost' => $prepost_cost,
            'barang_pakai' => $barang_pakai,
            'total_pengeluaran' => $total_keluar,
            'laba_rugi' => $total_pendapatan - $total_keluar,
            'qty' => $qty,
        ];
    }

    private function ensureRentKas($db, $periode, array $listCabang): void
    {
        $idPengeluaran = 102;
        $tglPertama = $periode . '-01';
        $item = $db->query(
            "SELECT item_pengeluaran FROM item_pengeluaran WHERE id_item_pengeluaran = ? LIMIT 1",
            [$idPengeluaran]
        )->row_array();
        $jenisNama = $item['item_pengeluaran'] ?? 'Rekap Bulanan';

        foreach ($listCabang as $cabang) {
            $idCabang = (int) ($cabang['id_cabang'] ?? 0);
            $rent = (int) ($cabang['rent'] ?? 0);
            if ($idCabang < 1 || $rent <= 0) {
                continue;
            }

            $ada = (int) ($db->query(
                "SELECT COUNT(*) AS cnt FROM kas
                 WHERE jenis_transaksi = 8 AND ref_transaksi = ? AND id_cabang = ?
                   AND DATE_FORMAT(insertTime, '%Y-%m') = ?",
                [(string) $idPengeluaran, $idCabang, $periode]
            )->row_array()['cnt'] ?? 0);

            if ($ada > 0) {
                continue;
            }

            $idKas = (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
            $db->insert('kas', [
                'id_kas' => $idKas,
                'id_cabang' => $idCabang,
                'jenis_mutasi' => 2,
                'jenis_transaksi' => 8,
                'metode_mutasi' => 2,
                'note' => 'Auto dari Rekap ' . $periode . ' (rent)',
                'note_primary' => $jenisNama,
                'status_mutasi' => 3,
                'jumlah' => $rent,
                'id_user' => 0,
                'id_client' => 0,
                'ref_transaksi' => $idPengeluaran,
                'insertTime' => $tglPertama . ' 00:00:00',
            ]);
        }
    }

    private function buildQtyJson($db, array $sales): array
    {
        $rekapQty = [];
        $rekap = [];
        foreach ($sales as $a) {
            $jenisId = (int) ($a['id_penjualan_jenis'] ?? 0);
            $serLayanan = $a['list_layanan'] ?? '';
            $qty = (float) ($a['qty'] ?? 0);
            if (!isset($rekap[$jenisId][$serLayanan])) {
                $rekap[$jenisId][$serLayanan] = 0;
            }
            $rekap[$jenisId][$serLayanan] += $qty;
            if (!isset($rekapQty[$jenisId])) {
                $rekapQty[$jenisId] = 0;
            }
            $rekapQty[$jenisId] += $qty;
        }

        $penjualanMap = [];
        foreach ($db->query('SELECT * FROM penjualan_jenis')->result_array() as $b) {
            $penjualanMap[(int) $b['id_penjualan_jenis']] = $b;
        }
        $satuanMap = [];
        foreach ($db->query('SELECT * FROM satuan')->result_array() as $sa) {
            $satuanMap[(int) $sa['id_satuan']] = $sa['nama_satuan'] ?? '';
        }
        $layananMap = [];
        foreach ($db->query('SELECT * FROM layanan')->result_array() as $e) {
            $layananMap[(int) $e['id_layanan']] = $e['layanan'] ?? '';
        }

        $qtySummary = [];
        foreach ($rekapQty as $jenisId => $qty) {
            $b = $penjualanMap[(int) $jenisId] ?? null;
            $unit = '';
            $jenisNama = 'Jenis #' . $jenisId;
            if ($b) {
                $jenisNama = $b['penjualan_jenis'] ?? $jenisNama;
                $unit = $satuanMap[(int) ($b['id_satuan'] ?? 0)] ?? '';
            }
            $qtySummary[] = [
                'id_penjualan_jenis' => (int) $jenisId,
                'jenis' => $jenisNama,
                'qty' => $qty,
                'unit' => $unit,
            ];
        }

        $qtyDetail = [];
        foreach ($rekap as $jenisId => $byLayanan) {
            $b = $penjualanMap[(int) $jenisId] ?? null;
            $unit = '';
            $jenisNama = 'Jenis #' . $jenisId;
            if ($b) {
                $jenisNama = $b['penjualan_jenis'] ?? $jenisNama;
                $unit = $satuanMap[(int) ($b['id_satuan'] ?? 0)] ?? '';
            }
            foreach ($byLayanan as $serLayanan => $qty) {
                $arrLayanan = @unserialize($serLayanan);
                $layananNama = '';
                if (is_array($arrLayanan)) {
                    foreach ($arrLayanan as $d) {
                        $layananNama .= ' ' . ($layananMap[(int) $d] ?? '');
                    }
                }
                $qtyDetail[] = [
                    'id_penjualan_jenis' => (int) $jenisId,
                    'jenis' => $jenisNama,
                    'layanan' => trim($layananNama),
                    'qty' => $qty,
                    'unit' => $unit,
                ];
            }
        }

        return [
            'summary' => $qtySummary,
            'detail' => $qtyDetail,
        ];
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
