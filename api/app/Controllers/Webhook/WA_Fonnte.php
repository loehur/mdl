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
    private const DEFAULT_FALLBACK_REPLY_FONNTE = "Maaf, mohon menunggu. Admin sedang melayani customer lain.\n\nUntuk balasan otomatis, silahkan ketik:\n- *BON* untuk info nota\n- *CEK* untuk info status\n- *BILL* untuk info tagihan\n\nUntuk informasi lainnya, kirimkan pesan ke *Madinah Laundry (CS)*\n💬 wa.me/6281170706611";

    /** Cooldown wa_auto_reply_log (handler DEFAULT) sebelum fallback dikirim lagi ke nomor yang sama — 24 jam */
    private const DEFAULT_FALLBACK_COOLDOWN_MINUTES = 1440;

    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        $method = $_SERVER['REQUEST_METHOD'];

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
        $data = json_decode($json, true);

        if (!$data) {
            \Log::write('WA_Fonnte: Invalid JSON', 'webhook', 'Fonnte');
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

        $replyText = '';

        $rawText = trim((string) ($message ?? $text ?? ''));
        // Gambar/file/voice dari Fonnte biasanya punya url; tanpa caption = anggap panjang teks 0 (tidak intent AI, tidak DEFAULT_FALLBACK_REPLY_FONNTE)
        $isMediaWithoutCaption = ($rawText === '' && ! empty($url));

        if ($rawText === '' && empty($url)) {
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        $waNumber = $this->normalizeWaNumber($sender);
        if (! $waNumber) {
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        if ($isMediaWithoutCaption) {
            $this->recordFonnteIncoming($waNumber, $timestamp);
            echo json_encode(['status' => 'ok', 'reply' => $replyText]);

            return;
        }

        $messageText = $rawText;

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

            // CSW Fonnte harus ter-commit dulu; baru handle intent (hindari race baca last_in_at vs balasan).
            $this->recordFonnteIncoming($waNumber, $timestamp);

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
            // Fallback default hanya jika intent tidak punya handler (atau unknown/no-intent), max sekali per nomor per DEFAULT_FALLBACK_COOLDOWN_MINUTES.
            // Pesan pendek (≤20 karakter) tidak dibalas fallback CS agar tidak mengganggu salam/stiker singkat.
            if (!empty($processResult->no_handler) && mb_strlen(trim((string) ($messageText ?? ''))) > 20) {
                if ($replies->shouldSendFonnteFallbackReply($waNumber, self::DEFAULT_FALLBACK_COOLDOWN_MINUTES)) {
                    $this->sendFallbackReply($sender, self::DEFAULT_FALLBACK_REPLY, $inboxid);
                }
            }
        } catch (\Throwable $e) {
            \Log::write('WA_Fonnte WAReplies: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine(), 'webhook', 'Fonnte');
        }

        echo json_encode(['status' => 'ok', 'reply' => $replyText]);
    }

    /**
     * Simpan waktu pesan masuk terakhir per nomor (db 0) untuk CSW Fonnte.
     * Dipanggil dengan $waNumber yang sama persis dengan alur process() (sudah dinormalisasi).
     * Transaksi singkat: commit sebelum WAReplies::process agar tidak ada race dengan pembaca last_in_at.
     */
    private function recordFonnteIncoming(string $waNumber, $timestamp = null): void
    {
        if ($waNumber === '') {
            return;
        }
        $lastInAt = $this->fonnteTimestampToLastInAt($timestamp);
        try {
            $db = $this->db(0);
            if (! $db->beginTransaction()) {
                \Log::write('WA_Fonnte: wa_fonnte_csw beginTransaction failed', 'webhook', 'Fonnte');

                return;
            }
            try {
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
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
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
        $fonnte->sendMessage($target, $message, $options);
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
