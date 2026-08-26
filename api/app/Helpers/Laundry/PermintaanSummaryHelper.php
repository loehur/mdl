<?php

namespace App\Helpers\Laundry;

/**
 * Ringkasan formal permintaan/pertanyaan pelanggan — dipakai webhook, laundry, CRM API.
 */
class PermintaanSummaryHelper
{
    /** Prompt system AI (satu sumber untuk API + laundry). */
    public static function aiSystemPrompt(int $maxChars = 120): string
    {
        return "Kamu merangkum chat pelanggan laundry menjadi SATU kalimat super singkat Bahasa Indonesia.\n"
            . "WAJIB gabungkan SEMUA bubble chat bernomor (bukan hanya yang terakhir).\n\n"
            . "Ambil HANYA pertanyaan kesiapan/waktu, permintaan layanan khusus laundry, atau permintaan pengecekan barang/cucian.\n"
            . "Jangan masukkan keluhan telepon, rekening, metode pembayaran, atau hal di luar layanan laundry.\n"
            . "Mulai dengan \"Menanyakan\" atau \"Meminta\". Gabungkan semua maksud yang terkait memakai kata \"dan\", bukan daftar.\n"
            . "WAJIB tepat satu kalimat, TANPA titik koma, dan akhiri titik. Maksimal {$maxChars} karakter.\n\n"
            . "CONTOH GAYA:\n"
            . "- Menanyakan apakah bisa siap jam 3.\n"
            . "- Meminta siap jam 5 sore.\n"
            . "- Meminta diubah ke ekspres.\n"
            . "- Meminta cek ada dompet di kantong.\n\n"
            . "Jawaban HANYA teks ringkasan.";
    }

    /**
     * Ringkasan kanonik untuk notifikasi grup. Detail chat sengaja tidak disalin
     * agar follow-up dengan maksud yang sama tidak memicu update berulang.
     */
    public static function compactForGroupSummary(string $chat): string
    {
        $chat = mb_strtolower(self::stripPreviewPrefix($chat), 'UTF-8');
        if ($chat === '') {
            return '';
        }

        // Bukan urusan operasional laundry; jangan menjadikannya PERMINTAAN.
        $excluded = '/\b(telepon|telp|diangkat|dihubungi|rekening|rek(?:ening)?|transfer|pembayaran|bayar|qris|gopay|ovo|dana|bank)\b/iu';
        $chat = preg_replace($excluded, ' ', $chat) ?? $chat;

        $items = [];
        $hasSpecificTime = (bool) preg_match('/\b(jam\s*\d{1,2}|pukul\s*\d{1,2}|siang ini|sore ini|malam ini|besok (?:pagi|siang|sore|malam))\b/iu', $chat);
        $hasReadyQuestion = (bool) preg_match('/\b(kapan|selesai|jadi|kelar|estimasi)\b/iu', $chat)
            || (!$hasSpecificTime && (bool) preg_match('/\bsiap\b/iu', $chat));
        if ($hasReadyQuestion) {
            $items[] = 'Menanyakan kapan siap';
        }
        if ($hasSpecificTime && preg_match('/\b(bisa|siap|selesai|jadi|ambil|jemput|antar)\b/iu', $chat)) {
            $items[] = 'Meminta siap di waktu tertentu';
        }
        if (preg_match('/\b(gosend|go\s*send|jemput|ambil|antar|kurir|prioritas|duluan|express|plastik|pisah|pewangi|wangi|lipat)\b/iu', $chat)) {
            $items[] = 'Meminta layanan khusus laundry';
        }
        if (preg_match('/\b(cek|status|setrika|cucian|laundry|sudah selesai|sudah jadi)\b/iu', $chat)) {
            $items[] = 'Meminta cek hal terkait laundry';
        }

        return self::finalize(implode(' dan ', array_values(array_unique($items))), 120);
    }

    public static function stripPreviewPrefix(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^[io]\-\s+/iu', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Rapikan tampilan ringkasan: satu kalimat tanpa titik koma, titik di akhir.
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
        $text = preg_replace('#\s*/\s*#u', ' dan ', $text) ?? $text;
        $text = preg_replace('/\s*;\s*/u', ' dan ', $text) ?? $text;
        $text = preg_replace('/(?:\s+dan){2,}\s+/u', ' dan ', $text) ?? $text;
        $text = preg_replace_callback('/\bdan\s+(Menanyakan|Meminta)\b/u', static function (array $m) {
            return 'dan ' . mb_strtolower($m[1], 'UTF-8');
        }, $text) ?? $text;
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

        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        // Jangan memotong kata atau mengirim notifikasi tanpa penutup kalimat.
        $cut = rtrim(mb_substr($text, 0, max(1, $maxLen - 1)));
        $cut = preg_replace('/\s+\S*$/u', '', $cut) ?? $cut;

        return rtrim($cut, " .,;") . '.';
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
