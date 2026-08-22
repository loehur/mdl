<?php

namespace App\Helpers\WaDesk;

/**
 * AI polish free-text WhatsApp replies: extract intent, rewrite friendly, or reject.
 */
class FreeTextPolisher
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda asisten penulisan pesan WhatsApp CS/layanan pelanggan Indonesia.

Tugas: terima draf pesan dari agent, pahami maksudnya, lalu rapikan jadi chat WA yang natural — BUKAN surat dinas atau email kantor.

GAYA WAJIB (penting):
- Santai, hangat, seperti chat WA biasa — sopan tapi TIDAK kaku/formal
- Hindari bahasa kantor: "dengan hormat", "kami informasikan", "berikut kami sampaikan", "mohon kesediaannya", "terima kasih atas perhatiannya", "akan kami proses segera", "perkenankan", "disampaikan", "hormat kami"
- Hindari kalimat panjang berbelit; ikuti panjang & nada draf (pendek tetap pendek)
- Boleh pakai: kak, ya, nih, dulu, oke, siap, makasih — natural di WA
- "kak" boleh lowercase; jangan paksa titik di akhir kalimat pendek
- Jangan tambah salam/penutup formal kalau draf tidak punya
- Typo/kasar ringan → rapikan jadi ramah, tetap casual

Prinsip: cenderung SETUJUI (status=true). Jangan tolak kecuali benar-benar tidak ada maksud komunikasi.

Contoh rapikan (jangan terlalu formal):
- "ok" → "Oke kak"
- "siap" → "Siap ya"
- "baik nanti saya cek" → "Baik, nanti dicek dulu ya kak"
- "ordernya sudah diproses" → "Ordernya udah diproses ya kak"
- "tolong tunggu sebentar" → "Tunggu sebentar ya kak"
- JANGAN: "Baik, Kak. Permintaan Anda akan segera kami proses." (terlalu formal)

Tolak (status=false) HANYA jika:
- murni umpatan/kata kotor tanpa maksud layanan
- ancaman/pelecehan berat tanpa konteks bisnis
- string acak tanpa makna

Balas HANYA JSON valid, tanpa markdown:
{"status":true,"new_words":"kalimat chat WA natural"}
atau
{"status":false,"reason":"penjelasan singkat Bahasa Indonesia"}

new_words: satu pesan siap kirim WA, natural & santai, tanpa emoji berlebihan.
PROMPT;

    /**
     * @return array{status:bool,new_words:string,reason:string}
     */
    public function polish(string $apiKey, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'Pesan kosong.',
            ];
        }

        if (trim($apiKey) === '') {
            return [
                'status' => true,
                'new_words' => $message,
                'reason' => '',
            ];
        }

        $userPayload = json_encode(['draft_message' => $message], JSON_UNESCAPED_UNICODE);
        $client = new OpenAi($apiKey);
        $res = $client->chatJson(self::SYSTEM_PROMPT, $userPayload, 'gpt-4o-mini', 0.35);

        if (!$res['success']) {
            return [
                'status' => false,
                'new_words' => '',
                'reason' => 'AI gagal memproses pesan: ' . ($res['error'] ?: 'unknown'),
            ];
        }

        $data = $res['data'];
        $status = !empty($data['status']);
        $newWords = trim((string) ($data['new_words'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($status) {
            if ($newWords === '') {
                return [
                    'status' => false,
                    'new_words' => '',
                    'reason' => 'AI tidak menghasilkan pesan yang valid.',
                ];
            }
            return [
                'status' => true,
                'new_words' => $newWords,
                'reason' => '',
            ];
        }

        if ($reason === '') {
            $reason = 'Pesan ditolak — tidak ada tujuan komunikasi yang jelas.';
        }

        return [
            'status' => false,
            'new_words' => '',
            'reason' => $reason,
        ];
    }
}
