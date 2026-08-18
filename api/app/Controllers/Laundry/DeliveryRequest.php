<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\DeliveryRequestStore;

/**
 * Buat delivery request sameday dari CRM / laundry.
 * URL: /Laundry/DeliveryRequest/create
 */
class DeliveryRequest extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    public function create()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        $body['id_pelanggan'] = (int) (
            $body['id_pelanggan']
            ?? $body['cust_id']
            ?? $this->query('id_pelanggan')
            ?? $this->query('cust_id')
            ?? 0
        );
        $this->reply(DeliveryRequestStore::create($body));
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
            http_response_code(isset($res['message']) ? 400 : 500);
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
