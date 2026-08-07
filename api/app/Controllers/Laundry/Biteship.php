<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\InstantKurir;

/**
 * Biteship proxy for laundry Instant kurir
 * URL: /Laundry/Biteship/{method}
 */
class Biteship extends Controller
{
    /**
     * POST /Laundry/Biteship/rates
     * Body: origin_latitude, origin_longitude, destination_latitude, destination_longitude,
     *       items? (optional), courier_type? filter
     */
    public function rates()
    {
        $this->handleCors();
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
            return;
        }

        $body = $this->getBody();
        $oLat = (float) ($body['origin_latitude'] ?? 0);
        $oLon = (float) ($body['origin_longitude'] ?? 0);
        $dLat = (float) ($body['destination_latitude'] ?? 0);
        $dLon = (float) ($body['destination_longitude'] ?? 0);

        if (($oLat == 0.0 && $oLon == 0.0) || ($dLat == 0.0 && $dLon == 0.0)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Koordinat origin/destination wajib']);
            return;
        }

        $items = $body['items'] ?? null;
        if (!is_array($items) || empty($items)) {
            $items = [[
                'name' => 'Laundry',
                'description' => 'Paket laundry',
                'value' => 50000,
                'quantity' => 1,
                'weight' => 1000,
            ]];
        }

        $payload = [
            'origin_latitude' => $oLat,
            'origin_longitude' => $oLon,
            'destination_latitude' => $dLat,
            'destination_longitude' => $dLon,
            // Instant / same-day bike couriers (coordinate-based)
            'couriers' => $body['couriers'] ?? 'grab,gojek,paxel,lalamove,borzo,maxim,deliveree',
            'items' => $items,
        ];

        $client = new \App\Models\Biteship();
        $res = $client->getRates($payload);
        if (empty($res['success']) && empty($res['pricing'])) {
            http_response_code(502);
            echo json_encode([
                'ok' => false,
                'message' => $res['message'] ?? $res['error'] ?? 'Gagal mengambil tarif Biteship',
                'raw' => $res,
            ]);
            return;
        }

        $pricing = is_array($res['pricing'] ?? null) ? $res['pricing'] : [];
        $instant = InstantKurir::filterInstantPricing($pricing);

        // Jika filter kosong tapi Biteship mengembalikan pricing, jangan fallback ke tarif jarak —
        // kembalikan semua pricing agar debug/ops bisa lihat, tapi tandai filtered=0
        echo json_encode([
            'ok' => true,
            'rates' => $instant,
            'count' => count($instant),
            'pricing_total' => count($pricing),
            'source' => 'biteship',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /Laundry/Biteship/activate
     * Internal: after QRIS paid — create Biteship order for jt=10 kas
     * Auth: ?secret= or X-Cron-Secret
     * Body: ref_finance OR id_request
     */
    public function activate()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyInternalSecret()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
            return;
        }

        $body = $this->getBody();
        if (empty($body) && $this->isPost()) {
            $body = $_POST;
        }

        $db = $this->db(1);
        if (!$db) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'DB error']);
            return;
        }

        $refFinance = trim((string) ($body['ref_finance'] ?? $_GET['ref_finance'] ?? ''));
        $idRequest = (int) ($body['id_request'] ?? $_GET['id_request'] ?? 0);

        $kas = null;
        if ($refFinance !== '') {
            $kas = $db->get_where('kas', ['ref_finance' => $refFinance])->row_array();
        }
        if (!$kas && $idRequest > 0) {
            $kas = $db->get_where('kas', [
                'jenis_transaksi' => InstantKurir::JENIS_TRANSAKSI,
                'ref_transaksi' => (string) $idRequest,
            ])->row_array();
        }

        if (!$kas) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Kas Instant tidak ditemukan']);
            return;
        }

        if ((int) ($kas['status_mutasi'] ?? 0) !== 3) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Kas belum lunas']);
            return;
        }

        $result = InstantKurir::activateAfterPayment($db, $kas);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /Laundry/Biteship/order/{id}
     */
    public function order($id = '')
    {
        $this->handleCors();
        header('Content-Type: application/json; charset=utf-8');
        $id = trim((string) $id);
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Order id wajib']);
            return;
        }
        $client = new \App\Models\Biteship();
        $res = $client->getOrder($id);
        echo json_encode(['ok' => !empty($res['success']) || !empty($res['id']), 'data' => $res], JSON_UNESCAPED_UNICODE);
    }

    private function verifyInternalSecret(): bool
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = (string) \Env::CRON_SECRET;
        }
        if ($expected === '') {
            $expected = (string) (getenv('CRON_SECRET') ?: '');
        }
        if ($expected === '') {
            return false;
        }
        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }
        return $provided !== '' && hash_equals($expected, $provided);
    }
}
