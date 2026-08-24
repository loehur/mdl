<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\PelangganLokasiStore;

/**
 * Lokasi pelanggan (pelanggan_lokasi) — dipakai CRM + laundry.
 * URL: /Laundry/PelangganLokasi/{listLokasi|defaultMap|add|update|delete|resolveMaps}
 */
class PelangganLokasi extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    public function listLokasi()
    {
        $this->jsonHeader();
        $id = $this->idPelangganFromRequest();
        if ($id <= 0) {
            $this->fail('id_pelanggan / cust_id wajib', 400);
            return;
        }
        $this->reply(PelangganLokasiStore::list($id));
    }

    public function defaultMap()
    {
        $this->jsonHeader();
        $id = $this->idPelangganFromRequest();
        if ($id <= 0) {
            $this->fail('id_pelanggan / cust_id wajib', 400);
            return;
        }
        $this->reply(array_merge(['ok' => true], PelangganLokasiStore::getDefaultMapCoords($id)));
    }

    public function add()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        $body['id_pelanggan'] = $this->idPelangganFromRequest($body);
        $this->reply(PelangganLokasiStore::add($body));
    }

    public function update()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        $body['id_pelanggan'] = $this->idPelangganFromRequest($body);
        $this->reply(PelangganLokasiStore::update($body));
    }

    public function delete()
    {
        $this->jsonHeader();
        if (!$this->isPost()) {
            $this->fail('Method not allowed', 405);
            return;
        }
        $body = $this->mergedInput();
        $idPelanggan = $this->idPelangganFromRequest($body);
        $idLokasi = (int) ($body['id_lokasi'] ?? 0);
        $this->reply(PelangganLokasiStore::delete($idPelanggan, $idLokasi));
    }

    public function resolveMaps()
    {
        $this->jsonHeader();
        $body = $this->mergedInput();
        $url = trim((string) ($body['url'] ?? $body['gmaps_url'] ?? $this->query('url', '')));
        $this->reply(PelangganLokasiStore::resolveMaps($url));
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
     * @param array<string,mixed>|null $body
     */
    private function idPelangganFromRequest(?array $body = null): int
    {
        $src = $body ?? $this->mergedInput();
        return (int) (
            $src['id_pelanggan']
            ?? $src['cust_id']
            ?? $this->query('id_pelanggan')
            ?? $this->query('cust_id')
            ?? 0
        );
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
