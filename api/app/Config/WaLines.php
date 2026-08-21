<?php

namespace App\Config;

/**
 * Mapping nomor bisnis YCloud (line_key → phone + label UI).
 * DB menyimpan business_phone (E.164), bukan short_label A/B.
 */
class WaLines
{
    public const KEY_ADMIN = 'admin';
    public const KEY_CS = 'cs';

    /** @var array<string, array{key:string,phone:string,short_label:string,display_name:string}>|null */
    private static $cache = null;

    /**
     * @return array<string, array{key:string,phone:string,short_label:string,display_name:string}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $adminPhone = self::envPhone('WA_LINE_ADMIN_PHONE', '+628117686252');
        $csPhone = self::envPhone('WA_LINE_CS_PHONE', null);
        if ($csPhone === null || $csPhone === '') {
            $csPhone = self::envPhone('WA_PHONE_NUMBER', '+6281170706611');
        }

        self::$cache = [
            self::KEY_ADMIN => [
                'key' => self::KEY_ADMIN,
                'phone' => $adminPhone,
                'short_label' => self::envStr('WA_LINE_ADMIN_LABEL', 'A'),
                'display_name' => self::envStr('WA_LINE_ADMIN_NAME', 'Admin'),
            ],
            self::KEY_CS => [
                'key' => self::KEY_CS,
                'phone' => $csPhone,
                'short_label' => self::envStr('WA_LINE_CS_LABEL', 'B'),
                'display_name' => self::envStr('WA_LINE_CS_NAME', 'CS'),
            ],
        ];

        return self::$cache;
    }

    /**
     * @return array{key:string,phone:string,short_label:string,display_name:string}
     */
    public static function get(string $lineKey): ?array
    {
        $all = self::all();

        return $all[$lineKey] ?? null;
    }

    /**
     * @return array{key:string,phone:string,short_label:string,display_name:string}
     */
    public static function defaultLine(): array
    {
        return self::get(self::KEY_CS) ?? reset(self::all());
    }

    /** @return string[] E.164 phones */
    public static function allPhones(): array
    {
        $phones = [];
        foreach (self::all() as $line) {
            $phones[] = $line['phone'];
        }

        return array_values(array_unique($phones));
    }

    public static function envPhone(string $const, ?string $fallback): string
    {
        $raw = null;
        if (defined('Env::' . $const)) {
            $raw = constant('Env::' . $const);
        }
        if ($raw === null || trim((string) $raw) === '') {
            $raw = $fallback;
        }

        return self::normalizeE164((string) $raw);
    }

    public static function envStr(string $const, string $fallback): string
    {
        if (defined('Env::' . $const)) {
            $v = trim((string) constant('Env::' . $const));
            if ($v !== '') {
                return $v;
            }
        }

        return $fallback;
    }

    public static function normalizeE164(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', trim($phone));
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '62') && str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return '+' . $digits;
    }

    /** API payload untuk satu line (tanpa duplikasi phone di root jika tidak perlu). */
    public static function lineMeta(string $lineKey): ?array
    {
        $line = self::get($lineKey);
        if (!$line) {
            return null;
        }

        return [
            'line_key' => $line['key'],
            'business_phone' => $line['phone'],
            'line_label' => $line['short_label'],
            'line_name' => $line['display_name'],
        ];
    }
}
