<?php

/**
 * Analisa AI pengeluaran kas laundry untuk admin approval.
 */
class PengeluaranAiReview
{
    private const HISTORY_LIMIT = 60;
    private const TIMELINE_ROWS = 3;

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
        $kode = (string) ($pending['kode_cabang'] ?? '-');

        if ($this->isMinyakKendaraan($jenis)) {
            $rows = $this->takeLastN($historyRows, self::TIMELINE_ROWS);

            return $this->renderTimeline($rows, true);
        }

        if ($this->isGasLpg($jenis)) {
            $rows = $this->takeLastN($historyRows, self::TIMELINE_ROWS);

            return $this->renderTimeline($rows, true);
        }

        $branchRows = $this->filterHistorySameBranch($historyRows, $kode);
        $rows = $this->takeLastN($branchRows, self::TIMELINE_ROWS);

        return $this->renderTimeline($rows, false);
    }

    public function localFallbackAnalysis(array $pending, array $historyRows): string
    {
        return $this->buildDisplayHtml($pending, $historyRows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function renderTimeline(array $rows, bool $showBranch): string
    {
        $fmt = static fn(int $n): string => number_format($n, 0, ',', '.');
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        if ($rows === []) {
            return '<div style="color:#64748b;font-size:.84rem;padding:8px 0">Belum ada riwayat 30 hari terakhir.</div>';
        }

        $html = ['<div class="pg-exp-timeline" style="margin:0;padding:0">'];
        $lastIdx = count($rows) - 1;

        foreach ($rows as $i => $row) {
            $amt = (int) round((float) ($row['jumlah'] ?? 0));
            $ket = trim((string) ($row['keterangan'] ?? ''));
            $dateLabel = $esc($this->formatDateId((string) ($row['insertTime'] ?? '')));
            $isLast = $i === $lastIdx;

            $metaParts = [];
            if ($showBranch) {
                $metaParts[] = '<strong style="color:#1e3a8a">' . $esc((string) ($row['kode_cabang'] ?? '-')) . '</strong>';
            }
            $metaParts[] = '<strong style="color:#0f172a">Rp ' . $fmt($amt) . '</strong>';
            if ($ket !== '' && $ket !== '-') {
                $metaParts[] = $esc($ket);
            }

            $html[] = '<div class="pg-exp-timeline__item" style="display:flex;gap:10px;margin:0;padding:0 0 '
                . ($isLast ? '0' : '12px') . '">';
            $html[] = '<div style="flex:0 0 auto;width:12px;display:flex;flex-direction:column;align-items:center">';
            $html[] = '<span style="width:10px;height:10px;background:#2563eb;border:1px solid #1d4ed8;display:block;margin-top:4px"></span>';
            if (!$isLast) {
                $html[] = '<span style="flex:1;width:2px;background:#cbd5e1;min-height:18px;margin-top:2px"></span>';
            }
            $html[] = '</div>';
            $html[] = '<div style="flex:1;min-width:0;padding-bottom:' . ($isLast ? '0' : '2px') . '">';
            $html[] = '<div style="font-size:.82rem;font-weight:900;color:#1d4ed8;margin-bottom:3px">' . $dateLabel . '</div>';
            $html[] = '<div style="font-size:.88rem;font-weight:750;color:#0f172a;line-height:1.4">' . implode(' · ', $metaParts) . '</div>';
            $html[] = '</div>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    private function isGasLpg(string $jenis): bool
    {
        $j = mb_strtoupper(trim($jenis), 'UTF-8');
        if ($j === '') {
            return false;
        }

        return str_contains($j, 'GAS') && str_contains($j, 'LPG');
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
