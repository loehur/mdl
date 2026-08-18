<?php

/**
 * Notifikasi task ESTIMASI / GRANT untuk petugas Laundry.
 * Tabel wa_estimasi_session di mdl_main → laundry db(100) (= API db(0)).
 *
 * Task terpisah:
 * - estimasi → petugas isi jam saja
 * - grant    → petugas setujui / tolak saja
 */
class Estimasi extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    /**
     * Jumlah task pending (estimasi + grant dihitung terpisah).
     */
    public function count()
    {
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $n = $this->syncNotifTaskCount();
            echo json_encode(['ok' => 1, 'count' => $n]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => 0, 'count' => $this->getNotifTaskCountFromSession(), 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Daftar task terpisah (satu baris session bisa jadi 2 task).
     */
    public function list()
    {
        if (ob_get_level() === 0) {
            ob_start();
        }
        @set_time_limit(90);
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->db(100)->query_array(
                'SELECT phone, id_penjualan, id_cabang, fase_proses, butuh_estimasi, estimasi_tanggal, estimasi_jam,
                        summary, updated_at, expires_at
                 FROM wa_estimasi_session
                 WHERE ' . $this->pendingWhereSql() . '
                 ORDER BY updated_at DESC
                 LIMIT 50'
            );
            if (!is_array($rows)) {
                $rows = [];
            }

            $items = [];
            foreach ($rows as $row) {
                $phone = (string) ($row['phone'] ?? '');
                $pelanggan = $this->resolvePelangganByPhone($phone);
                $base = [
                    'phone' => $phone,
                    'phone_display' => $this->displayPhone($phone),
                    'id_penjualan' => isset($row['id_penjualan']) ? (int) $row['id_penjualan'] : null,
                    'fase_proses' => $row['fase_proses'] ?? null,
                    'estimasi_tanggal' => $row['estimasi_tanggal'] ?? null,
                    'estimasi_jam' => $row['estimasi_jam'],
                    'estimasi_jam_label' => $this->formatEstimasiJamLabelFromDb($row['estimasi_jam'] ?? null),
                    'date_options' => $this->estimasiDateOptions(),
                    'updated_at' => $row['updated_at'] ?? null,
                    'expires_at' => $row['expires_at'] ?? null,
                    'nama' => $pelanggan['nama'] ?? '',
                ];

                $butuhEstimasi = (int) ($row['butuh_estimasi'] ?? 0) === 1
                    && ($row['estimasi_jam'] === null || $row['estimasi_jam'] === '');

                if ($butuhEstimasi) {
                    $items[] = array_merge($base, [
                        'task_type' => 'estimasi',
                        'task_id' => 'estimasi:' . $phone,
                        'customer_message' => null,
                        'request_waktu_label' => null,
                        'request_jam_label' => null,
                        'request_text' => null,
                    ]);
                }
            }

            // Pelanggan baru (#NEW#) — update nama segera
            try {
                $newRows = $this->db(100)->query_array(
                    'SELECT phone, id_pelanggan, id_cabang, jenis, lokasi_nama, lokasi_detail,
                            summary, updated_at, expires_at, butuh_update_nama
                     FROM wa_kurir_session
                     WHERE expires_at > NOW()
                       AND id_cabang = ' . (int) $this->currentCabangId() . '
                       AND butuh_update_nama = 1
                     ORDER BY updated_at DESC
                     LIMIT 50'
                );
                if (!is_array($newRows)) {
                    $newRows = [];
                }
                foreach ($newRows as $row) {
                    $phone = (string) ($row['phone'] ?? '');
                    $idPelanggan = (int) ($row['id_pelanggan'] ?? 0);
                    $namaDb = '';
                    $namaAsli = '';
                    if (preg_match('/new_nama_asli=([^|]+)/', (string) ($row['summary'] ?? ''), $mNama)) {
                        $namaAsli = trim($mNama[1]);
                    }
                    if ($idPelanggan > 0) {
                        try {
                            $pel = $this->db(0)->get_where_row(
                                'pelanggan',
                                'id_pelanggan = ' . $idPelanggan
                            );
                            if (is_array($pel) && !empty($pel['nama_pelanggan'])) {
                                $namaDb = trim((string) $pel['nama_pelanggan']);
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                    if ($namaDb === '') {
                        $pelanggan = $this->resolvePelangganByPhone($phone);
                        $namaDb = (string) ($pelanggan['nama'] ?? '');
                    }
                    $items[] = [
                        'task_type' => 'pelanggan_new',
                        'task_id' => 'pelanggan_new:' . $phone,
                        'phone' => $phone,
                        'phone_display' => $this->displayPhone($phone),
                        'id_penjualan' => null,
                        'id_pelanggan' => $idPelanggan > 0 ? $idPelanggan : null,
                        'jenis' => (string) ($row['jenis'] ?? 'jemput'),
                        'lokasi_nama' => $row['lokasi_nama'] ?? '',
                        'lokasi_detail' => $row['lokasi_detail'] ?? '',
                        'customer_message' => $namaAsli !== ''
                            ? ('Nama dari WA: ' . $namaAsli)
                            : '',
                        'nama_saran' => $namaAsli !== '' ? $namaAsli : $namaDb,
                        'nama_saat_ini' => $namaDb,
                        'updated_at' => $row['updated_at'] ?? null,
                        'expires_at' => $row['expires_at'] ?? null,
                        'nama' => $namaDb !== '' ? $namaDb : ($namaAsli !== '' ? $namaAsli : 'Pelanggan baru'),
                    ];
                }
            } catch (\Throwable $e) {
                // kolom / tabel belum ada
            }

            // Permintaan pelanggan — hanya wa_permintaan_session
            foreach ($this->getOpenPermintaanTasks() as $permItem) {
                $items[] = $permItem;
            }

            // Pelanggan baru dulu, lalu permintaan, estimasi
            usort($items, function ($a, $b) {
                $rank = function ($t) {
                    if ($t === 'pelanggan_new') return -1;
                    if ($t === 'permintaan') return 0;
                    if ($t === 'estimasi') return 1;
                    return 2;
                };
                $wa = $rank($a['task_type'] ?? '');
                $wb = $rank($b['task_type'] ?? '');
                if ($wa !== $wb) {
                    return $wa <=> $wb;
                }
                return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
            });

            $this->echoJson(['ok' => 1, 'items' => $items]);
        } catch (\Throwable $e) {
            $this->echoJson(['ok' => 0, 'items' => [], 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Update satu task.
     * POST: phone, task_type=estimasi|pelanggan_new,
     *       estimasi_jam?, id_karyawan?, nama_pelanggan?, send_wa?
     */
    public function update()
    {
        if (ob_get_level() === 0) {
            ob_start();
        }
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        $phone = trim((string) ($_POST['phone'] ?? ''));
        $taskType = strtolower(trim((string) ($_POST['task_type'] ?? '')));
        if ($phone === '') {
            $this->echoJson(['ok' => 0, 'msg' => 'Nomor WA wajib']);
            return;
        }
        if (!in_array($taskType, ['estimasi', 'pelanggan_new'], true)) {
            $this->echoJson(['ok' => 0, 'msg' => 'task_type wajib: estimasi atau pelanggan_new']);
            return;
        }

        $phoneEsc = $this->db(100)->escape($phone);

        if ($taskType === 'pelanggan_new') {
            $session = $this->db(100)->get_where_row(
                'wa_kurir_session',
                "phone = '" . $phoneEsc . "' AND expires_at > NOW()"
            );
            if (!is_array($session) || empty($session['phone'])) {
                echo json_encode(['ok' => 0, 'msg' => 'Session kurir tidak ditemukan / kedaluwarsa']);
                return;
            }
            $this->updatePelangganNewTask($phone, $phoneEsc, $session);
            return;
        }

        $session = $this->db(100)->get_where_row(
            'wa_estimasi_session',
            "phone = '" . $phoneEsc . "' AND expires_at > NOW()"
        );
        if (empty($session)) {
            echo json_encode(['ok' => 0, 'msg' => 'Session tidak ditemukan / sudah expired']);
            return;
        }

        if ($taskType === 'estimasi') {
            $this->updateEstimasiTask($phone, $phoneEsc, $session);
            return;
        }

        $this->echoJson(['ok' => 0, 'msg' => 'task_type tidak dikenali']);
    }

    private function updateEstimasiTask(string $phone, string $phoneEsc, array $session): void
    {
        if ((int) ($session['butuh_estimasi'] ?? 0) !== 1 || ($session['estimasi_jam'] !== null && $session['estimasi_jam'] !== '')) {
            echo json_encode(['ok' => 0, 'msg' => 'Task estimasi sudah tidak pending']);
            return;
        }

        $parsed = $this->parseEstimasiTanggalJamFromPost();
        if ($parsed === null) {
            echo json_encode(['ok' => 0, 'msg' => 'Tanggal (hari ini–lusa) dan jam wajib diisi']);
            return;
        }

        $waktuLabel = $this->formatEstimasiWaktuCustomer($parsed['tanggal'], $parsed['jam']);
        $idPenjualan = isset($session['id_penjualan']) ? (int) $session['id_penjualan'] : 0;
        $idLabel = $idPenjualan > 0 ? '#' . $idPenjualan : '';
        $sapaan = $this->resolveSapaanForPhone($phone);
        $replyText = $idLabel !== ''
            ? "Laundry ID {$idLabel} diperkirakan siap {$waktuLabel} ya {$sapaan} 😊"
            : "Diperkirakan siap {$waktuLabel} ya {$sapaan} 😊";

        $summary = trim((string) ($session['summary'] ?? ''));
        $summary .= ($summary !== '' ? ' | ' : '') . 'Petugas isi estimasi=' . $parsed['tanggal'] . ' ' . $this->formatEstimasiJamLabel($parsed['jam']);

        $up = $this->db(100)->update(
            'wa_estimasi_session',
            [
                'estimasi_tanggal' => $parsed['tanggal'],
                'estimasi_jam' => $parsed['jam'],
                'butuh_estimasi' => 0,
                'summary' => mb_substr($summary, 0, 500),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            "phone = '" . $phoneEsc . "'"
        );
        if (!empty($up['errno'])) {
            echo json_encode(['ok' => 0, 'msg' => $up['error'] ?? 'Gagal update']);
            return;
        }

        $this->respondAfterUpdate($phone, $replyText);
    }

    /**
     * Petugas update nama pelanggan #NEW# — tanpa WA ke customer.
     */
    private function updatePelangganNewTask(string $phone, string $phoneEsc, array $session): void
    {
        if ((int) ($session['butuh_update_nama'] ?? 0) !== 1) {
            echo json_encode(['ok' => 0, 'msg' => 'Task update nama sudah tidak pending']);
            return;
        }

        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        if ($idPelanggan <= 0) {
            echo json_encode(['ok' => 0, 'msg' => 'id_pelanggan kosong']);
            return;
        }

        $nama = trim((string) ($_POST['nama_pelanggan'] ?? ''));
        $nama = trim(preg_replace('/\s+/u', ' ', $nama));
        if ($nama === '' || mb_strlen($nama) < 2) {
            echo json_encode(['ok' => 0, 'msg' => 'Nama pelanggan wajib diisi']);
            return;
        }
        if (mb_strlen($nama) > 80) {
            $nama = mb_substr($nama, 0, 80);
        }

        $cabangId = $this->currentCabangId();
        $pel = $this->db(0)->get_where_row(
            'pelanggan',
            'id_pelanggan = ' . $idPelanggan
            . ($cabangId > 0 ? (' AND id_cabang = ' . (int) $cabangId) : '')
        );
        if (!is_array($pel) || empty($pel['id_pelanggan'])) {
            echo json_encode(['ok' => 0, 'msg' => 'Pelanggan tidak ditemukan di cabang ini']);
            return;
        }

        $updPel = $this->db(0)->update(
            'pelanggan',
            ['nama_pelanggan' => $nama],
            'id_pelanggan = ' . $idPelanggan
        );
        if (!empty($updPel['errno'])) {
            echo json_encode(['ok' => 0, 'msg' => $updPel['error'] ?? 'Gagal update nama pelanggan']);
            return;
        }

        $summary = trim((string) ($session['summary'] ?? ''));
        $summary .= ($summary !== '' ? ' | ' : '') . 'Petugas update nama=' . $nama;

        $up = $this->db(100)->update(
            'wa_kurir_session',
            [
                'butuh_update_nama' => 0,
                'summary' => mb_substr($summary, 0, 800),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            "phone = '" . $phoneEsc . "'"
        );
        if (!empty($up['errno'])) {
            echo json_encode(['ok' => 0, 'msg' => $up['error'] ?? 'Gagal update session']);
            return;
        }

        // Sengaja tanpa WA — customer tidak diberitahu soal update nama
        $pendingCount = $this->syncNotifTaskCount();
        echo json_encode([
            'ok' => 1,
            'wa_ok' => 0,
            'count' => $pendingCount,
            'msg' => 'Nama pelanggan disimpan',
        ]);
    }

    private function respondAfterUpdate(string $phone, string $replyText): void
    {
        $sendWa = !isset($_POST['send_wa']) || (int) $_POST['send_wa'] === 1;
        $pendingCount = $this->syncNotifTaskCount();
        $replyText = $this->sanitizeUtf8($replyText);

        $waOk = 0;
        $msg = 'Task selesai';
        if ($sendWa) {
            try {
                $waResult = $this->helper('Notif')->send_wa($phone, $replyText, 'free');
                if (empty($waResult['status'])) {
                    $this->echoJson([
                        'ok' => 1,
                        'wa_ok' => 0,
                        'count' => $pendingCount,
                        'msg' => 'State tersimpan, tapi WA gagal: ' . ($waResult['error'] ?? 'unknown'),
                        'reply_text' => $replyText,
                    ]);
                    return;
                }
                $waOk = 1;
                $msg = 'Task selesai & WA terkirim';
            } catch (\Throwable $e) {
                $this->echoJson([
                    'ok' => 1,
                    'wa_ok' => 0,
                    'count' => $pendingCount,
                    'msg' => 'State tersimpan, tapi WA gagal: ' . $e->getMessage(),
                    'reply_text' => $replyText,
                ]);
                return;
            }
        }

        $this->echoJson([
            'ok' => 1,
            'wa_ok' => $waOk,
            'count' => $pendingCount,
            'msg' => $msg,
            'reply_text' => $replyText,
        ]);
    }

    /** Buang output tak sengaja (BOM/warning) lalu kirim JSON bersih. */
    private function echoJson(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        // Sanitasi rekursif string agar json_encode tidak gagal
        array_walk_recursive($data, function (&$v) {
            if (is_string($v)) {
                $v = $this->sanitizeUtf8($v);
            }
        });
        $json = json_encode($data, $flags);
        if ($json === false) {
            $json = json_encode([
                'ok' => (int) ($data['ok'] ?? 0),
                'items' => [],
                'count' => (int) ($data['count'] ?? 0),
                'wa_ok' => (int) ($data['wa_ok'] ?? 0),
                'msg' => 'JSON encode failed: ' . json_last_error_msg(),
            ], $flags);
        }
        echo $json !== false ? $json : '{"ok":0,"items":[],"msg":"JSON encode failed"}';
    }

    private function sanitizeUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_convert_encoding')) {
            $clean = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            if (is_string($clean) && $clean !== '') {
                return $clean;
            }
        }
        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (is_string($clean)) {
                return $clean;
            }
        }
        return $text;
    }

    private function syncNotifTaskCount(): int
    {
        $n = 0;
        try {
            $cabangId = $this->currentCabangId();
            if ($cabangId <= 0) {
                $_SESSION[URL::SESSID]['notif_task_count'] = 0;
                return 0;
            }
            $rows = $this->db(100)->query_array(
                'SELECT
                    SUM(CASE WHEN butuh_estimasi = 1 AND estimasi_jam IS NULL THEN 1 ELSE 0 END) AS c
                 FROM wa_estimasi_session
                 WHERE expires_at > NOW() AND id_cabang = ' . (int) $cabangId
            );
            $n = (int) ($rows[0]['c'] ?? 0);
            try {
                $newNamaRows = $this->db(100)->query_array(
                    'SELECT COUNT(*) AS c FROM wa_kurir_session
                     WHERE expires_at > NOW() AND id_cabang = ' . (int) $cabangId . '
                       AND butuh_update_nama = 1'
                );
                $n += (int) ($newNamaRows[0]['c'] ?? 0);
            } catch (\Throwable $e) {
                // kolom belum ada
            }
            try {
                $n += count($this->getOpenPermintaanTasks());
            } catch (\Throwable $e) {
                // skip
            }
        } catch (\Throwable $e) {
            $n = $this->getNotifTaskCountFromSession();
        }
        $_SESSION[URL::SESSID]['notif_task_count'] = $n;
        return $n;
    }

    private function getNotifTaskCountFromSession(): int
    {
        return (int) ($_SESSION[URL::SESSID]['notif_task_count'] ?? 0);
    }

    private function currentCabangId(): int
    {
        return (int) ($this->id_cabang ?? $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0);
    }

    private function pendingWhereSql(): string
    {
        $cabangId = $this->currentCabangId();
        $cabangSql = $cabangId > 0
            ? ('id_cabang = ' . (int) $cabangId)
            : '1=0';

        return "expires_at > NOW()
            AND {$cabangSql}
            AND (butuh_estimasi = 1 AND estimasi_jam IS NULL)";
    }

    /**
     * Label waktu request customer untuk notifikasi.
     */
    private function formatRequestWaktuLabel($tanggal, float $jam): string
    {
        $tgl = $tanggal ? (string) $tanggal : date('Y-m-d');
        return $this->formatEstimasiWaktuCustomer($tgl, $jam);
    }

    /**
     * Opsi tanggal: hari ini, besok, lusa.
     * @return array<int, array{value:string,label:string}>
     */
    private function estimasiDateOptions(): array
    {
        $out = [];
        $labels = ['Hari ini', 'Besok', 'Lusa'];
        for ($i = 0; $i <= 2; $i++) {
            $out[] = [
                'value' => date('Y-m-d', strtotime("+{$i} day")),
                'label' => $labels[$i],
            ];
        }
        return $out;
    }

    /**
     * @return array{tanggal:string,jam:float}|null
     */
    private function parseEstimasiTanggalJamFromPost(): ?array
    {
        $tgl = trim((string) ($_POST['estimasi_tanggal'] ?? ''));
        $jam = $this->parseEstimasiJam(trim((string) ($_POST['estimasi_jam'] ?? '')));
        if ($tgl === '' || $jam === null) {
            return null;
        }
        if (!$this->isEstimasiTanggalAllowed($tgl)) {
            return null;
        }
        return ['tanggal' => $tgl, 'jam' => $jam];
    }

    private function isEstimasiTanggalAllowed(string $ymd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return false;
        }
        $today = date('Y-m-d');
        $max = date('Y-m-d', strtotime('+2 day'));
        return $ymd >= $today && $ymd <= $max;
    }

    /** "hari ini" / "besok" dari tanggal Y-m-d (selain itu: tanggal j/n). */
    private function labelHariIniAtauBesok(?string $tanggal): string
    {
        $ymd = substr(trim((string) $tanggal), 0, 10);
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($ymd === '' || $ymd === $today) {
            return 'hari ini';
        }
        if ($ymd === $tomorrow) {
            return 'besok';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return 'tanggal ' . date('j/n', strtotime($ymd));
        }
        return 'hari ini';
    }

    /**
     * Label waktu untuk customer: "hari ini jam 14:00" / "besok jam …" / "lusa jam …"
     * Tanpa kata "sekitar".
     */
    private function formatEstimasiWaktuCustomer(string $tanggal, float $jam): string
    {
        $jamLabel = $this->formatEstimasiJamLabel($jam);
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $lusa = date('Y-m-d', strtotime('+2 day'));

        if ($tanggal === $today) {
            $hari = 'hari ini';
        } elseif ($tanggal === $tomorrow) {
            $hari = 'besok';
        } elseif ($tanggal === $lusa) {
            $hari = 'lusa';
        } else {
            $hari = 'tanggal ' . date('j/n/Y', strtotime($tanggal));
        }

        return "{$hari} jam {$jamLabel}";
    }

    private function extractCustomerMessage(string $summary, string $fallback): string
    {
        if (preg_match('/\[pesan\]\s*(.+?)(?:\s*\||$)/u', $summary, $m)) {
            return trim($m[1]);
        }
        return $fallback;
    }

    /**
     * Ambil jam spesifik dari teks customer ("jam 10", "jam 10.00", "jam 10:30").
     */
    private function parseJamFromCustomerText(string $text): ?string
    {
        if (preg_match('/\bjam\s*(\d{1,2})([.:](\d{2}))?\b/iu', $text, $m)) {
            if (preg_match('/\bjam\s*(brp|brpa|berapa)\b/iu', $text)) {
                return null;
            }
            $h = (int) $m[1];
            $min = isset($m[3]) ? (int) $m[3] : 0;
            if ($h > 23 || $min > 59) {
                return null;
            }
            return sprintf('%02d:%02d', $h, $min);
        }
        return null;
    }

    /** @return array{nama:string} */
    private function resolvePelangganByPhone(string $phone): array
    {
        $nama = '';
        try {
            $resolved = $this->helper('PelangganByPhone')->resolve($phone);
            if (!empty($resolved['nama_pelanggan'])) {
                $nama = trim((string) $resolved['nama_pelanggan']);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if ($nama === '') {
            $nomor = $this->phoneKey($phone);
            if ($nomor !== '') {
                try {
                    $conv = $this->db(100)->query_array(
                        "SELECT contact_name FROM wa_conversations
                         WHERE " . $this->waNumberLikeSql($nomor) . "
                         ORDER BY id DESC LIMIT 1"
                    );
                    if (!empty($conv[0]['contact_name'])) {
                        $nama = trim((string) $conv[0]['contact_name']);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        return ['nama' => $nama];
    }

    private function displayPhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone);
        if (strlen($d) >= 10 && substr($d, 0, 2) === '62') {
            return '0' . substr($d, 2);
        }
        return $phone;
    }

    private function parseEstimasiJam(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h > 23 || $min > 59) {
                return null;
            }
            return (float) sprintf('%d.%02d', $h, $min);
        }
        if (preg_match('/^(\d{1,2})[.,](\d{1,2})$/', $raw, $m)) {
            $h = (int) $m[1];
            $min = (int) str_pad($m[2], 2, '0', STR_PAD_RIGHT);
            if ($h > 23 || $min > 59) {
                return null;
            }
            return (float) sprintf('%d.%02d', $h, $min);
        }
        if (preg_match('/^\d{1,2}$/', $raw)) {
            $h = (int) $raw;
            if ($h > 23) {
                return null;
            }
            return (float) sprintf('%d.00', $h);
        }

        return null;
    }

    private function formatEstimasiJamLabel(float $jam): string
    {
        $s = number_format($jam, 2, '.', '');
        $parts = explode('.', $s);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        return sprintf('%02d:%02d', $h, $m);
    }

    private function formatEstimasiJamLabelFromDb($jam): ?string
    {
        if ($jam === null || $jam === '') {
            return null;
        }
        return $this->formatEstimasiJamLabel((float) $jam);
    }

    /** Kartu notif PERMINTAAN: status open + notify_expires_at 24 jam (bukan expires_at 1 jam). */
    private function permintaanNotifyOpenWhereSql(): string
    {
        return "status = 'open' AND notify_expires_at > NOW()";
    }

    /**
     * Kartu notif PERMINTAAN: hanya wa_permintaan_session (bukan conversation case 3).
     * Kartu hilang sendiri setelah notify_expires_at.
     * @return list<array<string,mixed>>
     */
    private function getOpenPermintaanTasks(): array
    {
        $cabangId = $this->currentCabangId();
        if ($cabangId <= 0) {
            return [];
        }

        $kodeCabang = strtoupper(trim((string) ($this->dCabang['kode_cabang'] ?? '')));
        $items = [];
        $seenPhones = [];

        try {
            $rows = $this->db(100)->query_array(
                "SELECT phone, id_pelanggan, id_cabang, status, summary, raw_log, updated_at, expires_at, notify_expires_at
                 FROM wa_permintaan_session
                 WHERE " . $this->permintaanNotifyOpenWhereSql() . "
                 ORDER BY updated_at DESC
                 LIMIT 100"
            );
            if (!is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $waPhone = preg_replace('/[^0-9]/', '', (string) ($row['phone'] ?? ''));
                if ($waPhone === '') {
                    continue;
                }
                $phoneKey = $this->phoneKey($waPhone);
                if (isset($seenPhones[$phoneKey])) {
                    continue;
                }

                $sessCabang = (int) ($row['id_cabang'] ?? 0);
                if ($sessCabang > 0 && $sessCabang !== $cabangId) {
                    continue;
                }
                if ($sessCabang <= 0) {
                    $fakeRow = [
                        'code' => '',
                        'cust_id' => $row['id_pelanggan'] ?? null,
                        'wa_number' => $row['phone'] ?? '',
                    ];
                    if (!$this->permintaanBelongsToCabang($fakeRow, $cabangId, $kodeCabang, $waPhone)) {
                        continue;
                    }
                }

                $seenPhones[$phoneKey] = true;

                $pel = null;
                if (!empty($row['id_pelanggan'])) {
                    try {
                        $pelRow = $this->db(0)->get_where_row(
                            'pelanggan',
                            'id_pelanggan = ' . (int) $row['id_pelanggan'] . ' AND id_cabang = ' . (int) $cabangId
                        );
                        if (is_array($pelRow) && !empty($pelRow['id_pelanggan'])) {
                            $pel = $pelRow;
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
                if (!$pel) {
                    $pel = $this->resolvePelangganInCabangByPhone($waPhone, $cabangId);
                }

                $nama = trim((string) ($pel['nama_pelanggan'] ?? ''));
                if ($nama === '') {
                    $resolved = $this->resolvePelangganByPhone($waPhone);
                    $nama = trim((string) ($resolved['nama'] ?? ''));
                }

                $summary = '';
                try {
                    $summary = $this->resolvePermintaanAiSummary(
                        $waPhone,
                        (string) ($row['summary'] ?? ''),
                        (string) ($row['raw_log'] ?? ''),
                        true
                    );
                } catch (\Throwable $eSum) {
                    $summary = $this->permintaanShortFallbackFromLines(
                        $this->permintaanCollectChatLines($waPhone, (string) ($row['raw_log'] ?? ''))
                    );
                }
                if ($summary === '') {
                    $summary = 'Permintaan pelanggan';
                }
                $items[] = [
                    'task_type' => 'permintaan',
                    'task_id' => 'permintaan:' . $waPhone,
                    'phone' => $waPhone,
                    'phone_display' => $this->displayPhone($waPhone),
                    'id_penjualan' => null,
                    'id_pelanggan' => isset($pel['id_pelanggan'])
                        ? (int) $pel['id_pelanggan']
                        : (isset($row['id_pelanggan']) ? (int) $row['id_pelanggan'] : null),
                    'customer_message' => $summary,
                    'request_text' => $summary,
                    'updated_at' => $row['updated_at'] ?? null,
                    'expires_at' => $row['notify_expires_at'] ?? $row['expires_at'] ?? null,
                    'nama' => $nama !== '' ? $nama : 'Pelanggan',
                ];
            }
        } catch (\Throwable $e) {
            // tabel belum ada — tidak ada kartu permintaan
        }

        return $items;
    }

    /** Buang prefix preview CRM Fonnte "i- "/"o- ". */
    private function normalizePermintaanDisplayText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^[io]\-\s+/iu', '', $text) ?? $text;
        return trim($text);
    }

    /**
     * Isi notif = SATU kalimat ringkasan AI (bukan dump seluruh chat).
     * Jika summary session sudah bagus, pakai itu; kalau mentah/dump → AI rangkum lalu simpan.
     */
    private function resolvePermintaanAiSummary(
        string $phone,
        string $sessionSummary,
        string $rawLog,
        bool $persistToSession
    ): string {
        $sessionSummary = $this->normalizePermintaanDisplayText($sessionSummary);
        $lines = $this->permintaanCollectChatLines($phone, $rawLog);

        if ($sessionSummary !== '' && !$this->permintaanLooksLikeRawDump($sessionSummary, $lines)) {
            return mb_substr($sessionSummary, 0, 280);
        }

        if ($lines === []) {
            return $sessionSummary !== '' ? mb_substr($sessionSummary, 0, 280) : '';
        }

        $ai = $this->permintaanAiRangkumLines($lines, $sessionSummary);
        if ($ai === '') {
            // Fallback singkat: jangan dump semua; ambil inti 1–2 klausa terakhir yang unik
            $ai = $this->permintaanShortFallbackFromLines($lines);
        }

        if ($persistToSession && $ai !== '') {
            $this->permintaanPersistSessionSummary($phone, $ai, $lines);
        }

        return mb_substr($ai, 0, 280);
    }

    /** @return list<string> */
    private function permintaanCollectChatLines(string $phone, string $rawLog = ''): array
    {
        $texts = [];

        foreach (preg_split('/\n---\n/', trim($rawLog)) ?: [] as $line) {
            $t = $this->normalizePermintaanDisplayText(trim((string) $line));
            if ($t !== '' && mb_strlen($t) >= 2) {
                $texts = $this->permintaanAppendUniqueLine($texts, $t);
            }
        }

        try {
            $msgs = $this->helper('WaChatHistory')->fetchMessages($this->db(100), $phone, 20);
            $cutoff = time() - (90 * 60);
            if (is_array($msgs)) {
                foreach ($msgs as $m) {
                    if (($m['sender'] ?? '') !== 'customer') {
                        continue;
                    }
                    $t = $this->normalizePermintaanDisplayText(trim((string) ($m['text'] ?? '')));
                    if ($t === '' || mb_strlen($t) < 2) {
                        continue;
                    }
                    $at = strtotime((string) ($m['time'] ?? '')) ?: 0;
                    if ($at > 0 && $at < $cutoff) {
                        continue;
                    }
                    $texts = $this->permintaanAppendUniqueLine($texts, mb_substr($t, 0, 280));
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (count($texts) > 10) {
            $texts = array_slice($texts, -10);
        }

        return $texts;
    }

    /** @param list<string> $list @return list<string> */
    private function permintaanAppendUniqueLine(array $list, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return $list;
        }
        foreach ($list as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($text)) {
                return $list;
            }
            // near-dup: satu mengandung yang lain hampir penuh
            if (mb_stripos($existing, $text) !== false || mb_stripos($text, $existing) !== false) {
                if (mb_strlen($text) > mb_strlen($existing)) {
                    // replace shorter with longer
                    $key = array_search($existing, $list, true);
                    if ($key !== false) {
                        $list[(int) $key] = $text;
                    }
                }
                return $list;
            }
        }
        $list[] = $text;
        return $list;
    }

    /** @param list<string> $lines */
    private function permintaanLooksLikeRawDump(string $summary, array $lines): bool
    {
        if (preg_match('/^[io]\-\s+/iu', $summary)) {
            return true;
        }
        if (substr_count($summary, ';') >= 1) {
            return true;
        }
        if (count($lines) >= 2) {
            $last = mb_strtolower(trim((string) end($lines)));
            if ($last !== '' && mb_strtolower($summary) === $last) {
                return true;
            }
        }
        // terlalu mirip dump gabungan
        if (count($lines) >= 2 && mb_strlen($summary) > 90) {
            $hits = 0;
            foreach ($lines as $line) {
                if (mb_stripos($summary, mb_substr($line, 0, min(20, mb_strlen($line)))) !== false) {
                    $hits++;
                }
            }
            if ($hits >= 2) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $lines */
    private function permintaanAiRangkumLines(array $lines, string $prevSummary = ''): string
    {
        if ($lines === []) {
            return '';
        }

        $chatBlock = '';
        foreach ($lines as $i => $line) {
            $chatBlock .= ($i + 1) . '. ' . $line . "\n";
        }

        $system = "Kamu merangkum permintaan pelanggan laundry menjadi SATU kalimat singkat Bahasa Indonesia.\n"
            . "WAJIB gabungkan SEMUA poin chat (bukan hanya yang terakhir, jangan salin mentah).\n"
            . "Contoh input: dulukan baju sekolah + seragam merah putih + sekalian pramuka "
            . "→ output: \"Dulukan seragam merah putih dan baju pramuka\".\n"
            . "Tanpa sapaan kak/bu/pak, tanpa emoji, tanpa tanda kutip, tanpa nomor, tanpa titik koma daftar chat.\n"
            . "HANYA teks ringkasan, maksimal 180 karakter.";

        $user = "Ringkasan lama (opsional): " . ($prevSummary !== '' && !$this->permintaanLooksLikeRawDump($prevSummary, $lines) ? $prevSummary : '(abaikan)') . "\n\n"
            . "Chat pelanggan:\n{$chatBlock}\n"
            . "Tulis SATU ringkasan permintaan:";

        try {
            /** @var AiChat $ai */
            $ai = $this->helper('AiChat');
            $out = trim($ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 100, 0.2, 10));
            $out = $this->normalizePermintaanDisplayText($out);
            $out = trim($out, " \t\n\r\0\x0B\"'");
            $out = preg_replace('/\s+/u', ' ', $out) ?? $out;
            if ($out === '' || $this->permintaanLooksLikeRawDump($out, $lines)) {
                return '';
            }
            return mb_substr($out, 0, 280);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @param list<string> $lines */
    private function permintaanShortFallbackFromLines(array $lines): string
    {
        if ($lines === []) {
            return '';
        }
        // Satu kalimat sederhana dari item unik, bukan dump penuh
        $joined = implode(' dan ', array_slice($lines, -3));
        $joined = preg_replace('/\b(kak|gan|bos|min)\b/iu', '', $joined) ?? $joined;
        $joined = preg_replace('/\s+/u', ' ', trim($joined)) ?? $joined;
        return mb_substr($joined, 0, 220);
    }

    /** @param list<string> $lines */
    private function permintaanPersistSessionSummary(string $phone, string $summary, array $lines): void
    {
        $phoneEsc = $this->db(100)->escape($phone);
        $raw = implode("\n---\n", $lines);
        try {
            $nomor = $this->phoneKey($phone);
            $existingRows = $this->db(100)->query_array(
                "SELECT phone FROM wa_permintaan_session
                 WHERE " . $this->permintaanNotifyOpenWhereSql() . " AND ("
                . "phone = '" . $phoneEsc . "'"
                . ($nomor !== ''
                    ? (" OR " . $this->phoneLikeSql($nomor, 'phone'))
                    : '')
                . ") LIMIT 1"
            );
            if (!empty($existingRows[0]['phone'])) {
                $this->db(100)->update(
                    'wa_permintaan_session',
                    [
                        'summary' => mb_substr($summary, 0, 500),
                        'raw_log' => mb_substr($raw, 0, 8000),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    "phone = '" . $this->db(100)->escape((string) $existingRows[0]['phone']) . "'"
                );
            }
        } catch (\Throwable $e) {
            // tabel belum ada
        }
    }

    private function conversationHasOpenCase3($convCase): bool
    {
        if ($convCase === null || $convCase === '' || $convCase === '0' || $convCase === '[]') {
            return false;
        }

        $raw = is_string($convCase) ? $convCase : json_encode($convCase);
        if (is_numeric(trim($raw)) && (int) trim($raw) === 3) {
            return true;
        }

        $cases = json_decode($raw, true);
        if (!is_array($cases)) {
            return false;
        }
        if (isset($cases['case'])) {
            $cases = [$cases];
        }

        foreach ($cases as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((int) ($c['case'] ?? 0) === 3 && ($c['status'] ?? 'open') !== 'closed') {
                return true;
            }
        }

        return false;
    }

    private function permintaanBelongsToCabang(array $row, int $cabangId, string $kodeCabang, string $waPhone): bool
    {
        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        if ($kodeCabang !== '' && $code !== '' && $code !== '00' && $code === $kodeCabang) {
            return true;
        }

        $custId = (int) ($row['cust_id'] ?? 0);
        if ($custId > 0) {
            try {
                $pel = $this->db(0)->get_where_row(
                    'pelanggan',
                    'id_pelanggan = ' . $custId . ' AND id_cabang = ' . (int) $cabangId
                );
                if (is_array($pel) && !empty($pel['id_pelanggan'])) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $this->resolvePelangganInCabangByPhone($waPhone, $cabangId) !== null;
    }

    /** @return array<string,mixed>|null */
    private function resolvePelangganInCabangByPhone(string $waPhone, int $cabangId): ?array
    {
        try {
            $row = $this->helper('PelangganByPhone')->rowInCabang($waPhone, $cabangId);
            if (is_array($row) && !empty($row['id_pelanggan'])) {
                return $row;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /** Riwayat chat WA (JSON) — data via Helper\WaChatHistory, render via window.MdlWaChat. */
    public function chat_history()
    {
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        $hp = (string) ($_POST['hp'] ?? $_POST['phone'] ?? $_GET['phone'] ?? '');
        $limit = (int) ($_POST['limit'] ?? $_GET['limit'] ?? 30);
        try {
            $helper = $this->helper('WaChatHistory');
            $messages = $helper->fetchMessages($this->db(100), $hp, $limit > 0 ? $limit : 30);
            echo json_encode(['ok' => 1, 'messages' => $messages]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => 0, 'messages' => [], 'msg' => $e->getMessage()]);
        }
    }

    private function pushToWebSocket(array $data)
    {
        $url = 'http://127.0.0.1:3003/incoming';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /** Nasional 852… setelah buang +62 / 62 / 0. */
    private function phoneKey($phone): string
    {
        $this->helper('PelangganByPhone');

        return PelangganByPhone::key((string) $phone);
    }

    /** sapaan_stats → regex nama kontak → kak. */
    private function resolveSapaanForPhone(string $phone): string
    {
        $this->helper('SapaanGreeting');
        try {
            return SapaanGreeting::resolve($this->db(100), $phone);
        } catch (\Throwable $e) {
            return 'kak';
        }
    }

    private function waNumberLikeSql(string $nomor, string $column = 'wa_number'): string
    {
        return $this->phoneLikeSql($nomor, $column);
    }

    private function phoneLikeSql(string $nomor, string $column): string
    {
        $this->helper('PelangganByPhone');
        $esc = $this->db(100)->escape($nomor);

        return PelangganByPhone::likeSql($esc, $column);
    }
}
