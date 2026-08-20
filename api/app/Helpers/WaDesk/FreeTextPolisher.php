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

Prinsip: cenderung SETUJUI (status=true) dan rapikan. Jangan tolak kecuali benar-benar tidak ada maksud komunikasi sama sekali.

SELALU setujui dan rapikan (status=true), termasuk:
- sapaan singkat: halo, hai, selamat pagi/siang/sore/malam
- penutup/konfirmasi singkat: baik, ok, oke, siap, baik kak, siap kak, terima kasih, makasih, sama-sama
- balasan pendek yang wajar di chat CS, meski ada typo atau nada agak kasar — perbaiki jadi lebih ramah, jangan tolak

Jika draf pendek/kasar/typo tapi maksudnya jelas (mis. "ok", "siap bg", "baik nanti saya cek"), tulis ulang menjadi sopan:
- "ok" → "Baik, Kak."
- "siap" → "Siap, Kak."
- "baik nanti saya cek" → "Baik, Kak. Nanti akan kami cek ya."

Tolak (status=false) HANYA jika:
- murni umpatan/kata kotor/hujatan TANPA maksud layanan sama sekali
- ancaman atau pelecehan berat tanpa konteks komunikasi bisnis
- string acak/kosong makna yang benar-benar tidak bisa ditafsirkan

Setujui (status=true) meski bahasa awal kasar — tulis ulang menjadi sopan, jangan tolak.

Balas HANYA JSON valid, tanpa markdown:
{"status":true,"new_words":"kalimat baru yang ramah dan jelas"}
atau
{"status":false,"reason":"penjelasan singkat Bahasa Indonesia"}

new_words: satu pesan siap kirim WhatsApp, natural, tanpa emoji berlebihan. Untuk balasan singkat, boleh tetap singkat tapi sopan.
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
