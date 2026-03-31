<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * Fonnte WhatsApp Webhook Handler
 * Alur intent & balasan sama seperti Webhook\WhatsApp (WAReplies::process), tetapi:
 * - Tidak menulis wa_messages_in
 * - Tidak mengubah / membuat wa_conversations (setSkipConversationPersist)
 * - Balasan keluar via FonnteReplyAdapter (Fonnte API), termasuk inboxid bila ada
 * - wa_fonnte_csw tetap di-update untuk CSW Fonnte
 *
 * @see https://docs.fonnte.com/
 * URL: /Webhook/WA_Fonnte
 */
class WA_Fonnte extends Controller
{
    private const DEFAULT_FALLBACK_REPLY = "Mohon maaf jika slow respon.\n\nBila berkenan kirimkan pesan ke\n*Madinah Laundry (CS)*\n💬 wa.me/6281170706611\n\nTerima kasih.";

    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->trace('index hit');

        $method = $_SERVER['REQUEST_METHOD'];
        $this->trace('request method=' . ($method ?: 'unknown'));

        if ($method === 'GET') {
            echo json_encode(['status' => 'ok', 'message' => 'Fonnte webhook endpoint']);

            return;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

            return;
        }

        $json = file_get_contents('php://input');
        $this->trace('raw body length=' . (string) strlen((string) $json));
        $data = json_decode($json, true);
        $this->trace('json decoded=' . ($data ? 'true' : 'false'));

        if (!$data) {
            $this->trace('Invalid JSON');
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);

            return;
        }

        $sender = $data['sender'] ?? null;
        $message = $data['message'] ?? null;
        $text = $data['text'] ?? null;
        $name = $data['name'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
        $inboxid = $data['inboxid'] ?? null;
        $url = $data['url'] ?? null;
        $filename = $data['filename'] ?? null;

        $this->recordFonnteIncoming($sender, $timestamp);
        $this->trace(
            'inbound sender=' . ($sender ?? 'null') .
            ' | inboxid=' . (($inboxid !== null) ? (string)$inboxid : 'null') .
            ' | has_message=' . (($message !== null && $message !== '') ? '1' : '0') .
            ' | has_text=' . (($text !== null && $text !== '') ? '1' : '0') .
            ' | has_media_url=' . (($url !== null && $url !== '') ? '1' : '0')
        );

        $replyText = '';

        $messageText = trim((string) ($message ?? $text ?? ''));
        if ($messageText === '' && ! empty($url)) {
            $messageText = '📷 ' . (string) ($filename ?: 'Media');
        }
        if ($messageText === '') {
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        $waNumber = $this->normalizeWaNumber($sender);
        if (! $waNumber) {
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanPhone, 2);
        $phonePlus = '+' . $cleanPhone;
        $phoneNoPrefix = substr($cleanPhone, 2);
        $phones = ["'$cleanPhone'", "'$phone0'", "'$phonePlus'", "'$phoneNoPrefix'"];
        $phoneIn = implode(',', $phones);

        try {
            $wh = new WhatsApp();
            $user_data = $wh->getUserData($phone0);
            $assigned_user_id = $user_data ? ($user_data->assigned_user_id ?? null) : null;
            $code = $user_data ? ($user_data->code ?? null) : null;
            $cust_id = $user_data ? ($user_data->cust_id ?? null) : null;
            $contact_name = $user_data ? ($user_data->customer_name ?? $name) : ($name ?? null);

            $lastMessageSummary = $messageText;
            $isPrivateForLastMessage = false;
            if (!class_exists('\Env')) {
                require_once __DIR__ . '/../../Config/Env.php';
            }
            if (class_exists('\Env') && method_exists('\Env', 'textContainsPrivateWord')) {
                $isPrivateForLastMessage = \Env::textContainsPrivateWord($lastMessageSummary ?? '');
            }
            $lastMessage = $isPrivateForLastMessage
                ? 'i- 🔒 _Private Chat_'
                : 'i- ' . mb_substr($lastMessageSummary, 0, 50);

            if (! class_exists('\\App\\Models\\WAReplies')) {
                require_once __DIR__ . '/../../Models/WAReplies.php';
            }
            if (! class_exists('\\App\\Helpers\\FonnteReplyAdapter')) {
                require_once __DIR__ . '/../../Helpers/FonnteReplyAdapter.php';
            }

            $replies = new \App\Models\WAReplies();
            $replies->setCustomSender(new \App\Helpers\FonnteReplyAdapter($inboxid));
            $replies->setSkipConversationPersist(true);
            $replies->setAutoReplyProvider('B');

            $processResult = $replies->process(
                $phoneIn,
                $messageText,
                $waNumber,
                $contact_name,
                $assigned_user_id,
                $code,
                $lastMessage,
                $cust_id
            );
            $this->trace(
                'process result case=' . (string)($processResult->case ?? 'null') .
                ' | notify=' . (string)($processResult->notify ?? 'null') .
                ' | conv_id=' . (string)($processResult->conversation_id ?? 'null') .
                ' | wa=' . $waNumber
            );

            // Jika tidak masuk intent apa pun (fallback internal WAReplies = case 4), baru kirim balasan default.
            if ((int) ($processResult->case ?? 0) === 4) {
                $this->sendFallbackReply($sender, self::DEFAULT_FALLBACK_REPLY, $inboxid);
            }
        } catch (\Throwable $e) {
            $this->trace('WAReplies exception: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
        }

        echo json_encode(['status' => 'ok', 'reply' => $replyText]);
    }

    /**
     * Simpan waktu pesan masuk terakhir per nomor (db 0) untuk CSW Fonnte.
     */
    private function recordFonnteIncoming($sender, $timestamp = null): void
    {
        $waNumber = $this->normalizeWaNumber($sender);
        if ($waNumber === null) {
            return;
        }
        $lastInAt = $this->fonnteTimestampToLastInAt($timestamp);
        try {
            $db = $this->db(0);
            $check = $db->query(
                'SELECT id FROM wa_fonnte_csw WHERE phone = ? LIMIT 1',
                [$waNumber]
            );
            if ($check->num_rows() > 0) {
                $db->query(
                    'UPDATE wa_fonnte_csw SET last_in_at = ? WHERE phone = ?',
                    [$lastInAt, $waNumber]
                );
            } else {
                $db->query(
                    'INSERT INTO wa_fonnte_csw (phone, last_in_at) VALUES (?, ?)',
                    [$waNumber, $lastInAt]
                );
            }
        } catch (\Throwable $e) {
            \Log::write('WA_Fonnte: wa_fonnte_csw update failed: ' . $e->getMessage(), 'webhook', 'Fonnte');
        }
    }

    private function fonnteTimestampToLastInAt($timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return date('Y-m-d H:i:s');
        }
        if (is_numeric($timestamp)) {
            $ts = (int) $timestamp;
            if ($ts > 9999999999) {
                $ts = (int) ($ts / 1000);
            }

            return date('Y-m-d H:i:s', $ts);
        }
        $s = substr((string) $timestamp, 0, 40);

        return $s !== '' ? $s : date('Y-m-d H:i:s');
    }

    private function sendFallbackReply($target, $message, $inboxid = null): void
    {
        if (empty($target) || empty($message)) {
            return;
        }

        if (! class_exists('\\App\\Helpers\\FonnteService')) {
            require_once __DIR__ . '/../../Helpers/FonnteService.php';
        }

        $fonnte = new \App\Helpers\FonnteService();
        $options = [];
        if ($inboxid) {
            $options['inboxid'] = (int) $inboxid;
        }
        $res = $fonnte->sendMessage($target, $message, $options);
        $ok = !empty($res['success']) ? 'true' : 'false';
        $err = $res['error'] ?? '';
        $this->trace("fallback send to {$target} | success={$ok} | error={$err}");
    }

    private function trace(string $text): void
    {
        if (class_exists('\Log')) {
            \Log::write('[WA_Fonnte] ' . $text, 'webhook', 'Fonnte');
        }
        error_log('[WA_Fonnte] ' . $text);

        $dir = __DIR__ . '/../../../logs/' . date('Y-m-d') . '/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . 'webhook_fonnte_trace.log', date('H:i:s') . ' [WA_Fonnte] ' . $text . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Normalize sender ke format wa_number (+62xxx)
     */
    private function normalizeWaNumber($sender)
    {
        if (empty($sender)) {
            return null;
        }
        $clean = preg_replace('/[^0-9]/', '', $sender);
        if (strlen($clean) < 8) {
            return null;
        }
        if (substr($clean, 0, 1) === '0') {
            $clean = '62' . substr($clean, 1);
        } elseif (substr($clean, 0, 2) !== '62') {
            $clean = '62' . $clean;
        }

        return '+' . $clean;
    }
}
