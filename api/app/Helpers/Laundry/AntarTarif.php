<?php

namespace App\Helpers\Laundry;

/**
 * Jarak cabang → lokasi pelanggan + tarif antar/jemput sameday.
 * Rumus dari Config/AntarTarif.php via AntarTarifHelper.
 */
class AntarTarif
{
    /** @deprecated pakai config min_tarif */
    public const MIN_TARIF = AntarTarifHelper::DEFAULT_MIN_TARIF;

    public static function distanceKm($lat1, $lon1, $lat2, $lon2): float
    {
        return AntarTarifHelper::distanceKm($lat1, $lon1, $lat2, $lon2);
    }

    public static function tarifFromKm($km): int
    {
        return AntarTarifHelper::tarifFromKm($km);
    }

    /**
     * @return array{km:float,tarif:int}
     */
    public static function tarifFromCoords($latCabang, $lonCabang, $latLokasi, $lonLokasi): array
    {
        $calc = AntarTarifHelper::tarifFromCoords($latCabang, $lonCabang, $latLokasi, $lonLokasi);
        return [
            'km' => $calc['km'],
            'tarif' => $calc['tarif'],
        ];
    }

    public static function formatRp(int $n): string
    {
        return 'Rp' . number_format($n, 0, ',', '.');
    }
}
