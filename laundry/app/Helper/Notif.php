<?php

class Notif extends Controller
{

    function send_wa($hp, $jsonText, $template_name = 'free')
    {
        // FORCE CHANGE: User requested to remove all non-YCloud methods.
        // We override the configuration and directly use the YCloud adapter.
        // We do not pass parameters from URL::WA_TOKEN because they might contain legacy tokens.
        
        $res = $this->model('WA_YCloud')->send($hp, $jsonText, $template_name);
        
        // Only log errors (not success)
        if (!$res['status']) {
            $jsonRes = json_encode($res);
        }

        return $res;
    }

    /**
     * Direct call to yCloud without going through API server
     * Faster and more reliable for internal notifications
     */
    function send_wa_direct($hp, $text)
    {
        // Normalize phone
        $phone = preg_replace('/[^0-9]/', '', $hp);
        if (substr($phone, 0, 2) == '08') {
            $phone = '+628' . substr($phone, 2);
        } else if (substr($phone, 0, 2) == '62') {
            $phone = '+' . $phone;
        } else if (substr($phone, 0, 1) == '8') {
            $phone = '+62' . $phone;
        } else if (substr($phone, 0, 1) != '+') {
            $phone = '+' . $phone;
        }
        
        // yCloud API config - same as API server uses
        $apiKey = defined('YCLOUD_API_KEY') ? YCLOUD_API_KEY : '';
        $waNumber = defined('YCLOUD_WA_NUMBER') ? YCLOUD_WA_NUMBER : '';
        
        // Fallback: try to get from API config file
        if (empty($apiKey)) {
            $envFile = __DIR__ . '/../../api/app/Config/Env.php';
            if (file_exists($envFile)) {
                require_once $envFile;
                if (defined('App\\Config\\Env::WA_API_KEY')) {
                    $apiKey = \App\Config\Env::WA_API_KEY;
                }
                if (defined('App\\Config\\Env::WA_NUMBER')) {
                    $waNumber = \App\Config\Env::WA_NUMBER;
                }
            }
        }
        
        // Still no API key? Fallback to old method
        if (empty($apiKey)) {
            return $this->send_wa($hp, $text, 'free');
        }
        
        // Format waNumber
        $fromNumber = preg_replace('/[^0-9+]/', '', $waNumber);
        if (substr($fromNumber, 0, 1) !== '+') {
            $fromNumber = '+' . $fromNumber;
        }
        
        // Build yCloud payload
        $payload = [
            'from' => $fromNumber,
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $text]
        ];
        
        // Direct call to yCloud
        $ch = curl_init('https://api.ycloud.com/v2/whatsapp/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'status' => false,
                'error' => 'CURL Error: ' . $error,
                'data' => ['id' => null]
            ];
        }
        
        $decoded = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300;
        
        return [
            'status' => $success,
            'error' => $success ? null : ($decoded['error']['message'] ?? 'HTTP ' . $httpCode),
            'data' => $decoded ?? ['id' => null]
        ];
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
