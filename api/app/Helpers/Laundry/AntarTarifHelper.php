<?php

namespace App\Helpers\Laundry;

/**
 * Rumus ongkir sameday dari Config/AntarTarif.php.
 * Dipakai WA bot, endpoint Laundry/AntarTarif, dan helper AntarTarif.
 */
class AntarTarifHelper
{
    public const DEFAULT_MIN_TARIF = 5000;
    public const DEFAULT_RATE_PER_KM = 1000;
    public const DEFAULT_FREE_KM = 1.0;

    /** @return array{min_tarif:int,rate_per_km:int,free_km:float} */
    public static function loadConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/Config/AntarTarif.php';
        if (!is_file($path)) {
            return self::normalizeConfig([]);
        }
        $loaded = require $path;
        return self::normalizeConfig(is_array($loaded) ? $loaded : []);
    }

    /** @return array{min_tarif:int,rate_per_km:int,free_km:float} */
    public static function normalizeConfig(array $loaded): array
    {
        $cfg = [
            'min_tarif' => (int) ($loaded['min_tarif'] ?? self::DEFAULT_MIN_TARIF),
            'rate_per_km' => (int) ($loaded['rate_per_km'] ?? self::DEFAULT_RATE_PER_KM),
            'free_km' => (float) ($loaded['free_km'] ?? self::DEFAULT_FREE_KM),
        ];
        if ($cfg['min_tarif'] < 0) {
            $cfg['min_tarif'] = 0;
        }
        if ($cfg['rate_per_km'] < 0) {
            $cfg['rate_per_km'] = 0;
        }
        if ($cfg['free_km'] < 0) {
            $cfg['free_km'] = 0.0;
        }
        return $cfg;
    }

    public static function distanceKm($lat1, $lon1, $lat2, $lon2): float
    {
        $lat1 = (float) $lat1;
        $lon1 = (float) $lon1;
        $lat2 = (float) $lat2;
        $lon2 = (float) $lon2;

        if (!is_finite($lat1) || !is_finite($lon1) || !is_finite($lat2) || !is_finite($lon2)) {
            return 0.0;
        }

        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
        return $R * $c;
    }

    public static function tarifFromKm($km, ?array $cfg = null): int
    {
        $c = $cfg ?? self::loadConfig();
        $km = (float) $km;
        $freeKm = (float) ($c['free_km'] ?? 0);
        if ($freeKm > 0 && $km < $freeKm) {
            return 0;
        }

        return max($c['min_tarif'], (int) round($km * $c['rate_per_km']));
    }

    /**
     * @return array{km:float,tarif:int,min_tarif:int,rate_per_km:int,free_km:float}
     */
    public static function tarifFromCoords($latCabang, $lonCabang, $latLokasi, $lonLokasi): array
    {
        $cfg = self::loadConfig();
        $km = self::distanceKm($latCabang, $lonCabang, $latLokasi, $lonLokasi);
        return [
            'km' => round($km, 3),
            'tarif' => self::tarifFromKm($km, $cfg),
            'min_tarif' => $cfg['min_tarif'],
            'rate_per_km' => $cfg['rate_per_km'],
            'free_km' => $cfg['free_km'],
        ];
    }

    /**
     * Tarif sameday + grant gratis durasi -D (order member terbuka / saldo paket -D > 3).
     *
     * @return array{km:float,tarif:int,tarif_raw:int,grant_applied:bool,min_tarif:int,rate_per_km:int,free_km:float}
     */
    public static function tarifFromCoordsForPelanggan(
        $latCabang,
        $lonCabang,
        $latLokasi,
        $lonLokasi,
        int $idPelanggan
    ): array {
        $calc = self::tarifFromCoords($latCabang, $lonCabang, $latLokasi, $lonLokasi);
        $calc['tarif_raw'] = (int) $calc['tarif'];
        $calc['grant_applied'] = false;
        if ($idPelanggan > 0) {
            $tarif = DeliveryTarifGrant::apply($idPelanggan, (int) $calc['tarif']);
            $calc['grant_applied'] = ($tarif === 0 && $calc['tarif_raw'] > 0);
            $calc['tarif'] = $tarif;
        }

        return $calc;
    }
}
