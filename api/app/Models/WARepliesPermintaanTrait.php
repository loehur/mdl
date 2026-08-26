<?php

namespace App\Models;

use App\Core\DB;
use App\Helpers\Laundry\PermintaanSummaryHelper;

/**
 * Intent PERMINTAAN — session standby (tanpa autoreply), AI merangkum pertanyaan + permintaan pelanggan.
 * expires_at = follow-up chat 1 jam (baris tidak dihapus saat habis).
 * notify_expires_at = kartu notif laundry 24 jam (diset saat buat, tidak di-reset follow-up).
 */
trait WARepliesPermintaanTrait
{
    private static $permintaanSessionTtlMinutes = 60; // jendela follow-up chat
    private static $permintaanNotifyTtlHours = 24; // kartu notif laundry

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
            // State 1 jam habis: jangan consume chat sebagai follow-up, tapi jangan hapus baris (kartu 24 jam).
            if (empty($row['expires_at']) || strtotime($row['expires_at']) < time()) {
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
        if ($this->intentLabMode) {
            return;
        }
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + (self::$permintaanSessionTtlMinutes * 60));
        $notifyExpires = date('Y-m-d H:i:s', time() + (self::$permintaanNotifyTtlHours * 3600));

        try {
            $db = DB::getInstance(0);
            $existing = null;
            $res = $db->query('SELECT * FROM wa_permintaan_session WHERE phone = ? LIMIT 1', [$phone]);
            if ($res && $res->num_rows() > 0) {
                $existing = (array) $res->row();
            }

            $existingNotify = $existing['notify_expires_at'] ?? null;
            if ($existingNotify && strtotime((string) $existingNotify) >= time()) {
                $notifyExpires = $existingNotify;
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
                  reject_reason, reject_alt, reply_text, updated_at, expires_at, notify_expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    expires_at = VALUES(expires_at),
                    notify_expires_at = VALUES(notify_expires_at)',
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
                    $notifyExpires,
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
        if ($this->messageLooksLikeThanksPenutup($text)) {
            return true;
        }

        $breakout = [
            'TAGIHAN',
            'NOTA',
            'STATUS',
            'HARGA',
            'REKENING',
            'LOKASI',
            'KURIR',
            'PERMINTAAN',
            'JAM_OPERASIONAL',
            'SALDO',
            'SALDO_IAK',
            'SALDO_TOKOPAY',
            'SALDO_YCLOUD',
            'INFO_FONNTE',
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
                    if ($code === 'KURIR' && $this->messageIsPermintaanAmbilPakaianDulu($text)) {
                        continue;
                    }
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handler PERMINTAAN: upsert 1 session, AI rangkum pertanyaan + permintaan dari SELURUH chat inbound sesi, TANPA autoreply.
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

        // Kategori dipakai sebagai pagar konteks; AI tetap menyimpan detail penting yang ringkas.
        $relevantIntent = PermintaanSummaryHelper::compactForGroupSummary($newRaw);
        if ($relevantIntent === '') {
            $summary = $prevSummary;
        } else {
            $summary = $this->permintaanAiSummarize($waNumber, $prevSummary, $newRaw);
            if ($summary === '') {
                $summary = PermintaanSummaryHelper::shortFallbackFromLines(
                    preg_split('/\n---\n/', $newRaw) ?: []
                );
            }
            $summary = PermintaanSummaryHelper::finalize($summary, 220);
        }

        $this->savePermintaanSession($waNumber, [
            'id_pelanggan' => $idPelanggan,
            'id_cabang' => $idCabang,
            'status' => 'open',
            'summary' => $summary,
            'raw_log' => $newRaw,
        ]);

        $isNewSession = ($session === null);
        $summaryChanged = !$isNewSession
            && $summary !== ''
            && mb_strtolower(trim($prevSummary)) !== mb_strtolower(trim($summary));
        if ($summary !== '' && ($isNewSession || $summaryChanged)) {
            $this->permintaanForwardToCabangGroup(
                $waNumber,
                !empty($idCabang) ? (int) $idCabang : null,
                $summary,
                !$isNewSession
            );
        }

        $this->logAutoreplyTrace(
            $waNumber,
            'PERMINTAAN',
            'session_upsert lines=' . count($inbound) . ' summary=' . mb_substr($summary, 0, 120)
        );

        return true;
    }

    /**
     * Notif ke group Fonnte cabang (id_group_fonnte), fallback group estimasi global.
     */
    private function permintaanForwardToCabangGroup(
        string $waNumber,
        ?int $idCabang,
        string $summary,
        bool $isUpdate = false
    ): void {
        if ($this->intentLabMode) {
            return;
        }

        if (!class_exists('\\App\\Helpers\\Laundry\\PermintaanNotifyHelper')) {
            require_once __DIR__ . '/../Helpers/Laundry/PermintaanNotifyHelper.php';
        }

        $ok = \App\Helpers\Laundry\PermintaanNotifyHelper::forwardToCabangGroup(
            $waNumber,
            $idCabang,
            $summary,
            $isUpdate
        );

        $groupId = \App\Helpers\Laundry\PermintaanNotifyHelper::resolveFonnteGroupId($idCabang);
        $this->logAutoreplyTrace(
            $waNumber,
            'PERMINTAAN',
            'forward_group cabang=' . ($idCabang ?? 0) . ' target=' . $groupId . ' '
            . ($ok ? 'ok' : 'fail')
            . ($isUpdate ? ' update=1' : '')
        );
    }

    private function permintaanFormatGroupNama(string $waNumber): string
    {
        $nama = trim($this->getContactNameForGreeting($waNumber));
        if ($nama === '') {
            $nama = 'Pelanggan';
        }

        return mb_strtoupper($nama, 'UTF-8');
    }

    /**
     * @deprecated use PermintaanNotifyHelper::resolveFonnteGroupId
     */
    private function resolvePermintaanFonnteGroupId(?int $idCabang): string
    {
        if (!class_exists('\\App\\Helpers\\Laundry\\PermintaanNotifyHelper')) {
            require_once __DIR__ . '/../Helpers/Laundry/PermintaanNotifyHelper.php';
        }

        return \App\Helpers\Laundry\PermintaanNotifyHelper::resolveFonnteGroupId($idCabang);
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
     * AI: satukan SEMUA chat di session jadi 1 kalimat pertanyaan/permintaan (jangan hanya chat terakhir).
     *
     * @param string $prevSummary ringkasan lama (opsional)
     * @param string $fullRawLog  seluruh raw_log termasuk pesan baru (dipisah ---)
     */
    private function permintaanAiSummarize(string $waNumber, string $prevSummary, string $fullRawLog): string
    {
        $fullRawLog = trim($fullRawLog);
        if ($fullRawLog === '') {
            return PermintaanSummaryHelper::finalize($prevSummary, 500);
        }

        $chatLines = preg_split('/\n---\n/', $fullRawLog) ?: [$fullRawLog];
        $chatLines = array_values(array_filter(array_map('trim', $chatLines), static function ($l) {
            return $l !== '';
        }));
        $chatBlock = '';
        foreach ($chatLines as $i => $line) {
            $chatBlock .= ($i + 1) . '. ' . $line . "\n";
        }

        $system = PermintaanSummaryHelper::aiSystemPrompt(220);

        $user = "Ringkasan sebelumnya (opsional): " . ($prevSummary !== '' ? $prevSummary : '(belum ada)') . "\n\n"
            . "Semua chat pelanggan di sesi ini (urut waktu, WAJIB digabung):\n"
            . $chatBlock . "\n"
            . "Tulis SATU ringkasan formal:";

        try {
            $raw = $this->executeOpenAIRequestWithMessages(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                180,
                $waNumber
            );
            $out = PermintaanSummaryHelper::finalize((string) $raw, 500);
            if ($out === '') {
                return PermintaanSummaryHelper::fallbackFromRawLog($fullRawLog, $prevSummary, '');
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logAutoreplyTrace($waNumber, 'PERMINTAAN', 'ai_summary_fail: ' . mb_substr($e->getMessage(), 0, 120));
            return PermintaanSummaryHelper::fallbackFromRawLog($fullRawLog, $prevSummary, '');
        }
    }
}
