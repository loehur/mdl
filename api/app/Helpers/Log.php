<?php

class Log
{
    /**
     * Menulis log ke file
     *
     * @param string $text Teks yang akan ditulis ke log
     * @param string $app Nama app (admin, webhook, laundry, dll)
     */
    public static function write($text = "", $app = 'undefined', $controller = "undefined")
    {
        try {
            $assets_dir = "logs/". date('d') . "/";
            $data_to_write = date('H:i:s') . " " . $text . "\n";
            $file_path = $assets_dir . strtolower($app) . "_" . strtolower($controller) . ".log";

            if (!file_exists($assets_dir)) {
                // Directory tidak ada, buat baru
                if (!@mkdir($assets_dir, 0755, TRUE)) {
                    error_log("[MDL LOG FAIL] Cannot create dir: $assets_dir | Msg: $text");
                    return;
                }
            } else {
                // Directory ada, hapus semua file log lama di directory ini
                $logFiles = glob($assets_dir . "*.log");
                if ($logFiles !== false) {
                    foreach ($logFiles as $logFile) {
                        @unlink($logFile);
                    }
                }
            }

            // Write log to file
            if (@file_put_contents($file_path, $data_to_write, FILE_APPEND | LOCK_EX) === false) {
                error_log("[MDL LOG FAIL] Cannot write to: $file_path | Msg: $text");
            }
            
        } catch (Exception $e) {
            // Fallback terakhir agar aplikasi TIDAK CRASH
            error_log("[MDL LOG EXCEPTION] " . $e->getMessage() . " | Msg: $text");
        }
    }
}
