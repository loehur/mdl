<?php

namespace App\Helpers\CRM;

use App\Core\DB;

/**
 * Sapaan pelanggan untuk crew CRM — sapaan_stats → regex nama → kak.
 */
class SapaanResolver
{
    public static function resolve(string $waNumber): string
    {
        $db = DB::getInstance(0);
        $fromStats = self::fromStats($db, $waNumber);
        if ($fromStats !== null && $fromStats !== '') {
            return $fromStats;
        }

        $name = self::contactName($db, $waNumber);
        $fromRegex = self::fromContactNameRegex($name);
        if ($fromRegex !== null && $fromRegex !== '') {
            return $fromRegex;
        }

        return 'kak';
    }

    /** @param object $db */
    private static function fromStats($db, string $phone): ?string
    {
        try {
            [, $variants] = CrmChatMergeHelper::phoneInClause($phone);
            if ($variants !== []) {
                $placeholders = implode(',', array_fill(0, count($variants), '?'));
                $db->query(
                    "SELECT sapaan FROM sapaan_stats WHERE wa_number IN ({$placeholders})"
                    . ' ORDER BY jumlah DESC, sapaan ASC LIMIT 1',
                    $variants
                );
                if ($db->num_rows() > 0) {
                    $normalized = self::normalizeSapaan((string) ($db->row()->sapaan ?? ''));
                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            }

            $digits = preg_replace('/[^0-9]/', '', $phone) ?: '';
            if (strlen($digits) >= 10) {
                $expr = "REPLACE(REPLACE(REPLACE(REPLACE(wa_number,'+',''),'-',''),' ',''),'.','')";
                $db->query(
                    "SELECT sapaan FROM sapaan_stats WHERE {$expr} = ? ORDER BY jumlah DESC, sapaan ASC LIMIT 1",
                    [$digits]
                );
                if ($db->num_rows() > 0) {
                    return self::normalizeSapaan((string) ($db->row()->sapaan ?? ''));
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /** @param object $db */
    private static function contactName($db, string $phone): string
    {
        try {
            [, $variants] = CrmChatMergeHelper::phoneInClause($phone);
            if ($variants === []) {
                return '';
            }
            $placeholders = implode(',', array_fill(0, count($variants), '?'));
            $db->query(
                "SELECT contact_name FROM wa_conversations WHERE wa_number IN ({$placeholders})"
                . ' ORDER BY last_message_at DESC LIMIT 1',
                $variants
            );
            if ($db->num_rows() === 0) {
                return '';
            }

            return trim((string) ($db->row()->contact_name ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function normalizeSapaan(string $raw): ?string
    {
        $raw = strtolower(trim($raw));
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
        $path = dirname(__DIR__, 2) . '/Config/SapaanStatsKeywords.php';
        if (is_file($path)) {
            $cfg = require $path;
            $list = $cfg['keywords'] ?? [];
            if (is_array($list) && $list !== []) {
                return array_map('strtolower', $list);
            }
        }

        return ['pak', 'bu', 'buk', 'kak', 'kk', 'bg', 'bang', 'mbak', 'mas', 'om', 'nte', 'ko', 'ce', 'cece', 'tante'];
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
