<?php
namespace App\Config;

/**
 * AI Configuration
 *
 * SECURITY WARNING:
 * API key dipindahkan ke Config/Env.php (gitignored).
 *
 * Env.php:
 *   OPENAI_API_KEY, OPENAI_MODEL
 *   GEMINI_API_KEY, GEMINI_MODEL
 *   AI_PRIORITY = 'gemini' | 'openai'
 *     gemini → Gemini dulu, OpenAI cadangan
 *     openai → OpenAI dulu, Gemini cadangan
 */

class AI
{
    /**
     * OpenAI Configuration (loaded from Env.php)
     */
    private static $openAiApiKey = \Env::OPENAI_API_KEY ?? '';
    private static $openAiModel = \Env::OPENAI_MODEL ?? 'gpt-4o-mini';

    /** Gemini OpenAI-compatible API */
    private static $geminiDefaultModel = 'gemini-2.5-flash';

    /**
     * AI Settings
     */
    private static $temperature = 0.1;  // Low temperature untuk konsistensi klasifikasi
    private static $maxTokens = 50;     // Cukup untuk response 1 kata
    private static $timeout = 20;       // Timeout dalam detik (Increased to 20s for stability)

    /**
     * Enable/Disable AI Fallback
     */
    private static $aiEnabled = true;  // Set true jika sudah isi API key

    /**
     * Get OpenAI API Key
     */
    public static function getOpenAIApiKey()
    {
        return self::$openAiApiKey;
    }

    /**
     * Get OpenAI Model
     */
    public static function getOpenAIModel()
    {
        return self::$openAiModel;
    }

    /**
     * Gemini API key (opsional). Konstanta Env::GEMINI_API_KEY — jika tidak ada, kembalikan string kosong.
     */
    public static function getGeminiApiKey(): string
    {
        if (!\defined('Env::GEMINI_API_KEY')) {
            return '';
        }

        return (string) \Env::GEMINI_API_KEY;
    }

    /**
     * Model Gemini untuk chat completions (OpenAI-compatible).
     */
    public static function getGeminiModel(): string
    {
        if (\defined('Env::GEMINI_MODEL') && (string) \Env::GEMINI_MODEL !== '') {
            return (string) \Env::GEMINI_MODEL;
        }

        return self::$geminiDefaultModel;
    }

    /**
     * Primary provider: gemini | openai (default openai agar Env lama tetap sama).
     * Nilai lama 'groq' dipetakan ke 'gemini'.
     */
    public static function getPriority(): string
    {
        $raw = '';
        if (\defined('Env::AI_PRIORITY')) {
            $raw = strtolower(trim((string) \Env::AI_PRIORITY));
        }
        if ($raw === 'groq') {
            $raw = 'gemini';
        }
        if (in_array($raw, ['gemini', 'openai'], true)) {
            return $raw;
        }

        return 'openai';
    }

    /**
     * Provider yang punya API key, urut sesuai AI_PRIORITY.
     *
     * @return list<array{id:string,label:string,url:string,key:string,model:string}>
     */
    public static function getProvidersInOrder(): array
    {
        $openai = [
            'id' => 'openai',
            'label' => 'OpenAI',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'key' => (string) self::getOpenAIApiKey(),
            'model' => (string) (self::getOpenAIModel() ?: 'gpt-4o-mini'),
        ];
        $gemini = [
            'id' => 'gemini',
            'label' => 'Gemini',
            'url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'key' => self::getGeminiApiKey(),
            'model' => self::getGeminiModel(),
        ];
        $ordered = self::getPriority() === 'gemini'
            ? [$gemini, $openai]
            : [$openai, $gemini];

        return array_values(array_filter(
            $ordered,
            static function (array $p): bool {
                return trim($p['key']) !== '';
            }
        ));
    }

    /** Contoh: "Gemini primary (OpenAI fallback)" */
    public static function describePriority(): string
    {
        $providers = self::getProvidersInOrder();
        if ($providers === []) {
            return 'none';
        }
        $primary = $providers[0]['label'];
        $alts = [];
        for ($i = 1, $n = count($providers); $i < $n; $i++) {
            $alts[] = $providers[$i]['label'];
        }
        if ($alts === []) {
            return $primary . ' only';
        }

        return $primary . ' primary (' . implode('/', $alts) . ' fallback)';
    }

    /**
     * Get Temperature
     */
    public static function getTemperature()
    {
        return self::$temperature;
    }

    /**
     * Get Max Tokens
     */
    public static function getMaxTokens()
    {
        return self::$maxTokens;
    }

    /**
     * Get Timeout
     */
    public static function getTimeout()
    {
        return self::$timeout;
    }

    /**
     * Check if AI is enabled
     */
    public static function isEnabled()
    {
        if (!self::$aiEnabled) {
            return false;
        }

        return self::getProvidersInOrder() !== [];
    }
}
