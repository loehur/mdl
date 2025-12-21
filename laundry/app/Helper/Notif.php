<?php

class Notif extends Controller
{

    function send_wa($hp, $text, $private = true)
    {
        // FORCE CHANGE: User requested to remove all non-YCloud methods.
        // We override the configuration and directly use the YCloud adapter.
        // We do not pass parameters from URL::WA_TOKEN because they might contain legacy tokens.
        
        $res = $this->model('WA_YCloud')->send($hp, $text);
        
        $statusStr = $res['status'] ? 'Success' : 'Failed';
        
        // Filter log untuk CSW EXPIRED agar tidak spam
        $jsonRes = json_encode($res);
        $isCSWError = (strpos($jsonRes, 'CSW EXPIRED') !== false) || (strpos($jsonRes, 'Customer Service Window') !== false);
        
        if (!$isCSWError) {
             $this->model('Log')->write("[send_wa] YCloud (Forced) - HP: {$hp}, Status: {$statusStr}, Response: " . $jsonRes);
        }

        return $res;
    }

    function insertOTP($res, $today, $hp, $otp, $id_cabang)
    {
        // Fix: API returns nested data structure {status:true, data:{message_id:...}}
        $apiData = $res['data']['data'] ?? $res['data'] ?? [];
        
        $status = $apiData['status'] ?? 'sent';
        $messageId = $apiData['message_id'] ?? ($apiData['id'] ?? '');
        
        //SAVE DB NOTIF
        $data = [
            'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
            'insertTime' => date('Y-m-d H:i:s'),
            'id_cabang' => $id_cabang,
            'no_ref' => $today,
            'phone' => $hp,
            'text' => $otp,
            'tipe' => 6,
            'id_api' => $messageId,
            'state' => 'sent'  // Status pengiriman: sent, delivered, read, failed
        ];
        $do = $this->db(0)->insert('notif', $data);
        return $do;
    }

    function cek_deliver($hp, $date, $id_cabang = null)
    {
        // Simplified query - tidak cek state jika field tidak ada
        $where = "phone = '" . $hp . "' AND no_ref = '" . $date . "' AND tipe = 6";
        
        // Tambahkan kondisi id_cabang jika ada
        if ($id_cabang) {
            $where .= " AND id_cabang = " . $id_cabang;
        }

        $cek = $this->db(0)->get_where_row('notif', $where);
        
        // Return data jika ada, atau array kosong
        return $cek;
    }
}
