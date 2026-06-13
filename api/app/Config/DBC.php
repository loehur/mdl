<?php

class DBC
{
    const db_host = \Env::DB_HOST ?? 'localhost';

    /**
     * Default DB credentials per environment.
     * Env.php DB_CREDENTIALS will MERGE over these (not replace entirely).
     */
    private static function defaultDbm(): array
    {
        return [
            'dev' => [
                0 => ['db' => 'mdl_main', 'user' => 'root', 'pass' => ''],
                1 => ['db' => 'mdl_laundry', 'user' => 'root', 'pass' => ''],
                2 => ['db' => 'mdl_resto', 'user' => 'root', 'pass' => ''],
                3 => ['db' => 'mdl_water', 'user' => 'root', 'pass' => ''],
                4 => ['db' => 'mdl_salon', 'user' => 'root', 'pass' => ''],
                5 => ['db' => 'mdl_investasi', 'user' => 'root', 'pass' => ''],
                6 => ['db' => 'mdl_invoice', 'user' => 'root', 'pass' => ''],
            ],
            'pro' => [
                0 => ['db' => 'mdl_main', 'user' => 'mdl_main', 'pass' => 'wB5KjfjRYfPXBtFF'],
                1 => ['db' => 'mdl_laundry', 'user' => 'mdl_laundry', 'pass' => '3p66WMjmPa6AmidN'],
                2 => ['db' => 'mdl_resto', 'user' => 'mdl_resto', 'pass' => 'BY4PRtSDysp8Akfz'],
                3 => ['db' => 'mdl_water', 'user' => 'mdl_water', 'pass' => 'csFW7fjxxTXB7ryR'],
                4 => ['db' => 'mdl_salon', 'user' => 'mdl_salon', 'pass' => 'W6FLRYyeKFZdTpHC'],
                5 => ['db' => 'mdl_investasi', 'user' => 'mdl_investasi', 'pass' => ''],
                6 => ['db' => 'mdl_invoice', 'user' => 'mdl_invoice', 'pass' => ''],
            ],
        ];
    }

    /**
     * Merge Env credentials with defaults so new DB indexes are not lost.
     */
    public static function dbm(): array
    {
        $merged = self::defaultDbm();

        if (!defined('Env::DB_CREDENTIALS')) {
            return $merged;
        }

        $envCreds = \Env::DB_CREDENTIALS;
        if (!is_array($envCreds)) {
            return $merged;
        }

        foreach ($merged as $mode => $databases) {
            if (empty($envCreds[$mode]) || !is_array($envCreds[$mode])) {
                continue;
            }

            foreach ($envCreds[$mode] as $index => $config) {
                if (!is_array($config)) {
                    continue;
                }

                $base = $merged[$mode][$index] ?? ['db' => '', 'user' => '', 'pass' => ''];
                $merged[$mode][$index] = array_merge($base, $config);
            }
        }

        return $merged;
    }

    public static function getDbConfig(int $index): array
    {
        $mode = \Env::MODE ?? 'dev';
        $dbm = self::dbm();

        if (!isset($dbm[$mode][$index])) {
            throw new \RuntimeException("Database config index {$index} tidak ditemukan untuk mode '{$mode}'");
        }

        return $dbm[$mode][$index];
    }
}
