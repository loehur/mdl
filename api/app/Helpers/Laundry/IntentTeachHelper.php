<?php

namespace App\Helpers\Laundry;

/**
 * Intent Lab — usulkan pattern + potongan ai_prompt agar kalimat masuk intent target.
 */
class IntentTeachHelper
{
    /**
     * @return array{
     *   ok:bool,
     *   message?:string,
     *   intent?:string,
     *   text?:string,
     *   pattern?:string,
     *   prompt_append?:string,
     *   reason?:string,
     *   matches_text?:bool,
     *   pattern_exists?:bool,
     *   current_prompt?:string,
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
                'SELECT pattern FROM wa_autoreply_patterns WHERE intent_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 12',
                [(int) $intent['id']]
            )->result_array();
            $samplePatterns = [];
            foreach ($patRows ?: [] as $r) {
                $samplePatterns[] = $r['pattern'];
            }

            // Sudah match pattern existing?
            foreach ($samplePatterns as $pat) {
                if (@preg_match($pat, $text) === 1) {
                    return [
                        'ok' => true,
                        'intent' => $intentCode,
                        'text' => $text,
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

            $ai = self::askAiForProposal($text, $intentCode, (string) ($intent['ai_prompt'] ?? ''), $samplePatterns);
            if (empty($ai['ok'])) {
                return $ai;
            }

            $pattern = (string) ($ai['pattern'] ?? '');
            $promptAppend = trim((string) ($ai['prompt_append'] ?? ''));
            $reason = trim((string) ($ai['reason'] ?? ''));

            $valid = @preg_match($pattern, $text);
            if ($valid === false) {
                // Fallback deterministic
                $fallback = self::buildFallbackPattern($text);
                $pattern = $fallback;
                $valid = @preg_match($pattern, $text);
                $reason = trim($reason . ' | AI regex invalid; dipakai fallback aman.');
            }
            if ($valid !== 1) {
                $fallback = self::buildFallbackPattern($text);
                if (@preg_match($fallback, $text) === 1) {
                    $pattern = $fallback;
                    $valid = 1;
                    $reason = trim($reason . ' | Regex AI tidak match contoh; diganti fallback.');
                }
            }

            $exists = false;
            foreach ($samplePatterns as $pat) {
                if ($pat === $pattern) {
                    $exists = true;
                    break;
                }
            }

            if ($promptAppend === '') {
                $promptAppend = '| ' . self::shortExample($text) . ' |';
            }

            return [
                'ok' => true,
                'intent' => $intentCode,
                'text' => $text,
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
     * @param list<string> $samplePatterns
     * @return array{ok:bool,pattern?:string,prompt_append?:string,reason?:string,message?:string,error?:string}
     */
    private static function askAiForProposal(string $text, string $intentCode, string $currentPrompt, array $samplePatterns): array
    {
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../../Config/AI.php';
        }
        if (!\App\Config\AI::isEnabled()) {
            return [
                'ok' => true,
                'pattern' => self::buildFallbackPattern($text),
                'prompt_append' => '| ' . self::shortExample($text) . ' |',
                'reason' => 'AI disabled — fallback regex literal (case-insensitive).',
            ];
        }

        $openaiKey = \App\Config\AI::getOpenAIApiKey();
        $groqKey = \App\Config\AI::getGroqApiKey();
        if ($openaiKey === '' && $groqKey === '') {
            return [
                'ok' => true,
                'pattern' => self::buildFallbackPattern($text),
                'prompt_append' => '| ' . self::shortExample($text) . ' |',
                'reason' => 'No AI key — fallback regex literal.',
            ];
        }

        $patList = $samplePatterns === []
            ? '(belum ada)'
            : implode("\n", array_map(static fn ($p) => '- ' . $p, array_slice($samplePatterns, 0, 10)));
        $promptExcerpt = mb_substr(trim($currentPrompt), 0, 1800);
        if ($promptExcerpt === '') {
            $promptExcerpt = '(kosong)';
        }

        $system = "Kamu membantu merawat klasifikasi intent WhatsApp laundry (PHP PCRE + prompt AI).\n"
            . "Tugas: agar pesan customer MASUK intent target.\n"
            . "Aturan regex:\n"
            . "- Wajib format PHP delimiter, contoh /pola/iu\n"
            . "- Harus match PESAN contoh (case-insensitive)\n"
            . "- Tangkap slang/typo wajar, JANGAN terlalu luas (hindari .*)\n"
            . "- Prefer \\b untuk kata kunci; izinkan spasi fleksibel \\s*\n"
            . "- Jangan bentrok intent lain secara agresif\n"
            . "Aturan prompt_append:\n"
            . "- Satu baris pendek contoh untuk ditambahkan ke ai_prompt, format: | contoh kalimat |\n"
            . "- Bahasa Indonesia santai seperti chat customer\n"
            . "Balas HANYA JSON valid tanpa markdown:\n"
            . '{"pattern":"/.../iu","prompt_append":"| ... |","reason":"singkat"}';

        $user = "Intent target: {$intentCode}\n"
            . "Pesan customer:\n\"\"\"\n{$text}\n\"\"\"\n\n"
            . "Sample pattern aktif intent ini:\n{$patList}\n\n"
            . "Cuplikan ai_prompt saat ini:\n{$promptExcerpt}\n";

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

        try {
            $raw = self::chatCompletions($messages, 500);
        } catch (\Throwable $e) {
            return [
                'ok' => true,
                'pattern' => self::buildFallbackPattern($text),
                'prompt_append' => '| ' . self::shortExample($text) . ' |',
                'reason' => 'AI error — fallback: ' . mb_substr($e->getMessage(), 0, 120),
            ];
        }

        $parsed = self::parseJsonObject($raw);
        if ($parsed === null) {
            return [
                'ok' => true,
                'pattern' => self::buildFallbackPattern($text),
                'prompt_append' => '| ' . self::shortExample($text) . ' |',
                'reason' => 'AI tidak mengembalikan JSON valid — fallback regex.',
            ];
        }

        $pattern = trim((string) ($parsed['pattern'] ?? ''));
        $promptAppend = trim((string) ($parsed['prompt_append'] ?? ''));
        $reason = trim((string) ($parsed['reason'] ?? 'Usulan AI'));

        if ($pattern === '' || $pattern[0] !== '/') {
            $pattern = self::buildFallbackPattern($text);
            $reason .= ' | pattern AI diganti fallback';
        }

        return [
            'ok' => true,
            'pattern' => $pattern,
            'prompt_append' => $promptAppend,
            'reason' => $reason,
        ];
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     */
    private static function chatCompletions(array $messages, int $maxTokens): string
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

        return self::postChat(
            'https://api.groq.com/openai/v1/chat/completions',
            $groqKey,
            array_merge($payload, ['model' => \App\Config\AI::getGroqModel()]),
            $timeout
        );
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
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }
        $decoded = json_decode($raw, true);
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

    /** Regex aman: literal case-insensitive, spasi fleksibel. */
    public static function buildFallbackPattern(string $text): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $parts = preg_split('/\s+/u', $t) ?: [];
        $escaped = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $escaped[] = preg_quote($p, '/');
        }
        if ($escaped === []) {
            return '/.^/u'; // never matches
        }
        $body = implode('\\s+', $escaped);
        return '/\\b' . $body . '\\b/iu';
    }
}
