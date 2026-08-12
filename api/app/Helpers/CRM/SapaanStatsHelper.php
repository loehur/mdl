<?php

namespace App\Helpers\CRM;

/**
 * Deteksi sapaan pada pesan teks keluar agent & catat ke tabel sapaan_stats.
 * Hanya outbound human (sender_code terisi & bukan AR) yang dihitung.
 */
class SapaanStatsHelper
{
    /** Kode pengirim untuk autoreply / bot (yCloud & Fonnte). */
    public const SENDER_CODE_AUTOREPLY = 'AR';

    /**
     * @return list<string> daftar keyword (canonical) yang muncul sebagai token utuh di pesan
     */
    public static function findSapaanInMessage(string $message): array
    {
        $msg = mb_strtolower(trim($message), 'UTF-8');
        if ($msg === '') {
            return [];
        }

        $keywords = self::keywordsSortedByLength();
        $found = [];

        foreach ($keywords as $kw) {
            if ($kw === '') {
                continue;
            }
            if (self::messageContainsToken($msg, $kw)) {
                $found[$kw] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * Human = sender_code terisi dan bukan kode autoreply.
     */
    public static function isHumanSenderCode(?string $senderCode): bool
    {
        $c = trim((string) $senderCode);
        if ($c === '') {
            return false;
        }
        $u = strtoupper($c);

        return !in_array($u, [self::SENDER_CODE_AUTOREPLY, '-AI', 'AI'], true);
    }

    public static function isAutoreplySenderCode(?string $senderCode): bool
    {
        return !self::isHumanSenderCode($senderCode);
    }

    /**
     * Catat sapaan hanya untuk outbound human.
     *
     * @param object $db \App\Core\DB (CRM / wa: biasanya DB index 0)
     */
    public static function recordStatsIfHuman($db, string $waNumber, string $message, ?string $senderCode): void
    {
        if (!self::isHumanSenderCode($senderCode)) {
            return;
        }
        self::recordStats($db, $waNumber, $message);
    }

    /**
     * @param object $db \App\Core\DB (CRM / wa: biasanya DB index 0)
     */
    public static function recordStats($db, string $waNumber, string $message): void
    {
        try {
            $tokens = self::findSapaanInMessage($message);
            if ($tokens === []) {
                return;
            }

            foreach ($tokens as $sapaan) {
                try {
                    $row = $db->get_where('sapaan_stats', [
                        'wa_number' => $waNumber,
                        'sapaan' => $sapaan,
                    ], 1)->row();

                    if ($row) {
                        $newJumlah = (int) $row->jumlah + 1;
                        $ok = $db->update('sapaan_stats', [
                            'jumlah' => $newJumlah,
                        ], [
                            'wa_number' => $waNumber,
                            'sapaan' => $sapaan,
                        ]);
                        if (!$ok) {
                            self::logDbFailure('update', $db, $waNumber, $sapaan);
                        } else {
                            self::logLine("ok update wa_number={$waNumber} sapaan={$sapaan} jumlah={$newJumlah}", 'cms', 'SapaanStats');
                        }
                    } else {
                        $insertId = $db->insert('sapaan_stats', [
                            'wa_number' => $waNumber,
                            'sapaan' => $sapaan,
                            'jumlah' => 1,
                        ]);
                        if ($insertId === false) {
                            self::logDbFailure('insert', $db, $waNumber, $sapaan);
                        } else {
                            self::logLine("ok insert id={$insertId} wa_number={$waNumber} sapaan={$sapaan}", 'cms', 'SapaanStats');
                        }
                    }
                } catch (\Throwable $e) {
                    self::logThrowable($e, $waNumber, $sapaan);
                }
            }
        } catch (\Throwable $e) {
            self::logThrowable($e, $waNumber, null);
        }
    }

    /**
     * class_exists(Log, false) sering false di request pertama → log tidak pernah tertulis; load eksplisit.
     * Output: api/logs/Y-m-d/cms_sapaanstats.log (sukses, app=cms) dan cms_error_sapaanstats.log (gagal).
     */
    private static function ensureLogClass(): void
    {
        if (!class_exists('Log', false)) {
            $f = __DIR__ . '/Log.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
    }

    private static function logLine(string $text, string $app = 'cms_error', string $controller = 'SapaanStats'): void
    {
        self::ensureLogClass();
        if (class_exists('Log', false)) {
            \Log::write($text, $app, $controller);
        } else {
            error_log('[SapaanStats] ' . $text);
        }
    }

    /**
     * insert/update mengembalikan false tanpa exception — catat errno MySQL untuk debug (tabel hilang, kolom salah, dll.).
     */
    private static function logDbFailure(string $op, $db, string $waNumber, string $sapaan): void
    {
        $mysql = '';
        if (is_object($db) && method_exists($db, 'conn')) {
            $c = $db->conn();
            if ($c instanceof \mysqli) {
                $mysql = $c->error . ' (errno ' . $c->errno . ')';
            }
        }
        $line = "SapaanStats {$op} failed [db0 sapaan_stats] wa_number={$waNumber} sapaan={$sapaan} | {$mysql}";
        self::logLine($line, 'cms_error', 'SapaanStats');
    }

    private static function logThrowable(\Throwable $e, string $waNumber, ?string $sapaan): void
    {
        $s = $sapaan !== null ? "sapaan={$sapaan} " : '';
        $line = "SapaanStats exception [db0 sapaan_stats] wa_number={$waNumber} {$s}| {$e->getMessage()} | {$e->getFile()}:{$e->getLine()}";
        self::logLine($line, 'cms_error', 'SapaanStats');
    }

    /**
     * @return list<string>
     */
    private static function keywordsSortedByLength(): array
    {
        $path = __DIR__ . '/../../Config/SapaanStatsKeywords.php';
        if (!is_file($path)) {
            return [];
        }
        $cfg = require $path;
        $list = $cfg['keywords'] ?? [];
        if (!is_array($list)) {
            return [];
        }
        $list = array_values(array_unique(array_map('strval', $list)));
        usort($list, static function (string $a, string $b): int {
            return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
        });

        return $list;
    }

    /**
     * Token sapaan utuh saja (bukan substring kata).
     * "butuh" ≠ "bu"; "bu," / "bu!" / "bu." boleh; "Halo bu" / "bu Ani" boleh.
     */
    private static function messageContainsToken(string $lowerMessage, string $keyword): bool
    {
        $kw = preg_quote($keyword, '/');

        // Batas kiri/kanan: bukan huruf/angka Unicode (spasi, simbol, awal/akhir string OK).
        return (bool) preg_match('/(?<![\p{L}\p{N}])' . $kw . '(?![\p{L}\p{N}])/u', $lowerMessage);
    }
}
