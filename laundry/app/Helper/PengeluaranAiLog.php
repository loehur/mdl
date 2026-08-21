<?php

/**
 * Log khusus analisa AI pengeluaran → logs/YYYY-MM-DD/laundry_pengeluaran_ai.log
 */
class PengeluaranAiLog
{
    private static function write(string $text): void
    {
        if (!class_exists('Log', false)) {
            require_once dirname(__DIR__) . '/Models/Log.php';
        }
        Log::write($text, 'laundry', 'pengeluaran_ai');
    }

    public static function info(string $step, array $ctx = []): void
    {
        self::write(self::format('INFO', $step, $ctx));
    }

    public static function error(string $step, array $ctx = []): void
    {
        self::write(self::format('ERROR', $step, $ctx));
    }

    /** @param array<string,mixed> $ctx */
    private static function format(string $level, string $step, array $ctx): string
    {
        $parts = ['[' . $level . ']', $step];
        foreach ($ctx as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
            $s = trim((string) $v);
            if (strlen($s) > 500) {
                $s = substr($s, 0, 497) . '…';
            }
            $parts[] = $k . '=' . $s;
        }

        return implode(' | ', $parts);
    }
}
