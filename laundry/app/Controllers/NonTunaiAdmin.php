<?php

/**
 * Admin — laporan binding BCA Mutasi & BCA QRIS (mdl_main).
 */
class NonTunaiAdmin extends Controller
{
    private const MAX_RANGE_DAYS = 7;

    public function __construct()
    {
        $this->operating_data();
    }

    public function bcaMutasi()
    {
        $this->session_cek(1);
        $range = $this->parseDateRange();
        $dbMain = $this->db(100);

        $rows = [];
        try {
            $dbMain->query('SELECT 1 FROM bca_mutasi_link LIMIT 1');
            $rows = $this->fetchBoundMutasi($dbMain, $range['start'], $range['end']);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $pelangganByRef = $this->loadPelangganByEntityRef($rows);

        $this->view('layout', ['data_operasi' => ['title' => 'BCA Mutasi']]);
        $this->view('non_tunai_admin/bca_mutasi', [
            'rows' => $rows,
            'pelangganByRef' => $pelangganByRef,
            'startDate' => $range['start'],
            'endDate' => $range['end'],
            'maxRangeDays' => self::MAX_RANGE_DAYS,
        ]);
    }

    public function bcaQris()
    {
        $this->session_cek(1);
        $range = $this->parseDateRange();
        $dbMain = $this->db(100);

        $rows = [];
        try {
            $dbMain->query('SELECT 1 FROM bca_qris_link LIMIT 1');
            $rows = $this->fetchBoundQris($dbMain, $range['start'], $range['end']);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $pelangganByRef = $this->loadPelangganByEntityRef($rows);

        $this->view('layout', ['data_operasi' => ['title' => 'BCA QRIS']]);
        $this->view('non_tunai_admin/bca_qris', [
            'rows' => $rows,
            'pelangganByRef' => $pelangganByRef,
            'startDate' => $range['start'],
            'endDate' => $range['end'],
            'maxRangeDays' => self::MAX_RANGE_DAYS,
        ]);
    }

    /**
     * @return array{start:string,end:string}
     */
    private function parseDateRange(): array
    {
        $today = date('Y-m-d');
        $defaultStart = date('Y-m-d', strtotime('-' . (self::MAX_RANGE_DAYS - 1) . ' days'));

        $start = trim((string) ($_GET['start'] ?? $defaultStart));
        $end = trim((string) ($_GET['end'] ?? $today));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = $defaultStart;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = $today;
        }

        if ($start > $today) {
            $start = $today;
        }
        if ($end > $today) {
            $end = $today;
        }
        if ($start > $end) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }

        $diffDays = (int) floor((strtotime($end) - strtotime($start)) / 86400);
        if ($diffDays >= self::MAX_RANGE_DAYS) {
            $end = date('Y-m-d', strtotime($start . ' +' . (self::MAX_RANGE_DAYS - 1) . ' days'));
            if ($end > $today) {
                $end = $today;
            }
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param object $dbMain
     * @return list<array<string,mixed>>
     */
    private function fetchBoundMutasi($dbMain, string $start, string $end): array
    {
        $startEsc = $dbMain->escape($start);
        $endEsc = $dbMain->escape($end);

        $rows = $dbMain->query_array(
            "SELECT
                l.id AS link_id,
                l.entity_type,
                l.entity_ref,
                l.created_at AS linked_at,
                m.id AS mutasi_id,
                m.tanggal,
                m.tanggal_iso,
                m.keterangan,
                m.nominal,
                m.mutasi,
                m.created_at AS mutasi_created_at
             FROM bca_mutasi_link l
             INNER JOIN bca_mutasi m ON m.id = l.bca_mutasi_id
             WHERE (
                (m.tanggal_iso IS NOT NULL
                 AND m.tanggal_iso >= '" . $startEsc . "'
                 AND m.tanggal_iso <= '" . $endEsc . "')
                OR (
                  UPPER(m.tanggal) = 'PEND'
                  AND DATE(m.created_at) >= '" . $startEsc . "'
                  AND DATE(m.created_at) <= '" . $endEsc . "'
                )
             )
             ORDER BY
               COALESCE(m.tanggal_iso, DATE(m.created_at)) DESC,
               l.id DESC
             LIMIT 500"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param object $dbMain
     * @return list<array<string,mixed>>
     */
    private function fetchBoundQris($dbMain, string $start, string $end): array
    {
        $startEsc = $dbMain->escape($start);
        $endEsc = $dbMain->escape($end);

        $rows = $dbMain->query_array(
            "SELECT
                l.id AS link_id,
                l.entity_type,
                l.entity_ref,
                l.created_at AS linked_at,
                t.id AS qris_id,
                t.tanggal,
                t.waktu,
                t.rrn,
                t.nominal,
                t.status,
                t.keterangan,
                t.outlet_name
             FROM bca_qris_link l
             INNER JOIN bca_qris_transaksi t ON t.id = l.bca_qris_id
             WHERE t.tanggal >= '" . $startEsc . "'
               AND t.tanggal <= '" . $endEsc . "'
             ORDER BY t.tanggal DESC, t.waktu DESC, l.id DESC
             LIMIT 500"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private function loadPelangganByEntityRef(array $rows): array
    {
        $this->helper('BcaMutasiBind');

        $refs = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['entity_type'] ?? '') !== BcaMutasiBind::ENTITY_KAS_LAUNDRY) {
                continue;
            }
            $ref = trim((string) ($row['entity_ref'] ?? ''));
            if ($ref !== '') {
                $refs[$ref] = $ref;
            }
        }

        if ($refs === []) {
            return [];
        }

        $in = implode(',', array_map(function ($ref) {
            return "'" . $this->db(0)->escape($ref) . "'";
        }, array_values($refs)));

        $kasRows = $this->db(0)->query_array(
            "SELECT ref_finance, MAX(id_client) AS id_client
             FROM kas
             WHERE ref_finance IN ($in)
             GROUP BY ref_finance"
        );

        if (!is_array($kasRows)) {
            return [];
        }

        $clientIds = [];
        $refToClient = [];
        foreach ($kasRows as $kasRow) {
            if (!is_array($kasRow)) {
                continue;
            }
            $ref = trim((string) ($kasRow['ref_finance'] ?? ''));
            $idClient = (int) ($kasRow['id_client'] ?? 0);
            if ($ref === '' || $idClient < 1) {
                continue;
            }
            $refToClient[$ref] = $idClient;
            $clientIds[$idClient] = $idClient;
        }

        if ($clientIds === []) {
            return [];
        }

        $clientIn = implode(',', array_values($clientIds));
        $pelangganMap = $this->db(0)->get_where('pelanggan', "id_pelanggan IN ($clientIn)", 'id_pelanggan');
        if (!is_array($pelangganMap)) {
            $pelangganMap = [];
        }

        $out = [];
        foreach ($refToClient as $ref => $idClient) {
            if (!isset($pelangganMap[$idClient]) || !is_array($pelangganMap[$idClient])) {
                continue;
            }
            $out[$ref] = $pelangganMap[$idClient];
        }

        return $out;
    }
}
