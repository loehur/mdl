<?php

/**
 * Analisa AI pengeluaran kas laundry untuk admin approval.
 */
class PengeluaranAiReview
{
    private const HISTORY_LIMIT = 60;

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

        $pendingLine = $this->rowToLine($pendingPayload, $kodeFn, true);
        $stats = $this->buildStatsSummary($pendingPayload, $historyRows);
        $system = $this->isMinyakKendaraan($jenisFilter)
            ? $this->aiSystemPromptMinyakKendaraan()
            : $this->aiSystemPromptDefault();

        $user = "PENDING:\n" . $pendingLine . "\n\n"
            . "STATISTIK RATA-RATA HARIAN (30 hari, jenis sama):\n" . $stats . "\n";

        try {
            require_once dirname(__DIR__) . '/Helper/AiChat.php';
            PengeluaranAiLog::info('AI_CALL', ['req' => $reqId, 'jenis' => $jenisFilter, 'history' => $historyCount]);
            $ai = new AiChat();
            $raw = $ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 480, 0.25, 35);
            $analysis = trim($raw);

            PengeluaranAiLog::info('AI_OK', [
                'req' => $reqId,
                'len' => strlen($analysis),
                'preview' => substr($analysis, 0, 120),
            ]);

            if ($analysis === '') {
                $fallback = $this->localFallbackAnalysis($pendingPayload, $historyRows);
                return $this->wrapResult(true, $pendingPayload, $historyCount, $shown, $fallback, 'AI kosong — analisa otomatis.', 'local', $jenisFilter);
            }

            return $this->wrapResult(true, $pendingPayload, $historyCount, $shown, $analysis, null, 'ai', $jenisFilter);
        } catch (\Throwable $e) {
            PengeluaranAiLog::error('AI_FAIL', ['req' => $reqId, 'msg' => $e->getMessage()]);
            $fallback = $this->localFallbackAnalysis($pendingPayload, $historyRows);
            return $this->wrapResult(true, $pendingPayload, $historyCount, $shown, $fallback, 'AI tidak tersedia — analisa otomatis.', 'local', $jenisFilter);
        }
    }

    public function localFallbackAnalysis(array $pending, array $historyRows): string
    {
        $jenis = (string) ($pending['jenis_pengeluaran'] ?? '');
        if ($this->isMinyakKendaraan($jenis)) {
            return $this->localFallbackMinyakKendaraan($pending, $historyRows);
        }

        return $this->localFallbackDefault($pending, $historyRows);
    }

    /** @param array<string,mixed> $pending */
    private function localFallbackDefault(array $pending, array $historyRows): string
    {
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $jumlah = (int) round((float) ($pending['jumlah'] ?? 0));
        $days = 30;
        $byBranch = $this->aggregateByBranch($historyRows);
        $cur = $byBranch[$kode] ?? null;
        $curDaily = $cur ? (int) round($cur['total'] / $days) : 0;
        $curTrxAvg = ($cur && $cur['count'] > 0) ? (int) round($cur['total'] / $cur['count']) : 0;

        $fmt = static fn(int $n): string => number_format($n, 0, ',', '.');
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $html = [];

        $html[] = '<div style="margin-bottom:10px">';
        $html[] = '<div><strong>Cabang ' . $esc($kode) . '</strong> · Rp ' . $fmt($jumlah) . '</div>';

        if ($cur === null || $cur['count'] === 0) {
            $html[] = '<div style="color:#dc2626;margin-top:6px"><strong>Peringatan:</strong> Belum ada riwayat jenis ini di cabang ini (30 hari).</div>';
        } else {
            $html[] = '<div style="margin-top:4px">Rata-rata harian: <strong>Rp ' . $fmt($curDaily) . '/hari</strong></div>';
            $html[] = $this->buildWajarLine($jumlah, $curDaily, $curTrxAvg);
        }
        $html[] = '</div>';

        if ($byBranch !== []) {
            uasort($byBranch, static fn(array $a, array $b): int => (int) round($b['total'] / $days) <=> (int) round($a['total'] / $days));
            $html[] = '<div><strong>Perbandingan cabang lain</strong> (rata-rata/hari):</div>';
            $html[] = '<ul style="margin:4px 0 0;padding-left:18px;line-height:1.45">';
            foreach ($byBranch as $bk => $bd) {
                if ($bk === $kode) {
                    continue;
                }
                $daily = (int) round($bd['total'] / $days);
                $html[] = '<li>' . $esc($bk) . ': Rp ' . $fmt($daily) . '/hari</li>';
            }
            $html[] = '</ul>';

            if ($curDaily > 0 && count($byBranch) > 1) {
                $othersDaily = [];
                foreach ($byBranch as $bk => $bd) {
                    if ($bk === $kode) {
                        continue;
                    }
                    $othersDaily[] = (int) round($bd['total'] / $days);
                }
                if ($othersDaily !== []) {
                    $avgOthers = (int) round(array_sum($othersDaily) / count($othersDaily));
                    if ($avgOthers > 0) {
                        $diffPct = (int) round((($curDaily - $avgOthers) / $avgOthers) * 100);
                        if (abs($diffPct) >= 20) {
                            $dir = $diffPct > 0 ? 'di atas' : 'di bawah';
                            $color = abs($diffPct) >= 40 ? '#dc2626' : '#64748b';
                            $html[] = '<div style="margin-top:6px;color:' . $color . '">Cabang ini ' . abs($diffPct) . '% ' . $dir . ' rata-rata cabang lain (Rp ' . $fmt($avgOthers) . '/hari).</div>';
                        }
                    }
                }
            }
        } else {
            $html[] = '<div style="color:#64748b">Belum ada data perbandingan antar cabang.</div>';
        }

        return implode("\n", $html);
    }

    /** @param array<string,mixed> $pending */
    private function localFallbackMinyakKendaraan(array $pending, array $historyRows): string
    {
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $jumlah = (int) round((float) ($pending['jumlah'] ?? 0));
        $ketRaw = trim((string) ($pending['keterangan'] ?? ''));
        $ketKey = $this->normalizeKeteranganKey($ketRaw !== '' ? $ketRaw : '-');
        $days = 30;
        $byKet = $this->aggregateByKeterangan($historyRows);
        $cur = $byKet[$ketKey] ?? null;
        $curDaily = $cur ? (int) round($cur['total'] / $days) : 0;
        $curTrxAvg = ($cur && $cur['count'] > 0) ? (int) round($cur['total'] / $cur['count']) : 0;

        $fmt = static fn(int $n): string => number_format($n, 0, ',', '.');
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $ketLabel = $cur['label'] ?? ($ketRaw !== '' ? $ketRaw : '-');

        $html = [];
        $html[] = '<div style="margin-bottom:10px">';
        $html[] = '<div><strong>Cabang ' . $esc($kode) . '</strong> · Rp ' . $fmt($jumlah) . '</div>';
        $html[] = '<div class="text-muted" style="margin-top:2px">Keterangan: ' . $esc($ketLabel) . '</div>';

        if ($cur === null || $cur['count'] === 0) {
            $html[] = '<div style="color:#dc2626;margin-top:6px"><strong>Peringatan:</strong> Belum ada riwayat untuk keterangan ini (semua cabang, 30 hari).</div>';
        } else {
            $html[] = '<div style="margin-top:4px">Rata-rata harian keterangan ini (semua cabang): <strong>Rp ' . $fmt($curDaily) . '/hari</strong></div>';
            $html[] = $this->buildWajarLine($jumlah, $curDaily, $curTrxAvg, 'keterangan ini (semua cabang)');
        }
        $html[] = '</div>';

        if ($byKet !== []) {
            uasort($byKet, static fn(array $a, array $b): int => (int) round($b['total'] / $days) <=> (int) round($a['total'] / $days));
            $html[] = '<div><strong>Rata-rata semua cabang</strong> per keterangan (30 hari):</div>';
            $html[] = '<ul style="margin:4px 0 0;padding-left:18px;line-height:1.45">';
            foreach ($byKet as $key => $kd) {
                $daily = (int) round($kd['total'] / $days);
                $trxAvg = $kd['count'] > 0 ? (int) round($kd['total'] / $kd['count']) : 0;
                $label = $esc($kd['label']);
                if ($key === $ketKey) {
                    $label .= ' <em>(keterangan ini)</em>';
                }
                $html[] = '<li>' . $label . ': Rp ' . $fmt($daily) . '/hari · Rp ' . $fmt($trxAvg) . '/isi</li>';
            }
            $html[] = '</ul>';
        } else {
            $html[] = '<div style="color:#64748b">Belum ada data perbandingan per keterangan.</div>';
        }

        return implode("\n", $html);
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

    /** @return array<string, array{total:int,count:int,label:string}> */
    private function aggregateByKeterangan(array $historyRows): array
    {
        $byKet = [];
        foreach ($historyRows as $row) {
            $raw = trim((string) ($row['keterangan'] ?? ''));
            $key = $this->normalizeKeteranganKey($raw !== '' ? $raw : '-');
            $amt = (int) round((float) ($row['jumlah'] ?? 0));
            if (!isset($byKet[$key])) {
                $byKet[$key] = ['total' => 0, 'count' => 0, 'label' => $raw !== '' ? $raw : '-'];
            }
            $byKet[$key]['total'] += $amt;
            $byKet[$key]['count']++;
        }

        return $byKet;
    }

    private function aiSystemPromptDefault(): string
    {
        return <<<'SYS'
Kamu analis pengeluaran operasional laundry. Admin butuh analisa SINGKAT — hanya 2 poin:

1) Cabang yang dicek: rata-rata harian (total jenis sama 30 hari ÷ 30). Bandingkan nominal PENDING dengan rata-rata harian & rata-rata per transaksi cabang itu. Nyatakan wajar/tidak. Jika tidak wajar, tulis peringatan dengan HTML: <span style="color:#dc2626"><strong>Peringatan:</strong> ...</span>

2) Perbandingan rata-rata harian cabang lain (jenis sama, 30 hari). Ringkas, bullet "•", max 5 cabang (selain cabang pending jika perlu).

Jangan sebut total "X transaksi berhasil", frekuensi detail, atau narasi panjang.
Jangan ulang jenis pengeluaran berkali-kali. Maks ~120 kata.
Format plain text + HTML merah hanya untuk peringatan.
SYS;
    }

    private function aiSystemPromptMinyakKendaraan(): string
    {
        return <<<'SYS'
Kamu analis pengeluaran minyak kendaraan laundry. Admin butuh analisa SINGKAT — hanya 2 poin:

1) Cabang pending + keterangan (nama/plat kendaraan): bandingkan nominal PENDING dengan rata-rata harian & rata-rata per isi untuk KETERANGAN yang sama — gabungan SEMUA cabang (30 hari). Nyatakan wajar/tidak. Jika tidak wajar: <span style="color:#dc2626"><strong>Peringatan:</strong> ...</span>

2) Jangan bandingkan per cabang. Tampilkan rata-rata harian SEMUA cabang per keterangan/kendaraan (bullet "•"). Tandai keterangan pending.

Maks ~120 kata. HTML merah hanya untuk peringatan.
SYS;
    }

    /** @return array<string, array{total:int,count:int}> */
    private function aggregateByBranch(array $historyRows): array
    {
        $byBranch = [];
        foreach ($historyRows as $row) {
            $bk = (string) ($row['kode_cabang'] ?? '-');
            $amt = (int) round((float) ($row['jumlah'] ?? 0));
            if (!isset($byBranch[$bk])) {
                $byBranch[$bk] = ['total' => 0, 'count' => 0];
            }
            $byBranch[$bk]['total'] += $amt;
            $byBranch[$bk]['count']++;
        }

        return $byBranch;
    }

    private function buildWajarLine(int $pending, int $dailyAvg, int $trxAvg, string $context = 'cabang ini'): string
    {
        $fmt = static fn(int $n): string => number_format($n, 0, ',', '.');

        if ($trxAvg <= 0 && $dailyAvg <= 0) {
            return '<div style="color:#64748b;margin-top:4px">Data belum cukup untuk penilaian kewajaran.</div>';
        }

        $tooHighTrx = $trxAvg > 0 && $pending > (int) round($trxAvg * 1.75);
        $tooLowTrx = $trxAvg > 0 && $pending < (int) round($trxAvg * 0.4);
        $tooHighDaily = $dailyAvg > 0 && $pending > ($dailyAvg * 4);

        if ($tooHighTrx || $tooHighDaily) {
            $parts = [];
            if ($tooHighTrx) {
                $parts[] = round($pending / $trxAvg, 1) . '× rata-rata per isi (Rp ' . $fmt($trxAvg) . ')';
            }
            if ($tooHighDaily) {
                $parts[] = 'setara ' . round($pending / $dailyAvg, 1) . ' hari rata-rata harian';
            }

            return '<div style="color:#dc2626;margin-top:6px"><strong>Peringatan:</strong> Nominal di atas pola wajar — '
                . implode(', ', $parts) . '.</div>';
        }

        if ($tooLowTrx) {
            return '<div style="color:#dc2626;margin-top:6px"><strong>Peringatan:</strong> Nominal jauh di bawah rata-rata per isi (Rp '
                . $fmt($trxAvg) . ').</div>';
        }

        return '<div style="color:#15803d;margin-top:4px">Masih wajar terhadap rata-rata ' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . '.</div>';
    }

    /** @param list<array<string,mixed>> $historyRows */
    private function buildStatsSummary(array $pending, array $historyRows): string
    {
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $jumlah = (int) round((float) ($pending['jumlah'] ?? 0));
        $jenis = (string) ($pending['jenis_pengeluaran'] ?? '');
        $days = 30;

        if ($this->isMinyakKendaraan($jenis)) {
            $ketRaw = trim((string) ($pending['keterangan'] ?? ''));
            $byKet = $this->aggregateByKeterangan($historyRows);
            $lines = [
                '- Jenis: minyak kendaraan (gabung semua cabang, kelompok keterangan)',
                '- Cabang pending: ' . $kode,
                '- Keterangan pending: ' . ($ketRaw !== '' ? $ketRaw : '-'),
                '- Nominal pending: ' . $jumlah,
            ];
            foreach ($byKet as $kd) {
                $daily = (int) round($kd['total'] / $days);
                $trxAvg = $kd['count'] > 0 ? (int) round($kd['total'] / $kd['count']) : 0;
                $lines[] = '- ' . $kd['label'] . ': Rp ' . $daily . '/hari, rata-rata/isi Rp ' . $trxAvg;
            }

            return implode("\n", $lines);
        }

        $byBranch = $this->aggregateByBranch($historyRows);
        $lines = [
            '- Cabang pending: ' . $kode,
            '- Nominal pending: ' . $jumlah,
        ];

        foreach ($byBranch as $bk => $bd) {
            $daily = (int) round($bd['total'] / $days);
            $trxAvg = $bd['count'] > 0 ? (int) round($bd['total'] / $bd['count']) : 0;
            $mark = $bk === $kode ? ' (cabang ini)' : '';
            $lines[] = "- {$bk}{$mark}: Rp {$daily}/hari, rata-rata/transaksi Rp {$trxAvg}";
        }

        return implode("\n", $lines);
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

        $rows = $db->get_where('kas', $where);
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
