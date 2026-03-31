<?php

namespace App\Helpers;

/**
 * Adapter agar WAReplies bisa kirim via Fonnte (bukan YCloud).
 * Implementasi sendFreeText() dengan format return yang kompatibel.
 */
class FonnteReplyAdapter
{
    private $fonnte;
    private $inboxid;

    public function __construct($inboxid = null)
    {
        if (!class_exists('\\App\\Helpers\\FonnteService')) {
            require_once __DIR__ . '/FonnteService.php';
        }
        $this->fonnte = new FonnteService();
        $this->inboxid = $inboxid;
    }

    /**
     * Kirim teks - kompatibel dengan WhatsAppService::sendFreeText()
     * @return array ['success' => bool, 'data' => ['id' => ...], 'error' => ...]
     */
    public function sendFreeText($to, $message, $replyToMessageId = null, $senderCode = null)
    {
        $options = [];
        if ($this->inboxid) {
            $options['inboxid'] = (int) $this->inboxid;
        }
        $res = $this->fonnte->sendMessage($to, $message, $options);
        return [
            'success' => $res['success'] ?? false,
            'data' => [
                'id' => $res['data']['id'][0] ?? 'fonnte_' . time(),
                'wamid' => null,
            ],
            'error' => $res['error'] ?? null,
        ];
    }
}
