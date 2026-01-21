<?php

namespace App\Models;

/**
 * Flip Payment Gateway Model
 * Used for getting bank list
 */
class Flip
{
    private $secretKey;
    private $apiUrl;

    public function __construct()
    {
        $this->secretKey = \Env::FLIP_SECRET_KEY;
        $this->apiUrl = \Env::FLIP_API_URL;
    }

    /**
     * Get Basic Auth Header
     */
    private function getAuthHeader()
    {
        return 'Basic ' . base64_encode($this->secretKey . ':');
    }

    /**
     * Get list of available banks
     * @return array
     */
    public function getBanks()
    {
        $url = $this->apiUrl . '/general/banks';
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json; charset=UTF-8',
                'Authorization: ' . $this->getAuthHeader()
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return [
                'status' => false,
                'message' => 'Connection Error: ' . $error,
                'data' => null
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        return [
            'status' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'message' => $decodedResponse['message'] ?? ($httpCode >= 200 && $httpCode < 300 ? 'Success' : 'Failed'),
            'data' => $decodedResponse
        ];
    }
}
