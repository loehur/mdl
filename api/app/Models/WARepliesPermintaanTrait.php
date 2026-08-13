<?php

namespace App\Models;

use App\Core\DB;

/**
 * Intent PERMINTAAN — session standby (tanpa autoreply), AI merangkum isi permintaan.
 * Notifikasi laundry baca dari wa_permintaan_session.summary.
 */
trait WARepliesPermintaanTrait
{
    private const PERMINTAAN_SESSION_TTL_MINUTES = 60; // 1 jam (sama intent state lain)

    private function getPermintaanSession(string $waNumber): ?array
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return null;
        }
        try {
            $res = DB::getInstance(0)->query(
                'SELECT * FROM wa_permintaan_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if (!$res || $res->num_rows() === 0) {
                return null;
            }
            $row = (array) $res->row();
            if (($row['status'] ?? '') !== 'open') {
                return null;
            }
            if (empty($row['expires_at']) || strtotime($row['expires_at']) < time()) {
                $this->clearPermintaanSession($waNumber);
                return null;
            }
            return $row;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('getPermintaanSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return null;
        }
    }

    private function savePermintaanSession(string $waNumber, array $data): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + (self::PERMINTAAN_SESSION_TTL_MINUTES * 60));

        try {
            $db = DB::getInstance(0);
            $existing = null;
            $res = $db->query('SELECT * FROM wa_permintaan_session WHERE phone = ? LIMIT 1', [$phone]);
            if ($res && $res->num_rows() > 0) {
                $existing = (array) $res->row();
            }

            $merge = static function (string $key, $default = null) use ($data, $existing) {
                if (array_key_exists($key, $data)) {
                    return $data[$key];
                }
                return $existing[$key] ?? $default;
            };

            $summary = $merge('summary');
            if (is_string($summary) && mb_strlen($summary) > 2000) {
                $summary = mb_substr($summary, 0, 2000);
            }
            $rawLog = $merge('raw_log');
            if (is_string($rawLog) && mb_strlen($rawLog) > 8000) {
                $rawLog = mb_substr($rawLog, -8000);
            }

            $db->query(
                'INSERT INTO wa_permintaan_session
                 (phone, id_pelanggan, id_cabang, status, summary, raw_log,
                  reject_reason, reject_alt, reply_text, updated_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    id_pelanggan = VALUES(id_pelanggan),
                    id_cabang = VALUES(id_cabang),
                    status = VALUES(status),
                    summary = VALUES(summary),
                    raw_log = VALUES(raw_log),
                    reject_reason = VALUES(reject_reason),
                    reject_alt = VALUES(reject_alt),
                    reply_text = VALUES(reply_text),
                    updated_at = VALUES(updated_at),
                    expires_at = VALUES(expires_at)',
                [
                    $phone,
                    $merge('id_pelanggan'),
                    $merge('id_cabang'),
                    $merge('status', 'open'),
                    $summary,
                    $rawLog,
                    $merge('reject_reason'),
                    $merge('reject_alt'),
                    $merge('reply_text'),
                    $now,
                    $expires,
                ]
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('savePermintaanSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    private function clearPermintaanSession(string $waNumber): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        try {
            DB::getInstance(0)->query('DELETE FROM wa_permintaan_session WHERE phone = ?', [$phone]);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('clearPermintaanSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    /**
     * Intent tegas yang memutus follow-up session PERMINTAAN (session tetap open di DB,
     * hanya tidak consume pesan — biarkan routing intent lain).
     */
    private function messageBreaksPermintaanSession(string $text, array $keywordConfig): bool
    {
        if (preg_match('/\b(bon|bill|bil{1,}|tagihan|nota|invoice|pricelist|price\s*list)\b/iu', $text)) {
            return true;
        }

        $breakout = [
            'TAGIHAN',
            'NOTA',
            'STATUS',
            'HARGA',
            'HARGA_PAKET',
            'HARGA_PAKET_D',
            'REKENING',
            'LOKASI',
            'MINTA_JEMPUT_ANTAR',
            'ESTIMASI_SELESAI',
            'AMBIL_LEWAT_TUTUP',
            'JAM_OPERASIONAL',
        ];
        foreach ($breakout as $code) {
            $patterns = $keywordConfig[$code]['patterns'] ?? [];
            foreach ($patterns as $pat) {
                $pat = trim((string) $pat);
                if ($pat === '') {
                    continue;
                }
                $ok = @preg_match('/' . $pat . '/iu', $text);
                if ($ok === 1) {
                    // Kecuali pola MINTA yang sebenarnya permintaan ambil pakaian dulu
                    if ($code === 'MINTA_JEMPUT_ANTAR' && $this->messageIsPermintaanAmbilPakaianDulu($text)) {
                        continue;
                    }
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handler PERMINTAAN: upsert 1 session, AI rangkum dari SELURUH chat inbound sesi, TANPA autoreply.
     *
     * @return bool true = dikonsumsi
     */
    private function handlePermintaan($phoneIn, $waNumber, $textBody = '')
    {
        if ($this->intentLabMode) {
            $this->logAutoreplyTrace($waNumber, 'PERMINTAAN', 'lab_skip_session');
            return true;
        }

        $msg = trim((string) $textBody);
        $session = $this->getPermintaanSession($waNumber);

        $idPelanggan = $session['id_pelanggan'] ?? null;
        $idCabang = $session['id_cabang'] ?? null;
        if (empty($idPelanggan) || empty($idCabang)) {
            $meta = $this->resolvePermintaanPelangganMeta($phoneIn, $waNumber);
            if ($meta['id_pelanggan'] !== null) {
                $idPelanggan = $meta['id_pelanggan'];
            }
            if ($meta['id_cabang'] !== null) {
                $idCabang = $meta['id_cabang'];
            }
        }

        // Pelanggan baru / belum terdaftar: jangan buat/update session (case 3 tetap dari caller)
        if (empty($idPelanggan)) {
            if ($session !== null) {
                $this->clearPermintaanSession($waNumber);
            }
            $this->logAutoreplyTrace($waNumber, 'PERMINTAAN', 'skip_session_no_pelanggan');
            return true;
        }

        $prevSummary = trim((string) ($session['summary'] ?? ''));
        $prevRaw = trim((string) ($session['raw_log'] ?? ''));

        // Sumber utama: riwayat inbound DB (ycloud + fonnte) agar multi-bubble selalu masuk ringkasan
        $inbound = $this->permintaanFetchRecentInboundTexts($waNumber, 20, 90);
        if ($msg !== '') {
            $inbound = $this->permintaanAppendUniqueText($inbound, $msg);
        }
        // Cadangan: gabung raw_log session lama jika DB belum sempat menyimpan bubble terbaru
        if ($prevRaw !== '') {
            foreach (preg_split('/\n---\n/', $prevRaw) ?: [] as $line) {
                $inbound = $this->permintaanAppendUniqueText($inbound, trim((string) $line));
            }
        }

        $newRaw = $inbound === []
            ? mb_substr($msg, 0, 500)
            : implode("\n---\n", array_map(static function ($t) {
                return mb_substr($t, 0, 500);
            }, $inbound));

        $summary = $this->permintaanAiSummarize($waNumber, $prevSummary, $newRaw);
        if ($summary === '') {
            $summary = $this->permintaanFallbackSummary($newRaw, $prevSummary, $msg);
        }
        // Jangan simpan preview CRM "i- …" sebagai summary
        $summary = $this->permintaanStripPreviewPrefix($summary);

        $this->savePermintaanSession($waNumber, [
            'id_pelanggan' => $idPelanggan,
            'id_cabang' => $idCabang,
            'status' => 'open',
            'summary' => $summary,
            'raw_log' => $newRaw,
        ]);

        $this->logAutoreplyTrace(
            $waNumber,
            'PERMINTAAN',
            'session_upsert lines=' . count($inbound) . ' summary=' . mb_substr($summary, 0, 120)
        );

        return true;
    }

    /**
     * Ambil teks inbound pelanggan terbaru (urut lama→baru), strip prefix preview i-/o-.
     *
     * @return list<string>
     */
    private function permintaanFetchRecentInboundTexts(string $waNumber, int $limit = 20, int $withinMinutes = 90): array
    {
        $phones = $this->waMessagesOutPhoneVariants($waNumber);
        if ($phones === []) {
            $norm = $this->normalizePhoneNumber($waNumber);
            if ($norm) {
                $phones = [$norm];
            }
        }
        if ($phones === []) {
            return [];
        }

        $limit = max(3, min(40, $limit));
        $withinMinutes = max(5, min(24 * 60, $withinMinutes));
        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $params = array_merge($phones, [$withinMinutes, $limit]);
        $out = [];

        try {
            $db = DB::getInstance(0);
            $rows = $db->query(
                "SELECT text AS body, created_at AS at FROM wa_messages_in
                 WHERE phone IN ($placeholders)
                   AND created_at >= (NOW() - INTERVAL ? MINUTE)
                 ORDER BY created_at DESC
                 LIMIT ?",
                $params
            );
            if ($rows) {
                foreach ($rows->result_array() as $r) {
                    $body = $this->permintaanStripPreviewPrefix(trim((string) ($r['body'] ?? '')));
                    if ($body === '' || mb_strlen($body) < 2) {
                        continue;
                    }
                    $out[] = ['at' => (string) ($r['at'] ?? ''), 'body' => mb_substr($body, 0, 500)];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $db = DB::getInstance(0);
            $rows = $db->query(
                "SELECT text AS body, created_at AS at FROM wa_fonnte_messages_in
                 WHERE phone IN ($placeholders)
                   AND created_at >= (NOW() - INTERVAL ? MINUTE)
                 ORDER BY created_at DESC
                 LIMIT ?",
                $params
            );
            if ($rows) {
                foreach ($rows->result_array() as $r) {
                    $body = $this->permintaanStripPreviewPrefix(trim((string) ($r['body'] ?? '')));
                    if ($body === '' || mb_strlen($body) < 2) {
                        continue;
                    }
                    $out[] = ['at' => (string) ($r['at'] ?? ''), 'body' => mb_substr($body, 0, 500)];
                }
            }
        } catch (\Throwable $e) {
            // tabel fonnte belum ada
        }

        if ($out === []) {
            return [];
        }

        usort($out, static function ($a, $b) {
            return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });

        $texts = [];
        foreach ($out as $row) {
            $texts = $this->permintaanAppendUniqueText($texts, (string) ($row['body'] ?? ''));
        }

        // Ambil paling akhir (bubble terbaru) jika kebanyakan
        if (count($texts) > $limit) {
            $texts = array_slice($texts, -$limit);
        }

        return $texts;
    }

    /** @param list<string> $list @return list<string> */
    private function permintaanAppendUniqueText(array $list, string $text): array
    {
        $text = $this->permintaanStripPreviewPrefix(trim($text));
        if ($text === '') {
            return $list;
        }
        foreach ($list as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($text)) {
                return $list;
            }
        }
        $list[] = $text;
        return $list;
    }

    private function permintaanStripPreviewPrefix(string $text): string
    {
        $text = trim($text);
        // Preview CRM Fonnte: "i- …" / "o- …"
        $text = preg_replace('/^[io]\-\s+/iu', '', $text) ?? $text;
        return trim($text);
    }

    /**
     * @return array{id_pelanggan:?int,id_cabang:?int}
     */
    private function resolvePermintaanPelangganMeta(string $phoneIn, string $waNumber): array
    {
        $out = ['id_pelanggan' => null, 'id_cabang' => null];
        try {
            $db1 = DB::getInstance(1);
            $rows = $this->queryPelangganRowsByWaNumber(
                $db1,
                $phoneIn,
                $waNumber,
                'id_pelanggan, id_cabang'
            );
            if (empty($rows)) {
                return $out;
            }
            $ids = array_values(array_unique(array_filter(array_map(
                static function ($r) {
                    return (int) ($r['id_pelanggan'] ?? 0);
                },
                $rows
            ))));
            if ($ids === []) {
                return $out;
            }
            $idsIn = implode(',', $ids);
            $sale = $db1->query(
                "SELECT id_pelanggan, id_cabang FROM sale
                 WHERE bin = 0 AND id_pelanggan IN ($idsIn)
                 ORDER BY insertTime DESC LIMIT 1"
            )->row_array();
            if (!empty($sale['id_pelanggan'])) {
                $out['id_pelanggan'] = (int) $sale['id_pelanggan'];
                $out['id_cabang'] = isset($sale['id_cabang']) ? (int) $sale['id_cabang'] : null;
                return $out;
            }
            $first = $rows[0];
            $out['id_pelanggan'] = (int) ($first['id_pelanggan'] ?? 0) ?: null;
            $out['id_cabang'] = isset($first['id_cabang']) ? (int) $first['id_cabang'] : null;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('resolvePermintaanPelangganMeta: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
        return $out;
    }

    /**
     * AI: satukan SEMUA chat di session jadi 1 kalimat permintaan (jangan hanya chat terakhir).
     *
     * @param string $prevSummary ringkasan lama (opsional)
     * @param string $fullRawLog  seluruh raw_log termasuk pesan baru (dipisah ---)
     */
    private function permintaanAiSummarize(string $waNumber, string $prevSummary, string $fullRawLog): string
    {
        $fullRawLog = trim($fullRawLog);
        if ($fullRawLog === '') {
            return trim($prevSummary);
        }

        $chatLines = preg_split('/\n---\n/', $fullRawLog) ?: [$fullRawLog];
        $chatLines = array_values(array_filter(array_map('trim', $chatLines), static function ($l) {
            return $l !== '';
        }));
        $chatBlock = '';
        foreach ($chatLines as $i => $line) {
            $chatBlock .= ($i + 1) . '. ' . $line . "\n";
        }

        $system = "Kamu merangkum permintaan pelanggan laundry menjadi SATU kalimat singkat, jelas, dan rapi dalam Bahasa Indonesia.\n"
            . "WAJIB gabungkan SEMUA pesan chat bernomor di bawah (bukan hanya yang terakhir).\n"
            . "Contoh: (1) dulukan baju sekolah (2) seragam merah putih dulu (3) sekalian pramuka "
            . "→ \"Dulukan seragam merah putih dan baju pramuka\".\n"
            . "Sertakan aksi (dulukan/prioritas/ambil dulu/dll) + SEMUA item yang disebut di pesan mana pun.\n"
            . "Jangan buang detail dari pesan sebelumnya. Jangan salin mentah satu bubble terakhir saja.\n"
            . "Tanpa sapaan (kak/bu/pak), tanpa emoji, tanpa tanda kutip, tanpa nomor urut, tanpa prefix i-/o-.\n"
            . "Jawaban HANYA teks ringkasan, maksimal ~220 karakter.";

        $user = "Ringkasan sebelumnya (opsional): " . ($prevSummary !== '' ? $prevSummary : '(belum ada)') . "\n\n"
            . "Semua chat pelanggan di sesi ini (urut waktu, WAJIB digabung):\n"
            . $chatBlock . "\n"
            . "Tulis SATU ringkasan permintaan yang mencakup SEMUA item/aksi di atas:";

        try {
            $raw = $this->executeOpenAIRequestWithMessages(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                180,
                $waNumber
            );
            $out = trim(preg_replace('/\s+/u', ' ', (string) $raw));
            $out = trim($out, " \t\n\r\0\x0B\"'");
            $out = preg_replace('/^(baik|oke|ok|siap)[,.]?\s+/iu', '', (string) $out);
            $out = trim((string) $out);
            if ($out === '') {
                return $this->permintaanFallbackSummary($fullRawLog, $prevSummary, '');
            }
            return mb_substr($out, 0, 500);
        } catch (\Throwable $e) {
            $this->logAutoreplyTrace($waNumber, 'PERMINTAAN', 'ai_summary_fail: ' . mb_substr($e->getMessage(), 0, 120));
            return $this->permintaanFallbackSummary($fullRawLog, $prevSummary, '');
        }
    }

    /** Fallback tanpa AI: gabung semua baris raw_log. */
    private function permintaanFallbackSummary(string $fullRawLog, string $prevSummary, string $newMsg): string
    {
        $parts = preg_split('/\n---\n/', trim($fullRawLog)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static function ($l) {
            return $l !== '';
        }));
        if ($parts !== []) {
            return mb_substr(implode('; ', $parts), 0, 500);
        }
        if ($prevSummary !== '') {
            return $newMsg !== ''
                ? mb_substr($prevSummary . '; ' . $newMsg, 0, 500)
                : mb_substr($prevSummary, 0, 500);
        }
        return mb_substr($newMsg, 0, 280);
    }
}
