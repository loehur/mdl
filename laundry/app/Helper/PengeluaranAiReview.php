<?php

/**
 * Analisa AI pengeluaran kas laundry untuk admin approval.
 */
class PengeluaranAiReview
{
    private const HISTORY_LIMIT = 60;
    private const LAST_ROWS = 2;
    private const AVG_DAYS = 30;

    /**
     * @param callable(int):string $kodeFn
     * @return array{ok:bool,analysis?:string,message?:string,history_count?:int,history_shown?:int,pending?:array<string,mixed>,ai_source?:string,jenis_filter?:string}
     */
    public function analyze(array $pending, array $historyRows, callable $kodeFn, string $reqId = ''): array
    {
        require_once dirname(__DIR__) . '/Helper/PengeluaranAiLog.php';

        $pendingPayload = $this->pendingPayload($pending, $kodeFn);
        $jenisFilter = (string) ($pendingPayload['jenis_pengeluaran'] ?? '');
        $historyCount = count($historyRows);
        $shown = min($historyCount, self::HISTORY_LIMIT);
        $displayHtml = $this->buildDisplayHtml($pendingPayload, $historyRows);

        PengeluaranAiLog::info('DISPLAY_OK', ['req' => $reqId, 'jenis' => $jenisFilter, 'history' => $historyCount]);

        return $this->wrapResult(true, $pendingPayload, $historyCount, $shown, $displayHtml, null, 'local', $jenisFilter);
    }

    public function buildDisplayHtml(array $pending, array $historyRows): string
    {
        $jenis = (string) ($pending['jenis_pengeluaran'] ?? '');
        if ($this->isMinyakKendaraan($jenis)) {
            return $this->buildDisplayHtmlMinyak($pending, $historyRows);
        }

        return $this->buildDisplayHtmlDefault($pending, $historyRows);
    }

    /** @param array<string,mixed> $pending */
    private function buildDisplayHtmlDefault(array $pending, array $historyRows): string
    {
        $ctx = $this->buildAnalysisContextDefault($pending, $historyRows);
        $html = [];
        $html[] = $this->renderLastRowsSection(
            $ctx['last_rows'],
            $ctx['section_title'],
            (int) $ctx['daily_avg'],
            (int) $ctx['trx_avg'],
            false,
            true
        );
        $html[] = $this->renderOtherBranchesSection($ctx['other_branch_rows']);

        return implode("\n", $html);
    }

    /** @param array<string,mixed> $pending */
    private function buildDisplayHtmlMinyak(array $pending, array $historyRows): string
    {
        $ctx = $this->buildAnalysisContextMinyak($pending, $historyRows);

        return $this->renderLastRowsSection(
            $ctx['last_rows'],
            $ctx['section_title'],
            (int) $ctx['daily_avg'],
            (int) $ctx['trx_avg'],
            true,
            true
        );
    }

    public function localFallbackAnalysis(array $pending, array $historyRows): string
    {
        return $this->buildDisplayHtml($pending, $historyRows);
    }

    /**
     * @param list<array<string,mixed>> $lastRows
     */
    private function renderLastRowsSection(
        array $lastRows,
        string $title,
        int $dailyAvg,
        int $trxAvg,
        bool $showBranch,
        bool $withComments = true
    ): string {
        $fmt = static fn(int $n): string => number_format($n, 0, ',', '.');
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $html = [];
        $html[] = '<div style="margin-bottom:12px">';
        $html[] = '<div style="font-weight:900;color:#0f172a;margin-bottom:8px">' . $esc($title) . '</div>';

        if ($lastRows === []) {
            $html[] = '<div style="color:#64748b;font-size:.84rem">Belum ada riwayat untuk ditampilkan (30 hari).</div>';
            $html[] = '</div>';
            return implode("\n", $html);
        }

        foreach ($lastRows as $row) {
            $amt = (int) round((float) ($row['jumlah'] ?? 0));
            $ket = trim((string) ($row['keterangan'] ?? ''));
            $dateLabel = $this->formatDateId((string) ($row['insertTime'] ?? ''));
            $branchPart = $showBranch
                ? $esc((string) ($row['kode_cabang'] ?? '-')) . ' · '
                : '';
            $jenisPart = $showBranch
                ? $esc(trim((string) ($row['jenis_pengeluaran'] ?? ''))) . ' · '
                : '';

            $html[] = '<div style="margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #e2e8f0">';
            $html[] = '<div><strong style="color:#1d4ed8">' . $esc($dateLabel) . '</strong> · '
                . $branchPart . $jenisPart . 'Rp ' . $fmt($amt)
                . ($ket !== '' && $ket !== '-' ? ' · ' . $esc($ket) : '') . '</div>';
            if ($withComments) {
                $html[] = $this->buildShortRowComment($amt, $dailyAvg, $trxAvg);
            }
            $html[] = '</div>';
        }

        $html[] = '</div>';
        return implode("\n", $html);
    }

    /**
     * @param list<array<string,mixed>> $rows Satu transaksi terakhir per cabang lain
     */
    private function renderOtherBranchesSection(array $rows): string
    {
        $fmt = static fn(int $n): string => number_format($n, 0, ',', '.');
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $html = [];
        $html[] = '<div style="margin-top:12px;padding-top:10px;border-top:1px dashed #cbd5e1">';
        $html[] = '<div style="font-weight:900;color:#0f172a;margin-bottom:8px">Transaksi jenis sama — cabang lain</div>';

        if ($rows === []) {
            $html[] = '<div style="color:#64748b;font-size:.84rem">Tidak ada riwayat di cabang lain (30 hari).</div>';
            $html[] = '</div>';
            return implode("\n", $html);
        }

        $html[] = '<ul style="margin:0;padding-left:18px;line-height:1.5;font-size:.88rem;color:#0f172a">';
        foreach ($rows as $row) {
            $bk = $esc((string) ($row['kode_cabang'] ?? '-'));
            $dateLabel = $esc($this->formatDateId((string) ($row['insertTime'] ?? '')));
            $amt = (int) round((float) ($row['jumlah'] ?? 0));
            $ket = trim((string) ($row['keterangan'] ?? ''));
            $ketPart = ($ket !== '' && $ket !== '-') ? ' · ' . $esc($ket) : '';
            $html[] = '<li style="margin-bottom:6px"><strong>' . $bk . '</strong> · '
                . '<span style="color:#1d4ed8">' . $dateLabel . '</span> · Rp ' . $fmt($amt) . $ketPart . '</li>';
        }
        $html[] = '</ul>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * @param array<string,mixed> $pending
     * @return array{last_rows:list<array<string,mixed>>,other_branch_rows:list<array<string,mixed>>,daily_avg:int,trx_avg:int,section_title:string}
     */
    private function buildAnalysisContextDefault(array $pending, array $historyRows): array
    {
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $branchRows = $this->filterHistorySameBranch($historyRows, $kode);
        $lastRows = $this->takeLastN($branchRows, self::LAST_ROWS);
        $avg = $this->computeAvgFromRows($branchRows, self::AVG_DAYS);
        $otherBranchRows = $this->getLastPerOtherBranch($historyRows, $kode);

        return [
            'last_rows' => $lastRows,
            'other_branch_rows' => $otherBranchRows,
            'daily_avg' => $avg['daily_avg'],
            'trx_avg' => $avg['trx_avg'],
            'section_title' => '2 transaksi terakhir — Cabang ' . $kode,
        ];
    }

    /**
     * @param array<string,mixed> $pending
     * @return array{last_rows:list<array<string,mixed>>,daily_avg:int,trx_avg:int,section_title:string}
     */
    private function buildAnalysisContextMinyak(array $pending, array $historyRows): array
    {
        $jenisRaw = trim((string) ($pending['jenis_pengeluaran'] ?? ''));
        $ketRaw = trim((string) ($pending['keterangan'] ?? ''));
        $ketLabel = $ketRaw !== '' ? $ketRaw : '-';
        $matchedRows = $this->filterHistorySimilarJenisKeterangan($historyRows, $jenisRaw, $ketRaw);
        $lastRows = $this->takeLastN($matchedRows, self::LAST_ROWS);
        $avg = $this->computeAvgFromRows($matchedRows, self::AVG_DAYS);

        return [
            'last_rows' => $lastRows,
            'daily_avg' => $avg['daily_avg'],
            'trx_avg' => $avg['trx_avg'],
            'section_title' => '2 transaksi terakhir — "' . $ketLabel . '" (semua cabang)',
        ];
    }

    private function buildShortRowComment(int $amount, int $dailyAvg, int $trxAvg): string
    {
        if ($trxAvg <= 0 && $dailyAvg <= 0) {
            return '<div style="font-size:.84rem;color:#64748b;margin-top:3px">Belum ada baseline 30 hari untuk penilaian baris ini.</div>';
        }

        $fmt = static fn(int $n): string => number_format($n, 0, ',', '.');

        $tooHighTrx = $trxAvg > 0 && $amount > (int) round($trxAvg * 1.75);
        $tooLowTrx = $trxAvg > 0 && $amount < (int) round($trxAvg * 0.4);
        $tooHighDaily = $dailyAvg > 0 && $amount > ($dailyAvg * 4);

        if ($tooHighTrx || $tooHighDaily) {
            $parts = [];
            if ($tooHighTrx) {
                $parts[] = round($amount / $trxAvg, 1) . '× rata-rata/transaksi (Rp ' . $fmt($trxAvg) . ')';
            }
            if ($tooHighDaily) {
                $parts[] = 'setara ' . round($amount / max(1, $dailyAvg), 1) . ' hari rata-rata harian';
            }

            return '<div style="font-size:.84rem;color:#dc2626;margin-top:3px"><strong>Peringatan:</strong> '
                . implode(', ', $parts) . '.</div>';
        }

        if ($tooLowTrx) {
            return '<div style="font-size:.84rem;color:#dc2626;margin-top:3px"><strong>Peringatan:</strong> Jauh di bawah rata-rata/transaksi (Rp '
                . $fmt($trxAvg) . ').</div>';
        }

        $ratio = $trxAvg > 0 ? round($amount / $trxAvg, 1) : null;
        $hint = $ratio !== null
            ? ' (~' . $ratio . '× rata-rata/transaksi Rp ' . $fmt($trxAvg) . ')'
            : '';

        return '<div style="font-size:.84rem;color:#15803d;margin-top:3px">Masih wajar terhadap pola 30 hari' . $hint . '.</div>';
    }

    private function isMinyakKendaraan(string $jenis): bool
    {
        $j = mb_strtoupper(trim($jenis), 'UTF-8');
        if ($j === '') {
            return false;
        }

        return str_contains($j, 'MINYAK') && str_contains($j, 'KENDARAAN');
    }

    private function normalizeKeteranganKey(string $ket): string
    {
        $t = trim($ket);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return mb_strtoupper($t, 'UTF-8');
    }

    private function jenisSimilar(string $pendingJenis, string $rowJenis): bool
    {
        $p = $this->normalizeKeteranganKey($pendingJenis);
        $r = $this->normalizeKeteranganKey($rowJenis);
        if ($p === '' || $p === '-' || $r === '' || $r === '-') {
            return $p === $r;
        }
        if ($p === $r) {
            return true;
        }
        if ($this->isMinyakKendaraan($pendingJenis) && $this->isMinyakKendaraan($rowJenis)) {
            return true;
        }
        $minLen = 4;
        if (mb_strlen($p, 'UTF-8') >= $minLen && mb_strlen($r, 'UTF-8') >= $minLen) {
            if (str_contains($r, $p) || str_contains($p, $r)) {
                return true;
            }
        }

        return false;
    }

    private function keteranganSimilar(string $pendingRaw, string $rowRaw): bool
    {
        $p = $this->normalizeKeteranganKey($pendingRaw);
        $r = $this->normalizeKeteranganKey($rowRaw);
        if ($p === '' || $p === '-' || $r === '' || $r === '-') {
            return $p === $r;
        }
        if ($p === $r) {
            return true;
        }
        $minLen = 3;
        if (mb_strlen($p, 'UTF-8') >= $minLen && mb_strlen($r, 'UTF-8') >= $minLen) {
            if (str_contains($r, $p) || str_contains($p, $r)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $historyRows
     * @return list<array<string,mixed>>
     */
    private function filterHistorySameBranch(array $historyRows, string $kode): array
    {
        $out = [];
        foreach ($historyRows as $row) {
            if ((string) ($row['kode_cabang'] ?? '-') === $kode) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $historyRows
     * @return list<array<string,mixed>>
     */
    private function filterHistorySimilarJenisKeterangan(array $historyRows, string $pendingJenis, string $pendingKet): array
    {
        $out = [];
        foreach ($historyRows as $row) {
            $rowJenis = trim((string) ($row['jenis_pengeluaran'] ?? ''));
            $rowKet = trim((string) ($row['keterangan'] ?? ''));
            if ($this->jenisSimilar($pendingJenis, $rowJenis) && $this->keteranganSimilar($pendingKet, $rowKet)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $historyRows
     * @return list<array<string,mixed>>
     */
    private function filterHistorySimilarKeterangan(array $historyRows, string $pendingKet): array
    {
        $out = [];
        foreach ($historyRows as $row) {
            $rowKet = trim((string) ($row['keterangan'] ?? ''));
            if ($this->keteranganSimilar($pendingKet, $rowKet)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Satu transaksi terakhir per cabang selain cabang pending (jenis sama sudah difilter di history).
     *
     * @param list<array<string,mixed>> $historyRows
     * @return list<array<string,mixed>>
     */
    private function getLastPerOtherBranch(array $historyRows, string $excludeKode): array
    {
        $grouped = [];
        foreach ($historyRows as $row) {
            $bk = (string) ($row['kode_cabang'] ?? '-');
            if ($bk === $excludeKode) {
                continue;
            }
            if (!isset($grouped[$bk])) {
                $grouped[$bk] = [];
            }
            $grouped[$bk][] = $row;
        }

        $out = [];
        foreach ($grouped as $rows) {
            $last = $this->takeLastN($rows, 1);
            if ($last !== []) {
                $out[] = $last[0];
            }
        }

        usort($out, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['kode_cabang'] ?? ''), (string) ($b['kode_cabang'] ?? ''));
        });

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function takeLastN(array $rows, int $n): array
    {
        if ($n <= 0 || $rows === []) {
            return [];
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp((string) ($b['insertTime'] ?? ''), (string) ($a['insertTime'] ?? ''));
        });

        return array_slice($rows, 0, $n);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{daily_avg:int,trx_avg:int,count:int,total:int}
     */
    private function computeAvgFromRows(array $rows, int $days): array
    {
        $total = 0;
        $count = count($rows);
        foreach ($rows as $row) {
            $total += (int) round((float) ($row['jumlah'] ?? 0));
        }

        return [
            'total' => $total,
            'count' => $count,
            'daily_avg' => $count > 0 ? (int) round($total / max(1, $days)) : 0,
            'trx_avg' => $count > 0 ? (int) round($total / $count) : 0,
        ];
    }

    private function formatDateId(string $insertTime): string
    {
        $insertTime = trim($insertTime);
        if ($insertTime === '') {
            return '-';
        }
        $ts = strtotime($insertTime);
        if ($ts === false) {
            return substr($insertTime, 0, 10);
        }

        return date('d/m/Y', $ts);
    }

    /** @return array{ok:bool,analysis?:string,message?:string,history_count:int,history_shown:int,pending:array<string,mixed>,ai_source?:string,jenis_filter?:string} */
    private function wrapResult(
        bool $ok,
        array $pendingPayload,
        int $historyCount,
        int $shown,
        ?string $analysis,
        ?string $message,
        string $source = 'local',
        string $jenisFilter = ''
    ): array {
        $out = [
            'ok' => $ok,
            'history_count' => $historyCount,
            'history_shown' => $shown,
            'pending' => $pendingPayload,
            'ai_source' => $source,
            'jenis_filter' => $jenisFilter,
        ];
        if ($analysis !== null) {
            $out['analysis'] = $analysis;
        }
        if ($message !== null) {
            $out['message'] = $message;
        }

        return $out;
    }

    /**
     * Ambil riwayat analisa: default = jenis sama; minyak kendaraan = pool jenis mirip lalu filter jenis+keterangan mirip.
     *
     * @param callable(int):string $kodeFn
     * @return list<array<string,mixed>>
     */
    public function fetchHistoryForAnalysis($db, string $wCabangAll, array $pending, callable $kodeFn, string $excludeIdKas = ''): array
    {
        $jenis = trim((string) ($pending['note_primary'] ?? ''));
        $ket = trim((string) ($pending['note'] ?? ''));

        if ($this->isMinyakKendaraan($jenis)) {
            $pool = $this->fetchHistoryMinyakKendaraanPool($db, $wCabangAll, $kodeFn, $excludeIdKas);

            return $this->filterHistorySimilarJenisKeterangan($pool, $jenis, $ket);
        }

        return $this->fetchHistory30Days($db, $wCabangAll, $jenis, $kodeFn, $excludeIdKas);
    }

    /**
     * Pool 30 hari: semua pengeluaran minyak kendaraan (jenis mengandung MINYAK + KENDARAAN), semua cabang.
     *
     * @param callable(int):string $kodeFn
     * @return list<array<string,mixed>>
     */
    private function fetchHistoryMinyakKendaraanPool($db, string $wCabangAll, callable $kodeFn, string $excludeIdKas = ''): array
    {
        require_once dirname(__DIR__) . '/Helper/PengeluaranAiLog.php';

        $where = $wCabangAll
            . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4"
            . " AND status_mutasi = 3"
            . " AND UPPER(note_primary) LIKE '%MINYAK%'"
            . " AND UPPER(note_primary) LIKE '%KENDARAAN%'"
            . " AND insertTime >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        if ($excludeIdKas !== '') {
            $where .= " AND id_kas <> '" . $db->escape($excludeIdKas) . "'";
        }

        $where .= ' ORDER BY insertTime DESC LIMIT ' . self::HISTORY_LIMIT;

        PengeluaranAiLog::info('HISTORY_SQL_MINYAK', [
            'where' => substr($where, 0, 400),
        ]);

        return $this->mapHistoryRows($db->get_where('kas', $where), $kodeFn);
    }

    /**
     * Riwayat 30 hari: jenis pengeluaran sama + status berhasil (status_mutasi=3).
     *
     * @param callable(int):string $kodeFn
     * @return list<array<string,mixed>>
     */
    public function fetchHistory30Days($db, string $wCabangAll, string $jenisPengeluaran, callable $kodeFn, string $excludeIdKas = ''): array
    {
        require_once dirname(__DIR__) . '/Helper/PengeluaranAiLog.php';

        $jenisPengeluaran = trim($jenisPengeluaran);
        if ($jenisPengeluaran === '') {
            return [];
        }

        $jenisEsc = $db->escape($jenisPengeluaran);
        $where = $wCabangAll
            . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4"
            . " AND status_mutasi = 3"
            . " AND UPPER(TRIM(note_primary)) = UPPER(TRIM('" . $jenisEsc . "'))"
            . " AND insertTime >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        if ($excludeIdKas !== '') {
            $where .= " AND id_kas <> '" . $db->escape($excludeIdKas) . "'";
        }

        $where .= ' ORDER BY insertTime DESC LIMIT ' . self::HISTORY_LIMIT;

        PengeluaranAiLog::info('HISTORY_SQL', [
            'jenis' => $jenisPengeluaran,
            'where' => substr($where, 0, 400),
        ]);

        return $this->mapHistoryRows($db->get_where('kas', $where), $kodeFn);
    }

    /**
     * @param mixed $rows
     * @param callable(int):string $kodeFn
     * @return list<array<string,mixed>>
     */
    private function mapHistoryRows($rows, callable $kodeFn): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'kode_cabang' => $kodeFn((int) ($row['id_cabang'] ?? 0)),
                'jenis_pengeluaran' => trim((string) ($row['note_primary'] ?? '')),
                'keterangan' => trim((string) ($row['note'] ?? '')),
                'jumlah' => (float) ($row['jumlah'] ?? 0),
                'insertTime' => (string) ($row['insertTime'] ?? ''),
                'status_mutasi' => (int) ($row['status_mutasi'] ?? 0),
                'id_kas' => (string) ($row['id_kas'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param callable(int):string $kodeFn
     * @return array<string,mixed>
     */
    public function pendingPayload(array $row, callable $kodeFn): array
    {
        return [
            'id_kas' => (string) ($row['id_kas'] ?? ''),
            'kode_cabang' => $kodeFn((int) ($row['id_cabang'] ?? 0)),
            'jenis_pengeluaran' => trim((string) ($row['note_primary'] ?? '')),
            'keterangan' => trim((string) ($row['note'] ?? '')),
            'jumlah' => (float) ($row['jumlah'] ?? 0),
            'jumlah_fmt' => number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.'),
            'insertTime' => (string) ($row['insertTime'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $row */
    private function rowToLine(array $row, callable $kodeFn, bool $isPending): string
    {
        $kode = $row['kode_cabang'] ?? $kodeFn((int) ($row['id_cabang'] ?? 0));
        $jenis = $this->cellText($row['jenis_pengeluaran'] ?? $row['note_primary'] ?? '');
        $ket = $this->cellText($row['keterangan'] ?? $row['note'] ?? '');
        $jumlah = (int) round((float) ($row['jumlah'] ?? 0));
        $suffix = $isPending ? "\t[PENDING]" : '';

        return $kode . "\t" . $jenis . "\t" . $ket . "\t" . $jumlah . $suffix;
    }

    private function cellText($value): string
    {
        $t = trim((string) $value);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        if (strlen($t) > 80) {
            $t = substr($t, 0, 77) . '…';
        }

        return $t === '' ? '-' : $t;
    }
}
