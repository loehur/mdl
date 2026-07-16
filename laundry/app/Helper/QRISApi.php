<?php

/**
 * QRIS API Helper
 * Helper untuk memanggil API QRIS di api.nalju.com
 */
class QRISApi
{
    private $apiUrl = 'https://api.nalju.com/QRIS';
    
    /**
     * Generate QRIS payment
     * @param int $nominal Amount in Rupiah
     * @param string $ref_id Reference ID for the transaction
     * @param string $metode Payment method, default 'QRIS'
     * @return array Response from API
     */
    public function generate($nominal, $ref_id, $metode = 'QRIS')
    {
        $url = $this->apiUrl . '/generate';
        
        $data = [
            'nominal' => $nominal,
            'ref_id' => $ref_id,
            'metode' => $metode
        ];
        
        $response = $this->callApi($url, 'POST', $data);
        return $response;
    }
    
    /**
     * Check payment status
     * @param string $ref_id Reference ID or transaction ID
     * @param int $nominal Amount in Rupiah
     * @param string $metode Payment method, default 'QRIS'
     * @return array Response from API
     */
    public function checkStatus($ref_id, $nominal, $metode = 'QRIS')
    {
        $url = $this->apiUrl . '/status?ref_id=' . urlencode($ref_id) . '&nominal=' . $nominal . '&metode=' . urlencode($metode);
        
        $response = $this->callApi($url, 'GET');
        return $response;
    }
    
    /**
     * Withdraw balance
     * @param int $nominal Amount to withdraw (minimum 10,000)
     * @return array Response from API
     */
    public function withdraw($nominal)
    {
        $url = $this->apiUrl . '/withdraw';
        
        $data = [
            'nominal' => $nominal
        ];
        
        $response = $this->callApi($url, 'POST', $data);
        return $response;
    }
    
    /**
     * Call API endpoint
     * @param string $url Full URL
     * @param string $method HTTP method (GET, POST)
     * @param array $data Data to send (for POST)
     * @return array Decoded JSON response
     */
    private function callApi($url, $method = 'GET', $data = [])
    {
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_CUSTOMREQUEST] = 'POST';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
            $options[CURLOPT_HTTPHEADER] = [
                'Content-Type: application/json'
            ];
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = 'GET';
        }
        
        curl_setopt_array($curl, $options);

        $t0 = microtime(true);
        $response = curl_exec($curl);
        $elapsedMs = round((microtime(true) - $t0) * 1000, 1);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $totalTime = round((float) curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000, 1);
        $dnsMs = round((float) curl_getinfo($curl, CURLINFO_NAMELOOKUP_TIME) * 1000, 1);
        $connectMs = round((float) curl_getinfo($curl, CURLINFO_CONNECT_TIME) * 1000, 1);
        $ttfbMs = round((float) curl_getinfo($curl, CURLINFO_STARTTRANSFER_TIME) * 1000, 1);
        curl_close($curl);

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $refHint = '';
        if ($method === 'POST' && isset($data['ref_id'])) {
            $refHint = ' ref=' . $data['ref_id'];
        } elseif (preg_match('/[?&]ref_id=([^&]+)/', $url, $m)) {
            $refHint = ' ref=' . urldecode($m[1]);
        }

        $this->logLatency(
            "[QRISApi] {$method} {$path}{$refHint} | ok=" . ($error ? '0' : '1')
            . " http={$httpCode} elapsed_ms={$elapsedMs} curl_total_ms={$totalTime}"
            . " dns_ms={$dnsMs} connect_ms={$connectMs} ttfb_ms={$ttfbMs}"
            . ($error ? " error={$error}" : '')
        );
        
        if ($error) {
            return [
                'status' => false,
                'message' => 'Connection Error: ' . $error
            ];
        }
        
        $decoded = json_decode($response, true);
        
        // API sekarang sudah return format sederhana:
        // Generate: {status, trx_id, ref_id, qr_string}
        // Status: {status, trx_id, ref_id, payment_status}
        // Error: {status, message}
        
        // Return as-is, sudah dalam format yang benar
        return $decoded ?: ['status' => false, 'message' => 'Invalid response'];
    }

    private function logLatency($text)
    {
        try {
            if (!class_exists('Log')) {
                $logFile = __DIR__ . '/../Models/Log.php';
                if (is_file($logFile)) {
                    require_once $logFile;
                }
            }
            if (class_exists('Log')) {
                Log::write($text, 'laundry', 'qris_latency');
            } else {
                error_log($text);
            }
        } catch (Exception $e) {
            error_log($text);
        }
    }

}
