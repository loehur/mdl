<?php

namespace App\Helpers\Laundry;

/**
 * Jam operasional dari Config/OperatingHours.php
 * Dipakai WA auto-reply & Kurir Instant cutoff.
 */
class OperatingHoursHelper
{
    /** Instant ditutup N menit sebelum jam tutup operasional */
    public const INSTANT_CUTOFF_BEFORE_CLOSE_MINUTES = 30;

    public static function loadConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/Config/OperatingHours.php';
        if (!is_file($path)) {
            return [];
        }
        $cfg = require $path;
        return is_array($cfg) ? $cfg : [];
    }

    /**
     * Status Order Kurir Instant (cutoff 30 menit sebelum tutup).
     *
     * @return array{
     *   ok:bool,
     *   reason:string,
     *   message:string,
     *   open_label:string,
     *   close_label:string,
     *   cutoff_label:string,
     *   timezone:string,
     *   now:string,
     *   open_hour?:int,
     *   open_minute?:int,
     *   close_hour?:int,
     *   close_minute?:int
     * }
     */
    public static function instantOrderStatus(): array
    {
        $cfg = self::loadConfig();
        $tzName = (string) ($cfg['timezone'] ?? 'Asia/Jakarta');
        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('Asia/Jakarta');
            $tzName = 'Asia/Jakarta';
        }

        $openH = (int) ($cfg['open_hour'] ?? 7);
        $openM = (int) ($cfg['open_minute'] ?? 0);
        $closeH = (int) ($cfg['close_hour'] ?? 21);
        $closeM = (int) ($cfg['close_minute'] ?? 0);
        $openLabel = self::fmtHm($openH, $openM);
        $closeLabel = self::fmtHm($closeH, $closeM);

        $openMin = ($openH * 60) + $openM;
        $closeMin = ($closeH * 60) + $closeM;
        $cutoffMin = $closeMin - self::INSTANT_CUTOFF_BEFORE_CLOSE_MINUTES;
        if ($cutoffMin <= $openMin) {
            $cutoffMin = $openMin;
        }
        $cutoffLabel = self::fmtHm(intdiv($cutoffMin, 60), $cutoffMin % 60);

        $base = [
            'ok' => false,
            'reason' => '',
            'message' => '',
            'open_label' => $openLabel,
            'close_label' => $closeLabel,
            'cutoff_label' => $cutoffLabel,
            'timezone' => $tzName,
            'now' => '',
            'open_hour' => $openH,
            'open_minute' => $openM,
            'close_hour' => $closeH,
            'close_minute' => $closeM,
        ];

        if (empty($cfg)) {
            $base['reason'] = 'config_error';
            $base['message'] = 'Jam operasional belum dikonfigurasi. Kurir Instant sementara tidak tersedia.';
            return $base;
        }

        $now = new \DateTime('now', $tz);
        $base['now'] = $now->format('Y-m-d H:i:s');
        $currentDate = $now->format('Y-m-d');
        $dayOfWeek = (int) $now->format('N');
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
            $base['message'] = "Kurir Instant belum dibuka. Tersedia jam {$openLabel}–{$cutoffLabel}";
            return $base;
        }

        if ($currentMin >= $closeMin) {
            $base['reason'] = 'after_close';
            $base['message'] = "Kurir Instant sudah tutup. Tersedia jam {$openLabel}–{$cutoffLabel}";
            return $base;
        }

        if ($currentMin >= $cutoffMin) {
            $base['reason'] = 'near_close';
            $base['message'] = "Kurir Instant sudah tutup. Tersedia jam {$openLabel}–{$cutoffLabel}";
            return $base;
        }

        $base['ok'] = true;
        $base['reason'] = 'open';
        $base['message'] = "Kurir Instant tersedia hingga jam {$cutoffLabel}.";
        return $base;
    }

    private static function fmtHm(int $h, int $m): string
    {
        return str_pad((string) $h, 2, '0', STR_PAD_LEFT) . '.' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}
