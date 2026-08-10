<?php

/**
 * Notifikasi ESTIMASI_SELESAI untuk petugas Laundry.
 * Tabel wa_estimasi_session di mdl_main → laundry db(100) (= API db(0)).
 */
class Estimasi extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    /**
     * Jumlah task notifikasi yang belum dikerjakan (badge lonceng).
     * Menyimpan ke PHP session — badge turun hanya jika task selesai, bukan saat dibaca.
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
     * Daftar session estimasi yang perlu diisi petugas.
     */
    public function list()
    {
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->db(100)->query_array(
                'SELECT phone, id_penjualan, fase_proses, butuh_estimasi, estimasi_jam,
                        request_text, request_granted, summary, updated_at, expires_at
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
                $items[] = [
                    'phone' => $phone,
                    'phone_display' => $this->displayPhone($phone),
                    'id_penjualan' => isset($row['id_penjualan']) ? (int) $row['id_penjualan'] : null,
                    'fase_proses' => $row['fase_proses'] ?? null,
                    'butuh_estimasi' => (int) ($row['butuh_estimasi'] ?? 0),
                    'estimasi_jam' => $row['estimasi_jam'],
                    'request_text' => $row['request_text'] ?? null,
                    'request_granted' => $row['request_granted'],
                    'summary' => $row['summary'] ?? '',
                    'updated_at' => $row['updated_at'] ?? null,
                    'expires_at' => $row['expires_at'] ?? null,
                    'nama' => $pelanggan['nama'] ?? '',
                    'has_request' => trim((string) ($row['request_text'] ?? '')) !== '',
                ];
            }

            echo json_encode(['ok' => 1, 'items' => $items]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => 0, 'items' => [], 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Update state + kirim WA ke customer.
     * POST: phone, estimasi_jam (HH:MM atau 20.30), request_granted (1|0|''), send_wa (1|0)
     */
    public function update()
    {
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($phone === '') {
            echo json_encode(['ok' => 0, 'msg' => 'Nomor WA wajib']);
            return;
        }

        $phoneEsc = $this->db(100)->escape($phone);
        $session = $this->db(100)->get_where_row(
            'wa_estimasi_session',
            "phone = '" . $phoneEsc . "' AND expires_at > NOW()"
        );
        if (empty($session)) {
            echo json_encode(['ok' => 0, 'msg' => 'Session estimasi tidak ditemukan / sudah expired']);
            return;
        }

        $estimasiRaw = trim((string) ($_POST['estimasi_jam'] ?? ''));
        $estimasiJam = $this->parseEstimasiJam($estimasiRaw);
        if ($estimasiJam === null) {
            echo json_encode(['ok' => 0, 'msg' => 'Estimasi jam wajib (contoh 20:00 atau 20.00)']);
            return;
        }

        $hasRequest = trim((string) ($session['request_text'] ?? '')) !== '';
        $grantedRaw = $_POST['request_granted'] ?? '';
        $requestGranted = null;
        if ($hasRequest) {
            if ($grantedRaw === '' || $grantedRaw === null) {
                echo json_encode(['ok' => 0, 'msg' => 'Pilih Setujui / Tolak untuk permintaan customer']);
                return;
            }
            $requestGranted = ((int) $grantedRaw === 1) ? 1 : 0;
        } elseif ($grantedRaw !== '' && $grantedRaw !== null) {
            $requestGranted = ((int) $grantedRaw === 1) ? 1 : 0;
        }

        $jamLabel = $this->formatEstimasiJamLabel($estimasiJam);
        $idPenjualan = isset($session['id_penjualan']) ? (int) $session['id_penjualan'] : 0;
        $idLabel = $idPenjualan > 0 ? '#' . $idPenjualan : '';
        $sapaan = 'kak';

        if ($hasRequest && $requestGranted === 0) {
            $replyText = "Maaf {$sapaan}, antrian sedang padat, laundry baru selesai sekitar jam {$jamLabel}";
        } elseif ($hasRequest && $requestGranted === 1) {
            $replyText = $idLabel !== ''
                ? "Baik {$sapaan}, permintaan dicatat. Laundry ID {$idLabel} diperkirakan siap sekitar jam {$jamLabel} ya 😊"
                : "Baik {$sapaan}, permintaan dicatat. Diperkirakan siap sekitar jam {$jamLabel} ya 😊";
        } else {
            $replyText = $idLabel !== ''
                ? "Laundry ID {$idLabel} diperkirakan siap sekitar jam {$jamLabel} ya {$sapaan} 😊"
                : "Diperkirakan siap sekitar jam {$jamLabel} ya {$sapaan} 😊";
        }

        $summary = trim((string) ($session['summary'] ?? ''));
        $summary .= ($summary !== '' ? ' | ' : '')
            . 'Petugas set estimasi=' . $jamLabel
            . ($requestGranted === null ? '' : (', granted=' . ($requestGranted ? 'ya' : 'tidak')));

        $set = [
            'estimasi_jam' => $estimasiJam,
            'butuh_estimasi' => 0,
            'summary' => mb_substr($summary, 0, 500),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($requestGranted !== null) {
            $set['request_granted'] = $requestGranted;
        }

        $up = $this->db(100)->update(
            'wa_estimasi_session',
            $set,
            "phone = '" . $phoneEsc . "'"
        );
        if (!empty($up['errno'])) {
            echo json_encode(['ok' => 0, 'msg' => $up['error'] ?? 'Gagal update state']);
            return;
        }

        $sendWa = !isset($_POST['send_wa']) || (int) $_POST['send_wa'] === 1;
        $waResult = null;
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
            'msg' => $sendWa ? 'State diupdate & WA terkirim' : 'State diupdate',
            'reply_text' => $replyText,
        ]);
    }

    /**
     * Hitung ulang task pending → simpan ke session user.
     */
    private function syncNotifTaskCount(): int
    {
        $n = 0;
        try {
            $rows = $this->db(100)->query_array(
                'SELECT COUNT(*) AS c FROM wa_estimasi_session WHERE ' . $this->pendingWhereSql()
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

    private function pendingWhereSql(): string
    {
        return "expires_at > NOW()
            AND (
                (butuh_estimasi = 1 AND estimasi_jam IS NULL)
                OR (
                    request_text IS NOT NULL
                    AND TRIM(request_text) <> ''
                    AND request_granted IS NULL
                )
            )";
    }

    /**
     * @return array{nama:string}
     */
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

    /**
     * Terima "20:30" / "20.30" / "20" → float 20.30 (HH.MM).
     */
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
}
