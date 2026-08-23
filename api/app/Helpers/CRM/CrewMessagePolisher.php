<?php

namespace App\Helpers\CRM;

use App\Config\AI;
use App\Helpers\Jaggu_School\AiClient;

/**
 * AI rapikan pesan crew CRM sebelum kirim ke pelanggan.
 */
class CrewMessagePolisher
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda asisten penulisan pesan WhatsApp CS laundry Indonesia untuk karyawan cabang.

Tugas: terima draf pesan dari karyawan, pahami maksudnya, lalu rapikan jadi chat WA yang natural.

FOKUS:
- Hanya tujuan isi pesan — tidak berbelit, ringkas
- Ramah & sopan, TIDAK terlalu formal, tetap santai (seperti chat WA biasa)
- Hindari bahasa kantor: "dengan hormat", "kami informasikan", "mohon kesediaannya", dll.
- Boleh pakai sapaan pelanggan dari data (kak/pak/bu/bang/mas/mbak) — natural, lowercase boleh
- Typo/kasar ringan → rapikan jadi ramah, tetap casual

Prinsip: cenderung SETUJUI (status=true). Jangan tolak kecuali benar-benar tidak ada maksud komunikasi.

Tolak (status=false) HANYA jika:
- murni umpatan/kata kotor tanpa maksud yang jelas
- ancaman/pelecehan berat tanpa konteks bisnis
- string acak tanpa makna

Balas HANYA JSON valid, tanpa markdown:
{"status":true,"new_words":"kalimat chat WA natural"}
atau
{"status":false,"reason":"penjelasan singkat Bahasa Indonesia"}

new_words: satu pesan siap kirim WA, natural & santai, berikan 1 emote.
PROMPT;

    /**
     * @return array{status:bool,new_words:string,reason:string,sapaan?:string}
     */
    public function polish(string $draft, string $sapaan): array
    {
        $draft = trim($draft);
        if ($draft === '') {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'Pesan kosong.',
            ];
        }

        $sapaan = trim($sapaan) !== '' ? trim($sapaan) : 'kak';

        if (AI::getProvidersInOrder() === []) {
            return [
                'status' => true,
                'new_words' => $draft,
                'reason' => '',
                'sapaan' => $sapaan,
            ];
        }

        $userPayload = json_encode([
            'draft_message' => $draft,
            'customer_greeting' => $sapaan,
            'instruction' => 'Gunakan sapaan pelanggan "' . $sapaan . '" bila perlu di awal/konteks pesan.',
        ], JSON_UNESCAPED_UNICODE);

        try {
            $res = AiClient::chat([
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $userPayload],
            ], 220, 0.35);
            $raw = trim((string) ($res['content'] ?? ''));
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'AI gagal memproses: ' . $e->getMessage(),
                'sapaan' => $sapaan,
            ];
        }

        $data = $this->parseJsonResponse($raw);
        if ($data === null) {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'AI mengembalikan format tidak valid.',
                'sapaan' => $sapaan,
            ];
        }

        $status = !empty($data['status']);
        $newWords = trim((string) ($data['new_words'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($status) {
            if ($newWords === '') {
                return [
                    'status' => false,
                    'new_words' => '',
                    'reason' => 'AI tidak menghasilkan pesan yang valid.',
                    'sapaan' => $sapaan,
                ];
            }

            return [
                'status' => true,
                'new_words' => $newWords,
                'reason' => '',
                'sapaan' => $sapaan,
            ];
        }

        if ($reason === '') {
            $reason = 'Pesan ditolak — tidak ada tujuan komunikasi yang jelas.';
        }

        return [
            'status' => false,
            'new_words' => '',
            'reason' => $reason,
            'sapaan' => $sapaan,
        ];
    }

    /** @return array<string,mixed>|null */
    private function parseJsonResponse(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
