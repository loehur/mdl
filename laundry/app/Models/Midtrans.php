<?php
class Midtrans
{
    // Mode: true for Production, false for Sandbox
    private $isProduction = false;

    // TODO: USER MUST UPDATE THESE KEYS
    private $serverKey = 'SB-Mid-server-S8kTHrD71rT7TQFxLxMfEtsX';
    private $clientKey = 'SB-Mid-client-P7dk_OekQqrjN3w9'; // Not strictly used for Core API backend-only but good to have

    private function getBaseUrl()
    {
        return $this->isProduction
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    public function createTransaction($orderId, $grossAmount)
    {
        $url = $this->getBaseUrl() . '/v2/charge';

        $transaction_details = [
            'order_id' => $orderId,
            'gross_amount' => (int) $grossAmount,
        ];

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => $transaction_details,
            'qris' => [
                'acquirer' => 'gopay' // Optional, helps sometimes
            ]
        ];

        $payloadJson = json_encode($payload);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->serverKey . ':')
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return json_encode(['status_code' => '500', 'status_message' => 'CURL Error: ' . $err]);
        }

        return $response;
    }

    public function checkStatus($orderId)
    {
        $url = $this->getBaseUrl() . '/v2/' . $orderId . '/status';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->serverKey . ':')
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return json_encode(['status_code' => '500', 'status_message' => 'CURL Error: ' . $err]);
        }

        return $response;
    }
}
