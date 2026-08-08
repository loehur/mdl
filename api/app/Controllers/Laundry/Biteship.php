<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\InstantKurir;
use App\Models\BiteshipClient;

/**
 * Biteship proxy for laundry Instant kurir
 * URL: /Laundry/Biteship/{method}
 */
class Biteship extends Controller
{
    /**
     * POST /Laundry/Biteship/rates
     */
    public function rates()
    {
        $this->handleCors();
        header('Content-Type: application/json; charset=utf-8');

        // Ubah warning/notice jadi exception agar UI dapat pesan nyata (bukan "PHP Error")
        set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            if (!$this->isPost()) {
                http_response_code(405);
                echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
                return;
            }

            $body = $this->getBody();
            if (!is_array($body)) {
                $body = [];
            }
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
                'couriers' => $body['couriers'] ?? 'grab,gojek,paxel,lalamove,borzo,maxim,deliveree',
                'items' => $items,
            ];

            $client = new BiteshipClient();
            $res = $client->getRates($payload);
            if (empty($res['success']) && empty($res['pricing'])) {
                $msg = (string) ($res['message'] ?? $res['error'] ?? 'Gagal mengambil tarif kurir');
                if (stripos($msg, 'authorization') !== false || stripos($msg, 'unauthorized') !== false) {
                    $msg = 'Layanan Instant sementara tidak tersedia. Coba lagi nanti.';
                    \Log::write('Biteship rates auth failed — check BITESHIP_API_KEY', 'api', 'Biteship');
                }
                if (stripos($msg, 'BITESHIP_API_KEY') !== false) {
                    $msg = 'Layanan Instant sementara tidak tersedia. Coba lagi nanti.';
                }
                http_response_code(502);
                echo json_encode([
                    'ok' => false,
                    'message' => $msg,
                    'http_code' => $res['http_code'] ?? null,
                ]);
                return;
            }

            $pricing = is_array($res['pricing'] ?? null) ? $res['pricing'] : [];
            $instant = InstantKurir::filterInstantPricing($pricing);

            echo json_encode([
                'ok' => true,
                'rates' => $instant,
                'count' => count($instant),
                'pricing_total' => count($pricing),
                'source' => 'biteship',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \Log::write('Biteship rates err: ' . $e->getMessage(), 'api', 'Biteship');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], JSON_UNESCAPED_UNICODE);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * POST /Laundry/Biteship/activate
     *
     * Gate utama: kas Instant (jt=10) harus sudah lunas.
     * Secret cron opsional — laundry sering belum punya URL::API_CRON_SECRET.
     */
    public function activate()
    {
        $this->handleCors();
        header('Content-Type: application/json; charset=utf-8');

        // Notice/warning → exception (jangan biarkan global handler return "PHP Error")
        set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            if (!$this->verifyActivateAccess()) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
                return;
            }

            $body = $this->getBody();
            if (empty($body) && $this->isPost()) {
                $body = $_POST;
            }
            if (!is_array($body)) {
                $body = [];
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
            if (!is_array($result)) {
                $result = ['ok' => false, 'message' => 'Aktivasi gagal'];
            }
            if (empty($result['ok'])) {
                http_response_code(502);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \Log::write(
                'Biteship activate err: ' . $e->getMessage()
                . ' @' . basename($e->getFile()) . ':' . $e->getLine(),
                'api',
                'Biteship'
            );
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], JSON_UNESCAPED_UNICODE);
        } finally {
            restore_error_handler();
        }
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
        try {
            $client = new BiteshipClient();
            $res = $client->getOrder($id);
            echo json_encode([
                'ok' => !empty($res['success']) || !empty($res['id']),
                'data' => $res,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Activate access:
     * - secret benar → OK
     * - secret salah (dikirim tapi tidak cocok) → tolak
     * - secret kosong (laundry belum set URL::API_CRON_SECRET) → OK compat,
     *   tetap aman karena hanya memproses kas Instant yang sudah lunas
     */
    private function verifyActivateAccess(): bool
    {
        $expected = $this->expectedCronSecret();
        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }

        if ($expected !== '' && $provided !== '') {
            return hash_equals($expected, $provided);
        }
        if ($expected !== '' && $provided === '') {
            \Log::write('Biteship activate: no secret from laundry (compat allow)', 'api', 'Biteship');
            return true;
        }
        // API belum set CRON_SECRET — izinkan agar tidak deadlock
        return true;
    }

    private function expectedCronSecret(): string
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = (string) \Env::CRON_SECRET;
        }
        if ($expected === '') {
            $expected = (string) (getenv('CRON_SECRET') ?: '');
        }
        return $expected;
    }
}
