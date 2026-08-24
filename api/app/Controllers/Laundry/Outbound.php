<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\OutboundStore;

/**
 * Kirim info dinamis ke pelanggan dari CRM.
 * URL: /Laundry/Outbound/{tagihan|status}
 */
class Outbound extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    public function tagihan()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $this->reply(OutboundStore::sendTagihan($this->mergedInput()));
    }

    public function status()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $this->reply(OutboundStore::sendStatus($this->mergedInput()));
    }

    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->setCorsHeaders();
    }

    /**
     * @param array<string,mixed> $res
     */
    private function reply(array $res): void
    {
        $ok = !empty($res['ok']);
        $res['status'] = $ok;
        if (!$ok) {
            $code = !empty($res['cooldown']) ? 429 : 400;
            http_response_code($code);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    private function fail(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode([
            'ok' => false,
            'status' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string,mixed>
     */
    private function mergedInput(): array
    {
        $json = $this->getBody();
        if (!is_array($json)) {
            $json = [];
        }
        return array_merge($_GET, $_POST, $json);
    }
}
