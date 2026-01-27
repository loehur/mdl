<?php

/**
 * Controller Karyawan
 * Menampilkan dan mengelola data karyawan dengan verifikasi OTP dan Bank Inquiry
 * 
 * VERSION 3.3 - 2026-01-21
 * - OTP Encryption: CUSTOM MD5 (Simple & Reliable)
 * - OTP Expiry: 5 minutes
 * - Logging: Integrated with Log model
 * - FIX: Array key handling (empty string vs numeric)
 */
class Karyawan extends Controller
{
    public function __construct()
    {
        $this->session_cek();
        $this->operating_data();
    }

    /**
     * Safe logging yang tidak output HTML
     */
    private function log($message, $category = 'karyawan', $subcategory = 'general')
    {
        try {
            if (class_exists('Log')) {
                @\Log::write($message, $category, $subcategory);
            }
        } catch (\Exception $e) {
            // Silent fail - jangan output apapun
        }
    }

    /**
     * Custom OTP Encryption - Simple & Reliable
     * Menggunakan MD5 dengan salt untuk enkripsi OTP
     */
    private function encryptOTP($otp)
    {
        // Salt untuk keamanan (bisa diganti sesuai kebutuhan)
        $salt = 'MDL_LAUNDRY_OTP_2026';
        
        // Enkripsi menggunakan MD5 dengan salt
        return md5($salt . $otp . $salt);
    }

    /**
     * Menampilkan daftar data karyawan
     * GET /Karyawan/data
     */
    public function data()
    {
        $data_operasi = ['title' => 'Data Karyawan'];

        // Ambil id_cabang dari session
        $id_cabang = (int)($_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0);

        // Ambil data karyawan aktif di cabang ini
        $karyawan = $this->db(0)->get_cols_where(
            'user',
            'id_user, nama_user, no_user, bank_code, bank_account_name, bank_account_number',
            "en = 1 AND id_cabang = {$id_cabang}",
            1
        );

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('karyawan/data', [
            'karyawan' => $karyawan
        ]);
    }

    /**
     * Kirim OTP ke WhatsApp
     * POST /Karyawan/sendOTP
     */
    public function sendOTP()
    {
        // Suppress any output before JSON AND errors
        @ob_start();
        @ini_set('display_errors', 0);
        
        try {
            @header('Content-Type: application/json');
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Invalid request method');
            }

            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $wa = isset($_POST['wa']) ? preg_replace('/[^0-9]/', '', $_POST['wa']) : '';

            if ($id < 1) {
                throw new \Exception('ID tidak valid');
            }

            if (strlen($wa) < 9 || strlen($wa) > 15) {
                throw new \Exception('Nomor WhatsApp tidak valid');
            }

            // Generate OTP 6 digit
            $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // VERSION CHECK - Jika log ini muncul, berarti file sudah terupload
            $this->log("=== KARYAWAN.PHP VERSION 3.3 - FIXED ARRAY KEY ===", 'karyawan', 'sendOTP');
            
            // Enkripsi OTP menggunakan custom method
            $otp_enc = $this->encryptOTP($otp);
            $this->log("OTP Plain: '{$otp}' | OTP Encrypted: '{$otp_enc}' (length: " . strlen($otp_enc) . ")", 'karyawan', 'sendOTP');
            
            // Simpan OTP ke database dengan expiry 5 menit
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            // Log OTP yang akan disimpan
            $this->log("Generated OTP for User ID {$id}: '{$otp}' (length: " . strlen($otp) . ", type: " . gettype($otp) . ")", 'karyawan', 'sendOTP');
            $this->log("Expiry: {$expiry}", 'karyawan', 'sendOTP');
            
            $updateResult = $this->db(0)->update('user', [
                'otp' => $otp_enc,
                'otp_active' => $expiry
            ], "id_user = {$id}");
            
            $this->log("Database update result: " . ($updateResult ? 'SUCCESS' : 'FAILED'), 'karyawan', 'sendOTP');

            // Kirim OTP via WhatsApp API
            $sendResult = $this->sendWhatsAppOTP($wa, $otp);
            
            // Clear any buffered output
            ob_end_clean();
            
            if ($sendResult['status']) {
                echo json_encode(['status' => true, 'message' => 'OTP berhasil dikirim ke WhatsApp']);
            } else {
                // Tampilkan error dari API
                echo json_encode([
                    'status' => false, 
                    'message' => 'Gagal mengirim OTP: ' . ($sendResult['error'] ?? 'Unknown error'),
                    'api_response' => $sendResult['response'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            // Clear any buffered output
            @ob_end_clean();
            
            $this->log("ERROR: " . $e->getMessage(), 'karyawan', 'sendOTP');
            
            echo json_encode([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Verifikasi OTP
     * POST /Karyawan/verifyOTP
     */
    public function verifyOTP()
    {
        // Suppress any output before JSON AND errors
        @ob_start();
        @ini_set('display_errors', 0);
        
        try {
            @header('Content-Type: application/json');
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Invalid request method');
            }

            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $otp = isset($_POST['otp']) ? preg_replace('/[^0-9]/', '', $_POST['otp']) : '';

            if ($id < 1) {
                throw new \Exception('ID tidak valid');
            }

            if (strlen($otp) !== 6) {
                throw new \Exception('Kode OTP harus 6 digit');
            }

            // Enkripsi OTP input menggunakan custom method (sama dengan sendOTP)
            $otp_enc = $this->encryptOTP($otp);
            $this->log("Verify OTP - Plain: '{$otp}' | Encrypted: '{$otp_enc}'", 'karyawan', 'verifyOTP');

            // Cek OTP di database
            $user = $this->db(0)->get_where('user', "id_user = {$id}", 1);
            
            // Log raw data dari database
            $this->log("RAW DATA from get_where: " . json_encode($user), 'karyawan', 'verifyOTP');
            
            if (empty($user)) {
                throw new \Exception('User tidak ditemukan');
            }

            // FIX: Array key bisa "" (empty string) atau 0, gunakan reset() untuk ambil first element
            $userData = reset($user);
            
            // Clean dan standardize OTP untuk perbandingan
            $inputOtp = (string)trim($otp_enc);
            $dbOtp = (string)trim($userData['otp'] ?? '');
            
            // Log untuk debugging
            $this->log("User ID: {$id}", 'karyawan', 'verifyOTP');
            $this->log("Input OTP: '{$inputOtp}' (length: " . strlen($inputOtp) . ", type: " . gettype($inputOtp) . ")", 'karyawan', 'verifyOTP');
            $this->log("DB OTP: '{$dbOtp}' (length: " . strlen($dbOtp) . ", type: " . gettype($dbOtp) . ")", 'karyawan', 'verifyOTP');
            $this->log("Expiry: {$userData['otp_active']}", 'karyawan', 'verifyOTP');
            $this->log("Match: " . ($inputOtp === $dbOtp ? 'YES' : 'NO'), 'karyawan', 'verifyOTP');
            
            // Cek apakah OTP cocok dan belum expired
            if ($inputOtp !== $dbOtp) {
                // Debug: show character codes
                $this->log("Input OTP hex: " . bin2hex($inputOtp), 'karyawan', 'verifyOTP');
                $this->log("DB OTP hex: " . bin2hex($dbOtp), 'karyawan', 'verifyOTP');
                throw new \Exception('Kode OTP tidak valid');
            }

            $now = new DateTime();
            $expiry = new DateTime($userData['otp_active']);
            
            if ($now > $expiry) {
                throw new \Exception('Kode OTP sudah kadaluarsa');
            }

            // OTP valid - hapus OTP dari database
            $this->db(0)->update('user', [
                'otp' => '',
                'otp_active' => null
            ], "id_user = {$id}");

            // Clear buffer and send success
            ob_end_clean();
            echo json_encode(['status' => true, 'message' => 'OTP berhasil diverifikasi']);
            
        } catch (\Exception $e) {
            // Clear buffer and send error
            @ob_end_clean();
            
            $this->log("ERROR: " . $e->getMessage(), 'karyawan', 'verifyOTP');
            
            echo json_encode([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }



    /**
     * Simpan data karyawan setelah verifikasi
     * POST /Karyawan/save
     */
    public function save()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request']);
            return;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id < 1) {
            echo json_encode(['status' => false, 'message' => 'ID tidak valid']);
            return;
        }

        // Ambil data yang akan disimpan (no_user tidak termasuk karena tidak bisa diedit)
        $data = [
            'bank_code' => isset($_POST['bank_code']) ? strtolower(trim($_POST['bank_code'])) : '',
            'bank_account_name' => isset($_POST['bank_account_name']) ? trim($_POST['bank_account_name']) : '',
            'bank_account_number' => isset($_POST['bank_account_number']) ? preg_replace('/[^0-9]/', '', $_POST['bank_account_number']) : ''
        ];

        // Validasi
        if (empty($data['bank_code']) || empty($data['bank_account_number'])) {
            echo json_encode(['status' => false, 'message' => 'Data bank/e-wallet wajib diisi']);
            return;
        }

        // Update database
        $result = $this->db(0)->update('user', $data, "id_user = {$id}");

        if ($result) {
            echo json_encode(['status' => true, 'message' => 'Data berhasil disimpan']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Gagal menyimpan data']);
        }
    }

    /**
     * Kirim OTP via WhatsApp API
     * @param string $phoneNumber Nomor telepon (tanpa +62)
     * @param string $otp Kode OTP
     * @return array ['status' => bool, 'error' => string|null, 'response' => mixed]
     */
    private function sendWhatsAppOTP($phoneNumber, $otp)
    {
        // Format nomor: tambahkan 62 di depan jika belum ada
        if (substr($phoneNumber, 0, 2) !== '62') {
            $phoneNumber = '62' . $phoneNumber;
        }

        // Compose message
        $message = "🔐 *Kode OTP Verifikasi*\n\n";
        $message .= "Kode OTP Anda: *{$otp}*\n\n";
        $message .= "Kode ini berlaku selama 5 menit.\n";
        $message .= "⚠️ Jangan bagikan kode ini kepada siapapun.";

        try {
            // Call WhatsApp API endpoint - menggunakan send
            $apiUrl = 'https://api.nalju.com/WhatsApp/send';
            
            // Prepare payload untuk free text
            $payload = [
                'phone' => $phoneNumber,
                'message_mode' => 'free',
                'message' => $message,
            ];
            
            // Send request via cURL
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Log the request and response
            $this->log("Phone: {$phoneNumber}, HTTP: {$httpCode}", 'karyawan', 'whatsapp_otp');
            $this->log("Response: " . $response, 'karyawan', 'whatsapp_otp');
            
            // Handle cURL errors
            if ($curlError) {
                throw new \Exception("cURL Error: {$curlError}");
            }
            
            // Parse response
            $result = json_decode($response, true);
            
            if ($result === null) {
                throw new \Exception("Invalid JSON response from API: " . substr($response, 0, 200));
            }
            
            // Check if successful
            if (isset($result['status']) && $result['status'] === true) {
                return [
                    'status' => true,
                    'error' => null,
                    'response' => $result['data'] ?? null
                ];
            } else {
                // API returned error
                $errorMsg = $result['message'] ?? 'WhatsApp API error';
                
                return [
                    'status' => false,
                    'error' => $errorMsg,
                    'response' => $result
                ];
            }
            
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->log("Exception: " . $errorMsg, 'karyawan', 'whatsapp_otp');
            
            return [
                'status' => false,
                'error' => $errorMsg,
                'response' => null
            ];
        }
    }


}
