<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\PermintaanStore;

/**
 * Permintaan pelanggan (wa_permintaan_session) — CRM Customer Panel.
 * URL: /Laundry/Permintaan/listOpen
 */
class Permintaan extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    public function listOpen()
    {
        $this->jsonHeader();
        $body = $this->mergedInput();
        $id = (int) (
            $this->query('id_pelanggan')
            ?? $this->query('cust_id')
            ?? $body['id_pelanggan']
            ?? $body['cust_id']
            ?? 0
        );
        $waNumber = trim((string) (
            $this->query('wa_number')
            ?? $body['wa_number']
            ?? ''
        ));
        $this->reply(PermintaanStore::listOpen($id, $waNumber));
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
