<?php

namespace App\Helpers\WaDesk;

/**
 * Detect duplicate free-text follow-ups while the last outbound is still unanswered.
 */
class FreeTextSpamGuard
{
    public const REJECT_REASON = 'Isi pesan terindikasi spam, proses kirim ditolak.';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda analis anti-spam untuk chat WhatsApp layanan pelanggan Indonesia.

Konteks: agent CS sudah mengirim pesan teks ke pelanggan dan belum dibalas. Agent mencoba mengirim pesan teks baru. Tentukan apakah pesan baru benar-benar mengulang maksud dan tujuan komunikasi yang SAMA dengan pesan sebelumnya (indikasi spam/duplikat). Default keputusan adalah duplicate_spam=false; jangan menolak hanya karena kedua pesan membahas topik yang sama.

Set duplicate_spam=true jika pesan baru:
- mengulangi informasi, pertanyaan, permintaan, atau ajakan yang sama
- hanya parafrase, sinonim, atau perbaikan ejaan dari pesan sebelumnya
- follow-up/reminder tanpa konten baru (mis. "halo?", "mohon dibaca", "sudah lihat?") setelah pesan serupa
- menekan pelanggan untuk hal yang sama tanpa informasi, pertanyaan, atau tindakan berbeda

Set duplicate_spam=false jika pesan baru:
- menambah informasi, klarifikasi, detail, atau langkah berikutnya yang berbeda
- membahas poin baru meski masih satu topik layanan
- benar-benar melanjutkan percakapan dengan substansi tambahan
- memiliki bentuk komunikasi berbeda. Contoh WAJIB false: pesan pertama meminta pelanggan segera membayar tagihan/hutang, lalu pesan kedua menanyakan kapan tagihan tersebut akan dibayarkan. Keduanya bertopik tagihan, tetapi tujuan pertama adalah permintaan pembayaran dan tujuan kedua adalah meminta kepastian waktu.

Jangan menyimpulkan spam hanya dari kata yang sama seperti "bayar", "tagihan", "hutang", atau "segera". Set true hanya bila pengulangan substansinya hampir sama dan tidak ada maksud baru.

Balas HANYA JSON valid, tanpa markdown:
{"duplicate_spam":true}
atau
{"duplicate_spam":false}
PROMPT;

    /**
     * @return array{duplicate_spam:bool,reason:string}
     */
    public function check(string $apiKey, string $pendingMessage, string $newMessage): array
    {
        $pendingMessage = trim($pendingMessage);
        $newMessage = trim($newMessage);

        if ($pendingMessage === '' || $newMessage === '') {
            return ['duplicate_spam' => false, 'reason' => ''];
        }

        if ($this->isObviouslySame($pendingMessage, $newMessage)) {
            return ['duplicate_spam' => true, 'reason' => self::REJECT_REASON];
        }

        // Pertanyaan lanjutan yang berbeda dari perintah/pernyataan sebelumnya
        // adalah komunikasi baru, bukan spam. Jangan serahkan kasus jelas ini
        // ke klasifikasi AI yang bisa terlalu ketat.
        if ($this->hasQuestionIntent($pendingMessage) !== $this->hasQuestionIntent($newMessage)) {
            return ['duplicate_spam' => false, 'reason' => ''];
        }

        if (trim($apiKey) === '') {
            return ['duplicate_spam' => false, 'reason' => ''];
        }

        $userPayload = json_encode([
            'previous_outbound_unanswered' => $pendingMessage,
            'new_outbound_draft' => $newMessage,
        ], JSON_UNESCAPED_UNICODE);

        $client = new OpenAi($apiKey);
        $res = $client->chatJson(self::SYSTEM_PROMPT, $userPayload);

        if (!$res['success']) {
            // Fail open — jangan blokir operasional jika AI down.
            return ['duplicate_spam' => false, 'reason' => ''];
        }

        $duplicate = !empty($res['data']['duplicate_spam']);

        return [
            'duplicate_spam' => $duplicate,
            'reason' => $duplicate ? self::REJECT_REASON : '',
        ];
    }

    private function isObviouslySame(string $a, string $b): bool
    {
        $na = $this->normalize($a);
        $nb = $this->normalize($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        return $na === $nb;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? $text;

        return trim($text);
    }

    private function hasQuestionIntent(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        return str_contains($text, '?')
            || preg_match('/\b(kapan|apakah|sudahkah|bisakah|bagaimana|berapa|mohon info|minta info)\b/u', $text) === 1;
    }
}
