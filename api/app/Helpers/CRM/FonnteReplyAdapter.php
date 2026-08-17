<?php

namespace App\Helpers\CRM;

/**
 * Adapter agar WAReplies bisa kirim via Fonnte (bukan YCloud).
 * Implementasi sendFreeText() dengan format return yang kompatibel.
 */
class FonnteReplyAdapter
{
    private $fonnte;
    private $inboxid;
    /** @var FonnteMessageStore|null */
    private $messageStore;
    private $handler = null;

    public function __construct($inboxid = null, $messageStore = null)
    {
        if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
            require_once __DIR__ . '/FonnteService.php';
        }
        $this->fonnte = new FonnteService();
        $this->inboxid = $inboxid;
        $this->messageStore = $messageStore;
    }

    public function setMessageStore(FonnteMessageStore $store): void
    {
        $this->messageStore = $store;
    }

    public function setHandler(?string $handler): void
    {
        $this->handler = $handler !== null && $handler !== '' ? $handler : null;
    }

    /**
     * Kirim teks - kompatibel dengan WhatsAppService::sendFreeText()
     * @return array ['success' => bool, 'data' => ['id' => ...], 'error' => ...]
     */
    public function sendFreeText($to, $message, $replyToMessageId = null, $senderCode = null)
    {
        if (!class_exists(SapaanStatsHelper::class)) {
            require_once __DIR__ . '/SapaanStatsHelper.php';
        }

        $options = [];
        $inbox = null;
        if ($replyToMessageId !== null && is_numeric($replyToMessageId) && (int) $replyToMessageId > 0) {
            $inbox = (int) $replyToMessageId;
        } elseif ($this->inboxid && is_numeric($this->inboxid) && (int) $this->inboxid > 0) {
            $inbox = (int) $this->inboxid;
        }
        if ($inbox) {
            $options['inboxid'] = $inbox;
        }
        $res = $this->fonnte->sendMessage($to, $message, $options);

        $rawId = $res['data']['id'] ?? null;
        $fonnteId = null;
        if (is_array($rawId) && isset($rawId[0]) && $rawId[0] !== '') {
            $fonnteId = $rawId[0];
        } elseif (is_scalar($rawId) && trim((string) $rawId) !== '') {
            $fonnteId = $rawId;
        } else {
            $fonnteId = $res['data']['requestid'] ?? null;
        }
        $waNumber = $this->normalizePhoneForStore($to);

        // Default AR = autoreply/bot; human harus kirim sender_code eksplisit (bukan AR).
        $code = ($senderCode !== null && trim((string) $senderCode) !== '')
            ? trim((string) $senderCode)
            : SapaanStatsHelper::SENDER_CODE_AUTOREPLY;
        $isHuman = SapaanStatsHelper::isHumanSenderCode($code);

        if ($this->messageStore !== null) {
            $this->messageStore->saveOutgoing($waNumber, (string) $message, [
                'fonnte_message_id' => is_scalar($fonnteId) ? (string) $fonnteId : null,
                'reply_inboxid' => $inbox,
                'source' => $isHuman ? 'human' : 'autoreply',
                'sender_code' => $code,
                'handler' => $this->handler,
                'status' => !empty($res['success']) ? 'sent' : 'failed',
                'error_text' => !empty($res['success']) ? null : ($res['error'] ?? 'send failed'),
            ]);
        }

        return [
            'success' => $res['success'] ?? false,
            'data' => [
                'id' => $fonnteId,
                'wamid' => null,
            ],
            'error' => $res['error'] ?? null,
        ];
    }

    private function normalizePhoneForStore($phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }
        $s = trim((string) $phone);
        if (strpos($s, '@g.us') !== false) {
            return $s;
        }
        $clean = preg_replace('/[^0-9]/', '', $s);
        if (strlen($clean) < 8) {
            return $s;
        }
        if (substr($clean, 0, 1) === '0') {
            $clean = '62' . substr($clean, 1);
        } elseif (substr($clean, 0, 2) !== '62') {
            $clean = '62' . $clean;
        }

        return '+' . $clean;
    }
}
