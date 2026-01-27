<?php

namespace App\Models;

/**
 * Tokopay Payment Gateway Model
 * Used for QRIS payments
 */
class Tokopay
{
    private $merchantId = 'M240926BMTGB612';
    private $secretKey = '4aea0ede516df65d88ccb773a443c61b3b3702fe1b9647deb9293cac07fd72bf';
    private $apiUrl = "https://api.tokopay.id";

    /**
     * Create a payment order
     */
    public function createOrder($nominal, $ref_id, $kodeChannel = 'QRIS')
    {
        $mid = $this->merchantId;
        $secret = $this->secretKey;
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiUrl . "/v1/order?merchant=" . $mid . "&secret=" . $secret . "&ref_id=" . $ref_id . "&nominal=" . $nominal . "&metode=" . $kodeChannel,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return json_encode(['status' => false, 'message' => 'Connection Error: ' . $error]);
        }
        
        return $response;
    }

    /**
     * Check payment status
     */
    public function checkStatus($ref_id, $nominal, $kodeChannel = 'QRIS')
    {
        $mid = $this->merchantId;
        $secret = $this->secretKey;
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiUrl . "/v1/order?merchant=" . $mid . "&secret=" . $secret . "&ref_id=" . $ref_id . "&nominal=" . $nominal . "&metode=" . $kodeChannel,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return json_encode(['status' => false, 'message' => 'Connection Error: ' . $error, 'error_msg' => $error]);
        }
        
        return $response;
    }

    /**
     * Get merchant balance
     */
    public function getMerchantBalance()
    {
        $mid = $this->merchantId;
        $secret = $this->secretKey;
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiUrl . "/v1/merchant/balance?merchant=" . $mid . "&signature=" . md5("$mid:$secret"),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return $response;
    }

    /**
     * Tarik saldo (withdraw balance)
     */
    public function tarikSaldo($nominal)
    {
        $mid = $this->merchantId;
        $secret = $this->secretKey;
        $signature = md5("$mid:$secret:$nominal");
        
        $data = [
            'merchant_id' => $mid,
            'nominal' => $nominal,
            'signature' => $signature
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiUrl . '/v1/tarik-saldo',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return json_encode(['status' => false, 'message' => 'Connection Error: ' . $error, 'error_msg' => $error]);
        }
        
        return $response;
    }
}
