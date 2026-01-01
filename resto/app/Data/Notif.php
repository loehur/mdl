<?php

class Notif extends Controller
{
    function insertOTP($res, $today, $hp, $otp, $id_cabang)
    {
        //SAVE DB NOTIF
        $cols =  'insertTime, id_cabang, no_ref, phone, text, tipe, id_api, proses';
        
        // Ambil status dan message_id dari response WA_YCloud
        // Struktur response: $res['data']['data']['status'] dan $res['data']['data']['id']
        $status = isset($res['data']['data']['status']) ? $res['data']['data']['status'] : 'sent';
        $message_id = isset($res['data']['data']['id']) ? $res['data']['data']['id'] : '';
        
        $vals =  "'" . date('Y-m-d H:i:s') . "'," . $id_cabang . ",'" . $today . "','" . $hp . "','" . $otp . "',6,'" . $message_id . "','" . $status . "'";
        $do = $this->db(0)->insertCols('notif', $cols, $vals);
        return $do;
    }

    function cek_deliver($hp, $date, $id_cabang = 0)
    {
        $where = "phone = '" . $hp . "' AND no_ref = '" . $date . "' AND state NOT IN ('delivered','read') AND id_api_2 = ''";

        $cek = $this->db(0)->get_where_row('notif', $where);
        if (isset($cek['text'])) {
            return $cek;
        }
        return $cek;
    }
}
