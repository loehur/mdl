<?php

class WA_Local extends Controller
{
    public function send($target, $message, $token = "")
    {
        // Ambil session ID dari config jika tidak disediakan
        if (empty($token) && defined('URL::WA_TOKEN')) {
            $token = URL::WA_TOKEN[0] ?? "";
        }
        // Fallback jika URL class bukan static atau define
        if (empty($token) && class_exists('Config\URL')) {
            $token = \Config\URL::WA_TOKEN[0] ?? "";
        }

        $target = $this->valid_number($target);
        if ($target == false) {
            $res = [
                'code' => 0,
                'status' => false,
                'forward' => false,
                'error' => 'Invalid Whatsapp Number',
                'data' => [
                    'status' => 'invalid_number'
                ],
            ];
            return $res;
        }

        $curl = curl_init();

        $payload = json_encode([
            'sessionId' => (string)$token,
            'number' => $target,
            'message' => $message
        ]);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://127.0.0.1:8033/send-message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $rescode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }
        curl_close($curl);

        //DEFAULT
        $res = [
            'code' => $rescode,
            'status' => false,
            'forward' => true,
            'error' => 'DEFAULT',
            'data' => [
                'status' => ''
            ],
        ];

        if ($rescode <> 200) {
            $res = [
                'code' => $rescode,
                'status' => false,
                'forward' => true,
                'error' => 'SERVER DOWN',
                'data' => [
                    'status' => ''
                ]
            ];
            return $res;
        }

        if (isset($error_msg)) {
            $res = [
                'code' => $rescode,
                'status' => false,
                'forward' => true,
                'error' => $error_msg,
                'data' => [
                    'status' => ''
                ],
            ];
        } else {
            $response = json_decode($response, true);
            if (isset($response["status"]) && $response["status"]) {
                $status = $response['message'] ?? 'sent';
                $id = $response['response']['key']['id'] ?? 'unknown';

                $res = [
                    'code' => $rescode,
                    'status' => true,
                    'forward' => false,
                    'error' => 0,
                    'data' => [
                        'id' => $id,
                        'status' => $status
                    ],
                ];
            } else {
                $res = [
                    'code' => $rescode,
                    'status' => false,
                    'forward' => true,
                    'error' => json_encode($response),
                    'data' => [
                        'status' => ''
                    ],
                ];
            }
        }

        return $res;
    }

    function cek_status($token = "")
    {
        // Ambil session ID dari config jika tidak disediakan
        if (empty($token) && defined('URL::WA_TOKEN')) {
            $token = URL::WA_TOKEN[0] ?? "";
        }
        if (empty($token) && class_exists('Config\URL')) {
            $token = \Config\URL::WA_TOKEN[0] ?? "";
        }

        $curl = curl_init();

        $payload = json_encode(['sessionId' => (string)$token]);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://127.0.0.1:8033/cek-status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $res = json_decode($response, true);
        return $res;
    }
}
