<?php
namespace App\Config;

/**
 * Load AutoReply keyword config dari DB (mdl_main) dengan cache in-memory + version check.
 * Fallback ke Config/AutoReplyKeywords.php jika tabel kosong / DB error.
 */
class AutoReplyKeywordsLoader
{
    /** @var array<string, mixed>|null */
    private static $config = null;

    /** @var string|null */
    private static $version = null;

    /** Path file PHP fallback (return array) */
    public static function filePath(): string
    {
        return __DIR__ . '/AutoReplyKeywords.php';
    }

    /**
     * Config berbentuk sama seperti AutoReplyKeywords.php
     * @return array<string, array{patterns: list<string>, ai_prompt?: string, case?: int|null, notify?: bool}>
     */
    public static function all(): array
    {
        try {
            $version = self::readCacheVersion();
            if (self::$config !== null && self::$version === $version && self::$config !== []) {
                return self::$config;
            }

            $fromDb = self::loadFromDatabase();
            if ($fromDb !== []) {
                self::$config = $fromDb;
                self::$version = $version;
                return self::$config;
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('AutoReplyKeywordsLoader: ' . $e->getMessage(), 'wa_error', 'KeywordsLoader');
            }
        }

        $fromFile = self::loadFromFile();
        self::$config = $fromFile;
        self::$version = self::$version ?? 'file';
        return self::$config;
    }

    /** Paksa reload (setelah admin save dari proses yang sama). */
    public static function invalidate(): void
    {
        self::$config = null;
        self::$version = null;
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
            "SELECT id, code, case_value, notify, ai_prompt
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
            $out[$code] = $cfg;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadFromFile(): array
    {
        $path = self::filePath();
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /**
     * Seed DB dari file PHP. $replace=true menghapus semua lalu isi ulang.
     * @return array{ok:bool, intents:int, patterns:int, message:string}
     */
    public static function seedFromFile(bool $replace = false): array
    {
        $data = self::loadFromFile();
        if ($data === []) {
            return ['ok' => false, 'intents' => 0, 'patterns' => 0, 'message' => 'File AutoReplyKeywords.php kosong / tidak ditemukan'];
        }

        $db = \App\Core\DB::getInstance(0);

        if (!$replace) {
            $cnt = $db->query('SELECT COUNT(*) AS c FROM wa_autoreply_intents')->row_array();
            if ((int) ($cnt['c'] ?? 0) > 0) {
                return [
                    'ok' => false,
                    'intents' => 0,
                    'patterns' => 0,
                    'message' => 'DB sudah berisi data. Centang replace untuk timpa ulang dari file.',
                ];
            }
        } else {
            $db->query('DELETE FROM wa_autoreply_patterns');
            $db->query('DELETE FROM wa_autoreply_intents');
        }

        $intentCount = 0;
        $patternCount = 0;
        $sort = 0;

        foreach ($data as $code => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $sort++;
            $code = strtoupper(trim((string) $code));
            $caseVal = array_key_exists('case', $cfg) ? $cfg['case'] : null;
            $notify = array_key_exists('notify', $cfg) ? ($cfg['notify'] ? 1 : 0) : null;
            $aiPrompt = isset($cfg['ai_prompt']) && is_string($cfg['ai_prompt']) ? $cfg['ai_prompt'] : null;

            $rowData = [
                'code' => $code,
                'sort_order' => $sort,
                'is_active' => 1,
            ];
            if ($caseVal !== null) {
                $rowData['case_value'] = (int) $caseVal;
            }
            if ($notify !== null) {
                $rowData['notify'] = (int) $notify;
            }
            if ($aiPrompt !== null) {
                $rowData['ai_prompt'] = $aiPrompt;
            }

            $intentId = $db->insert('wa_autoreply_intents', $rowData);
            $intentId = (int) $intentId;
            if ($intentId <= 0) {
                $row = $db->query(
                    'SELECT id FROM wa_autoreply_intents WHERE code = ? LIMIT 1',
                    [$code]
                )->row_array();
                $intentId = (int) ($row['id'] ?? 0);
            }
            if ($intentId <= 0) {
                continue;
            }
            $intentCount++;

            $psort = 0;
            foreach (($cfg['patterns'] ?? []) as $pat) {
                if (!is_string($pat) || $pat === '') {
                    continue;
                }
                $psort++;
                $ok = $db->insert('wa_autoreply_patterns', [
                    'intent_id' => $intentId,
                    'pattern' => $pat,
                    'sort_order' => $psort,
                    'is_active' => 1,
                ]);
                if ($ok) {
                    $patternCount++;
                }
            }
        }

        self::bumpCacheVersion();
        self::$config = null;
        self::$version = null;

        return [
            'ok' => true,
            'intents' => $intentCount,
            'patterns' => $patternCount,
            'message' => "Seed OK: {$intentCount} intent, {$patternCount} pattern",
        ];
    }
}
