<?php

/**
 * Jam operasional dari api/app/Config/OperatingHours.php
 * Dipakai validasi Order Kurir Instant (+ cutoff sebelum tutup).
 */
class OperatingHours
{
   /** Instant ditutup N menit sebelum jam tutup operasional */
   const INSTANT_CUTOFF_BEFORE_CLOSE_MINUTES = 30;

   public function configPath(): string
   {
      // laundry/app/Helper → mdl/api/app/Config/OperatingHours.php
      return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'api'
         . DIRECTORY_SEPARATOR . 'app'
         . DIRECTORY_SEPARATOR . 'Config'
         . DIRECTORY_SEPARATOR . 'OperatingHours.php';
   }

   public function loadConfig(): array
   {
      $path = $this->configPath();
      if (!is_file($path)) {
         return [];
      }
      $cfg = require $path;
      return is_array($cfg) ? $cfg : [];
   }

   /**
    * Status apakah Order Kurir Instant boleh dibuat sekarang.
    *
    * @return array{
    *   ok:bool,
    *   reason:string,
    *   message:string,
    *   open_label:string,
    *   close_label:string,
    *   cutoff_label:string,
    *   timezone:string,
    *   now:string
    * }
    */
   public function instantOrderStatus(): array
   {
      $cfg = $this->loadConfig();
      $tzName = (string) ($cfg['timezone'] ?? 'Asia/Jakarta');
      try {
         $tz = new DateTimeZone($tzName);
      } catch (Exception $e) {
         $tz = new DateTimeZone('Asia/Jakarta');
         $tzName = 'Asia/Jakarta';
      }

      $openH = (int) ($cfg['open_hour'] ?? 7);
      $openM = (int) ($cfg['open_minute'] ?? 0);
      $closeH = (int) ($cfg['close_hour'] ?? 21);
      $closeM = (int) ($cfg['close_minute'] ?? 0);
      $openLabel = $this->fmtHm($openH, $openM);
      $closeLabel = $this->fmtHm($closeH, $closeM);

      $openMin = ($openH * 60) + $openM;
      $closeMin = ($closeH * 60) + $closeM;
      $cutoffMin = $closeMin - self::INSTANT_CUTOFF_BEFORE_CLOSE_MINUTES;
      if ($cutoffMin <= $openMin) {
         $cutoffMin = $openMin;
      }
      $cutoffLabel = $this->fmtHm(intdiv($cutoffMin, 60), $cutoffMin % 60);

      $base = [
         'ok' => false,
         'reason' => '',
         'message' => '',
         'open_label' => $openLabel,
         'close_label' => $closeLabel,
         'cutoff_label' => $cutoffLabel,
         'timezone' => $tzName,
         'now' => '',
      ];

      if (empty($cfg)) {
         $base['reason'] = 'config_error';
         $base['message'] = 'Jam operasional belum dikonfigurasi. Kurir Instant sementara tidak tersedia.';
         return $base;
      }

      $now = new DateTime('now', $tz);
      $base['now'] = $now->format('Y-m-d H:i:s');
      $currentDate = $now->format('Y-m-d');
      $dayOfWeek = (int) $now->format('N'); // 1=Sen … 7=Min
      $currentMin = ((int) $now->format('G') * 60) + (int) $now->format('i');

      $holidays = is_array($cfg['holidays'] ?? null) ? $cfg['holidays'] : [];
      if (in_array($currentDate, $holidays, true)) {
         $base['reason'] = 'holiday';
         $base['message'] = 'Kurir Instant tidak tersedia hari ini (libur operasional).';
         return $base;
      }

      $workingDays = is_array($cfg['working_days'] ?? null) ? $cfg['working_days'] : [1, 2, 3, 4, 5, 6, 7];
      $workingDays = array_map('intval', $workingDays);
      if (!in_array($dayOfWeek, $workingDays, true)) {
         $base['reason'] = 'closed_day';
         $base['message'] = 'Kurir Instant tidak tersedia di luar hari operasional.';
         return $base;
      }

      if ($currentMin < $openMin) {
         $base['reason'] = 'before_open';
         $base['message'] = "Kurir Instant belum dibuka. Tersedia jam {$openLabel}–{$cutoffLabel} (paling lambat 30 menit sebelum tutup {$closeLabel}).";
         return $base;
      }

      if ($currentMin >= $closeMin) {
         $base['reason'] = 'after_close';
         $base['message'] = "Kurir Instant sudah tutup. Tersedia jam {$openLabel}–{$cutoffLabel} (paling lambat 30 menit sebelum tutup {$closeLabel}).";
         return $base;
      }

      if ($currentMin >= $cutoffMin) {
         $base['reason'] = 'near_close';
         $base['message'] = "Kurir Instant sudah ditutup (paling lambat 30 menit sebelum tutup operasional jam {$closeLabel}). Coba lagi besok mulai jam {$openLabel}.";
         return $base;
      }

      $base['ok'] = true;
      $base['reason'] = 'open';
      $base['message'] = "Kurir Instant tersedia hingga jam {$cutoffLabel}.";
      return $base;
   }

   private function fmtHm(int $h, int $m): string
   {
      return str_pad((string) $h, 2, '0', STR_PAD_LEFT) . '.' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
   }
}
