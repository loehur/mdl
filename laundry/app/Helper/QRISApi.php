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
     * Get merchant balance
     * @return array Response from API
     */
    public function getBalance()
    {
        $url = $this->apiUrl . '/balance';
        
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
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($error) {
            return [
                'status' => false,
                'message' => 'Connection Error: ' . $error,
                'error_msg' => $error
            ];
        }
        
        $decoded = json_decode($response, true);
        
        // Handle API response format
        if (isset($decoded['status']) && $decoded['status'] === true && isset($decoded['data'])) {
            // API returns {status: true, message: "...", data: {...}}
            // Convert to format compatible with old TokoPay response
            
            // 1. Check for generate endpoint (has qr_string)
            if (isset($decoded['data']['qr_string'])) {
                // For generate endpoint - return in TokoPay format
                // JANGAN tambahkan 'status' => 'success' di data karena akan dianggap paid
                return [
                    'status' => true,
                    'data' => [
                        'qr_string' => $decoded['data']['qr_string']
                    ],
                    'qr_string' => $decoded['data']['qr_string']
                ];
            } 
            // 2. Check for status endpoint (has ref_id, nominal, status, atau status_detail)
            elseif (isset($decoded['data']['ref_id']) || isset($decoded['data']['nominal']) || isset($decoded['data']['status']) || isset($decoded['data']['status_detail'])) {
                // For status endpoint - API mengembalikan status di data.status
                // Pastikan selalu ada status, default 'pending' jika tidak ada
                $status_from_data = 'pending';
                $status_detail = 'pending';
                
                // Prioritaskan status_detail jika ada
                if (isset($decoded['data']['status_detail']) && !empty($decoded['data']['status_detail']) && $decoded['data']['status_detail'] !== 'unknown') {
                    $status_detail = strtolower($decoded['data']['status_detail']);
                    $status_from_data = $status_detail;
                }
                // Jika tidak ada status_detail, gunakan status
                elseif (isset($decoded['data']['status']) && !empty($decoded['data']['status']) && $decoded['data']['status'] !== 'unknown') {
                    $status_from_data = strtolower($decoded['data']['status']);
                    $status_detail = $status_from_data;
                }
                
                // Jika status kosong atau tidak jelas, default ke 'pending'
                if (empty($status_from_data) || $status_from_data === 'unknown' || $status_from_data === '') {
                    $status_from_data = 'pending';
                    $status_detail = 'pending';
                }
                
                // PASTIKAN selalu return dengan status yang jelas
                return [
                    'status' => true,
                    'data' => [
                        'status' => $status_from_data,
                        'status_pembayaran' => $status_from_data,
                        'status_detail' => $status_detail
                    ],
                    'status' => $status_from_data,
                    'status_detail' => $status_detail
                ];
            } 
            // 3. Check for status_detail only
            elseif (isset($decoded['data']['status_detail'])) {
                // Fallback jika hanya ada status_detail
                $status_detail = strtolower($decoded['data']['status_detail']);
                if (empty($status_detail) || $status_detail === 'unknown') {
                    $status_detail = 'pending';
                }
                return [
                    'status' => true,
                    'data' => [
                        'status' => $status_detail,
                        'status_pembayaran' => $status_detail
                    ],
                    'status' => $status_detail,
                    'status_detail' => $status_detail
                ];
            } 
            // 4. For other endpoints (balance, withdraw)
            else {
                // For other endpoints (balance, withdraw), return data directly wrapped in TokoPay format
                return [
                    'status' => true,
                    'data' => $decoded['data']
                ];
            }
        } elseif (isset($decoded['status']) && $decoded['status'] === false) {
            // API error response
            return [
                'status' => false,
                'message' => $decoded['message'] ?? 'API Error',
                'data' => $decoded['data'] ?? null
            ];
        }
        
        // Fallback: return decoded response as-is
        return $decoded ?: ['status' => false, 'message' => 'Invalid response'];
    }
}
