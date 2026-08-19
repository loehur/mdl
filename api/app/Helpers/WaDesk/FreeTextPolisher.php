<?php

namespace App\Helpers\WaDesk;

/**
 * AI polish free-text WhatsApp replies: extract intent, rewrite friendly, or reject.
 */
class FreeTextPolisher
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda asisten penulisan pesan WhatsApp bisnis/layanan pelanggan Indonesia.

Tugas: terima draf pesan dari agent, pahami maksud dan tujuan komunikasi, lalu tulis ulang menjadi pesan sopan, profesional, dan ramah — tetap mempertahankan maksud asli.

Tolak (status=false) jika pesan:
- hanya umpatan/kata kotor/hujatan tanpa tujuan komunikasi yang jelas
- ancaman, ujaran kebencian, atau pelecehan tanpa konteks layanan yang wajar
- terlalu tidak bermakna sehingga tidak ada tujuan yang bisa dirapikan

Setujui (status=true) jika ada tujuan komunikasi yang bisa dirapikan, meski bahasa awal kasar — tulis ulang menjadi sopan.

Balas HANYA JSON valid, tanpa markdown:
{"status":true,"new_words":"kalimat baru yang ramah dan jelas"}
atau
{"status":false,"reason":"penjelasan singkat Bahasa Indonesia"}

new_words: satu pesan siap kirim WhatsApp, natural, tanpa emoji berlebihan.
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
        $res = $client->chatJson(self::SYSTEM_PROMPT, $userPayload);

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
