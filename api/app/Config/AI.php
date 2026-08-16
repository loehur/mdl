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
 *   GROQ_API_KEY, GROQ_MODEL
 *   AI_PRIORITY = 'groq' | 'openai'
 *     groq   → Groq dulu, OpenAI cadangan
 *     openai → OpenAI dulu, Groq cadangan
 */

class AI
{
    /**
     * OpenAI Configuration (loaded from Env.php)
     */
    private static $openAiApiKey = \Env::OPENAI_API_KEY ?? '';
    private static $openAiModel = \Env::OPENAI_MODEL ?? 'gpt-4o-mini';

    /** Groq OpenAI-compatible API */
    private static $groqDefaultModel = 'llama-3.1-8b-instant';

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
     * Groq API key (opsional). Konstanta Env::GROQ_API_KEY — jika tidak ada, kembalikan string kosong.
     */
    public static function getGroqApiKey(): string
    {
        if (!\defined('Env::GROQ_API_KEY')) {
            return '';
        }

        return (string) \Env::GROQ_API_KEY;
    }

    /**
     * Model Groq untuk chat completions (OpenAI-compatible).
     */
    public static function getGroqModel(): string
    {
        if (\defined('Env::GROQ_MODEL') && (string) \Env::GROQ_MODEL !== '') {
            return (string) \Env::GROQ_MODEL;
        }

        return self::$groqDefaultModel;
    }

    /**
     * Primary provider: groq | openai (default openai agar Env lama tetap sama).
     */
    public static function getPriority(): string
    {
        $raw = '';
        if (\defined('Env::AI_PRIORITY')) {
            $raw = strtolower(trim((string) \Env::AI_PRIORITY));
        }
        if (in_array($raw, ['groq', 'openai'], true)) {
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
        $groq = [
            'id' => 'groq',
            'label' => 'Groq',
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'key' => self::getGroqApiKey(),
            'model' => self::getGroqModel(),
        ];
        $ordered = self::getPriority() === 'groq'
            ? [$groq, $openai]
            : [$openai, $groq];

        return array_values(array_filter(
            $ordered,
            static function (array $p): bool {
                return trim($p['key']) !== '';
            }
        ));
    }

    /** Contoh: "Groq primary (OpenAI fallback)" */
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
