<?php

namespace App\Controllers\Jaggu_School;

use App\Helpers\Jaggu_School\AiClient;

/**
 * Chat AI untuk anak.
 * GET  /Jaggu_School/Chat/today
 * POST /Jaggu_School/Chat/send  { message }
 */
class Chat extends JagguController
{
    private const MAX_MESSAGE_LEN = 2000;
    private const MAX_MESSAGES_PER_DAY = 30;
    private const RECENT_LIMIT = 6;
    private const SUMMARY_MAX_CHARS = 400;

    public function today()
    {
        $child = $this->requireChild();
        $userId = (int) $child['id'];
        $today = date('Y-m-d');

        try {
            $messages = $this->loadTodayMessages($userId, $today);
            $summary = $this->loadDailySummary($userId, $today);
            $count = $this->countUserMessagesToday($userId, $today);

            $this->success([
                'date' => $today,
                'messages' => $messages,
                'summary' => $summary,
                'remaining_today' => max(0, self::MAX_MESSAGES_PER_DAY - $count),
            ], 'OK');
        } catch (\Throwable $e) {
            $this->error('Gagal memuat chat: ' . $e->getMessage(), 500);
        }
    }

    public function send()
    {
        $child = $this->requireChild();

        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        try {
            $body = $this->getBody();
            $message = trim((string) ($body['message'] ?? ''));
            if ($message === '') {
                $this->error('Pesan kosong', 400);
            }
            if (mb_strlen($message) > self::MAX_MESSAGE_LEN) {
                $this->error('Pesan terlalu panjang (max ' . self::MAX_MESSAGE_LEN . ' karakter)', 400);
            }

            $userId = (int) $child['id'];
            $childName = (string) ($child['name'] ?? 'Jaggu');
            $aiCallName = 'Jaggu';
            $today = date('Y-m-d');
            $db = $this->db($this->db_index);

            $used = $this->countUserMessagesToday($userId, $today);
            if ($used >= self::MAX_MESSAGES_PER_DAY) {
                $this->error('Batas chat hari ini sudah tercapai (' . self::MAX_MESSAGES_PER_DAY . ' pesan). Coba lagi besok ya!', 429);
            }

            $userMsgId = (int) $db->insert('jaggu_chat_messages', [
                'user_id' => $userId,
                'role' => 'user',
                'content' => $message,
                'provider' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($userMsgId <= 0) {
                $this->error('Gagal menyimpan pesan', 500);
            }

            $summary = $this->loadDailySummary($userId, $today);
            $recent = $this->loadRecentMessages($userId, $today, self::RECENT_LIMIT);

            $aiMessages = [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt($aiCallName, $childName),
                ],
            ];

            if ($summary !== '') {
                $aiMessages[] = [
                    'role' => 'user',
                    'content' => "Ringkasan percakapan hari ini (untuk konteks, jangan ulangi mentah-mentah):\n" . $summary,
                ];
                $aiMessages[] = [
                    'role' => 'assistant',
                    'content' => 'Baik, saya ingat ringkasan itu dan siap membantu lagi.',
                ];
            }

            foreach ($recent as $row) {
                // Pesan user yang baru saja di-insert sudah termasuk di recent — OK
                $role = $row['role'] === 'assistant' ? 'assistant' : 'user';
                $aiMessages[] = [
                    'role' => $role,
                    'content' => $row['content'],
                ];
            }

            // Pastikan pesan terakhir adalah user message saat ini (jika recent kosong / race)
            $last = end($aiMessages);
            if (!$last || ($last['role'] ?? '') !== 'user' || ($last['content'] ?? '') !== $message) {
                $aiMessages[] = ['role' => 'user', 'content' => $message];
            }

            $result = AiClient::chat($aiMessages, 600, 0.7);
            $reply = $result['content'];
            $provider = $result['provider'];

            $assistantMsgId = (int) $db->insert('jaggu_chat_messages', [
                'user_id' => $userId,
                'role' => 'assistant',
                'content' => $reply,
                'provider' => $provider,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($assistantMsgId <= 0) {
                $this->error('Gagal menyimpan jawaban AI', 500);
            }

            $newSummary = $this->refreshDailySummary($userId, $today, $summary, $message, $reply);

            $this->success([
                'user_message' => [
                    'id' => $userMsgId,
                    'role' => 'user',
                    'content' => $message,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                'assistant_message' => [
                    'id' => $assistantMsgId,
                    'role' => 'assistant',
                    'content' => $reply,
                    'provider' => $provider,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                'summary' => $newSummary,
                'remaining_today' => max(0, self::MAX_MESSAGES_PER_DAY - ($used + 1)),
            ], 'OK');
        } catch (\Throwable $e) {
            $this->error('AI sedang sibuk: ' . $e->getMessage(), 502);
        }
    }

    private function systemPrompt(string $callName, string $accountName): string
    {
        return "Kamu adalah tutor AI ramah untuk aplikasi Jaggu School. "
            . "Siswa ini punya nama akun {$accountName}, tetapi SELALU panggil dia \"{$callName}\" saja — jangan pakai nama lain. "
            . "Tingkat SD (bisa tumbuh sampai kuliah). "
            . "Jawab dalam bahasa Indonesia yang sederhana, semangat, dan sopan. "
            . "Bantu soal pelajaran, PR, penjelasan konsep, dan motivasi belajar. "
            . "Jangan bahas topik berbahaya, kekerasan, atau konten dewasa. "
            . "Jika tidak yakin, akui dan sarankan bertanya ke orang tua/guru. "
            . "Jawaban ringkas dan jelas, boleh pakai contoh mudah.";
    }

    private function loadTodayMessages(int $userId, string $ymd): array
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT id, role, content, provider, created_at
             FROM jaggu_chat_messages
             WHERE user_id = ? AND DATE(created_at) = ?
             ORDER BY id ASC",
            [$userId, $ymd]
        )->result_array();

        return array_map(static function ($r) {
            return [
                'id' => (int) $r['id'],
                'role' => $r['role'],
                'content' => $r['content'],
                'provider' => $r['provider'],
                'created_at' => $r['created_at'],
            ];
        }, $rows ?: []);
    }

    private function loadRecentMessages(int $userId, string $ymd, int $limit): array
    {
        $rows = $this->db($this->db_index)->query(
            "SELECT id, role, content, provider, created_at
             FROM jaggu_chat_messages
             WHERE user_id = ? AND DATE(created_at) = ?
             ORDER BY id DESC
             LIMIT " . (int) $limit,
            [$userId, $ymd]
        )->result_array();

        $rows = array_reverse($rows ?: []);
        return array_map(static function ($r) {
            return [
                'id' => (int) $r['id'],
                'role' => $r['role'],
                'content' => $r['content'],
            ];
        }, $rows);
    }

    private function countUserMessagesToday(int $userId, string $ymd): int
    {
        $row = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS c FROM jaggu_chat_messages
             WHERE user_id = ? AND role = 'user' AND DATE(created_at) = ?",
            [$userId, $ymd]
        )->row_array();

        return (int) ($row['c'] ?? 0);
    }

    private function loadDailySummary(int $userId, string $ymd): string
    {
        $row = $this->db($this->db_index)->query(
            "SELECT summary_text FROM jaggu_chat_daily_summaries
             WHERE user_id = ? AND summary_date = ?
             LIMIT 1",
            [$userId, $ymd]
        )->row_array();

        return trim((string) ($row['summary_text'] ?? ''));
    }

    private function refreshDailySummary(
        int $userId,
        string $ymd,
        string $oldSummary,
        string $userMsg,
        string $assistantMsg
    ): string {
        $newSummary = '';

        try {
            $promptMessages = [
                [
                    'role' => 'system',
                    'content' => 'Perbarui ringkasan percakapan belajar anak SD. '
                        . 'Bahasa Indonesia, maksimal ' . self::SUMMARY_MAX_CHARS . ' karakter. '
                        . 'Hanya fakta/topik penting. Tanpa sapaan. Output teks ringkas saja.',
                ],
                [
                    'role' => 'user',
                    'content' => "Ringkasan lama:\n"
                        . ($oldSummary !== '' ? $oldSummary : '(belum ada)')
                        . "\n\nQ&A baru:\nAnak: {$userMsg}\nTutor: {$assistantMsg}\n\nTulis ringkasan baru:",
                ],
            ];
            $res = AiClient::chat($promptMessages, 200, 0.3);
            $newSummary = mb_substr(trim($res['content']), 0, self::SUMMARY_MAX_CHARS);
        } catch (\Throwable $e) {
            $blob = trim($oldSummary . ' | Anak: ' . mb_substr($userMsg, 0, 80) . ' → ' . mb_substr($assistantMsg, 0, 80));
            $newSummary = mb_substr($blob, -self::SUMMARY_MAX_CHARS);
        }

        if ($newSummary === '') {
            return $oldSummary;
        }

        $db = $this->db($this->db_index);
        $existing = $db->query(
            "SELECT id FROM jaggu_chat_daily_summaries WHERE user_id = ? AND summary_date = ? LIMIT 1",
            [$userId, $ymd]
        )->row_array();

        if ($existing) {
            $db->update('jaggu_chat_daily_summaries', [
                'summary_text' => $newSummary,
            ], ['id' => (int) $existing['id']]);
        } else {
            $db->insert('jaggu_chat_daily_summaries', [
                'user_id' => $userId,
                'summary_date' => $ymd,
                'summary_text' => $newSummary,
            ]);
        }

        return $newSummary;
    }
}
