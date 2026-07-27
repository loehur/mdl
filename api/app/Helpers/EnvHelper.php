<?php

/**
 * Helper terkait Env (mode app, kata privat WA).
 * Logic di sini; konstanta tetap di Config/Env.php.
 */
class EnvHelper
{
    public static function isProduction(): bool
    {
        return class_exists('Env', false) && Env::MODE === 'pro';
    }

    public static function isDev(): bool
    {
        return !class_exists('Env', false) || Env::MODE === 'dev';
    }

    /**
     * True jika teks mengandung salah satu Env::WA_PRIVATE_WORDS (case-insensitive).
     * @param mixed $text
     */
    public static function textContainsPrivateWord($text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }
        if (!is_string($text)) {
            $text = (string) $text;
        }

        $words = [];
        if (class_exists('Env', false) && defined('Env::WA_PRIVATE_WORDS')) {
            $words = Env::WA_PRIVATE_WORDS;
        }
        if (!is_array($words) || $words === []) {
            return false;
        }

        $lower = mb_strtolower($text);
        foreach ($words as $word) {
            $wordLower = mb_strtolower((string) $word);
            if ($wordLower !== '' && mb_strpos($lower, $wordLower) !== false) {
                return true;
            }
        }
        return false;
    }
}
