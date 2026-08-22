<?php

/**
 * Validasi alasan hapus nota (full REF) via AI.
 * Hanya boleh: pelanggan batal/gak jadi, atau salah pilih pelanggan.
 */
class HapusRefAiGuard
{
    private const MSG_UNCLEAR = 'Alasan hapus belum cukup jelas. Mohon jelaskan lebih detail apa yang terjadi.';

    /** @return array{ok:bool,allowed:bool,message:string,alternatives:array<int,string>,raw?:string} */
    public function validate(string $note): array
    {
        $note = trim($note);
        if ($note === '') {
            return [
                'ok' => true,
                'allowed' => false,
                'message' => 'Alasan hapus wajib diisi.',
                'alternatives' => [],
            ];
        }

        if (mb_strlen($note) < 4) {
            return $this->unclearReasonResponse('Alasan terlalu singkat. Mohon jelaskan lebih detail apa yang terjadi.');
        }

        if ($this->isUnclearReason($note)) {
            return $this->unclearReasonResponse();
        }

        $local = $this->localPrefilter($note);
        if ($local !== null) {
            return $local;
        }

        $system = <<<'SYS'
Kamu validator alasan hapus nota laundry di sistem operasional.

HAPUS NOTA FULL REF hanya BOLEH jika alasan pelanggan/staff masuk salah satu kategori ini:
1. batal — pelanggan batal, gak jadi, cancel order, tidak jadi laundry, customer tidak datang, dll.
2. salah_pelanggan — salah input/pilih nama pelanggan, nota salah orang, pelanggan keliru, typo nama pelanggan, dll.

Jika alasan sebenarnya koreksi data order (bukan batal & bukan salah pelanggan), tolak hapus.
Contoh alasan yang HARUS DITOLAK beserta solusi di sistem Operasi:
- Salah durasi (reguler, ekspres, kilat, premium, dll) → klik teks durasi pada item untuk ubah durasi.
- Salah quantity / qty / kilo / kg / pcs / jumlah → klik angka quantity pada item untuk ubah qty.
- Salah layanan (cuci, setrika, cuci-setrika, gosok, dll) → klik tombol "Ganti" di kolom layanan untuk ubah layanan.
- Salah kategori laundry (pakaian harian, kain tebal, dll) → klik nama kategori pada item untuk ubah laundry.
- Salah harga / diskon / item satuan → gunakan fitur edit yang sesuai, bukan hapus nota.

Balas HANYA JSON valid (tanpa markdown), format:
{"allowed":true|false,"category":"batal|salah_pelanggan|ditolak","message":"pesan singkat Bahasa Indonesia ke staff","alternatives":["solusi 1","solusi 2"]}

Aturan:
- allowed=true hanya jika category batal atau salah_pelanggan.
- allowed=false jika category ditolak atau alasan tidak jelas / curiga koreksi data.
- Jika alasan terlalu singkat atau vague (mis. hanya "salah", "keliru"), allowed=false dan message minta staff jelaskan lebih detail (tanpa sebut aturan internal).
- message: kalimat sopan ke staff, max 2 kalimat. Fokus solusi edit item. JANGAN sebut aturan internal (batal/salah pelanggan/kategori alasan yang boleh).
- alternatives: array solusi konkret (1-3 item) jika allowed=false; array kosong jika allowed=true.
- Jangan sarankan hapus nota jika allowed=false.
SYS;

        $user = "Alasan staff untuk hapus nota:\n\"{$note}\"";

        try {
            require_once dirname(__DIR__) . '/Helper/AiChat.php';
            $ai = new AiChat();
            $raw = $ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 320, 0.15, 18);

            $parsed = $this->parseJsonResponse($raw);
            if ($parsed === null) {
                if ($this->isUnclearReason($note)) {
                    return $this->unclearReasonResponse();
                }
                $fallback = $this->localPrefilter($note);
                if ($fallback !== null) {
                    return $fallback;
                }
                return [
                    'ok' => true,
                    'allowed' => false,
                    'message' => self::MSG_UNCLEAR,
                    'alternatives' => [],
                ];
            }

            $allowed = !empty($parsed['allowed']);
            $category = strtolower(trim((string) ($parsed['category'] ?? '')));
            if ($allowed && !in_array($category, ['batal', 'salah_pelanggan'], true)) {
                $allowed = false;
            }
            if (!$allowed && in_array($category, ['batal', 'salah_pelanggan'], true)) {
                $allowed = true;
            }

            $message = trim((string) ($parsed['message'] ?? ''));
            if ($message === '') {
                $message = $allowed
                    ? 'Alasan diterima. Nota dapat dihapus.'
                    : 'Nota tidak perlu dihapus — perbaiki data item yang bersangkutan.';
            } elseif (!$allowed) {
                $message = $this->sanitizeStaffMessage($message);
            }

            $alternatives = [];
            if (!$allowed && !empty($parsed['alternatives']) && is_array($parsed['alternatives'])) {
                foreach ($parsed['alternatives'] as $alt) {
                    $alt = trim((string) $alt);
                    if ($alt !== '') {
                        $alternatives[] = $alt;
                    }
                }
            }

            if (!$allowed && ($category === 'ditolak' || $category === '') && $this->isUnclearReason($note)) {
                $message = self::MSG_UNCLEAR;
                $alternatives = [];
            }

            if (!$allowed && $alternatives === [] && $message !== self::MSG_UNCLEAR) {
                $alternatives = $this->fallbackAlternatives($note);
            }

            return [
                'ok' => true,
                'allowed' => $allowed,
                'message' => $message,
                'alternatives' => $alternatives,
            ];
        } catch (\Throwable $e) {
            if ($this->isUnclearReason($note)) {
                return $this->unclearReasonResponse();
            }
            $fallback = $this->localPrefilter($note);
            if ($fallback !== null) {
                return $fallback;
            }
            return [
                'ok' => true,
                'allowed' => false,
                'message' => 'Validasi sementara tidak tersedia. Mohon coba lagi sebentar.',
                'alternatives' => [],
            ];
        }
    }

    /** @return array{ok:bool,allowed:bool,message:string,alternatives:array<int,string>} */
    private function unclearReasonResponse(?string $message = null): array
    {
        return [
            'ok' => true,
            'allowed' => false,
            'message' => $message ?? self::MSG_UNCLEAR,
            'alternatives' => [],
        ];
    }

    private function isUnclearReason(string $note): bool
    {
        $n = mb_strtolower(trim($note));
        $n = preg_replace('/\s+/u', ' ', $n) ?? $n;

        if (preg_match('/\b(batal|gak jadi|ga jadi|tidak jadi|cancel|batalin|batalkan|nggak jadi)\b/u', $n)) {
            return false;
        }
        if (preg_match('/(pelanggan|nama|customer|orang)/u', $n)) {
            return false;
        }
        if ($this->looksLikeDataCorrection($n)) {
            return false;
        }

        if (mb_strlen($n) <= 12) {
            return true;
        }

        if (preg_match('/^(salah|keliru|typo|error|salah input|salah ketik|salah pilih|salah order|salah nota|salah buat|salah enter)$/u', $n)) {
            return true;
        }

        if (!preg_match('/\s/u', $n) && mb_strlen($n) <= 24) {
            return (bool) preg_match('/^(salah|keliru|typo|error|hapus|cancel)$/u', $n);
        }

        return false;
    }

    /**
     * Deteksi cepat tanpa AI — setujui batal/salah pelanggan, tolak koreksi data order yang jelas.
     *
     * @return array{ok:bool,allowed:bool,message:string,alternatives:array<int,string>}|null
     */
    private function localPrefilter(string $note): ?array
    {
        $n = mb_strtolower($note);

        if ($this->isWrongCustomerReason($n)) {
            return $this->allowedReasonResponse();
        }

        $isCancel = (bool) preg_match(
            '/\b(batal|gak jadi|ga jadi|tidak jadi|cancel|batalin|batalkan|nggak jadi|gak jadi|tidak jadi laundry)\b/u',
            $n
        );
        if ($isCancel && !$this->looksLikeDataCorrection($n)) {
            return $this->allowedReasonResponse();
        }

        if (!$this->looksLikeDataCorrection($n)) {
            return null;
        }

        $alternatives = $this->fallbackAlternatives($note);
        return [
            'ok' => true,
            'allowed' => false,
            'message' => 'Masalah ini bisa diperbaiki tanpa hapus nota. Perbaiki data item saja.',
            'alternatives' => $alternatives,
        ];
    }

    private function isWrongCustomerReason(string $n): bool
    {
        if (preg_match(
            '/salah\s*(nama|pelanggan|customer|orang)\b|'
            . 'salah\s*(input|pilih|ketik|klik)\s*(nama|pelanggan|customer|orang)|'
            . 'salah\s*(input|pilih|ketik|klik).*(pelanggan|nama|customer|orang)|'
            . '(pelanggan|nama|customer|orang)\s*(salah|keliru|bukan)|'
            . 'nota\s*(salah|keliru)\s*(orang|pelanggan)|'
            . 'bukan\s*(pelanggan|customer|namanya)/u',
            $n
        )) {
            return true;
        }

        return (bool) preg_match('/(pelanggan|nama|customer|orang)/u', $n)
            && (bool) preg_match('/\bsalah\b/u', $n)
            && !$this->looksLikeDataCorrectionExceptCustomer($n);
    }

    /** Koreksi data order selain salah pelanggan/nama. */
    private function looksLikeDataCorrectionExceptCustomer(string $n): bool
    {
        if (preg_match('/durasi|reguler|ekspres|ekpress|kilat|premium|express/u', $n)) {
            return true;
        }
        if (preg_match('/qty|quantity|kilo|kg|pcs|jumlah|berat/u', $n)) {
            return true;
        }
        if (preg_match('/layanan|cuci|setrika|gosok|lipat|pack/u', $n)) {
            return true;
        }
        if (preg_match('/pakaian|kain tebal|kategori laundry|jenis laundry/u', $n)) {
            return true;
        }
        if (preg_match('/harusnya|seharusnya|bukan\s*(reguler|ekspres|kilat|premium)/u', $n)) {
            return true;
        }

        return false;
    }

    /** @return array{ok:bool,allowed:bool,message:string,alternatives:array<int,string>} */
    private function allowedReasonResponse(): array
    {
        return [
            'ok' => true,
            'allowed' => true,
            'message' => 'Alasan diterima. Nota dapat dihapus.',
            'alternatives' => [],
        ];
    }

    private function looksLikeDataCorrection(string $n): bool
    {
        if (preg_match('/durasi|reguler|ekspres|ekpress|kilat|premium|express/u', $n)) {
            return true;
        }
        if (preg_match('/qty|quantity|kilo|kg|pcs|jumlah|berat/u', $n)) {
            return true;
        }
        if (preg_match('/layanan|cuci|setrika|gosok|lipat|pack/u', $n)) {
            return true;
        }
        if (preg_match('/pakaian|kain tebal|kategori laundry|jenis laundry/u', $n)) {
            return true;
        }
        if (preg_match('/harusnya|seharusnya|bukan\s*(reguler|ekspres|kilat|premium)/u', $n)) {
            return true;
        }
        if (preg_match('/salah\s*(input|pilih|klik|tekan)/u', $n)) {
            return true;
        }

        return false;
    }

    /** @return array<string,mixed>|null */
    private function parseJsonResponse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $raw = $m[0];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<int,string> */
    private function fallbackAlternatives(string $note): array
    {
        $n = mb_strtolower($note);
        $out = [];

        if (preg_match('/durasi|reguler|ekspres|kilat|premium|express/u', $n)) {
            $out[] = 'Klik teks durasi pada item (mis. REGULER 2h) lalu pilih durasi yang benar.';
        }
        if (preg_match('/qty|quantity|kilo|kg|pcs|jumlah|berat/u', $n)) {
            $out[] = 'Klik angka quantity pada item untuk mengubah qty/kilo/pcs.';
        }
        if (preg_match('/layanan|cuci|setrika|gosok|lipat|pack/u', $n)) {
            $out[] = 'Klik tombol "Ganti" di kolom layanan lalu pilih layanan yang benar.';
        }
        if (preg_match('/pakaian|kain|tebal|harian|kategori|laundry/u', $n)) {
            $out[] = 'Klik nama kategori laundry pada item (mis. Pakaian Harian) untuk ubah jenis laundry.';
        }

        if ($out === []) {
            $out[] = 'Periksa kembali: ubah durasi, qty, layanan, atau kategori pada item langsung di operasi.';
        }

        return $out;
    }

    private function sanitizeStaffMessage(string $message): string
    {
        $patterns = [
            '/\b(batal order|salah pelanggan|salah pilih nama pelanggan|bukan batal|bukan salah pelanggan)\b/iu',
            '/\b(hanya|harus)\s+(batal|salah pelanggan)[^.]*/iu',
            '/kategori\s*(alasan|hapus)[^.]*/iu',
        ];
        $clean = preg_replace($patterns, '', $message) ?? $message;
        $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? trim($clean);
        $clean = trim($clean, " \t\n\r\0\x0B.,;:-");

        if ($clean === '' || mb_strlen($clean) < 8) {
            return 'Nota tidak perlu dihapus — perbaiki data item yang bersangkutan.';
        }

        return $clean;
    }
}
