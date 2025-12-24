<?php

class WA_YCloud extends DB
{
    // API Endpoint Lokal (Centralized Logic)
    // Arahkan ke endpoint API Backend yang sudah kita update
    // Sesuaikan domain jika di hosting (misal https://laundry.com/api/WhatsApp/send)
    private $local_api_url = 'https://api.nalju.com/WhatsApp/send';

    // Modifikasi: param ke-3 jadi message_mode untuk support template
    public function send($phone, $message, $message_mode = 'free')
    {
        // 1. Normalisasi Nomor (Standard)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 2) == '08') {
            $phone = '628' . substr($phone, 2);
        } else if (substr($phone, 0, 1) == '8') {
            $phone = '62' . $phone;
        }
        
        // 2. Prepare data for API
        $data = [
            'phone' => $phone,
            'message_mode' => $message_mode,
        ];
        
        // 3. Handle template mode - extract params from JSON
        if ($message_mode === 'template') {
            $messageData = json_decode($message, true);
            if ($messageData && isset($messageData['template_params'])) {
                // IMPORTANT: Extract the actual text from JSON
                // This text will be used for free text sending if CSW is open
                $data['message'] = $messageData['text'] ?? '';
                $data['template_params'] = $messageData['template_params'];
                $data['template_name'] = 'template_utility_20251222163105'; // Default template name
            } else {
                // Fallback: treat as free text if no template_params
                $data['message'] = $message;
                $data['message_mode'] = 'free';
            }
        } else {
            // Free mode - just send message
            $data['message'] = $message;
        }

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
        
        // Bersihkan response dari whitespace dan BOM (Byte Order Mark)
        $response = trim($response);
        // Remove UTF-8 BOM if present
        if (substr($response, 0, 3) === "\xEF\xBB\xBF") {
            $response = substr($response, 3);
        }
        
        $decoded = json_decode($response, true);
        
        // Cek apakah JSON valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            // JSON Error (kemungkinan ada output PHP Warning/Notice atau HTML Error page)
            $rawBrief = substr(trim(strip_tags($response)), 0, 150);
            $msg = "Invalid JSON Response: " . ($rawBrief ?: 'Empty Response');
            
            // Log full response untuk debug
            // $decoded dianggap null
        } else {
            // JSON Valid
            if ($httpCode == 200) {
                // Cek sukses standard framework kita
                if (isset($decoded['status']) && ($decoded['status'] === true || $decoded['status'] === 'success')) {
                    $status = true;
                    $msg = 'Success';
                } else {
                    $msg = $decoded['message'] ?? ($decoded['error'] ?? 'API Error (No Message)');
                }
            } else {
                // Handle HTTP Error
                $apiError = $decoded['message'] ?? ($decoded['error'] ?? '');
                $msg = "HTTP $httpCode: " . ($apiError ? $apiError : ($error ? $error : 'Request Failed'));
                
                // Highlight CSW Check result
                if ($httpCode == 400 && (isset($decoded['data']['csw_expired']) || strpos($msg, 'CSW') !== false)) {
                     $msg = "CSW EXPIRED: Pesan gagal dikirim karena pelanggan belum chat dalam 24 jam terakhir.";
                }
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
