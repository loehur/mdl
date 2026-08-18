<?php

/**
 * Sapaan customer untuk balasan petugas (Estimasi).
 * Urutan sama autoreply: sapaan_stats (CRM) → regex nama kontak → kak.
 */
class SapaanGreeting
{
    /**
     * @param object $dbCrm laundry db(100) = mdl_main
     */
    public static function resolve($dbCrm, string $phone): string
    {
        if (!class_exists('PelangganByPhone', false)) {
            require_once __DIR__ . '/PelangganByPhone.php';
        }

        $fromStats = self::fromStats($dbCrm, $phone);
        if ($fromStats !== null && $fromStats !== '') {
            return $fromStats;
        }

        $name = self::contactName($dbCrm, $phone);
        $fromRegex = self::fromContactNameRegex($name);
        if ($fromRegex !== null && $fromRegex !== '') {
            return $fromRegex;
        }

        return 'kak';
    }

    /**
     * @param object $dbCrm
     */
    private static function fromStats($dbCrm, string $phone): ?string
    {
        try {
            $variants = self::waNumberVariants($phone);
            if ($variants !== []) {
                $in = [];
                foreach ($variants as $v) {
                    $in[] = "'" . $dbCrm->escape($v) . "'";
                }
                $rows = $dbCrm->query_array(
                    'SELECT sapaan, jumlah FROM sapaan_stats WHERE wa_number IN ('
                    . implode(',', $in)
                    . ') ORDER BY jumlah DESC, sapaan ASC LIMIT 1'
                );
                $normalized = self::normalizeFromRow($rows);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            $digitsIn = preg_replace('/[^0-9]/', '', $phone) ?: '';
            if (strlen($digitsIn) >= 10) {
                $escDigits = $dbCrm->escape($digitsIn);
                $expr = PelangganByPhone::sqlDigitsExpr('wa_number');
                $rows = $dbCrm->query_array(
                    "SELECT sapaan, jumlah FROM sapaan_stats WHERE {$expr} = '{$escDigits}'"
                    . ' ORDER BY jumlah DESC, sapaan ASC LIMIT 1'
                );
                $normalized = self::normalizeFromRow($rows);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            $nomor = PelangganByPhone::toNomorNasional($phone);
            if ($nomor === null || $nomor === '') {
                return null;
            }
            $esc = $dbCrm->escape($nomor);
            $rows = $dbCrm->query_array(
                'SELECT sapaan, jumlah FROM sapaan_stats WHERE '
                . PelangganByPhone::likeSql($esc, 'wa_number')
                . ' ORDER BY jumlah DESC, sapaan ASC LIMIT 1'
            );

            return self::normalizeFromRow($rows);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param object $dbCrm
     */
    private static function contactName($dbCrm, string $phone): string
    {
        try {
            $nomor = PelangganByPhone::key($phone);
            if ($nomor === '') {
                return '';
            }
            $esc = $dbCrm->escape($nomor);
            $rows = $dbCrm->query_array(
                'SELECT contact_name FROM wa_conversations WHERE '
                . PelangganByPhone::likeSql($esc, 'wa_number')
                . ' ORDER BY last_message_at DESC LIMIT 1'
            );
            if (!is_array($rows) || empty($rows[0])) {
                return '';
            }

            return trim((string) ($rows[0]['contact_name'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @param mixed $rows */
    private static function normalizeFromRow($rows): ?string
    {
        if (!is_array($rows) || empty($rows[0])) {
            return null;
        }
        $raw = strtolower(trim((string) ($rows[0]['sapaan'] ?? '')));
        if ($raw === '') {
            return null;
        }
        $allowed = self::allowedKeywords();
        if ($allowed !== [] && !in_array($raw, $allowed, true)) {
            return null;
        }

        return $raw;
    }

    /** @return list<string> */
    private static function allowedKeywords(): array
    {
        $path = dirname(__DIR__, 3) . '/api/app/Config/SapaanStatsKeywords.php';
        if (is_file($path)) {
            $cfg = require $path;
            $list = $cfg['keywords'] ?? [];
            if (is_array($list) && $list !== []) {
                return array_map('strtolower', $list);
            }
        }

        return ['pak', 'bu', 'buk', 'kak', 'kk', 'bg', 'bang', 'mbak', 'mas', 'om', 'nte', 'ko', 'ce', 'cece', 'tante'];
    }

    /** @return list<string> */
    private static function waNumberVariants(string $waNumber): array
    {
        $trimmed = trim($waNumber);
        $d = preg_replace('/[^0-9]/', '', $trimmed) ?: '';
        $out = [$trimmed];
        if (strlen($d) < 9) {
            return array_values(array_unique(array_filter($out)));
        }
        if (strpos($d, '62') === 0 && strlen($d) >= 11) {
            $rest = substr($d, 2);
            $out[] = '+' . $d;
            $out[] = $d;
            $out[] = '0' . $rest;
            if (strpos($rest, '8') === 0 || strlen($rest) >= 9) {
                $out[] = $rest;
            }
        } elseif (strpos($d, '0') === 0 && strlen($d) >= 10) {
            $out[] = $d;
            $out[] = '62' . substr($d, 1);
            $out[] = '+62' . substr($d, 1);
        } elseif (strpos($d, '8') === 0 && strlen($d) >= 9) {
            $out[] = $d;
            $out[] = '0' . $d;
            $out[] = '62' . $d;
            $out[] = '+62' . $d;
        }

        return array_values(array_unique(array_filter($out)));
    }

    private static function fromContactNameRegex(?string $contactName): ?string
    {
        $n = strtolower(trim((string) $contactName));
        if ($n === '') {
            return null;
        }
        if (preg_match('/\b(pakde|pak\s*de)\b/i', $n)) {
            return 'pakde';
        }
        if (preg_match('/\b(bude|bukde|bu\s*de|buk\s*de)\b/i', $n)) {
            return 'bude';
        }
        if (preg_match('/\b(ibu|ibuk|bu|buk)\b/', $n)) {
            return 'bu';
        }
        if (preg_match('/^b\s|^b\./i', $n)) {
            return 'bu';
        }
        if (preg_match('/\b(bapak|pak|bpk)\b/', $n)) {
            return 'pak';
        }
        if (preg_match('/\bom\b/', $n)) {
            return 'om';
        }
        if (preg_match('/\bmas\b/', $n)) {
            return 'mas';
        }
        if (preg_match('/\bmbak\b/', $n)) {
            return 'mbak';
        }
        if (preg_match('/\b(bg|bang)\b|^bg/i', $n)) {
            return 'bang';
        }
        if (preg_match('/\b(kak|kakak)\b/', $n)) {
            return 'kak';
        }

        return null;
    }
}
