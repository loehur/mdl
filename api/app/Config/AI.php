<?php
namespace App\Config;

/**
 * AI Configuration
 * 
 * SECURITY WARNING:
 * OpenAI API Key telah dipindahkan ke Config/Env.php (gitignored)
 * untuk keamanan. Tambahkan konstanta OPENAI_API_KEY di Env.php
 * 
 * Cara mendapatkan API Key:
 * 1. Buka https://platform.openai.com/api-keys
 * 2. Login dengan OpenAI Account
 * 3. Klik "Create new secret key"
 * 4. Copy API Key
 * 5. Paste di Env.php sebagai konstanta OPENAI_API_KEY
 *
 * Fallback intent (saat OpenAI timeout/error): tambahkan GROQ_API_KEY di Env.php
 * — https://console.groq.com/keys — model default: llama-3.1-8b-instant (override via GROQ_MODEL)
 *
 * Lihat template di bawah untuk cara setup di Env.php
 */

class AI
{
    /**
     * OpenAI Configuration (loaded from Env.php)
     */
    private static $openAiApiKey = \Env::OPENAI_API_KEY ?? '';
    private static $openAiModel = \Env::OPENAI_MODEL ?? 'gpt-4o-mini';

    /** Groq OpenAI-compatible API — dipakai jika pemanggilan OpenAI gagal (timeout, DNS, HTTP) */
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
        if (!empty(self::$openAiApiKey)) {
            return true;
        }

        return self::getGroqApiKey() !== '';
    }
}