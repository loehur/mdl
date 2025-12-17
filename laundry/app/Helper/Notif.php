<?php

class Notif extends Controller
{

    function send_wa($hp, $text, $private = true)
    {
        if ($private == true) {
            $res = $this->model(URL::WA_API[0])->send($hp, $text, URL::WA_TOKEN[0]);
            $this->model('Log')->write("[send_wa] Private API[0] - HP: {$hp}, Status: " . ($res['status'] ? 'Success' : 'Failed') . ", Response: " . json_encode($res));
            if ($res['forward']) {
                //ALTERNATIF WHATSAPP
                $res = $this->model(URL::WA_API[1])->send($hp, $text, URL::WA_TOKEN[1]);
                $this->model('Log')->write("[send_wa] Private API[1] (Forward) - HP: {$hp}, Status: " . ($res['status'] ? 'Success' : 'Failed') . ", Response: " . json_encode($res));
            }
        } else {
            if (URL::WA_PUBLIC == true) {
                if (URL::WA_USER == 1) {
                    $res = $this->model(URL::WA_API[0])->send($hp, $text, URL::WA_TOKEN[0]);
                    $this->model('Log')->write("[send_wa] Public API[0] - HP: {$hp}, Status: " . ($res['status'] ? 'Success' : 'Failed') . ", Response: " . json_encode($res));
                    if ($res['forward']) {
                        //ALTERNATIF WHATSAPP
                        $res = $this->model(URL::WA_API[1])->send($hp, $text, URL::WA_TOKEN[1]);
                        $this->model('Log')->write("[send_wa] Public API[1] (Forward) - HP: {$hp}, Status: " . ($res['status'] ? 'Success' : 'Failed') . ", Response: " . json_encode($res));
                    }
                } else {
                    $res = $this->model(URL::WA_API[1])->send($hp, $text, URL::WA_TOKEN[1]);
                    $this->model('Log')->write("[send_wa] Public API[1] - HP: {$hp}, Status: " . ($res['status'] ? 'Success' : 'Failed') . ", Response: " . json_encode($res));
                }
            } else {
                $res = [
                    'code' => 0,
                    'status' => false,
                    'forward' => false,
                    'error' => 'No Error',
                    'data' => [
                        'status' => 'Disabled'
                    ],
                ];
                $this->model('Log')->write("[send_wa] HP: {$hp}, Status: Disabled - WA_PUBLIC is turned off");
            }
        }

        return $res;
    }

    function insertOTP($res, $today, $hp, $otp, $id_cabang)
    {
        $status = $res['data']['status'];
        //SAVE DB NOTIF
        $data = [
            'insertTime' => date('Y-m-d H:i:s'),
            'id_cabang' => $id_cabang,
            'no_ref' => $today,
            'phone' => $hp,
            'text' => $otp,
            'tipe' => 6,
            'id_api' => $res['data']['id'],
            'proses' => $status
        ];
        $do = $this->db(date('Y'))->insert('notif', $data);
        return $do;
    }

    function cek_deliver($hp, $date)
    {
        $where = "phone = '" . $hp . "' AND no_ref = '" . $date . "' AND state NOT IN ('delivered','read') AND id_api_2 = ''";

        $cek = $this->db(date('Y'))->get_where_row('notif', $where);
        if (isset($cek['text'])) {
            return $cek;
        }
        return $cek;
    }
}
