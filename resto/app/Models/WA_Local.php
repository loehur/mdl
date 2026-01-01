<?php

class WA_Local extends Controller
{
    public function send($target, $message, $token = "")
    {
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

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://127.0.0.1:8033/send-message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('message' => $message, 'number' => $target),
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
                $status = $response["response"]['status'];
                $id = $response["response"]['key']['id'];

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

    function cek_status()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://127.0.0.1:8033/cek-status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [],
        ));

        $response = curl_exec($curl);
        $rescode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }
        curl_close($curl);

        $res = json_decode($response, true);
        return $res;
    }
}
