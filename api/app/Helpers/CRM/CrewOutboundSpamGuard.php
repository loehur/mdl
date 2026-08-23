<?php

namespace App\Helpers\CRM;

use App\Config\AI;
use App\Helpers\Jaggu_School\AiClient;

/**
 * Cegah kirim ulang pesan crew dengan maksud sama saat pelanggan belum membalas.
 */
class CrewOutboundSpamGuard
{
    public const REJECT_MESSAGE = 'Pesan terindikasi spam, tidak dapat dikirim';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda analis anti-spam untuk chat WhatsApp layanan pelanggan Indonesia.

Konteks: karyawan sudah mengirim satu atau lebih pesan teks ke pelanggan dan belum dibalas. Karyawan mencoba mengirim pesan teks baru. Tentukan apakah pesan baru punya maksud dan tujuan komunikasi yang SAMA dengan salah satu pesan sebelumnya (indikasi spam/duplikat).

Set duplicate_spam=true jika pesan baru:
- mengulangi informasi, pertanyaan, permintaan, atau ajakan yang sama
- hanya parafrase, sinonim, atau perbaikan ejaan dari pesan sebelumnya
- follow-up/reminder tanpa konten baru (mis. "halo?", "mohon dibaca", "sudah lihat?") setelah pesan serupa
- menekan pelanggan untuk hal yang sama tanpa menambah nilai

Set duplicate_spam=false jika pesan baru:
- menambah informasi, klarifikasi, detail, atau langkah berikutnya yang berbeda
- membahas poin baru meski masih satu topik layanan
- benar-benar melanjutkan percakapan dengan substansi tambahan

Balas HANYA JSON valid, tanpa markdown:
{"duplicate_spam":true}
atau
{"duplicate_spam":false}
PROMPT;

    /**
     * @param list<string> $pendingMessages Pesan keluar sebelumnya yang belum dibalas pelanggan
     * @return array{duplicate_spam:bool,message:string}
     */
    public function check(array $pendingMessages, string $newMessage): array
    {
        $newMessage = trim($newMessage);
        $pendingMessages = array_values(array_filter(array_map(
            static fn ($m) => trim((string) $m),
            $pendingMessages
        ), static fn ($m) => $m !== ''));

        if ($pendingMessages === [] || $newMessage === '') {
            return ['duplicate_spam' => false, 'message' => ''];
        }

        foreach ($pendingMessages as $pending) {
            if ($this->isObviouslySame($pending, $newMessage)) {
                return ['duplicate_spam' => true, 'message' => self::REJECT_MESSAGE];
            }
        }

        if (AI::getProvidersInOrder() === []) {
            return ['duplicate_spam' => false, 'message' => ''];
        }

        $userPayload = json_encode([
            'previous_outbound_unanswered' => $pendingMessages,
            'new_outbound_draft' => $newMessage,
        ], JSON_UNESCAPED_UNICODE);

        try {
            $res = AiClient::chat([
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $userPayload],
            ], 120, 0.1);
            $raw = trim((string) ($res['content'] ?? ''));
        } catch (\Throwable $e) {
            // Fail open — jangan blokir operasional jika AI down.
            return ['duplicate_spam' => false, 'message' => ''];
        }

        $data = $this->parseJsonResponse($raw);
        if ($data === null) {
            return ['duplicate_spam' => false, 'message' => ''];
        }

        $duplicate = !empty($data['duplicate_spam']);

        return [
            'duplicate_spam' => $duplicate,
            'message' => $duplicate ? self::REJECT_MESSAGE : '',
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
