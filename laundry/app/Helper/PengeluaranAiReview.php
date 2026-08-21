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
        $historyForAi = array_slice($historyRows, 0, self::HISTORY_LIMIT);

        $pendingLine = $this->rowToLine($pendingPayload, $kodeFn, true);
        $table = $this->formatHistoryTable($historyForAi, $kodeFn);
        $stats = $this->buildStatsSummary($pendingPayload, $historyRows);

        $system = <<<'SYS'
Kamu analis pengeluaran operasional laundry (cabang/counter). Admin akan membaca komentarmu sebelum konfirmasi pengeluaran pending.

Konteks riwayat: HANYA pengeluaran JENIS SAMA dengan pending, status sudah BERHASIL/dikonfirmasi, 30 hari terakhir (semua cabang).

Tugas:
- Bandingkan pengeluaran PENDING dengan riwayat jenis yang sama.
- Komentari: wajar/tidak wajar, duplikasi potensial, nominal vs pola, frekuensi, keterangan kurang jelas, perbedaan antar cabang.
- Beri insight praktis untuk admin (bukan keputusan otomatis).

Format respons (Bahasa Indonesia, rapi):
1. Ringkasan pending (1 kalimat)
2. Temuan utama (bullet 2-5 poin)
3. Catatan untuk admin (1-2 kalimat, netral & profesional)

Jangan pakai markdown heading (#). Bullet pakai "•". Maks ~220 kata.
Jangan menyuruh admin wajib tolak/terima — hanya analisa informatif.
SYS;

        $user = "PENGELUARAN PENDING (yang akan dikonfirmasi):\n"
            . $pendingLine . "\n\n"
            . "JENIS YANG DIBANDINGKAN: {$jenisFilter}\n"
            . "STATISTIK RINGKAS:\n" . $stats . "\n"
            . "RIWAYAT 30 HARI — JENIS SAMA, STATUS BERHASIL (kode_cabang | jenis_pengeluaran | keterangan | jumlah):\n"
            . "Total baris: {$historyCount}" . ($historyCount > $shown ? " (AI membaca {$shown} terbaru)" : '') . "\n"
            . $table;

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
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $jenis = (string) ($pending['jenis_pengeluaran'] ?? '-');
        $jumlah = (int) round((float) ($pending['jumlah'] ?? 0));
        $ket = trim((string) ($pending['keterangan'] ?? ''));

        $sameJenisCabang = 0;
        $sameAmountWeek = 0;
        $amountsSameJenis = [];
        $amountsAllJenis = [];
        $weekCut = strtotime('-7 days');

        foreach ($historyRows as $row) {
            $rowKode = (string) ($row['kode_cabang'] ?? '');
            $rowAmt = (int) round((float) ($row['jumlah'] ?? 0));
            $rowTime = strtotime((string) ($row['insertTime'] ?? ''));

            $amountsAllJenis[] = $rowAmt;
            if ($rowKode === $kode) {
                $sameJenisCabang++;
                $amountsSameJenis[] = $rowAmt;
            }
            if ($rowAmt === $jumlah && $rowTime !== false && $rowTime >= $weekCut) {
                $sameAmountWeek++;
            }
        }

        $avgAll = $amountsAllJenis !== []
            ? (int) round(array_sum($amountsAllJenis) / count($amountsAllJenis))
            : 0;
        $avgCabang = $amountsSameJenis !== []
            ? (int) round(array_sum($amountsSameJenis) / count($amountsSameJenis))
            : 0;

        $lines = [];
        $lines[] = 'Ringkasan pending: Pengeluaran ' . $jenis . ' cabang ' . $kode . ' sebesar Rp '
            . number_format($jumlah, 0, ',', '.') . '.';
        $lines[] = '';
        $lines[] = 'Temuan otomatis (jenis sama, status berhasil, 30 hari):';
        $lines[] = '• Riwayat jenis "' . $jenis . '": ' . count($historyRows) . ' transaksi berhasil.';
        if ($sameJenisCabang > 0) {
            $lines[] = '• Di cabang ' . $kode . ': ' . $sameJenisCabang . ' kali.';
        }
        if ($avgAll > 0) {
            $diff = $jumlah - $avgAll;
            $lines[] = '• Rata-rata semua cabang (jenis ini): Rp ' . number_format($avgAll, 0, ',', '.')
                . ($diff !== 0 ? ' — pending ' . ($diff > 0 ? 'lebih' : 'kurang') . ' Rp ' . number_format(abs($diff), 0, ',', '.') : ' — sejajar rata-rata');
        }
        if ($avgCabang > 0 && $avgCabang !== $avgAll) {
            $lines[] = '• Rata-rata cabang ' . $kode . ': Rp ' . number_format($avgCabang, 0, ',', '.') . '.';
        }
        if ($sameAmountWeek > 0) {
            $lines[] = '• Perhatian: nominal sama (Rp ' . number_format($jumlah, 0, ',', '.') . ') sudah muncul ' . $sameAmountWeek . 'x dalam 7 hari.';
        }
        if (count($historyRows) === 0) {
            $lines[] = '• Belum ada riwayat berhasil jenis ini dalam 30 hari — tinjau extra hati-hati.';
        }
        if ($ket === '' || $ket === '-') {
            $lines[] = '• Keterangan kosong — minta detail ke staff jika perlu.';
        }
        $lines[] = '';
        $lines[] = 'Catatan: analisa otomatis; silakan tinjau manual sebelum konfirmasi.';

        return implode("\n", $lines);
    }

    /** @param list<array<string,mixed>> $historyRows */
    private function buildStatsSummary(array $pending, array $historyRows): string
    {
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $jenis = (string) ($pending['jenis_pengeluaran'] ?? '-');
        $jumlah = (int) round((float) ($pending['jumlah'] ?? 0));
        $sameCabang = 0;
        $sameNominal = 0;
        $amounts = [];

        foreach ($historyRows as $row) {
            $amt = (int) round((float) ($row['jumlah'] ?? 0));
            $amounts[] = $amt;
            if ((string) ($row['kode_cabang'] ?? '') === $kode) {
                $sameCabang++;
            }
            if ($amt === $jumlah) {
                $sameNominal++;
            }
        }

        $avg = $amounts !== [] ? (int) round(array_sum($amounts) / count($amounts)) : 0;
        $min = $amounts !== [] ? min($amounts) : 0;
        $max = $amounts !== [] ? max($amounts) : 0;

        return "- Jenis: {$jenis}\n"
            . "- Cabang pending: {$kode}\n"
            . "- Nominal pending: {$jumlah}\n"
            . "- Riwayat berhasil jenis sama: " . count($historyRows) . " baris\n"
            . "- Frekuensi cabang pending: {$sameCabang}\n"
            . "- Frekuensi nominal sama: {$sameNominal}\n"
            . "- Rentang nominal riwayat: {$min} – {$max}, rata-rata {$avg}";
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

    /** @param list<array<string,mixed>> $rows */
    private function formatHistoryTable(array $rows, callable $kodeFn): string
    {
        if ($rows === []) {
            return "(belum ada riwayat berhasil jenis yang sama dalam 30 hari)\n";
        }

        $lines = ["kode_cabang\tjenis_pengeluaran\tketerangan\tjumlah"];
        foreach ($rows as $row) {
            $lines[] = $this->rowToLine($row, $kodeFn, false);
        }

        return implode("\n", $lines) . "\n";
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
