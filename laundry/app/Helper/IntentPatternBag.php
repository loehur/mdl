<?php

/**
 * Gabung pattern keyword sederhana jadi satu alternation.
 * /\bterimakash\b/iu + /\bmksh\b/iu → /\b(?:terimakash|mksh)\b/iu
 */
class IntentPatternBag
{
    /** @var list<string> */
    private static $stopwords = [
        'kak', 'kakak', 'kk', 'ya', 'yah', 'iya', 'dong', 'sih', 'lah', 'nya',
        'tuh', 'nih', 'yaudah', 'min', 'admin', 'cs', 'byk', 'banyak', 'banget',
        'bgt', 'pls', 'please', 'tolong', 'mohon',
    ];

    /**
     * @return array{alts:list<string>,delim:string,flags:string,word_boundary:bool}|null
     */
    public static function parseSimple(string $pattern): ?array
    {
        $split = self::splitRegex($pattern);
        if ($split === null) {
            return null;
        }
        $body = $split['body'];
        if ($body === '' || strlen($body) > 2500) {
            return null;
        }

        $wb = false;
        if (preg_match('/^\\\\b/', $body)) {
            $wb = true;
            $body = (string) preg_replace('/^\\\\b/', '', $body);
        }
        if (preg_match('/\\\\b$/', $body)) {
            $wb = true;
            $body = (string) preg_replace('/\\\\b$/', '', $body);
        }

        $unwrapped = self::unwrapOneGroup($body);
        if ($unwrapped !== null) {
            $body = $unwrapped;
        }

        if (preg_match('/\\\\[sSdDwW]|\\.\\*|\\.\\+|\\(\\?|\\s/', $body)) {
            return null;
        }

        $rawAlts = self::splitTopLevelAlts($body);
        if ($rawAlts === null || $rawAlts === []) {
            return null;
        }

        $alts = [];
        foreach ($rawAlts as $alt) {
            $alt = trim($alt);
            if ($alt === '' || !self::isSimpleAtom($alt)) {
                return null;
            }
            $alts[] = $alt;
        }

        $flags = preg_replace('/[^imsuxADSUXJ]/', '', $split['flags']) ?? '';
        if (strpos($flags, 'i') === false) {
            $flags .= 'i';
        }
        if (strpos($flags, 'u') === false) {
            $flags .= 'u';
        }

        return [
            'alts' => $alts,
            'delim' => $split['delim'],
            'flags' => $flags,
            'word_boundary' => $wb,
        ];
    }

    /**
     * @param list<string> $alts
     */
    public static function build(array $alts, string $flags = 'iu', bool $wordBoundary = true): string
    {
        $clean = [];
        $seen = [];
        foreach ($alts as $alt) {
            $alt = trim((string) $alt);
            if ($alt === '' || !self::isSimpleAtom($alt)) {
                continue;
            }
            $key = mb_strtolower($alt);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $alt;
        }
        if ($clean === []) {
            return '/.^/u';
        }
        $body = count($clean) === 1 ? $clean[0] : ('(?:' . implode('|', $clean) . ')');
        if ($wordBoundary) {
            $body = '\\b' . $body . '\\b';
        }
        $flags = $flags !== '' ? $flags : 'iu';
        return '/' . $body . '/' . $flags;
    }

    /**
     * @param list<string> $newAlts
     */
    public static function addAlts(string $existingPattern, array $newAlts): ?string
    {
        $bag = self::parseSimple($existingPattern);
        if ($bag === null) {
            return null;
        }
        $merged = self::build(
            array_merge($bag['alts'], $newAlts),
            $bag['flags'],
            $bag['word_boundary'] || true
        );
        if (@preg_match($merged, '') === false) {
            return null;
        }
        return $merged;
    }

    /**
     * Cari pattern keyword sederhana terbaik untuk ditambahi alternatif.
     *
     * @param list<array{id:int,pattern:string}> $patRows
     * @return array{id:int,pattern:string,alts:list<string>}|null
     */
    public static function findBestBag(array $patRows): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($patRows as $row) {
            $pat = (string) ($row['pattern'] ?? '');
            $bag = self::parseSimple($pat);
            if ($bag === null) {
                continue;
            }
            $n = count($bag['alts']);
            $score = ($n * 20) + ($bag['word_boundary'] ? 10 : 0) - ((int) ($row['id'] ?? 0) * 0.001);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'id' => (int) $row['id'],
                    'pattern' => $pat,
                    'alts' => $bag['alts'],
                ];
            }
        }
        return $best;
    }

    /**
     * Token teks → atom regex sederhana (huruf berulang → +), tanpa stopword.
     *
     * @return list<string>
     */
    public static function keywordAltsFromText(string $text): array
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
        if ($t === '') {
            return [];
        }
        preg_match_all('/\p{L}{3,}/u', $t, $m);
        $alts = [];
        $seen = [];
        foreach ($m[0] as $tok) {
            if (in_array($tok, self::$stopwords, true)) {
                continue;
            }
            $atom = self::tokenToFlexibleRegex($tok);
            if ($atom === '' || !self::isSimpleAtom($atom)) {
                continue;
            }
            $key = mb_strtolower($atom);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $alts[] = $atom;
        }
        return $alts;
    }

    /**
     * Gabungkan semua pattern keyword sederhana per intent jadi 1 row.
     *
     * @param list<array{id:int,pattern:string,is_active?:int|string}> $rows
     * @return array{
     *   needed:bool,
     *   keeper_id?:int,
     *   merged_pattern?:string,
     *   delete_ids?:list<int>,
     *   simple_count?:int,
     *   alt_count?:int,
     *   reason?:string
     * }
     */
    public static function compactRows(array $rows): array
    {
        $simple = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $pat = (string) ($row['pattern'] ?? '');
            $active = !isset($row['is_active']) || (int) $row['is_active'] === 1;
            if ($id <= 0 || $pat === '' || !$active) {
                continue;
            }
            $bag = self::parseSimple($pat);
            if ($bag === null) {
                continue;
            }
            $simple[] = [
                'id' => $id,
                'pattern' => $pat,
                'alts' => $bag['alts'],
                'flags' => $bag['flags'],
                'word_boundary' => $bag['word_boundary'],
            ];
        }

        if (count($simple) < 2) {
            return [
                'needed' => false,
                'reason' => 'Tidak ada pattern keyword sederhana yang bisa digabung (butuh minimal 2).',
                'simple_count' => count($simple),
            ];
        }

        $allAlts = [];
        foreach ($simple as $s) {
            foreach ($s['alts'] as $alt) {
                $allAlts[] = $alt;
            }
        }
        $merged = self::build($allAlts, 'iu', true);
        if (@preg_match($merged, '') === false) {
            return ['needed' => false, 'reason' => 'Regex gabungan tidak valid'];
        }

        foreach ($simple as $s) {
            foreach ($s['alts'] as $alt) {
                $sample = self::sampleFromAtom($alt);
                if ($sample !== '' && @preg_match($merged, $sample) !== 1) {
                    return [
                        'needed' => false,
                        'reason' => 'Gabungan tidak mencakup "' . $sample . '" dari pattern #' . $s['id'],
                    ];
                }
            }
        }

        usort($simple, static function ($a, $b) {
            $na = count($a['alts']);
            $nb = count($b['alts']);
            if ($na !== $nb) {
                return $nb <=> $na;
            }
            return $a['id'] <=> $b['id'];
        });
        $keeperId = (int) $simple[0]['id'];
        $deleteIds = [];
        foreach ($simple as $s) {
            if ((int) $s['id'] !== $keeperId) {
                $deleteIds[] = (int) $s['id'];
            }
        }

        return [
            'needed' => true,
            'keeper_id' => $keeperId,
            'merged_pattern' => $merged,
            'delete_ids' => $deleteIds,
            'simple_count' => count($simple),
            'alt_count' => count(self::parseSimple($merged)['alts'] ?? []),
            'reason' => 'Gabung ' . count($simple) . ' pattern keyword menjadi 1 record.',
        ];
    }

    private static function sampleFromAtom(string $atom): string
    {
        $s = preg_replace('/\\\\(.)/', '$1', $atom) ?? $atom;
        $s = preg_replace('/[+*?]$/', '', $s) ?? $s;
        $s = preg_replace('/\{\d+,?\d*\}$/', '', $s) ?? $s;
        return $s;
    }

    private static function isSimpleAtom(string $alt): bool
    {
        if ($alt === '' || strlen($alt) > 80) {
            return false;
        }
        if (preg_match('/[|()\\s]/', $alt)) {
            return false;
        }
        return (bool) preg_match('/^(?:(?:\\\\.|[\p{L}\p{N}])+(?:[+*?]|\\{\d+,?\d*\\})?)+$/u', $alt);
    }

    private static function unwrapOneGroup(string $body): ?string
    {
        if (preg_match('/^\(\?:(.*)\)$/s', $body, $m)) {
            $inner = $m[1];
            return self::isBalanced($inner) ? $inner : null;
        }
        if (preg_match('/^\((?!\?)(.*)\)$/s', $body, $m)) {
            $inner = $m[1];
            return self::isBalanced($inner) ? $inner : null;
        }
        return null;
    }

    /** @return list<string>|null */
    private static function splitTopLevelAlts(string $body): ?array
    {
        $alts = [];
        $buf = '';
        $depth = 0;
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $buf .= $ch . $body[$i + 1];
                $i++;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth < 0) {
                    return null;
                }
            }
            if ($ch === '|' && $depth === 0) {
                $alts[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if ($depth !== 0) {
            return null;
        }
        $alts[] = $buf;
        return $alts;
    }

    private static function isBalanced(string $s): bool
    {
        $depth = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            if ($s[$i] === '\\' && $i + 1 < $len) {
                $i++;
                continue;
            }
            if ($s[$i] === '(') {
                $depth++;
            } elseif ($s[$i] === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            }
        }
        return $depth === 0;
    }

    /** @return array{delim:string,body:string,flags:string}|null */
    private static function splitRegex(string $pattern): ?array
    {
        $pattern = trim($pattern);
        if (strlen($pattern) < 3) {
            return null;
        }
        $delim = $pattern[0];
        if (preg_match('/[A-Za-z0-9\\\\]/', $delim)) {
            return null;
        }
        $end = strrpos($pattern, $delim);
        if ($end === false || $end === 0) {
            return null;
        }
        return [
            'delim' => $delim,
            'body' => substr($pattern, 1, $end - 1),
            'flags' => substr($pattern, $end + 1),
        ];
    }

    private static function tokenToFlexibleRegex(string $token): string
    {
        $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        $n = count($chars);
        $i = 0;
        while ($i < $n) {
            $ch = $chars[$i];
            $j = $i + 1;
            while ($j < $n && $chars[$j] === $ch) {
                $j++;
            }
            $q = preg_quote($ch, '/');
            $out .= (($j - $i) >= 2) ? ($q . '+') : $q;
            $i = $j;
        }
        return $out;
    }

    /**
     * Bersihkan korupsi umum regex PHP (AI / copy-paste).
     * - spasi sebelum /delimiter
     * - \\?/ sebelum penutup (slash ekstra)
     * - \\b setelah ?!., di akhir (PCRE tidak match)
     */
    public static function sanitizePatternString(string $pattern): string
    {
        $pattern = trim($pattern);
        // spasi sebelum penutup delimiter /flags
        $pattern = preg_replace('/\s+(?=\/[a-zA-Z]*$)/', '', $pattern) ?? $pattern;
        // \?/ \/! dll sebelum penutup — slash ekstra dari AI
        $pattern = preg_replace('/\\\\([?!.,;:])\\\\\/(?=\/[a-zA-Z]*$)/', '\\\\$1', $pattern) ?? $pattern;
        // literal \?/ sebelum penutup (tanpa double-escape di source)
        $pattern = preg_replace('/\\\\([?!.,;:])\/(?=\/[a-zA-Z]*$)/', '\\\\$1', $pattern) ?? $pattern;
        // \b setelah tanda baca di akhir (PCRE tidak match teks berakhir ?!)
        $pattern = preg_replace('/\\\\([?!.,;:])\s*\\\\b(?=\/[a-zA-Z]*$)/', '\\\\$1', $pattern) ?? $pattern;
        // flags invalid ganda: //iu → /iu
        $pattern = preg_replace('/\/\/+([a-zA-Z]*)$/', '/$1', $pattern) ?? $pattern;

        return $pattern;
    }

    /** Pattern mentah terlihat korup / invalid — perlu dirapikan. */
    public static function patternLooksBroken(string $pattern): bool
    {
        $p = trim($pattern);
        if ($p === '') {
            return false;
        }
        if (@preg_match($p, '') === false) {
            return true;
        }
        if (self::sanitizePatternString($p) !== $p) {
            return true;
        }
        if (preg_match('/\\\\[?!.,;:]\s*\\\\\//', $p)) {
            return true;
        }
        if (preg_match('/\\\\[?!.,;:]\s*\\\\b\s*\//', $p)) {
            return true;
        }
        if (preg_match('/\s+\/[a-zA-Z]*$/', $p)) {
            return true;
        }

        return false;
    }

    /** Ambil teks contoh dari kolom note (Intent Lab teach, dll). */
    public static function extractSampleFromNote(string $note): string
    {
        $note = trim($note);
        if ($note === '') {
            return '';
        }
        if (preg_match('/^Intent Lab teach:\s*(.+)$/iu', $note, $m)) {
            $sample = trim((string) ($m[1] ?? ''));
            if (($pipe = strpos($sample, ' | ')) !== false) {
                $sample = trim(substr($sample, 0, $pipe));
            }

            return $sample;
        }
        if (preg_match('/^Intent Lab untouch [^:]+:\s*(.+)$/iu', $note, $m)) {
            $sample = trim((string) ($m[1] ?? ''));
            if (($pipe = strpos($sample, ' | ')) !== false) {
                $sample = trim(substr($sample, 0, $pipe));
            }

            return $sample;
        }
        if (preg_match('/^(Rapikan:|Intent Lab|Gabung)/i', $note)) {
            return '';
        }
        if (mb_strlen($note) >= 4) {
            if (($pipe = strpos($note, ' | ')) !== false) {
                return trim(substr($note, 0, $pipe));
            }

            return $note;
        }

        return '';
    }

    /** Perbaiki pattern agar match teks contoh (pakai IntentTeachHelper jika ada). */
    public static function normalizePatternForSample(string $pattern, string $sampleText): string
    {
        $pattern = self::sanitizePatternString($pattern);
        $sampleText = trim($sampleText);
        if ($pattern === '' || $sampleText === '') {
            return $pattern;
        }
        if (@preg_match($pattern, $sampleText) === 1) {
            return $pattern;
        }

        $helper = self::loadTeachHelperClass();
        if ($helper !== null) {
            $fixed = $helper::normalizePatternForText($pattern, $sampleText);
            if ($fixed !== '' && @preg_match($fixed, $sampleText) === 1) {
                return $fixed;
            }
        }

        $fixed = preg_replace(
            '/\\\\([?!.,;:])\s*\\\\b(?=\/[a-zA-Z]*$)/',
            '\\\\$1',
            $pattern
        );
        if (is_string($fixed) && $fixed !== '' && @preg_match($fixed, $sampleText) === 1) {
            return $fixed;
        }

        return $pattern;
    }

    /** @return class-string|null */
    private static function loadTeachHelperClass(): ?string
    {
        static $loaded = false;
        if (!$loaded) {
            $loaded = true;
            $helper = dirname(__DIR__, 3) . '/api/app/Helpers/Laundry/IntentTeachHelper.php';
            if (is_file($helper)) {
                require_once $helper;
            }
        }
        if (class_exists('\\App\\Helpers\\Laundry\\IntentTeachHelper')) {
            return '\\App\\Helpers\\Laundry\\IntentTeachHelper';
        }

        return null;
    }

    /**
     * @param list<array{id:int|string,pattern:string,note?:string}> $rows
     * @return list<array{id:int,before:string,after:string,needed:bool,reasons:list<string>,note?:string}>
     */
    public static function repairPlansForRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $before = (string) ($row['pattern'] ?? '');
            if ($id <= 0 || trim($before) === '') {
                continue;
            }
            $beforeTrim = trim($before);
            $sample = self::extractSampleFromNote((string) ($row['note'] ?? ''));
            $looksBroken = self::patternLooksBroken($beforeTrim);
            $sampleMismatch = $sample !== '' && @preg_match($beforeTrim, $sample) !== 1;
            if (!$looksBroken && !$sampleMismatch) {
                continue;
            }

            $after = self::sanitizePatternString($beforeTrim);
            $reasons = [];
            if ($before !== $beforeTrim) {
                $reasons[] = 'trim spasi';
            }
            if (@preg_match($beforeTrim, '') === false) {
                $reasons[] = 'regex invalid';
            }
            if (preg_match('/\s+(?=\/[a-zA-Z]*$)/', $beforeTrim)) {
                $reasons[] = 'spasi sebelum delimiter';
            }
            if (preg_match('/\\\\[?!.,;:][\\\\\/]/', $beforeTrim)) {
                $reasons[] = 'korupsi \\?/ sebelum penutup';
            }
            if (preg_match('/\\\\[?!.,;:]\s*\\\\b(?=\/[a-zA-Z]*$)/', $beforeTrim)) {
                $reasons[] = '\\b setelah tanda baca akhir';
            }
            if ($sampleMismatch) {
                $reasons[] = 'tidak match teks contoh: "' . mb_substr($sample, 0, 60) . '"';
                $normalized = self::normalizePatternForSample($after, $sample);
                if ($normalized !== $after && @preg_match($normalized, $sample) === 1) {
                    $after = $normalized;
                    $reasons[] = 'rebuild dari teks contoh';
                }
            }
            if ($after === $beforeTrim && $looksBroken) {
                $reasons[] = 'terdeteksi rusak (perlu cek manual)';
            }

            $needed = ($after !== $beforeTrim);
            if (!$needed) {
                $out[] = [
                    'id' => $id,
                    'before' => $beforeTrim,
                    'after' => $after,
                    'needed' => false,
                    'reasons' => $reasons,
                    'note' => (string) ($row['note'] ?? ''),
                    'sample' => $sample,
                ];
                continue;
            }
            if (@preg_match($after, '') === false) {
                $out[] = [
                    'id' => $id,
                    'before' => $beforeTrim,
                    'after' => $after,
                    'needed' => false,
                    'reasons' => array_merge($reasons, ['SKIP: hasil perbaikan tidak valid']),
                    'note' => (string) ($row['note'] ?? ''),
                    'sample' => $sample,
                ];
                continue;
            }
            if ($sample !== '' && @preg_match($after, $sample) !== 1) {
                $out[] = [
                    'id' => $id,
                    'before' => $beforeTrim,
                    'after' => $after,
                    'needed' => false,
                    'reasons' => array_merge($reasons, ['SKIP: masih tidak match teks contoh']),
                    'note' => (string) ($row['note'] ?? ''),
                    'sample' => $sample,
                ];
                continue;
            }
            $out[] = [
                'id' => $id,
                'before' => $beforeTrim,
                'after' => $after,
                'needed' => true,
                'reasons' => $reasons !== [] ? $reasons : ['normalisasi regex'],
                'note' => (string) ($row['note'] ?? ''),
                'sample' => $sample,
            ];
        }

        return $out;
    }
}
