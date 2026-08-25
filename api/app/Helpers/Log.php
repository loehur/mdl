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
            // Path absolut (api/logs/...) — tidak bergantung CWD agar selalu tertulis.
            $logs_base = dirname(__DIR__, 2) . '/logs/';
            $assets_dir = $logs_base . date('Y-m-d') . '/';
            $data_to_write = date('H:i:s') . " " . $text . "\n";
            $file_path = $assets_dir . strtolower($app) . "_" . strtolower($controller) . ".log";

            if (!file_exists($assets_dir)) {
                // Directory tidak ada, buat baru
                if (!@mkdir($assets_dir, 0755, TRUE)) {
                    error_log("[MDL LOG FAIL] Cannot create dir: $assets_dir | Msg: $text");
                    return;
                }
            }

            // Hapus log yang sudah lebih dari 7 hari
            $limit_date = date('Y-m-d', strtotime('-7 days'));
            $oldDirs = glob($logs_base . '*', GLOB_ONLYDIR);
            if (is_array($oldDirs)) {
                foreach ($oldDirs as $old_dir) {
                    if (basename($old_dir) < $limit_date) {
                        $oldFiles = glob("$old_dir/*");
                        if (is_array($oldFiles)) {
                            foreach ($oldFiles as $old_file) {
                                @unlink($old_file);
                            }
                        }
                        @rmdir($old_dir);
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
