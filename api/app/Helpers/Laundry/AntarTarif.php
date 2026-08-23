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

    public const SURCAS_JENIS_GABUNGAN = 1;
    public const SURCAS_JENIS_PENGANTARAN = 2;
    public const SURCAS_JENIS_PENJEMPUTAN = 3;

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

    /**
     * @return array{km:float,tarif:int,tarif_raw:int,grant_applied:bool}
     */
    public static function tarifFromCoordsForPelanggan(
        $latCabang,
        $lonCabang,
        $latLokasi,
        $lonLokasi,
        int $idPelanggan
    ): array {
        return AntarTarifHelper::tarifFromCoordsForPelanggan(
            $latCabang,
            $lonCabang,
            $latLokasi,
            $lonLokasi,
            $idPelanggan
        );
    }

    public static function formatRp(int $n): string
    {
        if ($n <= 0) {
            return 'gratis';
        }

        return 'Rp' . number_format($n, 0, ',', '.');
    }
}
