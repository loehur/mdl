<?php

/**
 * Jarak cabang → lokasi pelanggan + tarif antar.
 * Tarif = max(5000, round(km × 1000)).
 */
class AntarTarif
{
   const MIN_TARIF = 5000;
   const SURCAS_JENIS_PENGANTARAN = 2;
   const SURCAS_JENIS_PENJEMPUTAN = 3;

   /**
    * Haversine distance in kilometers.
    */
   public function distanceKm($lat1, $lon1, $lat2, $lon2)
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

   /**
    * Tarif antar dari jarak km (minimal 5000).
    */
   public function tarifFromKm($km)
   {
      return max(self::MIN_TARIF, (int) round((float) $km * 1000));
   }

   /**
    * Tarif dari dua koordinat.
    */
   public function tarifFromCoords($latCabang, $lonCabang, $latLokasi, $lonLokasi)
   {
      $km = $this->distanceKm($latCabang, $lonCabang, $latLokasi, $lonLokasi);
      return [
         'km' => round($km, 3),
         'tarif' => $this->tarifFromKm($km),
      ];
   }
}
