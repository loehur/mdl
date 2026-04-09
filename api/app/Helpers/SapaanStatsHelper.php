<?php

namespace App\Helpers;

/**
 * Deteksi sapaan pada pesan teks keluar agent & catat ke tabel sapaan_stats.
 */
class SapaanStatsHelper
{
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
                        $ok = $db->update('sapaan_stats', [
                            'jumlah' => (int) $row->jumlah + 1,
                        ], [
                            'wa_number' => $waNumber,
                            'sapaan' => $sapaan,
                        ]);
                        if (!$ok) {
                            self::logDbFailure('update', $db, $waNumber, $sapaan);
                        }
                    } else {
                        $insertId = $db->insert('sapaan_stats', [
                            'wa_number' => $waNumber,
                            'sapaan' => $sapaan,
                            'jumlah' => 1,
                        ]);
                        if ($insertId === false) {
                            self::logDbFailure('insert', $db, $waNumber, $sapaan);
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
        if (class_exists('\Log', false)) {
            \Log::write($line, 'cms_error', 'SapaanStats');
        }
    }

    private static function logThrowable(\Throwable $e, string $waNumber, ?string $sapaan): void
    {
        $s = $sapaan !== null ? "sapaan={$sapaan} " : '';
        $line = "SapaanStats exception [db0 sapaan_stats] wa_number={$waNumber} {$s}| {$e->getMessage()} | {$e->getFile()}:{$e->getLine()}";
        if (class_exists('\Log', false)) {
            \Log::write($line, 'cms_error', 'SapaanStats');
        }
    }

    /**
     * @return list<string>
     */
    private static function keywordsSortedByLength(): array
    {
        $path = __DIR__ . '/../Config/SapaanStatsKeywords.php';
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
     * Token utuh: tidak mengecek substring di tengah kata (mis. "bu" di "buat" tidak dihitung).
     */
    private static function messageContainsToken(string $lowerMessage, string $keyword): bool
    {
        $kw = preg_quote($keyword, '/');

        return (bool) preg_match('/(?<![\p{L}\p{N}])' . $kw . '(?![\p{L}\p{N}])/u', $lowerMessage);
    }
}
