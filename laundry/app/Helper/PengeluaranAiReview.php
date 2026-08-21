<?php

/**
 * Analisa AI pengeluaran kas laundry untuk admin approval.
 */
class PengeluaranAiReview
{
    private const HISTORY_LIMIT = 60;

    /**
     * @param callable(int):string $kodeFn
     * @return array{ok:bool,analysis?:string,message?:string,history_count?:int,history_shown?:int,pending?:array<string,mixed>,ai_source?:string}
     */
    public function analyze(array $pending, array $historyRows, callable $kodeFn): array
    {
        $pendingPayload = $this->pendingPayload($pending, $kodeFn);
        $historyCount = count($historyRows);
        $shown = min($historyCount, self::HISTORY_LIMIT);
        $historyForAi = array_slice($historyRows, 0, self::HISTORY_LIMIT);

        $pendingLine = $this->rowToLine($pendingPayload, $kodeFn, true);
        $table = $this->formatHistoryTable($historyForAi, $kodeFn);
        $stats = $this->buildStatsSummary($pendingPayload, $historyRows);

        $system = <<<'SYS'
Kamu analis pengeluaran operasional laundry (cabang/counter). Admin akan membaca komentarmu sebelum konfirmasi pengeluaran pending.

Tugas:
- Bandingkan pengeluaran PENDING dengan riwayat 30 hari (semua cabang operasional).
- Komentari: wajar/tidak wajar, duplikasi potensial, nominal vs pola, frekuensi jenis pengeluaran, keterangan kurang jelas, atau anomali cabang tertentu.
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
            . "STATISTIK RINGKAS:\n" . $stats . "\n"
            . "RIWAYAT 30 HARI TERAKHIR (kolom: kode_cabang | jenis_pengeluaran | keterangan | jumlah):\n"
            . "Total baris: {$historyCount}" . ($historyCount > $shown ? " (AI membaca {$shown} terbaru)" : '') . "\n"
            . $table;

        try {
            require_once dirname(__DIR__) . '/Helper/AiChat.php';
            $ai = new AiChat();
            $analysis = trim($ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 480, 0.25, 35));

            if ($analysis === '') {
                return $this->wrapResult(false, $pendingPayload, $historyCount, $shown, null, 'AI tidak mengembalikan analisa.');
            }

            return $this->wrapResult(true, $pendingPayload, $historyCount, $shown, $analysis, null, 'ai');
        } catch (\Throwable $e) {
            $fallback = $this->localFallbackAnalysis($pendingPayload, $historyRows);
            return $this->wrapResult(true, $pendingPayload, $historyCount, $shown, $fallback, null, 'local');
        }
    }

    /**
     * @param callable(int):string $kodeFn
     */
    public function localFallbackAnalysis(array $pending, array $historyRows): string
    {
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $jenis = (string) ($pending['jenis_pengeluaran'] ?? '-');
        $jumlah = (int) round((float) ($pending['jumlah'] ?? 0));
        $ket = trim((string) ($pending['keterangan'] ?? ''));

        $sameJenisCabang = 0;
        $sameAmountWeek = 0;
        $amountsSameJenis = [];
        $cabangTotal = 0;
        $weekCut = strtotime('-7 days');

        foreach ($historyRows as $row) {
            $rowJenis = trim((string) ($row['jenis_pengeluaran'] ?? ''));
            $rowKode = (string) ($row['kode_cabang'] ?? '');
            $rowAmt = (int) round((float) ($row['jumlah'] ?? 0));
            $rowTime = strtotime((string) ($row['insertTime'] ?? ''));

            if ($rowKode === $kode) {
                $cabangTotal += $rowAmt;
            }
            if ($rowJenis === $jenis && $rowKode === $kode) {
                $sameJenisCabang++;
                $amountsSameJenis[] = $rowAmt;
            }
            if ($rowAmt === $jumlah && $rowJenis === $jenis && $rowTime !== false && $rowTime >= $weekCut) {
                $sameAmountWeek++;
            }
        }

        $avgJenis = $amountsSameJenis !== []
            ? (int) round(array_sum($amountsSameJenis) / count($amountsSameJenis))
            : 0;

        $lines = [];
        $lines[] = 'Ringkasan pending: Pengeluaran ' . $jenis . ' cabang ' . $kode . ' sebesar Rp '
            . number_format($jumlah, 0, ',', '.') . '.';
        $lines[] = '';
        $lines[] = 'Temuan otomatis (AI tidak tersedia):';
        $lines[] = '• Riwayat 30 hari: ' . count($historyRows) . ' baris pengeluaran.';
        $lines[] = '• Jenis "' . $jenis . '" di cabang ' . $kode . ' muncul ' . $sameJenisCabang . ' kali (30 hari).';
        if ($avgJenis > 0) {
            $diff = $jumlah - $avgJenis;
            $lines[] = '• Rata-rata jenis ini di cabang sama: Rp ' . number_format($avgJenis, 0, ',', '.')
                . ($diff !== 0 ? ' (pending ' . ($diff > 0 ? 'lebih' : 'kurang') . ' Rp ' . number_format(abs($diff), 0, ',', '.') . ')' : ' (sama rata-rata)');
        }
        if ($sameAmountWeek > 0) {
            $lines[] = '• Perhatian: ada ' . $sameAmountWeek . ' pengeluaran serupa (jenis+nominal) dalam 7 hari terakhir.';
        }
        if ($cabangTotal > 0) {
            $lines[] = '• Total pengeluaran cabang ' . $kode . ' (30 hari): Rp ' . number_format($cabangTotal, 0, ',', '.') . '.';
        }
        if ($ket === '' || $ket === '-') {
            $lines[] = '• Keterangan kosong — pertimbangkan minta detail ke staff.';
        }
        $lines[] = '';
        $lines[] = 'Catatan: analisa ini otomatis; silakan tinjau manual sebelum konfirmasi.';

        return implode("\n", $lines);
    }

    /** @param list<array<string,mixed>> $historyRows */
    private function buildStatsSummary(array $pending, array $historyRows): string
    {
        $kode = (string) ($pending['kode_cabang'] ?? '-');
        $jenis = (string) ($pending['jenis_pengeluaran'] ?? '-');
        $jumlah = (int) round((float) ($pending['jumlah'] ?? 0));
        $sameJenis = 0;
        $sameNominal = 0;

        foreach ($historyRows as $row) {
            if ((string) ($row['jenis_pengeluaran'] ?? '') === $jenis && (string) ($row['kode_cabang'] ?? '') === $kode) {
                $sameJenis++;
            }
            if ((int) round((float) ($row['jumlah'] ?? 0)) === $jumlah && (string) ($row['jenis_pengeluaran'] ?? '') === $jenis) {
                $sameNominal++;
            }
        }

        return "- Cabang pending: {$kode}\n"
            . "- Jenis pending: {$jenis}\n"
            . "- Nominal pending: {$jumlah}\n"
            . "- Frekuensi jenis+cabang sama (30 hari): {$sameJenis}\n"
            . "- Frekuensi jenis+nominal sama (30 hari): {$sameNominal}\n"
            . '- Total baris riwayat: ' . count($historyRows);
    }

    /** @return array{ok:bool,analysis?:string,message?:string,history_count:int,history_shown:int,pending:array<string,mixed>,ai_source?:string} */
    private function wrapResult(
        bool $ok,
        array $pendingPayload,
        int $historyCount,
        int $shown,
        ?string $analysis,
        ?string $message,
        string $source = 'local'
    ): array {
        $out = [
            'ok' => $ok,
            'history_count' => $historyCount,
            'history_shown' => $shown,
            'pending' => $pendingPayload,
            'ai_source' => $source,
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
     * @param callable(int):string $kodeFn
     * @return list<array<string,mixed>>
     */
    public function fetchHistory30Days($db, string $wCabangAll, callable $kodeFn): array
    {
        $where = $wCabangAll
            . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4"
            . " AND status_mutasi IN (2, 3)"
            . " AND insertTime >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            . " ORDER BY insertTime DESC LIMIT " . self::HISTORY_LIMIT;

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
            return "(belum ada riwayat pengeluaran 30 hari)\n";
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
