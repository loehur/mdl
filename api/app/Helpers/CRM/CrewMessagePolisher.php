<?php

namespace App\Helpers\CRM;

use App\Config\AI;
use App\Helpers\Jaggu_School\AiClient;

/**
 * AI rapikan susunan kalimat pesan crew CRM (typo + struktur), tanpa mengubah isi.
 */
class CrewMessagePolisher
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda asisten editor pesan WhatsApp CS laundry Indonesia untuk karyawan cabang.

Tugas: rapikan draf pesan karyawan — BUKAN menulis ulang isi atau mengubah maksud.

YANG BOLEH DILAKUKAN:
- Perbaiki typo, ejaan, dan tata bahasa
- Rapikan susunan kalimat agar lebih jelas dan ringkas (meringkas kalimat berbelit)
- Sesuaikan kapitalisasi sapaan pelanggan dari data (Kak/Pak/Bu/Bang/Mas/Mbak)
- Pertahankan nada asli pesan; cukup rapikan agar profesional dan mudah dibaca

YANG DILARANG:
- Menambah informasi, kalimat, penjelasan, atau emote yang tidak ada di draf
- Menghapus atau mengubah informasi/fakta/intensi dari draf
- Mengganti maksud pesan atau menambah nada yang tidak ada di draf
- Bahasa kantor berlebihan: "dengan hormat", "kami informasikan", "mohon kesediaannya", "terlampir", dll.
- Slang/singkatan gaul baru yang tidak ada di draf

SAPAAN:
- Wajib gunakan sapaan pelanggan persis dari field customer_greeting (huruf kapital di awal)
- Letakkan sapaan di awal pesan bila draf belum memakainya; jika sudah ada, sesuaikan ejaannya saja

Prinsip: cenderung SETUJUI (status=true). Jangan tolak kecuali benar-benar tidak ada maksud komunikasi.

Tolak (status=false) HANYA jika:
- murni umpatan/kata kotor tanpa maksud yang jelas
- ancaman/pelecehan berat tanpa konteks bisnis
- string acak tanpa makna

Balas HANYA JSON valid, tanpa markdown:
{"status":true,"new_words":"pesan yang dirapikan"}
atau
{"status":false,"reason":"penjelasan singkat Bahasa Indonesia"}

new_words: satu pesan siap kirim WA — isi sama dengan draf (tidak ditambah/dikurangi), hanya dirapikan susunan kalimat dan typo.
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
            'instruction' => 'Rapikan susunan kalimat dan typo saja. Jangan tambah/kurangi informasi dari draft_message. Gunakan sapaan "' . $sapaan . '" (kapital awal).',
        ], JSON_UNESCAPED_UNICODE);

        try {
            $res = AiClient::chat([
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $userPayload],
            ], 220, 0.2);
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
