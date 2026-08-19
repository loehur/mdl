<?php

namespace App\Helpers\CRM;

/**
 * Mapping LID WhatsApp → nomor HP.
 * File utama: api/data/lid_phone_map.json (dalam open_basedir PHP).
 * Ditulis juga oleh node/fonnte_server (LID_MAP_MIRROR_FILE).
 */
class FonnteLidMap
{
    /** @var array<string, string>|null lid JID → phone JID */
    private static $cache = null;

    public static function mapFilePath(): string
    {
        if (class_exists('\\Env') && defined('Env::FONNTE_LID_MAP_FILE')) {
            $fromEnv = trim((string) \Env::FONNTE_LID_MAP_FILE);
            if ($fromEnv !== '') {
                return $fromEnv;
            }
        }

        return dirname(__DIR__, 3) . '/data/lid_phone_map.json';
    }

    /**
     * @return array<string, string>
     */
    private static function loadMap(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        $path = self::mapFilePath();
        if (!is_file($path)) {
            return self::$cache;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return self::$cache;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return self::$cache;
        }
        foreach ($data as $lid => $phone) {
            $lidJid = self::normalizeLidJid((string) $lid);
            $phoneJid = self::normalizePhoneJid((string) $phone);
            if ($lidJid !== '' && $phoneJid !== '') {
                self::$cache[$lidJid] = $phoneJid;
            }
        }

        return self::$cache;
    }

    /** @return string|null Nomor 628… */
    public static function phoneDigitsForLid(string $lid): ?string
    {
        $lidJid = self::normalizeLidJid($lid);
        if ($lidJid === '') {
            return null;
        }
        $map = self::loadMap();
        $phoneJid = $map[$lidJid] ?? null;
        if ($phoneJid === null || $phoneJid === '') {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $phoneJid);
        if ($digits === '' || !WaSenderContext::looksLikeIndonesianMobile($digits)) {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '62') && str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return $digits;
    }

    private static function normalizeLidJid(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '@')) {
            return $value;
        }
        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits !== '' ? ($digits . '@lid') : '';
    }

    private static function normalizePhoneJid(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '@')) {
            return $value;
        }
        $digits = preg_replace('/[^0-9]/', '', $value);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '62') && str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return $digits . '@s.whatsapp.net';
    }
}
