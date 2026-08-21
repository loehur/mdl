<?php

/**
 * Analisa AI pengeluaran kas laundry untuk admin approval.
 */
class PengeluaranAiReview
{
    private const HISTORY_LIMIT = 120;

    /**
     * @param callable(int):string $kodeFn
     * @return array{ok:bool,analysis?:string,message?:string,history_count?:int,history_shown?:int,pending?:array<string,mixed>}
     */
    public function analyze(array $pending, array $historyRows, callable $kodeFn): array
    {
        $pendingLine = $this->rowToLine($pending, $kodeFn, true);
        $table = $this->formatHistoryTable($historyRows, $kodeFn);
        $historyCount = count($historyRows);
        $shown = min($historyCount, self::HISTORY_LIMIT);

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
            . "RIWAYAT 30 HARI TERAKHIR (kolom: kode_cabang | jenis_pengeluaran | keterangan | jumlah):\n"
            . "Total baris: {$historyCount}" . ($historyCount > $shown ? " (ditampilkan {$shown} terbaru)" : '') . "\n"
            . $table;

        try {
            require_once dirname(__DIR__) . '/Helper/AiChat.php';
            $ai = new AiChat();
            $analysis = trim($ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 520, 0.25, 22));

            if ($analysis === '') {
                return [
                    'ok' => false,
                    'message' => 'AI tidak mengembalikan analisa.',
                ];
            }

            return [
                'ok' => true,
                'analysis' => $analysis,
                'history_count' => $historyCount,
                'history_shown' => $shown,
                'pending' => $this->pendingPayload($pending, $kodeFn),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Analisa AI tidak tersedia: ' . $e->getMessage(),
            ];
        }
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
