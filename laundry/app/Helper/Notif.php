<?php

class Notif extends Controller
{

    function send_wa($id_pelanggan, $jsonText, $template_name = 'free')
    {
        $id_pelanggan = (int) $id_pelanggan;
        $this->helper('NotifRecipient');
        $sendPhone = NotifRecipient::phoneById($this->db(0), $id_pelanggan);
        if ($sendPhone === null || $sendPhone === '') {
            return [
                'status' => false,
                'error' => 'Nomor pelanggan tidak ditemukan',
                'data' => ['id' => null],
            ];
        }

        // Nomor alternatif (nomor_pelanggan_2). API server yang memilih nomor
        // tujuan berdasarkan status CSW (prefer nomor utama bila keduanya terbuka).
        $secondPhone = NotifRecipient::secondPhoneById($this->db(0), $id_pelanggan);

        $res = $this->model('WA_YCloud')->send($sendPhone, $jsonText, $template_name, $id_pelanggan, $secondPhone);

        if (!$res['status']) {
            $jsonRes = json_encode($res);
        }

        return $res;
    }

    /**
     * Direct call to yCloud without going through API server
     */
    function send_wa_direct($hp, $text)
    {
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

        $apiKey = defined('YCLOUD_API_KEY') ? YCLOUD_API_KEY : '';
        $waNumber = defined('YCLOUD_WA_NUMBER') ? YCLOUD_WA_NUMBER : '';

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

        if (empty($apiKey)) {
            $this->helper('PelangganByPhone');
            $idPel = (int) (new PelangganByPhone())->id($hp);
            if ($idPel > 0) {
                return $this->send_wa($idPel, $text, 'free');
            }
            return ['status' => false, 'error' => 'Pelanggan tidak ditemukan', 'data' => ['id' => null]];
        }

        $fromNumber = preg_replace('/[^0-9+]/', '', $waNumber);
        if (substr($fromNumber, 0, 1) !== '+') {
            $fromNumber = '+' . $fromNumber;
        }

        $payload = [
            'from' => $fromNumber,
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $text]
        ];

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

    /**
     * Hapus baris wa_messages_out queue/processing dengan teks sama + id_pelanggan cocok.
     */
    public function deleteMatchingWaOutQueue(int $id_pelanggan, string $text): int
    {
        $id_pelanggan = (int) $id_pelanggan;
        $textTrim = trim($text);
        if ($id_pelanggan <= 0 || $textTrim === '') {
            return 0;
        }

        $this->helper('NotifRecipient');
        $this->helper('PelangganByPhone');
        $resolvedPhone = NotifRecipient::phoneById($this->db(0), $id_pelanggan);
        $nomor = $resolvedPhone !== null ? PelangganByPhone::key($resolvedPhone) : '';

        try {
            $db100 = $this->db(100);
            $escText = $db100->escape($textTrim);
            $conds = ['id_pelanggan = ' . $id_pelanggan];
            if ($nomor !== '' && strlen($nomor) >= 8) {
                $conds[] = PelangganByPhone::likeSql($db100->escape($nomor), 'phone');
            }
            $sql = "
                DELETE FROM wa_messages_out
                WHERE status IN ('queue', 'processing')
                  AND TRIM(COALESCE(content, '')) = '{$escText}'
                  AND (" . implode(' OR ', $conds) . ")
            ";
            $ok = $db100->query($sql);
            return $ok ? 1 : 0;
        } catch (\Throwable $e) {
            if (class_exists('Log')) {
                @\Log::write(
                    '[Notif::deleteMatchingWaOutQueue] ' . $e->getMessage() . ' | id_pelanggan=' . $id_pelanggan,
                    'laundry',
                    'wa_out_cleanup'
                );
            }
            return 0;
        }
    }

    function insertOTP($res, $today, $hp, $otp, $id_cabang)
    {
        $apiData = $res['data']['data'] ?? $res['data'] ?? [];

        $status = $apiData['status'] ?? 'sent';
        $messageId = $apiData['message_id'] ?? ($apiData['id'] ?? '');

        $this->helper('PelangganByPhone');
        $id_pelanggan = (int) (new PelangganByPhone())->id($hp);

        $data = [
            'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
            'insertTime' => date('Y-m-d H:i:s'),
            'id_cabang' => $id_cabang,
            'no_ref' => $today,
            'id_pelanggan' => $id_pelanggan > 0 ? $id_pelanggan : null,
            'text' => $otp,
            'tipe' => 6,
            'id_api' => $messageId,
            'state' => 'sent'
        ];
        $do = $this->db(0)->insert('notif', $data);
        return $do;
    }

    function cek_deliver($hp, $date, $id_cabang = null)
    {
        $this->helper('PelangganByPhone');
        $id_pelanggan = (int) (new PelangganByPhone())->id($hp);
        if ($id_pelanggan <= 0) {
            return null;
        }

        $where = "id_pelanggan = " . $id_pelanggan . " AND no_ref = '" . $this->db(0)->escape($date) . "' AND tipe = 6";

        if ($id_cabang) {
            $where .= " AND id_cabang = " . (int) $id_cabang;
        }

        $cek = $this->db(0)->get_where_row('notif', $where);

        return $cek;
    }
}
