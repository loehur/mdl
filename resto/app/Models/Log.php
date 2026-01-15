<?php

class Log
{
    public static function write($text = "")
    {
        try {
            // Dapatkan caller info dari debug_backtrace
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = isset($trace[1]) ? $trace[1] : $trace[0];
            
            // Ambil nama controller dari class yang memanggil
            $controller = isset($caller['class']) ? strtolower($caller['class']) : 'general';
            $function = isset($caller['function']) ? $caller['function'] : '';
            
            $assets_dir = "logs/" . date('Y-m-d') . "/";
            $data_to_write = date('H:i:s') . " [$function] " . $text . "\n";
            $file_path = $assets_dir . $controller . ".log";

            if (!file_exists($assets_dir)) {
                // Directory tidak ada, buat baru
                if (!@mkdir($assets_dir, 0755, TRUE)) {
                    error_log("[MDL LOG FAIL] Cannot create dir: $assets_dir | Msg: $text");
                    return;
                }
            }

            // Hapus log yang sudah lebih dari 3 hari
            $limit_date = date('Y-m-d', strtotime('-7 days'));
            foreach (glob("logs/*", GLOB_ONLYDIR) as $old_dir) {
                if (basename($old_dir) < $limit_date) {
                    foreach (glob("$old_dir/*") as $old_file) {
                        @unlink($old_file);
                    }
                    @rmdir($old_dir);
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
