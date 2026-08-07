<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\OperatingHoursHelper;

/**
 * Jam operasional (sumber tunggal Config/OperatingHours.php)
 * URL: /Laundry/OperatingHours/{method}
 */
class OperatingHours extends Controller
{
    /**
     * GET /Laundry/OperatingHours/instant
     * Status jendela Order Kurir Instant (cutoff 30 menit sebelum tutup).
     */
    public function instant()
    {
        $this->handleCors();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        try {
            if (!$this->isGet() && !$this->isPost()) {
                http_response_code(405);
                echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
                return;
            }

            $status = OperatingHoursHelper::instantOrderStatus();
            echo json_encode([
                'ok' => true,
                'instantWindow' => $status,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \Log::write('OperatingHours instant err: ' . $e->getMessage(), 'api', 'OperatingHours');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal memuat jam operasional',
                'instantWindow' => [
                    'ok' => false,
                    'reason' => 'api_error',
                    'message' => 'Jam operasional sementara tidak bisa dicek. Coba lagi.',
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /Laundry/OperatingHours (alias → instant)
     */
    public function index()
    {
        $this->instant();
    }
}
