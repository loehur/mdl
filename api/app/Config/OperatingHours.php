<?php
/**
 * Operating Hours Configuration
 * 
 * Konfigurasi jam kerja untuk auto-reply WhatsApp
 * 
 * CARA SETUP:
 * 1. Buka file Config/Env.php
 * 2. Tambahkan konstanta OPERATING_HOURS seperti contoh di bawah
 * 3. Ubah nilai sesuai kebutuhan
 * 
 * Jika tidak ada di Env.php, akan menggunakan default values di bawah
 */

// Load Env.php to get constants
$envFile = __DIR__ . '/Env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

// Check if OPERATING_HOURS constant exists (defined in Env.php)
$envHours = [];
if (defined('OPERATING_HOURS')) {
    $envHours = OPERATING_HOURS;
} elseif (defined('Env::operating_hours')) {
    $envHours = Env::operating_hours;
}

// Define function BEFORE return (dipanggil di 'holidays' array)
if (!function_exists('expandHolidayRanges')) {
    function expandHolidayRanges(array $ranges): array
    {
        $dates = [];
        foreach ($ranges as $range) {
            $start = $range['start'] ?? $range[0] ?? null;
            $end = $range['end'] ?? $range[1] ?? null;
            if (!$start || !$end) continue;
            try {
                $current = new \DateTime($start);
                $endDate = new \DateTime($end);
                while ($current <= $endDate) {
                    $dates[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
            } catch (\Exception $e) {}
        }
        return $dates;
    }
}

// Default configuration (will be overridden by Env.php if set)
return [
    // Jam buka (24-hour format)
    'open_hour' => $envHours['open_hour'] ?? 7,
    'open_minute' => $envHours['open_minute'] ?? 0,
    
    // Jam tutup (24-hour format)
    'close_hour' => $envHours['close_hour'] ?? 21,
    'close_minute' => $envHours['close_minute'] ?? 0,
    
    // Hari kerja (1 = Monday, 7 = Sunday)
    'working_days' => $envHours['working_days'] ?? [1, 2, 3, 4, 5, 6, 7],
    
    // Timezone
    'timezone' => $envHours['timezone'] ?? 'Asia/Jakarta',
    
    // Hari libur (hanya dari holiday_ranges, di-expand untuk cek tanggal)
    'holidays' => array_values(array_unique(expandHolidayRanges($envHours['holiday_ranges'] ?? []))),
    // Rentang libur asli (untuk format display yang benar saat beda bulan)
    'holiday_ranges' => $envHours['holiday_ranges'] ?? [],
];