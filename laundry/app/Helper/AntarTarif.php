<?php

/**
 * Jarak cabang → lokasi pelanggan + tarif antar.
 * Config rumus via GET https://api.nalju.com/Laundry/AntarTarif/config
 * (sumber tunggal di api Config/AntarTarif.php — jangan baca file langsung).
 */
class AntarTarif
{
   /** Fallback bila API tidak terbaca */
   const MIN_TARIF = 5000;
   const RATE_PER_KM = 1000;
   const FREE_KM = 1.0;

   /** Legacy: gabungan jemput+antar — dibagi 2 saat penyelesaian delivery */
   const SURCAS_JENIS_GABUNGAN = 1;
   const SURCAS_JENIS_PENGANTARAN = 2;
   const SURCAS_JENIS_PENJEMPUTAN = 3;

   private $apiUrl = 'https://api.nalju.com/Laundry/AntarTarif';

   /**
    * @return array{min_tarif:int,rate_per_km:int,free_km:float}
    */
   private function config()
   {
      static $cfg = null;
      if ($cfg !== null) {
         return $cfg;
      }

      $res = $this->callApi($this->apiUrl . '/config', 'GET');
      if (!empty($res['ok'])) {
         $cfg = [
            'min_tarif' => (int) ($res['min_tarif'] ?? self::MIN_TARIF),
            'rate_per_km' => (int) ($res['rate_per_km'] ?? self::RATE_PER_KM),
            'free_km' => isset($res['free_km']) ? (float) $res['free_km'] : self::FREE_KM,
         ];
      } else {
         $cfg = [
            'min_tarif' => self::MIN_TARIF,
            'rate_per_km' => self::RATE_PER_KM,
            'free_km' => self::FREE_KM,
         ];
      }

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
    * Tarif antar dari jarak km.
    * km < free_km → 0; selain itu max(min_tarif, round(km × rate_per_km)).
    */
   public function tarifFromKm($km)
   {
      $c = $this->config();
      $km = (float) $km;
      $freeKm = (float) ($c['free_km'] ?? 0);
      if ($freeKm > 0 && $km < $freeKm) {
         return 0;
      }

      return max($c['min_tarif'], (int) round($km * $c['rate_per_km']));
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

   /**
    * Tarif sameday + grant gratis durasi -D (order member / saldo paket -D).
    * @return array{km:float,tarif:int,tarif_raw:int,grant_applied:bool}
    */
   public function tarifFromCoordsForPelanggan($latCabang, $lonCabang, $latLokasi, $lonLokasi, $idPelanggan)
   {
      $calc = $this->tarifFromCoords($latCabang, $lonCabang, $latLokasi, $lonLokasi);
      $calc['tarif_raw'] = (int) ($calc['tarif'] ?? 0);
      $calc['grant_applied'] = false;
      $idPelanggan = (int) $idPelanggan;
      if ($idPelanggan > 0) {
         require_once 'app/Helper/DeliveryTarifGrant.php';
         $tarif = DeliveryTarifGrant::apply($idPelanggan, (int) $calc['tarif']);
         $calc['grant_applied'] = ($tarif === 0 && $calc['tarif_raw'] > 0);
         $calc['tarif'] = $tarif;
      }

      return $calc;
   }

   private function callApi($url, $method = 'GET')
   {
      $curl = curl_init();
      $headers = ['Accept: application/json'];

      $options = [
         CURLOPT_URL => $url,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_ENCODING => '',
         CURLOPT_MAXREDIRS => 5,
         CURLOPT_TIMEOUT => 15,
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
         CURLOPT_CUSTOMREQUEST => strtoupper($method),
         CURLOPT_HTTPHEADER => $headers,
         CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
         CURLOPT_SSL_VERIFYPEER => false,
         CURLOPT_SSL_VERIFYHOST => false,
      ];
      curl_setopt_array($curl, $options);
      $response = curl_exec($curl);
      $error = curl_error($curl);
      $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
      curl_close($curl);

      if ($error) {
         return ['ok' => false, 'message' => 'Connection Error: ' . $error];
      }
      $decoded = json_decode((string) $response, true);
      if (!is_array($decoded)) {
         return [
            'ok' => false,
            'message' => 'Invalid JSON dari API tarif antar (HTTP ' . $httpCode . ')',
            'raw' => $response,
         ];
      }
      return $decoded;
   }
}
