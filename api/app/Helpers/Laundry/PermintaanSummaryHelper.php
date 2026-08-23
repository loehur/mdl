<?php

namespace App\Helpers\Laundry;

/**
 * Ringkasan formal permintaan/pertanyaan pelanggan — dipakai webhook, laundry, CRM API.
 */
class PermintaanSummaryHelper
{
    /** Prompt system AI (satu sumber untuk API + laundry). */
    public static function aiSystemPrompt(int $maxChars = 220): string
    {
        return "Kamu merangkum chat pelanggan laundry menjadi SATU baris ringkasan formal Bahasa Indonesia.\n"
            . "WAJIB gabungkan SEMUA bubble chat bernomor (bukan hanya yang terakhir).\n\n"
            . "ATURAN FORMAT (WAJIB):\n"
            . "- Pisahkan tiap poin dengan titik koma (;) — bukan slash (/), bukan koma daftar.\n"
            . "- Pertanyaan (info/kapan/jam/estimasi/bisa-tidak) → klause diawali \"Menanyakan …\"\n"
            . "- Permintaan (aksi/perlakuan khusus) → klause diawali \"Meminta …\"\n"
            . "- Jika ada keduanya: \"Menanyakan …\" dulu, lalu \"Meminta …\".\n"
            . "- Huruf kapital di awal setiap klause; akhiri seluruh ringkasan dengan titik (.).\n"
            . "- Tanpa sapaan (kak/bu/pak), emoji, tanda kutip, nomor urut, prefix i-/o-.\n"
            . "- Maksimal ~{$maxChars} karakter.\n\n"
            . "CONTOH:\n"
            . "Chat: kapan siap + dulukan baju sekolah\n"
            . "→ Menanyakan kapan cucian siap; meminta dulukan baju sekolah.\n\n"
            . "Chat: jam berapa bisa diambil + besok pagi bisa?\n"
            . "→ Menanyakan jam berapa bisa diambil; menanyakan apakah besok pagi memungkinkan.\n\n"
            . "Chat: plastik terpisah + pramuka duluan\n"
            . "→ Meminta plastik terpisah; meminta dulukan baju pramuka.\n\n"
            . "Jawaban HANYA teks ringkasan.";
    }

    public static function stripPreviewPrefix(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^[io]\-\s+/iu', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Rapikan tampilan ringkasan: buang koma/titik koma di awal, normalisasi pemisah, titik di akhir.
     */
    public static function normalize(string $text): string
    {
        $text = self::stripPreviewPrefix($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B\"'");
        $text = preg_replace('/^(baik|oke|ok|siap)[,.]?\s+/iu', '', $text) ?? $text;
        $text = preg_replace('/^[,;.\-–—:\s]+/u', '', $text) ?? $text;
        $text = preg_replace('#\s*/\s*#u', '; ', $text) ?? $text;
        $text = preg_replace('/\s*;\s*/u', '; ', $text) ?? $text;
        $text = preg_replace('/;\s*;/u', '; ', $text) ?? $text;
        $text = trim($text, " ;");

        if ($text === '') {
            return '';
        }

        $text = self::formalizeClauseStarters($text);

        if (!preg_match('/[.!?]$/u', $text)) {
            $text .= '.';
        }

        return trim($text);
    }

    /** @return string Ringkasan siap simpan/tampil, max $maxLen karakter. */
    public static function finalize(string $text, int $maxLen = 500): string
    {
        $text = self::normalize($text);
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $maxLen);
    }

    /**
     * Apakah summary masih dump chat mentah (bukan ringkasan AI formal)?
     *
     * @param list<string> $lines
     */
    public static function looksLikeRawDump(string $summary, array $lines): bool
    {
        $summary = self::stripPreviewPrefix($summary);
        if ($summary === '') {
            return true;
        }

        if (preg_match('/^[io]\-\s+/iu', $summary)) {
            return true;
        }

        if (preg_match('/^[,;.\-–—:\s]/u', $summary)) {
            return true;
        }

        // Ringkasan formal (Menanyakan/Meminta + titik koma) = sudah bagus
        if (self::looksFormalSummary($summary)) {
            return false;
        }

        if (count($lines) >= 2) {
            $last = mb_strtolower(trim((string) end($lines)));
            if ($last !== '' && mb_strtolower($summary) === $last) {
                return true;
            }
        }

        if (count($lines) >= 2 && mb_strlen($summary) > 90) {
            $hits = 0;
            foreach ($lines as $line) {
                $needle = mb_substr($line, 0, min(20, mb_strlen($line)));
                if ($needle !== '' && mb_stripos($summary, $needle) !== false) {
                    $hits++;
                }
            }
            if ($hits >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback singkat dari baris chat (tanpa AI).
     *
     * @param list<string> $lines
     */
    public static function shortFallbackFromLines(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        $parts = [];
        foreach (array_slice($lines, -3) as $line) {
            $line = self::stripPreviewPrefix(trim($line));
            $line = preg_replace('/\b(kak|gan|bos|min|bang|bu|pak)\b/iu', '', $line) ?? $line;
            $line = preg_replace('/^[,;.\s]+/u', '', trim($line)) ?? trim($line);
            $line = preg_replace('/\s+/u', ' ', $line) ?? $line;
            if ($line !== '' && mb_strlen($line) >= 2) {
                $parts[] = $line;
            }
        }

        if ($parts === []) {
            return '';
        }

        $joined = implode('; ', $parts);

        return self::finalize(self::guessFormalFromChat($joined), 280);
    }

    public static function fallbackFromRawLog(string $fullRawLog, string $prevSummary = '', string $newMsg = ''): string
    {
        $parts = preg_split('/\n---\n/', trim($fullRawLog)) ?: [];
        $parts = array_values(array_filter(array_map(static function ($l) {
            return self::stripPreviewPrefix(trim((string) $l));
        }, $parts), static function ($l) {
            return $l !== '';
        }));

        if ($parts !== []) {
            return self::finalize(self::guessFormalFromChat(implode('; ', $parts)), 500);
        }

        if ($prevSummary !== '') {
            $merged = $newMsg !== ''
                ? self::finalize($prevSummary . '; ' . $newMsg, 500)
                : self::finalize($prevSummary, 500);

            return $merged;
        }

        return self::finalize($newMsg, 280);
    }

    private static function looksFormalSummary(string $summary): bool
    {
        return (bool) preg_match('/^(Menanyakan|Meminta)\b/u', trim($summary));
    }

    private static function formalizeClauseStarters(string $text): string
    {
        $text = preg_replace('/^tanya\b/iu', 'Menanyakan', $text) ?? $text;
        $text = preg_replace('/^minta\b/iu', 'Meminta', $text) ?? $text;
        $text = preg_replace('/^dulukan\b/iu', 'Meminta dulukan', $text) ?? $text;
        $text = preg_replace('/^ambil\b/iu', 'Meminta ambil', $text) ?? $text;

        $text = preg_replace_callback(
            '/;\s*(\p{Ll})/u',
            static function (array $m) {
                return '; ' . mb_strtoupper($m[1], 'UTF-8');
            },
            $text
        ) ?? $text;

        $text = preg_replace('/;\s*tanya\b/iu', '; menanyakan', $text) ?? $text;
        $text = preg_replace('/;\s*minta\b/iu', '; meminta', $text) ?? $text;
        $text = preg_replace('/;\s*dulukan\b/iu', '; meminta dulukan', $text) ?? $text;
        $text = preg_replace('/;\s*ambil\b/iu', '; meminta ambil', $text) ?? $text;

        // Kapitalisasi klause setelah titik koma
        $text = preg_replace_callback(
            '/;\s*(\p{L})/u',
            static function (array $m) {
                return '; ' . mb_strtoupper($m[1], 'UTF-8');
            },
            $text
        ) ?? $text;

        if (preg_match('/^(\p{Ll})/u', $text, $m)) {
            $text = mb_strtoupper($m[1], 'UTF-8') . mb_substr($text, 1);
        }

        return trim($text);
    }

    /** Tebak format formal dari teks chat mentah (fallback tanpa AI). */
    private static function guessFormalFromChat(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $chunks = preg_split('/\s*;\s*/u', $text) ?: [$text];
        $formal = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (preg_match('/^(menanyakan|meminta)\b/iu', $chunk)) {
                $formal[] = self::capitalizeFirst($chunk);
                continue;
            }
            $lower = mb_strtolower($chunk);
            if (preg_match('/\b(kapan|jam|berapa|bisa|besok|siap|estimasi|brp|kpn)\b/u', $lower)) {
                $formal[] = 'Menanyakan ' . mb_strtolower($chunk);
            } else {
                $formal[] = 'Meminta ' . mb_strtolower($chunk);
            }
        }

        return implode('; ', $formal);
    }

    private static function capitalizeFirst(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (preg_match('/^(\p{L})(.*)$/u', $text, $m)) {
            return mb_strtoupper($m[1], 'UTF-8') . $m[2];
        }

        return $text;
    }
}
