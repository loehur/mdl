<?php

class Log
{
    function write($text)
    {
        $uploads_dir = "logs/local/" . date('Y/') . date('m/');
        $file_name = date('d');
        $data_to_write = date('Y-m-d H:i:s') . " " . $text . "\n";
        $file_path = $uploads_dir . $file_name;

        if (!file_exists($uploads_dir)) {
            mkdir($uploads_dir, 0777, TRUE);
            $file_handle = fopen($file_path, 'w');
        } else {
            $file_handle = fopen($file_path, 'a');
        }

        fwrite($file_handle, $data_to_write);
        fclose($file_handle);
    }
}
