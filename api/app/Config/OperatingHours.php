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
$envHours = defined('OPERATING_HOURS') ? OPERATING_HOURS : [];

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
    
    // Hari libur khusus (format: 'Y-m-d')
    'holidays' => $envHours['holidays'] ?? [],
];

/*
=============================================================================
TAMBAHKAN KONFIGURASI INI DI Config/Env.php:
=============================================================================

const OPERATING_HOURS = [
    'open_hour' => 7,      // Buka jam 07:00
    'open_minute' => 0,
    'close_hour' => 21,    // Tutup jam 21:00
    'close_minute' => 0,
    'working_days' => [1, 2, 3, 4, 5, 6, 7], // Senin - Minggu
    'timezone' => 'Asia/Jakarta',
    'holidays' => [
        // '2025-01-01', // Tahun Baru
        // '2025-12-25', // Natal
    ],
];

=============================================================================
*/
