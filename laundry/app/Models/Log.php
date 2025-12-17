<?php

class Log
{
    function write($text)
    {
        $uploads_dir = "logs/local/" . date('Y/') . date('m/');
        $file_name = date('d');
        $data_to_write = date('H:i') . " " . $text . "\n";
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

    /**
     * Log khusus untuk response API
     * @param string $endpoint Endpoint API yang dipanggil
     * @param mixed $request Data request yang dikirim (array atau string)
     * @param mixed $response Data response dari API (array atau string)
     * @param string $status Status response (success/error)
     */
    function apiLog($endpoint, $request = null, $response = null, $status = 'info')
    {
        $uploads_dir = "logs/api/" . date('Y/') . date('m/');
        $file_name = date('d') . ".log";
        $file_path = $uploads_dir . $file_name;

        // Format log entry
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => strtoupper($status),
            'endpoint' => $endpoint,
            'request' => is_array($request) ? json_encode($request) : $request,
            'response' => is_array($response) ? json_encode($response) : $response
        ];

        $data_to_write = "[" . $log_entry['timestamp'] . "] ";
        $data_to_write .= "[" . $log_entry['status'] . "] ";
        $data_to_write .= $log_entry['endpoint'] . "\n";
        
        if ($request !== null) {
            $data_to_write .= "  REQ: " . $log_entry['request'] . "\n";
        }
        if ($response !== null) {
            $data_to_write .= "  RES: " . $log_entry['response'] . "\n";
        }
        $data_to_write .= "---\n";

        if (!file_exists($uploads_dir)) {
            mkdir($uploads_dir, 0777, TRUE);
        }

        $file_handle = fopen($file_path, 'a');
        fwrite($file_handle, $data_to_write);
        fclose($file_handle);
    }
}
