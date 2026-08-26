<?php

/**
 * Notifikasi task permintaan / pelanggan baru untuk petugas Laundry.
 * Estimasi selesai (dulu wa_estimasi_session) sudah digabung ke wa_permintaan_session via intent PERMINTAAN.
 */
class Estimasi extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    /**
     * Jumlah task pending (permintaan + pelanggan baru).
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
            $items = [];

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

            // Pelanggan baru dulu, lalu permintaan
            usort($items, function ($a, $b) {
                $rank = function ($t) {
                    if ($t === 'pelanggan_new') return -1;
                    if ($t === 'permintaan') return 0;
                    return 1;
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
     * Tandai permintaan pelanggan sudah ditangani: set status='fulfilled' di wa_permintaan_session
     * dan resolve case 3 di wa_conversations.
     * POST: phone
     */
    public function selesaiPermintaan()
    {
        if (ob_get_level() === 0) {
            ob_start();
        }
        $this->session_cek();
        header('Content-Type: application/json; charset=utf-8');

        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($phone === '') {
            $this->echoJson(['ok' => 0, 'msg' => 'Nomor WA wajib']);
            return;
        }

        try {
            $db = $this->db(100);
            $phoneEsc = $db->escape($phone);
            $nomor = $this->phoneKey($phone);
            $phoneWhere = "phone = '" . $phoneEsc . "'";
            if ($nomor !== '') {
                $phoneWhere = '(' . $phoneWhere . ' OR ' . $this->phoneLikeSql($nomor, 'phone') . ')';
            }

            // Tutup semua record terbuka untuk nomor ini
            $updateResult = $db->update(
                'wa_permintaan_session',
                ['status' => 'fulfilled'],
                '(' . $this->permintaanNotifyOpenWhereSql() . ') AND (' . $phoneWhere . ')'
            );
            $sessionClosed = (int) ($updateResult['affected_rows'] ?? 0) > 0;

            // Resolve case 3 di wa_conversations (jika ada)
            $conv = null;
            if ($nomor !== '') {
                $convRows = $db->query_array(
                    "SELECT id, wa_number, conv_case FROM wa_conversations
                     WHERE " . $this->waNumberLikeSql($nomor) . "
                     LIMIT 1"
                );
                $conv = is_array($convRows[0] ?? null) ? $convRows[0] : null;
            }
            if (!$conv) {
                $conv = $db->get_where_row(
                    'wa_conversations',
                    "wa_number = '" . $phoneEsc . "'"
                );
            }
            $caseClosed = false;
            if (!empty($conv['id'])) {
                $cases = json_decode($conv['conv_case'] ?? '[]', true);
                $changed = false;
                if (is_array($cases)) {
                    foreach ($cases as &$c) {
                        if ((int) ($c['case'] ?? 0) === 3 && ($c['status'] ?? 'open') !== 'closed') {
                            $c['status'] = 'closed';
                            $changed = true;
                        }
                    }
                    unset($c);
                }
                if ($changed) {
                    $db->update(
                        'wa_conversations',
                        ['conv_case' => json_encode($cases)],
                        "id = " . (int) $conv['id']
                    );
                    $caseClosed = true;
                    $this->pushToWebSocket([
                        'type'      => 'case_resolved',
                        'phone'     => $conv['wa_number'],
                        'case'      => 3,
                        'target_id' => '0',
                        'sender_id' => $_SESSION[URL::SESSID]['user']['id_user'] ?? 'system',
                    ]);
                }
            }

            if (!$sessionClosed && !$caseClosed) {
                $this->echoJson(['ok' => 0, 'msg' => 'Permintaan tidak ditemukan atau sudah selesai']);
                return;
            }

            $count = $this->syncNotifTaskCount();
            $this->echoJson(['ok' => 1, 'msg' => 'Permintaan ditandai selesai', 'count' => $count]);
        } catch (\Throwable $e) {
            $this->echoJson(['ok' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Update satu task.
     * POST: phone, task_type=pelanggan_new, nama_pelanggan?, send_wa?
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
        if ($taskType !== 'pelanggan_new') {
            $this->echoJson(['ok' => 0, 'msg' => 'task_type wajib: pelanggan_new']);
            return;
        }

        $phoneEsc = $this->db(100)->escape($phone);

        $session = $this->db(100)->get_where_row(
            'wa_kurir_session',
            "phone = '" . $phoneEsc . "' AND expires_at > NOW()"
        );
        if (!is_array($session) || empty($session['phone'])) {
            echo json_encode(['ok' => 0, 'msg' => 'Session kurir tidak ditemukan / kedaluwarsa']);
            return;
        }
        $this->updatePelangganNewTask($phone, $phoneEsc, $session);
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
            $n = 0;
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
                // Badge hanya butuh angka. Jangan membangun kartu notifikasi di sini:
                // getOpenPermintaanTasks() dapat mencari pelanggan dan merangkum chat/AI.
                $n += $this->countOpenPermintaanTasks();
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

    /** Jumlah kartu permintaan aktif untuk badge, tanpa lookup pelanggan atau ringkasan AI. */
    private function countOpenPermintaanTasks(): int
    {
        $cabangId = $this->currentCabangId();
        if ($cabangId <= 0) {
            return 0;
        }

        $rows = $this->db(100)->query_array(
            "SELECT COUNT(DISTINCT phone) AS c
             FROM wa_permintaan_session
             WHERE " . $this->permintaanNotifyOpenWhereSql() . "
               AND id_cabang = " . (int) $cabangId . "
               AND notify_expires_at > NOW()"
        );

        return (int) ($rows[0]['c'] ?? 0);
    }

    private function currentCabangId(): int
    {
        return (int) ($this->id_cabang ?? $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0);
    }

    /** Kartu notif PERMINTAAN mengikuti state sampai petugas menanganinya. */
    private function permintaanNotifyOpenWhereSql(): string
    {
        return "status = 'open'";
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

    /**
     * Kartu notif PERMINTAAN: hanya wa_permintaan_session (bukan conversation case 3).
     * Kartu tetap terlihat selama status permintaan masih open.
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
                   AND notify_expires_at > NOW()
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
                    // Session lama bisa membawa cabang dari transaksi sebelumnya.
                    // Validasi ulang nomor WA agar kartu tidak hilang di cabang pelanggan yang sebenarnya.
                    $fakeRow = [
                        'code' => '',
                        'cust_id' => $row['id_pelanggan'] ?? null,
                        'wa_number' => $row['phone'] ?? '',
                    ];
                    if (!$this->permintaanBelongsToCabang($fakeRow, $cabangId, $kodeCabang, $waPhone)) {
                        continue;
                    }
                } elseif ($sessCabang <= 0) {
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
                    $summary = 'Permintaan atau pertanyaan pelanggan.';
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

    /** Muat helper ringkasan permintaan (shared dengan API/CRM). */
    private function loadPermintaanSummaryHelper(): void
    {
        if (!class_exists('\\App\\Helpers\\Laundry\\PermintaanSummaryHelper', false)) {
            require_once dirname(__DIR__, 3) . '/api/app/Helpers/Laundry/PermintaanSummaryHelper.php';
        }
    }

    /** Buang prefix preview CRM Fonnte "i- "/"o- ". */
    private function normalizePermintaanDisplayText(string $text): string
    {
        $this->loadPermintaanSummaryHelper();

        return \App\Helpers\Laundry\PermintaanSummaryHelper::stripPreviewPrefix($text);
    }

    /**
     * Isi notif = SATU kalimat ringkasan AI (pertanyaan + permintaan), bukan dump seluruh chat.
     * Jika summary session sudah bagus, pakai itu; kalau mentah/dump → AI rangkum lalu simpan.
     */
    private function resolvePermintaanAiSummary(
        string $phone,
        string $sessionSummary,
        string $rawLog,
        bool $persistToSession
    ): string {
        $this->loadPermintaanSummaryHelper();
        $H = \App\Helpers\Laundry\PermintaanSummaryHelper::class;

        $sessionSummary = $H::stripPreviewPrefix($sessionSummary);
        $lines = $this->permintaanCollectChatLines($phone, $rawLog);

        if ($sessionSummary !== '' && !$H::looksLikeRawDump($sessionSummary, $lines)) {
            return $H::finalize($sessionSummary, 280);
        }

        if ($lines === []) {
            return $sessionSummary !== '' ? $H::finalize($sessionSummary, 280) : '';
        }

        $ai = $this->permintaanAiRangkumLines($lines, $sessionSummary);
        if ($ai === '') {
            $ai = $H::shortFallbackFromLines($lines);
        }

        $ai = $H::finalize($ai, 280);

        if ($persistToSession && $ai !== '') {
            $this->permintaanPersistSessionSummary($phone, $ai, $lines);
        }

        return $ai;
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
        $this->loadPermintaanSummaryHelper();

        return \App\Helpers\Laundry\PermintaanSummaryHelper::looksLikeRawDump($summary, $lines);
    }

    /** @param list<string> $lines */
    private function permintaanAiRangkumLines(array $lines, string $prevSummary = ''): string
    {
        if ($lines === []) {
            return '';
        }

        $this->loadPermintaanSummaryHelper();
        $H = \App\Helpers\Laundry\PermintaanSummaryHelper::class;

        $chatBlock = '';
        foreach ($lines as $i => $line) {
            $chatBlock .= ($i + 1) . '. ' . $line . "\n";
        }

        $system = $H::aiSystemPrompt(180);
        $user = 'Ringkasan lama (opsional): '
            . ($prevSummary !== '' && !$H::looksLikeRawDump($prevSummary, $lines) ? $prevSummary : '(abaikan)')
            . "\n\nChat pelanggan:\n{$chatBlock}\nTulis SATU ringkasan formal:";

        try {
            /** @var AiChat $ai */
            $ai = $this->helper('AiChat');
            $out = trim($ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 100, 0.2, 10));
            $out = $H::finalize($out, 280);
            if ($out === '' || $H::looksLikeRawDump($out, $lines)) {
                return '';
            }
            return $out;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @param list<string> $lines */
    private function permintaanShortFallbackFromLines(array $lines): string
    {
        $this->loadPermintaanSummaryHelper();

        return \App\Helpers\Laundry\PermintaanSummaryHelper::shortFallbackFromLines($lines);
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
