<?php

/**
 * Jam operasional via API (sumber tunggal di api Config/OperatingHours.php).
 * GET https://api.nalju.com/Laundry/OperatingHours/instant
 */
class OperatingHours
{
    private $apiUrl = 'https://api.nalju.com/Laundry/OperatingHours';

    /**
     * Status apakah Order Kurir Instant boleh dibuat sekarang.
     *
     * @return array{
     *   ok:bool,
     *   reason:string,
     *   message:string,
     *   open_label:string,
     *   close_label:string,
     *   cutoff_label:string,
     *   timezone?:string,
     *   now?:string
     * }
     */
    public function instantOrderStatus(): array
    {
        $res = $this->callApi($this->apiUrl . '/instant', 'GET');

        if (!empty($res['instantWindow']) && is_array($res['instantWindow'])) {
            $w = $res['instantWindow'];
            return [
                'ok' => !empty($w['ok']),
                'reason' => (string) ($w['reason'] ?? ''),
                'message' => (string) ($w['message'] ?? ''),
                'open_label' => (string) ($w['open_label'] ?? '07.00'),
                'close_label' => (string) ($w['close_label'] ?? '21.00'),
                'cutoff_label' => (string) ($w['cutoff_label'] ?? '20.30'),
                'timezone' => (string) ($w['timezone'] ?? 'Asia/Jakarta'),
                'now' => (string) ($w['now'] ?? ''),
            ];
        }

        $msg = (string) ($res['message'] ?? 'Jam operasional sementara tidak bisa dicek. Coba lagi.');
        return [
            'ok' => false,
            'reason' => 'api_error',
            'message' => $msg,
            'open_label' => '07.00',
            'close_label' => '21.00',
            'cutoff_label' => '20.30',
            'timezone' => 'Asia/Jakarta',
            'now' => '',
        ];
    }

    private function callApi($url, $method = 'GET')
    {
        $curl = curl_init();
        $headers = ['Accept: application/json'];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error) {
            return ['ok' => false, 'message' => 'Connection Error: ' . $error];
        }
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Invalid JSON dari API jam operasional (HTTP ' . $httpCode . ')',
                'raw' => $response,
            ];
        }
        return $decoded;
    }
}
