<?php

class WA_YCloud extends DB
{
    // API Endpoint Lokal (Centralized Logic)
    // Arahkan ke endpoint API Backend yang sudah kita update
    // Sesuaikan domain jika di hosting (misal https://laundry.com/api/WhatsApp/send)
    private $local_api_url = 'https://api.nalju.com/WhatsApp/send';

    public function send($phone, $message, $token = null)
    {
        // 1. Normalisasi Nomor (Standard)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 2) == '08') {
            $phone = '628' . substr($phone, 2);
        } else if (substr($phone, 0, 1) == '8') {
            $phone = '62' . $phone;
        }
        
        // 2. Kirim ke API LOCAL (Backend)
        // Kita serahkan logic CSW check dan Save DB ke API Server
        $data = [
            'phone' => $phone,
            'message' => $message,
            'message_mode' => 'free' // Sesuai request, mode Free (Session)
            // 'last_message_at' tidak dikirim, agar API Server cek sendiri ke DB
        ];

        $ch = curl_init($this->local_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        // Timeout agak lama karena API Server mungkin query DB dan forward ke YCloud
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // 3. Parse Response
        $status = false;
        $msg = 'Failed';
        $decoded = json_decode($response, true);
        
        if ($httpCode == 200) {
            // Cek sukses standard framework kita
            if (isset($decoded['status']) && $decoded['status'] === true) {
                $status = true;
                $msg = 'Success';
            } else {
                $msg = $decoded['message'] ?? ($decoded['error'] ?? 'API Error');
            }
        } else {
            // Handle HTTP Error (misal 400 Bad Request karena CSW Expired)
            $apiError = $decoded['message'] ?? ($decoded['error'] ?? '');
            $msg = "HTTP $httpCode: " . ($apiError ? $apiError : ($error ? $error : 'Request Failed'));
            
            // Highlight CSW Check result
            if ($httpCode == 400 && (isset($decoded['data']['csw_expired']) || strpos($msg, 'CSW') !== false)) {
                 $msg = "CSW EXPIRED: Pesan gagal dikirim karena pelanggan belum chat dalam 24 jam terakhir.";
            }
        }
        
        return [
            'status' => $status,
            'code' => $httpCode,
            'forward' => !$status, 
            'error' => $msg,
            'data' => $decoded
        ];
    }
}
