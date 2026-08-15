<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\Laundry\AntarTarifHelper;

/**
 * Tarif ongkir sameday (sumber tunggal Config/AntarTarif.php)
 * URL: /Laundry/AntarTarif/{method}
 */
class AntarTarif extends Controller
{
    /**
     * GET /Laundry/AntarTarif/config
     * Rumus: km < free_km → 0; selain itu max(min_tarif, round(km × rate_per_km))
     */
    public function config()
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

            $cfg = AntarTarifHelper::loadConfig();
            echo json_encode([
                'ok' => true,
                'min_tarif' => $cfg['min_tarif'],
                'rate_per_km' => $cfg['rate_per_km'],
                'free_km' => $cfg['free_km'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \Log::write('AntarTarif config err: ' . $e->getMessage(), 'api', 'AntarTarif');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal memuat config tarif antar',
                'min_tarif' => AntarTarifHelper::DEFAULT_MIN_TARIF,
                'rate_per_km' => AntarTarifHelper::DEFAULT_RATE_PER_KM,
                'free_km' => AntarTarifHelper::DEFAULT_FREE_KM,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /Laundry/AntarTarif/fromCoords?cab_lat=&cab_lon=&loc_lat=&loc_lon=
     */
    public function fromCoords()
    {
        $this->handleCors();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        try {
            if (!$this->isGet()) {
                http_response_code(405);
                echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
                return;
            }

            $cabLat = $this->query('cab_lat');
            $cabLon = $this->query('cab_lon');
            $locLat = $this->query('loc_lat');
            $locLon = $this->query('loc_lon');

            if ($cabLat === null || $cabLon === null || $locLat === null || $locLon === null) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Parameter cab_lat, cab_lon, loc_lat, loc_lon wajib']);
                return;
            }

            $calc = AntarTarifHelper::tarifFromCoords($cabLat, $cabLon, $locLat, $locLon);
            echo json_encode(array_merge(['ok' => true], $calc), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \Log::write('AntarTarif fromCoords err: ' . $e->getMessage(), 'api', 'AntarTarif');
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal hitung tarif antar']);
        }
    }

    /** GET /Laundry/AntarTarif (alias → config) */
    public function index()
    {
        $this->config();
    }
}
