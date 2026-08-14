<?php

namespace App\Helpers\Laundry;

/**
 * Intent Lab — usulkan pattern + potongan ai_prompt agar kalimat masuk / keluar intent target.
 */
class IntentTeachHelper
{
    /**
     * @return array{
     *   ok:bool,
     *   message?:string,
     *   intent?:string,
     *   text?:string,
     *   action?:string,
     *   pattern_id?:int,
     *   existing_pattern?:string,
     *   pattern?:string,
     *   prompt_append?:string,
     *   reason?:string,
     *   matches_text?:bool,
     *   pattern_exists?:bool,
     *   current_prompt?:string,
     *   already_covered?:bool,
     *   error?:string
     * }
     */
    public static function propose(string $text, string $intentCode): array
    {
        $text = trim($text);
        $intentCode = strtoupper(trim($intentCode));
        if ($text === '' || $intentCode === '') {
            return ['ok' => false, 'message' => 'text dan intent wajib'];
        }
        if (mb_strlen($text) > 2000) {
            return ['ok' => false, 'message' => 'Teks maksimal 2000 karakter'];
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $intentCode)) {
            return ['ok' => false, 'message' => 'Kode intent tidak valid'];
        }

        try {
            $db = \App\Core\DB::getInstance(0);
            $intent = $db->query(
                'SELECT id, code, ai_prompt FROM wa_autoreply_intents WHERE code = ? AND is_active = 1 LIMIT 1',
                [$intentCode]
            )->row_array();
            if (!$intent) {
                return ['ok' => false, 'message' => 'Intent tidak ditemukan / nonaktif: ' . $intentCode];
            }

            $patRows = $db->query(
                'SELECT id, pattern FROM wa_autoreply_patterns WHERE intent_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 120',
                [(int) $intent['id']]
            )->result_array();
            $patRows = is_array($patRows) ? $patRows : [];
            $samplePatterns = [];
            foreach ($patRows as $i => $r) {
                $patRows[$i]['id'] = (int) ($r['id'] ?? 0);
                $patRows[$i]['pattern'] = (string) ($r['pattern'] ?? '');
                if ($patRows[$i]['pattern'] !== '') {
                    $samplePatterns[] = $patRows[$i]['pattern'];
                }
            }

            foreach ($patRows as $r) {
                $pat = $r['pattern'];
                if ($pat !== '' && @preg_match($pat, $text) === 1) {
                    return [
                        'ok' => true,
                        'intent' => $intentCode,
                        'text' => $text,
                        'action' => 'none',
                        'pattern_id' => $r['id'],
                        'existing_pattern' => $pat,
                        'pattern' => $pat,
                        'prompt_append' => '',
                        'reason' => 'Kalimat sudah match pattern aktif intent ini. Cukup perkuat AI prompt bila klasifikasi AI masih salah.',
                        'matches_text' => true,
                        'pattern_exists' => true,
                        'current_prompt' => (string) ($intent['ai_prompt'] ?? ''),
                        'already_covered' => true,
                    ];
                }
            }

            $heuristic = self::suggestFromExisting($text, $patRows);
            $ai = self::askAiForProposal(
                $text,
                $intentCode,
                (string) ($intent['ai_prompt'] ?? ''),
                $patRows,
                $heuristic
            );
            if (empty($ai['ok'])) {
                return $ai;
            }

            $chosen = self::pickProposal($text, $patRows, $ai, $heuristic);
            $pattern = (string) ($chosen['pattern'] ?? '');
            $promptAppend = trim((string) ($chosen['prompt_append'] ?? ''));
            $reason = trim((string) ($chosen['reason'] ?? ''));
            $action = (string) ($chosen['action'] ?? 'insert');
            $patternId = (int) ($chosen['pattern_id'] ?? 0);
            $existingPattern = (string) ($chosen['existing_pattern'] ?? '');

            $valid = @preg_match($pattern, $text);
            if ($valid === false || $valid !== 1) {
                $fallback = self::buildFallbackPattern($text);
                if (@preg_match($fallback, $text) === 1) {
                    $pattern = $fallback;
                    $valid = 1;
                    $promoted = self::maybePromoteInsertToUpdate($fallback, $patRows);
                    if ($promoted !== null) {
                        $action = 'update';
                        $patternId = $promoted['pattern_id'];
                        $existingPattern = $promoted['existing_pattern'];
                        $reason = trim($reason . ' | Regex tidak match; dipakai pelebaran pattern existing.');
                    } else {
                        $action = 'insert';
                        $patternId = 0;
                        $existingPattern = '';
                        $reason = trim($reason . ' | Regex tidak match; dipakai fallback cerdas (huruf berulang → +).');
                    }
                }
            }

            $exists = false;
            foreach ($samplePatterns as $pat) {
                if ($pat === $pattern) {
                    $exists = true;
                    break;
                }
            }

            if ($action === 'update' && $patternId <= 0 && $existingPattern !== '') {
                $resolved = self::findPatternRow($patRows, 0, $existingPattern);
                if ($resolved !== null) {
                    $patternId = $resolved['id'];
                }
            }
            if ($action === 'update' && $patternId > 0 && $existingPattern === '') {
                $resolved = self::findPatternRow($patRows, $patternId, '');
                if ($resolved !== null) {
                    $existingPattern = $resolved['pattern'];
                }
            }
            if ($action !== 'update') {
                $promoted = self::maybePromoteInsertToUpdate($pattern, $patRows);
                if ($promoted !== null) {
                    $action = 'update';
                    $patternId = $promoted['pattern_id'];
                    $existingPattern = $promoted['existing_pattern'];
                    if ($reason === '' || stripos($reason, 'pattern baru') !== false) {
                        $reason = 'Pattern baru tidak perlu: cukup lebarkan pattern yang sudah ada.';
                    }
                } else {
                    $merged = self::suggestMergeIntoBag($text, $patRows);
                    if ($merged !== null) {
                        $action = 'update';
                        $patternId = (int) $merged['pattern_id'];
                        $existingPattern = (string) $merged['existing_pattern'];
                        $pattern = (string) $merged['pattern'];
                        $promptAppend = '';
                        $reason = (string) $merged['reason'];
                    } else {
                        $action = 'insert';
                        $patternId = 0;
                        $existingPattern = '';
                    }
                }
            }

            if ($promptAppend === '' && $action === 'insert') {
                $promptAppend = '| ' . self::shortExample($text) . ' |';
            }

            return [
                'ok' => true,
                'intent' => $intentCode,
                'text' => $text,
                'action' => $action,
                'pattern_id' => $patternId,
                'existing_pattern' => $existingPattern,
                'pattern' => $pattern,
                'prompt_append' => $promptAppend,
                'reason' => $reason !== '' ? $reason : 'Usulan AI',
                'matches_text' => $valid === 1,
                'pattern_exists' => $exists,
                'current_prompt' => (string) ($intent['ai_prompt'] ?? ''),
                'already_covered' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal membuat usulan',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Usulan kebalikan teach: nonaktifkan pattern yang match + append pengecualian ai_prompt
     * agar kalimat TIDAK masuk intent target.
     *
     * @return array{
     *   ok:bool,
     *   message?:string,
     *   intent?:string,
     *   text?:string,
     *   matching_patterns?:list<array{id:int,pattern:string,note?:string}>,
     *   prompt_append?:string,
     *   reason?:string,
     *   has_matching_patterns?:bool,
     *   current_prompt?:string,
     *   error?:string
     * }
     */
    public static function proposeUntouch(string $text, string $intentCode): array
    {
        $text = trim($text);
        $intentCode = strtoupper(trim($intentCode));
        if ($text === '' || $intentCode === '') {
            return ['ok' => false, 'message' => 'text dan intent wajib'];
        }
        if (mb_strlen($text) > 2000) {
            return ['ok' => false, 'message' => 'Teks maksimal 2000 karakter'];
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $intentCode)) {
            return ['ok' => false, 'message' => 'Kode intent tidak valid'];
        }

        try {
            $db = \App\Core\DB::getInstance(0);
            $intent = $db->query(
                'SELECT id, code, ai_prompt FROM wa_autoreply_intents WHERE code = ? AND is_active = 1 LIMIT 1',
                [$intentCode]
            )->row_array();
            if (!$intent) {
                return ['ok' => false, 'message' => 'Intent tidak ditemukan / nonaktif: ' . $intentCode];
            }

            $patRows = $db->query(
                'SELECT id, pattern, note FROM wa_autoreply_patterns WHERE intent_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC',
                [(int) $intent['id']]
            )->result_array();

            $matching = [];
            $samplePatterns = [];
            foreach ($patRows ?: [] as $r) {
                $pat = (string) ($r['pattern'] ?? '');
                if ($pat === '') {
                    continue;
                }
                $samplePatterns[] = $pat;
                if (@preg_match($pat, $text) === 1) {
                    $matching[] = [
                        'id' => (int) $r['id'],
                        'pattern' => $pat,
                        'note' => (string) ($r['note'] ?? ''),
                    ];
                }
            }

            $ai = self::askAiForUntouchProposal(
                $text,
                $intentCode,
                (string) ($intent['ai_prompt'] ?? ''),
                $samplePatterns,
                $matching
            );
            if (empty($ai['ok'])) {
                return $ai;
            }

            $promptAppend = trim((string) ($ai['prompt_append'] ?? ''));
            $reason = trim((string) ($ai['reason'] ?? ''));
            if ($promptAppend === '') {
                $promptAppend = 'BUKAN ' . $intentCode . ': | ' . self::shortExample($text) . ' |';
            }
            if ($reason === '') {
                $reason = $matching === []
                    ? 'Tidak ada pattern aktif yang match. Cukup append pengecualian ke ai_prompt agar AI tidak mengklasifikasi ke intent ini.'
                    : 'Nonaktifkan pattern yang match teks ini, lalu append pengecualian ke ai_prompt.';
            }

            return [
                'ok' => true,
                'intent' => $intentCode,
                'text' => $text,
                'matching_patterns' => $matching,
                'prompt_append' => $promptAppend,
                'reason' => $reason,
                'has_matching_patterns' => $matching !== [],
                'current_prompt' => (string) ($intent['ai_prompt'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal membuat usulan keluarkan',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param list<string> $samplePatterns
     * @param list<array{id:int,pattern:string,note?:string}> $matching
     * @return array{ok:bool,prompt_append?:string,reason?:string,message?:string,error?:string}
     */
    private static function askAiForUntouchProposal(
        string $text,
        string $intentCode,
        string $currentPrompt,
        array $samplePatterns,
        array $matching
    ): array {
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../../Config/AI.php';
        }
        if (!\App\Config\AI::isEnabled()) {
            return [
                'ok' => true,
                'prompt_append' => 'BUKAN ' . $intentCode . ': | ' . self::shortExample($text) . ' |',
                'reason' => 'AI disabled — fallback pengecualian literal.',
            ];
        }

        $openaiKey = \App\Config\AI::getOpenAIApiKey();
        $groqKey = \App\Config\AI::getGroqApiKey();
        if ($openaiKey === '' && $groqKey === '') {
            return [
                'ok' => true,
                'prompt_append' => 'BUKAN ' . $intentCode . ': | ' . self::shortExample($text) . ' |',
                'reason' => 'No AI key — fallback pengecualian literal.',
            ];
        }

        $matchList = $matching === []
            ? '(tidak ada pattern aktif yang match teks ini)'
            : implode("\n", array_map(
                static fn ($m) => '- id=' . $m['id'] . ' ' . $m['pattern'],
                array_slice($matching, 0, 15)
            ));
        $patList = $samplePatterns === []
            ? '(belum ada)'
            : implode("\n", array_map(static fn ($p) => '- ' . $p, array_slice($samplePatterns, 0, 10)));
        $promptExcerpt = mb_substr(trim($currentPrompt), 0, 1800);
        if ($promptExcerpt === '') {
            $promptExcerpt = '(kosong)';
        }

        $system = "Kamu membantu merawat klasifikasi intent WhatsApp laundry (PHP PCRE + prompt AI).\n"
            . "Tugas: agar pesan customer TIDAK MASUK / KELUAR dari intent target.\n"
            . "Sistem akan menonaktifkan pattern yang match teks ini (jika ada).\n"
            . "Aturan prompt_append:\n"
            . "- Satu baris pendek pengecualian untuk ditambahkan ke ai_prompt\n"
            . "- Format: BUKAN {$intentCode}: | contoh kalimat |\n"
            . "- Jelaskan singkat bahwa contoh ini bukan intent tersebut\n"
            . "- Bahasa Indonesia santai seperti chat customer\n"
            . "Balas HANYA JSON valid tanpa markdown:\n"
            . '{"prompt_append":"BUKAN ...: | ... |","reason":"singkat"}';

        $user = "Intent yang harus DILEWATI (keluarkan):\n{$intentCode}\n"
            . "Pesan customer:\n\"\"\"\n{$text}\n\"\"\"\n\n"
            . "Pattern aktif yang MATCH teks ini (akan dinonaktifkan):\n{$matchList}\n\n"
            . "Sample pattern aktif intent ini:\n{$patList}\n\n"
            . "Cuplikan ai_prompt saat ini:\n{$promptExcerpt}\n";

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

        try {
            $raw = self::chatCompletions($messages, 400, true);
        } catch (\Throwable $e) {
            return [
                'ok' => true,
                'prompt_append' => 'BUKAN ' . $intentCode . ': | ' . self::shortExample($text) . ' |',
                'reason' => 'AI error — fallback: ' . mb_substr($e->getMessage(), 0, 120),
            ];
        }

        $parsed = self::parseJsonObject($raw);
        if ($parsed === null) {
            return [
                'ok' => true,
                'prompt_append' => 'BUKAN ' . $intentCode . ': | ' . self::shortExample($text) . ' |',
                'reason' => 'AI tidak mengembalikan JSON valid — fallback pengecualian.',
            ];
        }

        $promptAppend = trim((string) ($parsed['prompt_append'] ?? ''));
        $reason = trim((string) ($parsed['reason'] ?? 'Usulan AI keluarkan'));

        return [
            'ok' => true,
            'prompt_append' => $promptAppend,
            'reason' => $reason,
        ];
    }

    /**
     * @param list<array{id:int,pattern:string}> $patRows
     * @param array<string,mixed>|null $heuristic
     * @return array{ok:bool,ai_valid?:bool,action?:string,pattern_id?:int,existing_pattern?:string,pattern?:string,prompt_append?:string,reason?:string,message?:string,error?:string}
     */
    private static function askAiForProposal(
        string $text,
        string $intentCode,
        string $currentPrompt,
        array $patRows,
        ?array $heuristic
    ): array {
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../../Config/AI.php';
        }
        if (!\App\Config\AI::isEnabled()) {
            return ['ok' => true, 'ai_valid' => false, 'reason' => 'AI disabled'];
        }

        $openaiKey = \App\Config\AI::getOpenAIApiKey();
        $groqKey = \App\Config\AI::getGroqApiKey();
        if ($openaiKey === '' && $groqKey === '') {
            return ['ok' => true, 'ai_valid' => false, 'reason' => 'No AI key'];
        }

        $related = self::relatedPatternLines($text, $patRows);
        $allLines = [];
        foreach (array_slice($patRows, 0, 50) as $r) {
            if (($r['pattern'] ?? '') === '') {
                continue;
            }
            $allLines[] = '- id=' . (int) $r['id'] . ' ' . $r['pattern'];
        }
        $relatedBlock = $related === []
            ? '(tidak ada yang dekat dengan kata dasar contoh)'
            : implode("\n", $related);
        $patList = $allLines === [] ? '(belum ada)' : implode("\n", $allLines);
        $hint = '';
        if (is_array($heuristic) && ($heuristic['action'] ?? '') === 'update') {
            $hint = "Saran sistem (wajib diikuti jika masuk akal): UPDATE id="
                . (int) ($heuristic['pattern_id'] ?? 0)
                . " dari " . ($heuristic['existing_pattern'] ?? '')
                . " menjadi " . ($heuristic['pattern'] ?? '')
                . " — huruf berulang chat cukup pakai + (cek → cek+).\n\n";
        }

        $promptExcerpt = mb_substr(trim($currentPrompt), 0, 1800);
        if ($promptExcerpt === '') {
            $promptExcerpt = '(kosong)';
        }

        $system = "Kamu merawat klasifikasi intent WhatsApp laundry (PHP PCRE + prompt AI).\n"
            . "Tugas: agar pesan customer MASUK intent target, dengan merawat pattern yang SUDAH ADA.\n"
            . "PRIORITAS WAJIB (urut):\n"
            . "1. JANGAN menambah pattern baru jika pattern existing bisa DILEBARKAN atau DIGABUNG.\n"
            . "2. Huruf berulang chat (cekkkkk, siappp, hallooo) = tambah + pada huruf yang diulang. Contoh: cek → cek+, siap → siap+. JANGAN buat /\\bcekkkkk\\b/iu.\n"
            . "3. Keyword sejenis (terimakash + mksh, makasih + thanks) = GABUNG ke SATU pattern sebagai alternatif: /\\b(?:terimakash|mksh)\\b/iu. JANGAN row baru.\n"
            . "4. Jika ada pattern yang sudah mengandung kata dasar (cek), action=update pada pattern itu.\n"
            . "5. Pattern baru (action=insert) HANYA jika kata kuncinya benar-benar belum ada DAN tidak bisa digabung ke bag keyword existing.\n"
            . "Aturan regex:\n"
            . "- Wajib format PHP delimiter, contoh /pola/iu\n"
            . "- Harus match PESAN contoh (case-insensitive)\n"
            . "- Tangkap slang/typo wajar, JANGAN terlalu luas (hindari .*)\n"
            . "- Prefer \\b untuk kata kunci; izinkan spasi fleksibel \\s*\n"
            . "- Jangan bentrok intent lain secara agresif\n"
            . "Aturan prompt_append:\n"
            . "- Jika action=update karena huruf berulang, prompt_append boleh string kosong\n"
            . "- Jika insert, satu baris pendek: | contoh kalimat |\n"
            . "Balas HANYA JSON valid tanpa markdown:\n"
            . '{"action":"update|insert","pattern_id":0,"existing_pattern":"","pattern":"/.../iu","prompt_append":"","reason":"singkat"}';

        $user = $hint
            . "Intent target: {$intentCode}\n"
            . "Pesan customer:\n\"\"\"\n{$text}\n\"\"\"\n\n"
            . "Pattern TERKAIT (prioritas ubah ini):\n{$relatedBlock}\n\n"
            . "Semua pattern aktif intent ini:\n{$patList}\n\n"
            . "Cuplikan ai_prompt saat ini:\n{$promptExcerpt}\n";

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

        try {
            $raw = self::chatCompletions($messages, 700, true);
        } catch (\Throwable $e) {
            return [
                'ok' => true,
                'ai_valid' => false,
                'reason' => 'AI error: ' . mb_substr($e->getMessage(), 0, 120),
            ];
        }

        $parsed = self::parseJsonObject($raw);
        if ($parsed === null) {
            return [
                'ok' => true,
                'ai_valid' => false,
                'reason' => 'AI tidak mengembalikan JSON valid',
            ];
        }

        $pattern = trim((string) ($parsed['pattern'] ?? ''));
        $promptAppend = trim((string) ($parsed['prompt_append'] ?? ''));
        $reason = trim((string) ($parsed['reason'] ?? 'Usulan AI'));
        $action = strtolower(trim((string) ($parsed['action'] ?? 'insert')));
        if ($action !== 'update') {
            $action = 'insert';
        }
        $patternId = (int) ($parsed['pattern_id'] ?? 0);
        $existingPattern = trim((string) ($parsed['existing_pattern'] ?? ''));

        if ($pattern === '' || ($pattern[0] !== '/' && $pattern[0] !== '#' && $pattern[0] !== '~')) {
            return [
                'ok' => true,
                'ai_valid' => false,
                'reason' => trim($reason . ' | pattern AI kosong/invalid'),
            ];
        }

        return [
            'ok' => true,
            'ai_valid' => true,
            'action' => $action,
            'pattern_id' => $patternId,
            'existing_pattern' => $existingPattern,
            'pattern' => $pattern,
            'prompt_append' => $promptAppend,
            'reason' => $reason,
        ];
    }

    /**
     * @param list<array{id:int,pattern:string}> $patRows
     * @param array<string,mixed> $ai
     * @param array<string,mixed>|null $heuristic
     * @return array{action:string,pattern_id:int,existing_pattern:string,pattern:string,prompt_append:string,reason:string}
     */
    private static function pickProposal(string $text, array $patRows, array $ai, ?array $heuristic): array
    {
        $aiValid = !empty($ai['ai_valid']);
        $aiPattern = (string) ($ai['pattern'] ?? '');
        $aiMatches = $aiValid && $aiPattern !== '' && @preg_match($aiPattern, $text) === 1;
        $aiOverfit = $aiMatches && self::isOverfitLiteral($aiPattern, $text);
        $heuristicReady = is_array($heuristic)
            && ($heuristic['pattern'] ?? '') !== ''
            && @preg_match((string) $heuristic['pattern'], $text) === 1;

        if ($heuristicReady && ($heuristic['action'] ?? '') === 'update') {
            if ($aiMatches && !$aiOverfit && ($ai['action'] ?? '') === 'update') {
                $row = self::findPatternRow($patRows, (int) ($ai['pattern_id'] ?? 0), (string) ($ai['existing_pattern'] ?? ''));
                if ($row !== null) {
                    return self::normalizeChosen($ai, $row);
                }
            }
            return [
                'action' => 'update',
                'pattern_id' => (int) ($heuristic['pattern_id'] ?? 0),
                'existing_pattern' => (string) ($heuristic['existing_pattern'] ?? ''),
                'pattern' => (string) $heuristic['pattern'],
                'prompt_append' => (string) ($heuristic['prompt_append'] ?? ''),
                'reason' => (string) ($heuristic['reason'] ?? ''),
            ];
        }

        if ($aiMatches && !$aiOverfit) {
            $row = self::findPatternRow($patRows, (int) ($ai['pattern_id'] ?? 0), (string) ($ai['existing_pattern'] ?? ''));
            if (($ai['action'] ?? '') === 'update' && $row !== null) {
                return self::normalizeChosen($ai, $row);
            }
            $promoted = self::maybePromoteInsertToUpdate($aiPattern, $patRows);
            if ($promoted !== null) {
                return [
                    'action' => 'update',
                    'pattern_id' => $promoted['pattern_id'],
                    'existing_pattern' => $promoted['existing_pattern'],
                    'pattern' => $aiPattern,
                    'prompt_append' => trim((string) ($ai['prompt_append'] ?? '')),
                    'reason' => trim((string) ($ai['reason'] ?? '') . ' | Dipromosikan jadi ubah pattern existing (cek → cek+).'),
                ];
            }
            $merged = self::suggestMergeIntoBag($text, $patRows);
            if ($merged !== null) {
                return $merged;
            }
            return [
                'action' => 'insert',
                'pattern_id' => 0,
                'existing_pattern' => '',
                'pattern' => $aiPattern,
                'prompt_append' => trim((string) ($ai['prompt_append'] ?? '')),
                'reason' => (string) ($ai['reason'] ?? 'Usulan AI'),
            ];
        }

        if ($heuristicReady) {
            $reason = (string) ($heuristic['reason'] ?? '');
            if (!$aiValid && ($ai['reason'] ?? '') !== '') {
                $reason = trim($reason . ' | ' . $ai['reason']);
            } elseif ($aiOverfit) {
                $reason = trim($reason . ' | AI mengusulkan regex terlalu literal; dipakai pelebaran cerdas.');
            }
            return [
                'action' => (string) ($heuristic['action'] ?? 'insert'),
                'pattern_id' => (int) ($heuristic['pattern_id'] ?? 0),
                'existing_pattern' => (string) ($heuristic['existing_pattern'] ?? ''),
                'pattern' => (string) $heuristic['pattern'],
                'prompt_append' => (string) ($heuristic['prompt_append'] ?? ''),
                'reason' => $reason,
            ];
        }

        $fallback = self::buildFallbackPattern($text);
        $promoted = self::maybePromoteInsertToUpdate($fallback, $patRows);
        if ($promoted !== null) {
            return [
                'action' => 'update',
                'pattern_id' => $promoted['pattern_id'],
                'existing_pattern' => $promoted['existing_pattern'],
                'pattern' => $fallback,
                'prompt_append' => '',
                'reason' => 'Fallback cerdas: lebarkan pattern existing agar huruf berulang ikut tertangkap.',
            ];
        }
        $merged = self::suggestMergeIntoBag($text, $patRows);
        if ($merged !== null) {
            return $merged;
        }
        return [
            'action' => 'insert',
            'pattern_id' => 0,
            'existing_pattern' => '',
            'pattern' => $fallback,
            'prompt_append' => '| ' . self::shortExample($text) . ' |',
            'reason' => trim((string) ($ai['reason'] ?? '') . ' | Fallback regex cerdas (huruf berulang → +).'),
        ];
    }

    /**
     * @param array<string,mixed> $ai
     * @param array{id:int,pattern:string} $row
     * @return array{action:string,pattern_id:int,existing_pattern:string,pattern:string,prompt_append:string,reason:string}
     */
    private static function normalizeChosen(array $ai, array $row): array
    {
        return [
            'action' => 'update',
            'pattern_id' => $row['id'],
            'existing_pattern' => $row['pattern'],
            'pattern' => (string) ($ai['pattern'] ?? ''),
            'prompt_append' => trim((string) ($ai['prompt_append'] ?? '')),
            'reason' => (string) ($ai['reason'] ?? 'Ubah pattern yang sudah ada'),
        ];
    }

    /**
     * @param list<array{id:int,pattern:string}> $patRows
     * @return array{action:string,pattern_id:int,existing_pattern:string,pattern:string,prompt_append:string,reason:string}|null
     */
    private static function suggestFromExisting(string $text, array $patRows): ?array
    {
        $tokens = self::tokenize($text);
        $best = null;
        $bestScore = -1;

        foreach ($patRows as $row) {
            $pat = (string) ($row['pattern'] ?? '');
            if ($pat === '') {
                continue;
            }
            foreach (self::extractLiteralStems($pat) as $stem) {
                if (mb_strlen($stem) < 3) {
                    continue;
                }
                foreach ($tokens as $tok) {
                    if (!self::isElongationOf($tok, $stem)) {
                        continue;
                    }
                    $newPat = self::widenStemInPattern($pat, $stem);
                    if ($newPat === $pat) {
                        continue;
                    }
                    if (@preg_match($newPat, $text) !== 1) {
                        continue;
                    }
                    if (@preg_match($newPat, $stem) !== 1) {
                        continue;
                    }
                    $score = self::scoreWidenCandidate($pat, $stem);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = [
                            'action' => 'update',
                            'pattern_id' => (int) $row['id'],
                            'existing_pattern' => $pat,
                            'pattern' => $newPat,
                            'prompt_append' => '',
                            'reason' => 'Lebarkan pattern yang sudah ada: ' . $stem . ' → ' . $stem
                                . '+ agar ejaan berulang seperti "' . $tok . '" ikut tertangkap, tanpa menambah pattern baru.',
                        ];
                    }
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        $merged = self::suggestMergeIntoBag($text, $patRows);
        if ($merged !== null) {
            return $merged;
        }

        $smart = self::buildFallbackPattern($text);
        if (@preg_match($smart, $text) !== 1) {
            return null;
        }
        $promoted = self::maybePromoteInsertToUpdate($smart, $patRows);
        if ($promoted !== null) {
            return [
                'action' => 'update',
                'pattern_id' => $promoted['pattern_id'],
                'existing_pattern' => $promoted['existing_pattern'],
                'pattern' => $smart,
                'prompt_append' => '',
                'reason' => 'Lebarkan pattern yang sudah ada agar huruf berulang tertangkap (cek → cek+).',
            ];
        }

        return [
            'action' => 'insert',
            'pattern_id' => 0,
            'existing_pattern' => '',
            'pattern' => $smart,
            'prompt_append' => '| ' . self::shortExample($text) . ' |',
            'reason' => 'Tidak ada pattern existing yang cukup dekat; usul pattern baru yang sudah meng-generalisasi huruf berulang.',
        ];
    }

    /**
     * @param list<array{id:int,pattern:string}> $patRows
     * @return array{action:string,pattern_id:int,existing_pattern:string,pattern:string,prompt_append:string,reason:string}|null
     */
    private static function suggestMergeIntoBag(string $text, array $patRows): ?array
    {
        if (!class_exists('\\App\\Helpers\\Laundry\\IntentPatternBag')) {
            require_once __DIR__ . '/IntentPatternBag.php';
        }
        $newAlts = IntentPatternBag::keywordAltsFromText($text);
        if ($newAlts === []) {
            return null;
        }
        $target = IntentPatternBag::findBestBag($patRows);
        if ($target === null) {
            return null;
        }
        $merged = IntentPatternBag::addAlts($target['pattern'], $newAlts);
        if ($merged === null || $merged === $target['pattern']) {
            return null;
        }
        if (@preg_match($merged, $text) !== 1) {
            return null;
        }
        return [
            'action' => 'update',
            'pattern_id' => (int) $target['id'],
            'existing_pattern' => (string) $target['pattern'],
            'pattern' => $merged,
            'prompt_append' => '',
            'reason' => 'Gabung ke pattern yang sudah ada sebagai alternatif ('
                . implode('|', $newAlts)
                . '), bukan menambah row baru.',
        ];
    }

    /**
     * @param list<array{id:int,pattern:string}> $patRows
     * @return array{pattern_id:int,existing_pattern:string}|null
     */
    private static function maybePromoteInsertToUpdate(string $newPattern, array $patRows): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($patRows as $row) {
            $old = (string) ($row['pattern'] ?? '');
            if ($old === '' || $old === $newPattern) {
                continue;
            }
            if (!self::isWideningOf($old, $newPattern)) {
                continue;
            }
            $score = 200 - mb_strlen($old);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'pattern_id' => (int) $row['id'],
                    'existing_pattern' => $old,
                ];
            }
        }
        return $best;
    }

    /**
     * @param list<array{id:int,pattern:string}> $patRows
     * @return array{id:int,pattern:string}|null
     */
    private static function findPatternRow(array $patRows, int $id, string $pattern): ?array
    {
        if ($id > 0) {
            foreach ($patRows as $row) {
                if ((int) $row['id'] === $id && ($row['pattern'] ?? '') !== '') {
                    return ['id' => (int) $row['id'], 'pattern' => (string) $row['pattern']];
                }
            }
        }
        $pattern = trim($pattern);
        if ($pattern === '') {
            return null;
        }
        foreach ($patRows as $row) {
            if ((string) ($row['pattern'] ?? '') === $pattern) {
                return ['id' => (int) $row['id'], 'pattern' => $pattern];
            }
        }
        return null;
    }

    /**
     * @param list<array{id:int,pattern:string}> $patRows
     * @return list<string>
     */
    private static function relatedPatternLines(string $text, array $patRows): array
    {
        $tokens = self::tokenize($text);
        $lines = [];
        foreach ($patRows as $row) {
            $pat = (string) ($row['pattern'] ?? '');
            if ($pat === '') {
                continue;
            }
            foreach (self::extractLiteralStems($pat) as $stem) {
                foreach ($tokens as $tok) {
                    if (self::isElongationOf($tok, $stem) || mb_strtolower($tok) === $stem) {
                        $lines[] = '- id=' . (int) $row['id'] . ' ' . $pat . '  ← kata dasar "' . $stem . '"';
                        break 2;
                    }
                }
            }
        }
        return array_slice($lines, 0, 15);
    }

    /** @return list<string> */
    private static function tokenize(string $text): array
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
        if ($t === '') {
            return [];
        }
        preg_match_all('/\p{L}{2,}/u', $t, $m);
        return array_values(array_unique($m[0] ?? []));
    }

    /** @return list<string> */
    private static function extractLiteralStems(string $pattern): array
    {
        $split = self::splitRegex($pattern);
        $body = $split['body'] ?? '';
        if ($body === '') {
            return [];
        }
        $body = preg_replace('/\[[^\]]*\]/u', ' ', $body) ?? $body;
        $body = preg_replace('/\\\\./', ' ', $body) ?? $body;
        preg_match_all('/\p{L}{3,}/u', $body, $m);
        $stems = [];
        foreach ($m[0] as $s) {
            $stems[] = mb_strtolower($s);
        }
        return array_values(array_unique($stems));
    }

    private static function isElongationOf(string $word, string $stem): bool
    {
        $word = mb_strtolower($word);
        $stem = mb_strtolower($stem);
        if ($word === $stem || $stem === '') {
            return false;
        }
        $cWord = preg_replace('/(.)\1+/u', '$1', $word) ?? $word;
        $cStem = preg_replace('/(.)\1+/u', '$1', $stem) ?? $stem;
        return $cWord === $cStem && mb_strlen($word) > mb_strlen($stem);
    }

    private static function widenStemInPattern(string $pattern, string $stem): string
    {
        $split = self::splitRegex($pattern);
        if ($split === null) {
            return $pattern;
        }
        $q = preg_quote($stem, '/');
        $newBody = preg_replace(
            '/(^|\\\\b|[|()\\s])(' . $q . ')(?!\\+)(?=\\\\b|[|()\\s]|$|\\*)/iu',
            '$1$2+',
            $split['body'],
            1,
            $count
        );
        if (!$count || !is_string($newBody)) {
            return $pattern;
        }
        return $split['delim'] . $newBody . $split['delim'] . $split['flags'];
    }

    private static function scoreWidenCandidate(string $pattern, string $stem): int
    {
        $score = max(0, 220 - mb_strlen($pattern));
        $q = preg_quote($stem, '/');
        if (preg_match('/\\\\b' . $q . '\\\\b/iu', $pattern)) {
            $score += 80;
        }
        $score += mb_strlen($stem);
        return $score;
    }

    private static function isWideningOf(string $old, string $new): bool
    {
        $norm = static function (string $p): string {
            return preg_replace('/\s+/', '', $p) ?? $p;
        };
        $o = $norm($old);
        $n = $norm($new);
        if ($o === $n) {
            return false;
        }
        $nNoPlus = str_replace('+', '', $n);
        $oNoPlus = str_replace('+', '', $o);
        return $nNoPlus === $oNoPlus && strlen($n) > strlen($o);
    }

    private static function isOverfitLiteral(string $pattern, string $text): bool
    {
        foreach (self::tokenize($text) as $tok) {
            if (!preg_match('/(.)\1{2,}/u', $tok)) {
                continue;
            }
            $collapsed = preg_replace('/(.)\1+/u', '$1', $tok) ?? $tok;
            if ($collapsed === $tok) {
                continue;
            }
            if (mb_strpos($pattern, $tok) !== false || mb_strpos($pattern, preg_quote($tok, '/')) !== false) {
                return true;
            }
        }
        return false;
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

    /**
     * @param list<array{role:string,content:string}> $messages
     */
    private static function chatCompletions(array $messages, int $maxTokens, bool $jsonMode = false): string
    {
        $openaiKey = \App\Config\AI::getOpenAIApiKey();
        $groqKey = \App\Config\AI::getGroqApiKey();
        $temperature = 0.2;
        $timeout = max(20, (int) \App\Config\AI::getTimeout());

        $payload = [
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        if ($openaiKey !== '') {
            try {
                return self::postChat(
                    'https://api.openai.com/v1/chat/completions',
                    $openaiKey,
                    array_merge($payload, ['model' => \App\Config\AI::getOpenAIModel() ?: 'gpt-4o-mini']),
                    $timeout
                );
            } catch (\Throwable $e) {
                if ($groqKey === '') {
                    throw $e;
                }
            }
        }

        if ($groqKey === '') {
            throw new \Exception('No OpenAI or Groq API key');
        }

        try {
            return self::postChat(
                'https://api.groq.com/openai/v1/chat/completions',
                $groqKey,
                array_merge($payload, ['model' => \App\Config\AI::getGroqModel()]),
                $timeout
            );
        } catch (\Throwable $e) {
            if (!$jsonMode) {
                throw $e;
            }
            unset($payload['response_format']);
            return self::postChat(
                'https://api.groq.com/openai/v1/chat/completions',
                $groqKey,
                array_merge($payload, ['model' => \App\Config\AI::getGroqModel()]),
                $timeout
            );
        }
    }

    private static function postChat(string $url, string $apiKey, array $data, int $timeout): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new \Exception('AI HTTP error: ' . $err);
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new \Exception('AI respon invalid (HTTP ' . $code . ')');
        }
        if (!empty($decoded['error']['message'])) {
            throw new \Exception((string) $decoded['error']['message']);
        }
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || trim($content) === '') {
            throw new \Exception('AI content kosong');
        }
        return trim($content);
    }

    /** @return array<string,mixed>|null */
    private static function parseJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $raw, $m)) {
            $raw = $m[1];
        } elseif (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $fixed = preg_replace('/,\s*([}\]])/', '$1', $raw);
        $decoded = json_decode((string) $fixed, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function shortExample(string $text): string
    {
        $t = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        if (mb_strlen($t) > 80) {
            $t = mb_substr($t, 0, 77) . '...';
        }
        return $t;
    }

    /** Regex aman: literal case-insensitive, spasi fleksibel, huruf berulang → +. */
    public static function buildFallbackPattern(string $text): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $parts = preg_split('/\s+/u', $t) ?: [];
        $escaped = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $escaped[] = self::tokenToFlexibleRegex($p);
        }
        if ($escaped === []) {
            return '/.^/u'; // never matches
        }
        $body = implode('\\s+', $escaped);
        return '/\\b' . $body . '\\b/iu';
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
}
