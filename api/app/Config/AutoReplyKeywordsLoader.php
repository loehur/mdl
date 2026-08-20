<?php
namespace App\Config;

/**
 * Load AutoReply keyword config dari DB mdl_main saja (wajib).
 * Cache in-memory + version check via wa_autoreply_meta.cache_version.
 */
class AutoReplyKeywordsLoader
{
    /** @var array<string, mixed>|null */
    private static $config = null;

    /** @var string|null */
    private static $version = null;

    /**
     * @return array<string, array{patterns: list<string>, ai_prompt?: string, case?: int|null, notify?: bool, chat_maxlength?: int}>
     */
    public static function all(): array
    {
        try {
            $version = self::readCacheVersion();
            if (self::$config !== null && self::$version === $version) {
                return self::$config;
            }

            $fromDb = self::loadFromDatabase();
            self::$config = $fromDb;
            self::$version = $version;
            return self::$config;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('AutoReplyKeywordsLoader: ' . $e->getMessage(), 'wa_error', 'KeywordsLoader');
            }
            // Jangan silent-fallback ke file — kembalikan cache lama jika ada, else []
            if (self::$config !== null) {
                return self::$config;
            }
            return [];
        }
    }

    /** Kosongkan cache in-memory saja (tanpa bump cache_version). */
    public static function clearCache(): void
    {
        self::$config = null;
        self::$version = null;
    }

    /** Paksa reload (setelah admin save dari proses yang sama). */
    public static function invalidate(): void
    {
        self::clearCache();
        try {
            self::bumpCacheVersion();
        } catch (\Throwable $e) {
            // ignore — next all() will re-read
        }
    }

    public static function bumpCacheVersion(): void
    {
        $db = \App\Core\DB::getInstance(0);
        $row = $db->query(
            "SELECT meta_value FROM wa_autoreply_meta WHERE meta_key = 'cache_version' LIMIT 1"
        )->row_array();
        if (!$row) {
            $db->query(
                "INSERT INTO wa_autoreply_meta (meta_key, meta_value) VALUES ('cache_version', '1')"
            );
            return;
        }
        $next = (string) ((int) ($row['meta_value'] ?? 0) + 1);
        $db->query(
            "UPDATE wa_autoreply_meta SET meta_value = ? WHERE meta_key = 'cache_version'",
            [$next]
        );
    }

    private static function readCacheVersion(): string
    {
        try {
            $db = \App\Core\DB::getInstance(0);
            $row = $db->query(
                "SELECT meta_value FROM wa_autoreply_meta WHERE meta_key = 'cache_version' LIMIT 1"
            )->row_array();
            if ($row && isset($row['meta_value'])) {
                return (string) $row['meta_value'];
            }
        } catch (\Throwable $e) {
            return '0';
        }
        return '0';
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadFromDatabase(): array
    {
        $db = \App\Core\DB::getInstance(0);

        $intents = $db->query(
            "SELECT id, code, case_value, notify, ai_prompt, chat_maxlength,
                    is_admin, is_karyawan, is_pelanggan
             FROM wa_autoreply_intents
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        )->result_array();

        if ($intents === [] || $intents === null) {
            return [];
        }

        $patternsByIntent = [];
        $patRows = $db->query(
            "SELECT intent_id, pattern
             FROM wa_autoreply_patterns
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        )->result_array();

        foreach ($patRows ?: [] as $p) {
            $iid = (int) $p['intent_id'];
            if (!isset($patternsByIntent[$iid])) {
                $patternsByIntent[$iid] = [];
            }
            $patternsByIntent[$iid][] = $p['pattern'];
        }

        $out = [];
        foreach ($intents as $row) {
            $code = strtoupper(trim((string) $row['code']));
            if ($code === '') {
                continue;
            }
            $cfg = [
                'patterns' => $patternsByIntent[(int) $row['id']] ?? [],
            ];
            if (array_key_exists('case_value', $row) && $row['case_value'] !== null && $row['case_value'] !== '') {
                $cfg['case'] = (int) $row['case_value'];
            }
            if (array_key_exists('notify', $row) && $row['notify'] !== null && $row['notify'] !== '') {
                $cfg['notify'] = ((int) $row['notify']) === 1;
            }
            $prompt = $row['ai_prompt'] ?? null;
            if (is_string($prompt) && trim($prompt) !== '') {
                $cfg['ai_prompt'] = $prompt;
            }
            $cfg['is_admin'] = ((int) ($row['is_admin'] ?? 0)) === 1;
            $cfg['is_karyawan'] = ((int) ($row['is_karyawan'] ?? 0)) === 1;
            $cfg['is_pelanggan'] = ((int) ($row['is_pelanggan'] ?? 0)) === 1;
            $maxLen = (int) ($row['chat_maxlength'] ?? 0);
            if ($maxLen > 0) {
                $cfg['chat_maxlength'] = $maxLen;
            }
            $out[$code] = $cfg;
        }

        return $out;
    }
}
