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
        $unboundRows = [];
        try {
            $dbMain->query('SELECT 1 FROM bca_mutasi_link LIMIT 1');
            $rows = $this->fetchBoundMutasi($dbMain, $range['start'], $range['end']);
            $unboundRows = $this->fetchUnboundMutasi($dbMain, $range['start'], $range['end']);
        } catch (\Throwable $e) {
            $rows = [];
            $unboundRows = [];
        }

        $payerByRef = $this->loadPayerByEntityRef($rows);

        $this->view('layout', ['data_operasi' => ['title' => 'BCA Mutasi']]);
        $this->view('non_tunai_admin/bca_mutasi', [
            'rows' => $rows,
            'unboundRows' => $unboundRows,
            'unboundTotalNominal' => $this->sumNominal($unboundRows),
            'payerByRef' => $payerByRef,
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
        $unboundRows = [];
        try {
            $dbMain->query('SELECT 1 FROM bca_qris_link LIMIT 1');
            $rows = $this->fetchBoundQris($dbMain, $range['start'], $range['end']);
            $unboundRows = $this->fetchUnboundQris($dbMain, $range['start'], $range['end']);
        } catch (\Throwable $e) {
            $rows = [];
            $unboundRows = [];
        }

        $payerByRef = $this->loadPayerByEntityRef($rows);

        $this->view('layout', ['data_operasi' => ['title' => 'BCA QRIS']]);
        $this->view('non_tunai_admin/bca_qris', [
            'rows' => $rows,
            'unboundRows' => $unboundRows,
            'unboundTotalNominal' => $this->sumNominal($unboundRows),
            'payerByRef' => $payerByRef,
            'startDate' => $range['start'],
            'endDate' => $range['end'],
            'maxRangeDays' => self::MAX_RANGE_DAYS,
        ]);
    }

    /**
     * POST — unbind mutasi BCA, blokir entity, kembalikan status pembayaran.
     */
    public function unbindMutasiLink()
    {
        $this->session_cek(1);

        $linkId = (int) ($_POST['link_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($linkId < 1) {
            echo json_encode(['ok' => false, 'message' => 'link_id tidak valid'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->bootstrapApi();
            $blockedBy = trim((string) ($_SESSION[URL::SESSID]['user']['nama_user'] ?? 'admin'));

            $result = \App\Helpers\Payment\BcaMutasiUnbind::execute(
                \App\Core\DB::getInstance(0),
                \App\Core\DB::getInstance(1),
                \App\Core\DB::getInstance(6),
                \App\Core\DB::getInstance(4),
                $linkId,
                $reason !== '' ? $reason : 'Unbind admin BCA Mutasi',
                $blockedBy
            );

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[NonTunaiAdmin::unbindMutasiLink] ' . $e->getMessage());
            echo json_encode([
                'ok' => false,
                'message' => 'Unbind gagal: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function bootstrapApi(): void
    {
        if (class_exists('\\App\\Core\\DB', false)) {
            return;
        }

        $apiRoot = dirname(__DIR__, 3) . '/api/app';
        require_once $apiRoot . '/Config/Env.php';
        require_once $apiRoot . '/Config/DBC.php';
        require_once $apiRoot . '/Core/DB.php';
        require_once $apiRoot . '/Helpers/BcaScrapper.php';
        require_once $apiRoot . '/Helpers/Laundry/KasNonTunaiConfirm.php';
        require_once $apiRoot . '/Helpers/Payment/BcaMutasiUnbind.php';
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
                l.bill_nominal,
                l.bind_nominal,
                l.created_at AS linked_at,
                m.id AS mutasi_id,
                m.tanggal,
                m.tanggal_iso,
                m.keterangan,
                m.nominal,
                m.mutasi AS db_cr,
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
                l.bill_nominal,
                l.bind_nominal,
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
     * @param object $dbMain
     * @return list<array<string,mixed>>
     */
    private function fetchUnboundMutasi($dbMain, string $start, string $end): array
    {
        $startEsc = $dbMain->escape($start);
        $endEsc = $dbMain->escape($end);

        $rows = $dbMain->query_array(
            "SELECT
                m.id AS mutasi_id,
                m.tanggal,
                m.tanggal_iso,
                m.keterangan,
                m.nominal,
                m.mutasi AS db_cr,
                m.created_at AS mutasi_created_at
             FROM bca_mutasi m
             LEFT JOIN bca_mutasi_link l ON l.bca_mutasi_id = m.id
             WHERE l.id IS NULL
               AND (
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
               m.id DESC
             LIMIT 500"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param object $dbMain
     * @return list<array<string,mixed>>
     */
    private function fetchUnboundQris($dbMain, string $start, string $end): array
    {
        $startEsc = $dbMain->escape($start);
        $endEsc = $dbMain->escape($end);

        $rows = $dbMain->query_array(
            "SELECT
                t.id AS qris_id,
                t.tanggal,
                t.waktu,
                t.rrn,
                t.nominal,
                t.status,
                t.keterangan,
                t.outlet_name
             FROM bca_qris_transaksi t
             LEFT JOIN bca_qris_link l ON l.bca_qris_id = t.id
             WHERE l.id IS NULL
               AND t.tanggal >= '" . $startEsc . "'
               AND t.tanggal <= '" . $endEsc . "'
             ORDER BY t.tanggal DESC, t.waktu DESC, t.id DESC
             LIMIT 500"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function sumNominal(array $rows): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sum += (float) ($row['nominal'] ?? 0);
        }

        return $sum;
    }

    /**
     * Resolve payer (pelanggan / karyawan / umum) dari kas laundry via ref_finance.
     * entity_type bind tetap kas_laundry — jenis_transaksi dibedakan di sini, bukan di link table.
     *
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{name:string,url:?string,badge:string,jenis_transaksi:int}>
     */
    private function loadPayerByEntityRef(array $rows): array
    {
        $this->helper('BcaMutasiBind');

        $kasRefs = [];
        $invoiceRefs = [];
        $salonRefs = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entityType = (string) ($row['entity_type'] ?? '');
            $ref = trim((string) ($row['entity_ref'] ?? ''));
            if ($ref === '') {
                continue;
            }

            if ($entityType === BcaMutasiBind::ENTITY_KAS_LAUNDRY) {
                $kasRefs[$ref] = $ref;
            } elseif ($entityType === 'invoice') {
                $invoiceRefs[$ref] = $ref;
            } elseif ($entityType === 'salon_subscription') {
                $salonRefs[$ref] = $ref;
            }
        }

        $out = [];

        if ($kasRefs !== []) {
            $out = array_merge($out, $this->loadKasPayerByRef($kasRefs));
        }
        if ($invoiceRefs !== []) {
            $out = array_merge($out, $this->loadInvoicePayerByRef($invoiceRefs));
        }
        if ($salonRefs !== []) {
            $out = array_merge($out, $this->loadSalonPayerByRef($salonRefs));
        }

        return $out;
    }

    /**
     * @param array<string,string> $refs
     * @return array<string,array{name:string,url:?string,badge:string,jenis_transaksi:int}>
     */
    private function loadKasPayerByRef(array $refs): array
    {
        $in = implode(',', array_map(function ($ref) {
            return "'" . $this->db(0)->escape($ref) . "'";
        }, array_values($refs)));

        $kasRows = $this->db(0)->query_array(
            "SELECT ref_finance,
                    MAX(jenis_transaksi) AS jenis_transaksi,
                    MAX(id_client) AS id_client,
                    MAX(id_user) AS id_user,
                    MAX(note_primary) AS note_primary,
                    MAX(ref_transaksi) AS ref_transaksi
             FROM kas
             WHERE ref_finance IN ($in)
             GROUP BY ref_finance"
        );

        if (!is_array($kasRows) || $kasRows === []) {
            return [];
        }

        $clientIds = [];
        $userIds = [];
        foreach ($kasRows as $kasRow) {
            if (!is_array($kasRow)) {
                continue;
            }
            $jt = (int) ($kasRow['jenis_transaksi'] ?? 0);
            $idClient = (int) ($kasRow['id_client'] ?? 0);
            $idUser = (int) ($kasRow['id_user'] ?? 0);

            if ($jt === 2) {
                if ($idUser > 0) {
                    $userIds[$idUser] = $idUser;
                }
            } elseif ($jt === 5) {
                if ($idClient > 0) {
                    $userIds[$idClient] = $idClient;
                }
            } elseif ($idClient > 0) {
                $clientIds[$idClient] = $idClient;
            } elseif ($idUser > 0) {
                $userIds[$idUser] = $idUser;
            }
        }

        $pelangganMap = [];
        if ($clientIds !== []) {
            $clientIn = implode(',', array_values($clientIds));
            $map = $this->db(0)->get_where('pelanggan', "id_pelanggan IN ($clientIn)", 'id_pelanggan');
            $pelangganMap = is_array($map) ? $map : [];
        }

        $userMap = [];
        if ($userIds !== []) {
            $userIn = implode(',', array_values($userIds));
            $map = $this->db(0)->get_where('user', "id_user IN ($userIn)", 'id_user');
            $userMap = is_array($map) ? $map : [];
        }

        $out = [];
        foreach ($kasRows as $kasRow) {
            if (!is_array($kasRow)) {
                continue;
            }
            $ref = trim((string) ($kasRow['ref_finance'] ?? ''));
            if ($ref === '') {
                continue;
            }
            $payer = $this->resolvePayerFromKasRow($kasRow, $pelangganMap, $userMap);
            if ($payer !== null) {
                $out[$ref] = $payer;
            }
        }

        return $out;
    }

    /**
     * @param array<string,string> $refs payment_ref
     * @return array<string,array{name:string,url:?string,badge:string,jenis_transaksi:int}>
     */
    private function loadInvoicePayerByRef(array $refs): array
    {
        try {
            $this->bootstrapApi();
            $invoiceDb = \App\Core\DB::getInstance(6);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($refs as $ref) {
            $payment = $invoiceDb->query(
                'SELECT p.payment_ref, i.invoice_number, i.public_token
                 FROM invoice_payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE p.payment_ref = ?
                 LIMIT 1',
                [$ref]
            )->row_array();

            if (!is_array($payment) || empty($payment['payment_ref'])) {
                $out[$ref] = [
                    'name' => $ref,
                    'url' => null,
                    'badge' => 'Invoice',
                    'jenis_transaksi' => 0,
                ];
                continue;
            }

            $number = trim((string) ($payment['invoice_number'] ?? ''));
            $token = trim((string) ($payment['public_token'] ?? ''));
            $name = $number !== '' ? $number : $ref;

            $out[$ref] = [
                'name' => $name,
                'url' => $token !== '' ? ('https://ml.nalju.com/invoice/' . rawurlencode($token)) : null,
                'badge' => 'Invoice',
                'jenis_transaksi' => 0,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,string> $refs payment_ref
     * @return array<string,array{name:string,url:?string,badge:string,jenis_transaksi:int}>
     */
    private function loadSalonPayerByRef(array $refs): array
    {
        try {
            $this->bootstrapApi();
            $salonDb = \App\Core\DB::getInstance(4);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($refs as $ref) {
            $payment = $salonDb->query(
                'SELECT p.payment_ref, p.salon_id, s.salon_name
                 FROM subscription_payments p
                 LEFT JOIN salon s ON s.salon_id = p.salon_id
                 WHERE p.payment_ref = ?
                 LIMIT 1',
                [$ref]
            )->row_array();

            $salonName = trim((string) ($payment['salon_name'] ?? ''));
            if ($salonName === '') {
                $salonName = 'Salon #' . (int) ($payment['salon_id'] ?? 0);
            }

            $out[$ref] = [
                'name' => $salonName,
                'url' => null,
                'badge' => 'Salon',
                'jenis_transaksi' => 0,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $kasRow
     * @param array<int,array<string,mixed>> $pelangganMap
     * @param array<int,array<string,mixed>> $userMap
     * @return array{name:string,url:?string,badge:string,jenis_transaksi:int}|null
     */
    private function resolvePayerFromKasRow(array $kasRow, array $pelangganMap, array $userMap): ?array
    {
        $jt = (int) ($kasRow['jenis_transaksi'] ?? 0);
        $idClient = (int) ($kasRow['id_client'] ?? 0);
        $idUser = (int) ($kasRow['id_user'] ?? 0);
        $notePrimary = trim((string) ($kasRow['note_primary'] ?? ''));
        $refTransaksi = trim((string) ($kasRow['ref_transaksi'] ?? ''));

        $badgeMap = [
            1 => 'Laundry',
            2 => 'Penarikan',
            3 => 'Member',
            5 => 'Kasbon',
            6 => 'Deposit',
            7 => 'Jualan',
            10 => 'Instant',
        ];
        $badge = $badgeMap[$jt] ?? ('Kas #' . $jt);

        if ($jt === 2) {
            $nama = $this->userNameFromMap($userMap, $idUser);
            if ($nama === '') {
                $nama = $notePrimary !== '' ? $notePrimary : 'Kasir';
            }

            return [
                'name' => $nama,
                'url' => null,
                'badge' => $badge,
                'jenis_transaksi' => $jt,
            ];
        }

        if ($jt === 5) {
            $nama = $this->userNameFromMap($userMap, $idClient);
            if ($nama === '') {
                $nama = 'Karyawan';
            }

            return [
                'name' => $nama,
                'url' => null,
                'badge' => $badge,
                'jenis_transaksi' => $jt,
            ];
        }

        if ($jt === 7 && $idClient < 1) {
            return [
                'name' => 'Umum',
                'url' => $refTransaksi !== ''
                    ? (URL::BASE_URL . 'Sales/preview_nota/' . rawurlencode($refTransaksi))
                    : null,
                'badge' => $badge,
                'jenis_transaksi' => $jt,
            ];
        }

        if ($idClient > 0 && isset($pelangganMap[$idClient]) && is_array($pelangganMap[$idClient])) {
            $p = $pelangganMap[$idClient];
            $nama = trim((string) ($p['nama_pelanggan'] ?? ''));
            if ($nama === '') {
                $nama = (string) $idClient;
            }

            return [
                'name' => $nama,
                'url' => 'https://ml.nalju.com/J/tagihan/' . $idClient,
                'badge' => $badge,
                'jenis_transaksi' => $jt,
            ];
        }

        if ($idUser > 0) {
            $nama = $this->userNameFromMap($userMap, $idUser);
            if ($nama !== '') {
                return [
                    'name' => $nama,
                    'url' => null,
                    'badge' => $badge,
                    'jenis_transaksi' => $jt,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $userMap
     */
    private function userNameFromMap(array $userMap, int $idUser): string
    {
        if ($idUser < 1 || !isset($userMap[$idUser]) || !is_array($userMap[$idUser])) {
            return '';
        }

        return trim((string) ($userMap[$idUser]['nama_user'] ?? ''));
    }
}
