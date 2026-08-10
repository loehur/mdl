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
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->db(100)->query_array(
                'SELECT phone, id_penjualan, id_cabang, fase_proses, butuh_estimasi, estimasi_tanggal, estimasi_jam,
                        request_text, request_tanggal, request_jam, request_granted, summary, updated_at, expires_at
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
                $requestText = trim((string) ($row['request_text'] ?? ''));
                $requestJam = $row['request_jam'] ?? null;
                $needGrant = $requestText !== ''
                    && $requestJam !== null && $requestJam !== ''
                    && ($row['request_granted'] === null || $row['request_granted'] === '');

                if ($butuhEstimasi) {
                    $pesan = $this->extractCustomerMessage($row['summary'] ?? '', '');
                    $items[] = array_merge($base, [
                        'task_type' => 'estimasi',
                        'task_id' => 'estimasi:' . $phone,
                        'customer_message' => $pesan,
                        'request_waktu_label' => null,
                        'request_jam_label' => null,
                        'request_text' => null,
                    ]);
                }

                if ($needGrant) {
                    $reqTgl = $row['request_tanggal'] ?? null;
                    $waktuLabel = $this->formatRequestWaktuLabel($reqTgl, (float) $requestJam);
                    $items[] = array_merge($base, [
                        'task_type' => 'grant',
                        'task_id' => 'grant:' . $phone,
                        'customer_message' => $requestText,
                        'request_text' => $requestText,
                        'request_tanggal' => $reqTgl,
                        'request_jam' => $requestJam,
                        'request_jam_label' => $this->formatEstimasiJamLabelFromDb($requestJam),
                        'request_waktu_label' => $waktuLabel,
                    ]);
                }
            }

            // Estimasi dulu, lalu grant
            usort($items, function ($a, $b) {
                $wa = ($a['task_type'] ?? '') === 'grant' ? 1 : 0;
                $wb = ($b['task_type'] ?? '') === 'grant' ? 1 : 0;
                if ($wa !== $wb) {
                    return $wa <=> $wb;
                }
                return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
            });

            echo json_encode(['ok' => 1, 'items' => $items]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => 0, 'items' => [], 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Update satu task.
     * POST: phone, task_type=estimasi|grant, estimasi_jam?, request_granted?, send_wa?
     */
    public function update()
    {
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        $phone = trim((string) ($_POST['phone'] ?? ''));
        $taskType = strtolower(trim((string) ($_POST['task_type'] ?? '')));
        if ($phone === '') {
            echo json_encode(['ok' => 0, 'msg' => 'Nomor WA wajib']);
            return;
        }
        if (!in_array($taskType, ['estimasi', 'grant'], true)) {
            echo json_encode(['ok' => 0, 'msg' => 'task_type wajib: estimasi atau grant']);
            return;
        }

        $phoneEsc = $this->db(100)->escape($phone);
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

        $this->updateGrantTask($phone, $phoneEsc, $session);
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
        $sapaan = 'kak';
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

    private function updateGrantTask(string $phone, string $phoneEsc, array $session): void
    {
        $requestText = trim((string) ($session['request_text'] ?? ''));
        $requestJam = $session['request_jam'] ?? null;
        if ($requestText === ''
            || $requestJam === null || $requestJam === ''
            || !($session['request_granted'] === null || $session['request_granted'] === '')) {
            echo json_encode(['ok' => 0, 'msg' => 'Task grant sudah tidak pending / jam request tidak ada']);
            return;
        }

        $grantedRaw = $_POST['request_granted'] ?? '';
        if ($grantedRaw === '' || $grantedRaw === null) {
            echo json_encode(['ok' => 0, 'msg' => 'Pilih Setujui atau Tolak']);
            return;
        }
        $requestGranted = ((int) $grantedRaw === 1) ? 1 : 0;

        $sapaan = 'kak';
        $idPenjualan = isset($session['id_penjualan']) ? (int) $session['id_penjualan'] : 0;
        $idLabel = $idPenjualan > 0 ? '#' . $idPenjualan : '';

        $set = [
            'request_granted' => $requestGranted,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($requestGranted === 1) {
            // Setujui: pakai waktu REQUEST customer — bukan data estimasi petugas
            $reqTgl = $session['request_tanggal'] ?? null;
            if (!$reqTgl) {
                $reqTgl = date('Y-m-d'); // default hari ini jika customer tidak sebut tanggal
            }
            $waktuLabel = $this->formatEstimasiWaktuCustomer((string) $reqTgl, (float) $requestJam);
            $replyText = $idLabel !== ''
                ? "Baik {$sapaan}, petugas telah mengonfirmasi. Sesuai permintaan, Laundry ID {$idLabel} diperkirakan siap {$waktuLabel} ya {$sapaan} 😊"
                : "Baik {$sapaan}, petugas telah mengonfirmasi. Sesuai permintaan, diperkirakan siap {$waktuLabel} ya {$sapaan} 😊";
        } else {
            // Tolak: petugas WAJIB isi tanggal+jam alternatif (bukan ambil dari estimasi)
            $parsed = $this->parseEstimasiTanggalJamFromPost();
            if ($parsed === null) {
                echo json_encode(['ok' => 0, 'msg' => 'Untuk tolak, isi tanggal & jam alternatif']);
                return;
            }
            $waktuLabel = $this->formatEstimasiWaktuCustomer($parsed['tanggal'], $parsed['jam']);
            $replyText = "Maaf {$sapaan}, antrian sedang padat, laundry diperkirakan siap {$waktuLabel}";
            // simpan alternatif di kolom estimasi (jawaban petugas), terpisah dari request customer
            $set['estimasi_tanggal'] = $parsed['tanggal'];
            $set['estimasi_jam'] = $parsed['jam'];
            $set['butuh_estimasi'] = 0;
        }

        $summary = trim((string) ($session['summary'] ?? ''));
        $summary .= ($summary !== '' ? ' | ' : '')
            . 'Petugas grant=' . ($requestGranted ? 'ya' : 'tidak');

        $set['summary'] = mb_substr($summary, 0, 500);

        $up = $this->db(100)->update(
            'wa_estimasi_session',
            $set,
            "phone = '" . $phoneEsc . "'"
        );
        if (!empty($up['errno'])) {
            echo json_encode(['ok' => 0, 'msg' => $up['error'] ?? 'Gagal update']);
            return;
        }

        $this->respondAfterUpdate($phone, $replyText);
    }

    private function respondAfterUpdate(string $phone, string $replyText): void
    {
        $sendWa = !isset($_POST['send_wa']) || (int) $_POST['send_wa'] === 1;
        $pendingCount = $this->syncNotifTaskCount();

        if ($sendWa) {
            $waResult = $this->helper('Notif')->send_wa($phone, $replyText, 'free');
            if (empty($waResult['status'])) {
                echo json_encode([
                    'ok' => 1,
                    'wa_ok' => 0,
                    'count' => $pendingCount,
                    'msg' => 'State tersimpan, tapi WA gagal: ' . ($waResult['error'] ?? 'unknown'),
                    'reply_text' => $replyText,
                ]);
                return;
            }
        }

        echo json_encode([
            'ok' => 1,
            'wa_ok' => $sendWa ? 1 : 0,
            'count' => $pendingCount,
            'msg' => $sendWa ? 'Task selesai & WA terkirim' : 'Task selesai',
            'reply_text' => $replyText,
        ]);
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
                    SUM(
                        CASE WHEN butuh_estimasi = 1 AND estimasi_jam IS NULL THEN 1 ELSE 0 END
                        + CASE WHEN request_jam IS NOT NULL AND request_granted IS NULL THEN 1 ELSE 0 END
                    ) AS c
                 FROM wa_estimasi_session
                 WHERE expires_at > NOW() AND id_cabang = ' . (int) $cabangId
            );
            $n = (int) ($rows[0]['c'] ?? 0);
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
            AND (
                (butuh_estimasi = 1 AND estimasi_jam IS NULL)
                OR (
                    request_jam IS NOT NULL
                    AND request_granted IS NULL
                )
            )";
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
        $digits = preg_replace('/\D+/', '', $phone);
        $last8 = strlen($digits) >= 8 ? substr($digits, -8) : '';
        $nama = '';

        if ($last8 !== '') {
            try {
                $conv = $this->db(100)->query_array(
                    "SELECT contact_name FROM wa_conversations
                     WHERE RIGHT(REPLACE(REPLACE(REPLACE(wa_number,'+',''),'-',''),' ',''), 8) = '"
                    . $this->db(100)->escape($last8) . "'
                     ORDER BY id DESC LIMIT 1"
                );
                if (!empty($conv[0]['contact_name'])) {
                    $nama = trim((string) $conv[0]['contact_name']);
                }
            } catch (\Throwable $e) {
                // ignore
            }

            if ($nama === '') {
                try {
                    $pel = $this->db(0)->query_array(
                        "SELECT nama_pelanggan FROM pelanggan
                         WHERE RIGHT(REPLACE(REPLACE(REPLACE(nomor_pelanggan,'+',''),'-',''),' ',''), 8) = '"
                        . $this->db(0)->escape($last8) . "'
                         ORDER BY id_pelanggan ASC LIMIT 1"
                    );
                    if (!empty($pel[0]['nama_pelanggan'])) {
                        $nama = trim((string) $pel[0]['nama_pelanggan']);
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
}
