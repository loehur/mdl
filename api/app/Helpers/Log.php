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
        $assets_dir = "logs/" . strtolower($app) . "/" . strtolower($controller) . "/" . date('Y/') . date('m/');
        $file_name = date('d') . ".log";
        $data_to_write = date('H:i') . " " . $text . "\n";
        $file_path = $assets_dir . $file_name;

        if (!file_exists($assets_dir)) {
            mkdir($assets_dir, 0777, TRUE);
        }

        $file_handle = fopen($file_path, 'a');
        fwrite($file_handle, $data_to_write);
        fclose($file_handle);
    }
}
