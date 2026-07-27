<?php
/**
 * Jam operasional untuk auto-reply WhatsApp.
 * Edit nilai di bawah sesuai kebutuhan (bukan via Env.php).
 */

if (!function_exists('expandHolidayRanges')) {
    function expandHolidayRanges(array $ranges): array
    {
        $dates = [];
        foreach ($ranges as $range) {
            $start = $range['start'] ?? $range[0] ?? null;
            $end = $range['end'] ?? $range[1] ?? null;
            if (!$start || !$end) {
                continue;
            }
            try {
                $current = new \DateTime($start);
                $endDate = new \DateTime($end);
                while ($current <= $endDate) {
                    $dates[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
            } catch (\Exception $e) {
            }
        }
        return $dates;
    }
}

$holiday_ranges = [
    ['start' => '2026-01-01', 'end' => '2026-01-01'], // Tahun Baru
];

return [
    // Jam buka / tutup (24-hour)
    'open_hour' => 7,
    'open_minute' => 0,
    'close_hour' => 21,
    'close_minute' => 0,

    // 1 = Senin … 7 = Minggu
    'working_days' => [1, 2, 3, 4, 5, 6, 7],

    'timezone' => 'Asia/Jakarta',

    // Expanded dates (cek tanggal) + rentang asli (display lintas bulan)
    'holidays' => array_values(array_unique(expandHolidayRanges($holiday_ranges))),
    'holiday_ranges' => $holiday_ranges,
];
