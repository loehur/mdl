<?php
class Tokopay
{
    public $merchantId = 'M240926BMTGB612';
    public $secretKey = '4aea0ede516df65d88ccb773a443c61b3b3702fe1b9647deb9293cac07fd72bf';
    public $apiUrl = "https://api.tokopay.id";

    public function createOrder($nominal, $ref_id, $kodeChannel)
    {
        $mid = $this->merchantId;
        $secret = $this->secretKey;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiUrl . "/v1/order?merchant=" . $mid . "&secret=" . $secret . "&ref_id=" . $ref_id . "&nominal=" . $nominal . "&metode=" . $kodeChannel,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

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
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
