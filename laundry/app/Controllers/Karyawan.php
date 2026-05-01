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
            'id_user, nama_user, nama_pemilik, no_user, bank_code, bank_account_name, bank_account_number',
            "en = 1 AND id_cabang = {$id_cabang}",
            1
        );
        
        // Ambil daftar banks untuk mapping bank_code -> bank_name
        $banks = $this->db(100)->get('banks', 'bank_code'); // Indexed by bank_code
        
        // Map nama bank ke setiap karyawan
        foreach ($karyawan as &$row) {
            $bankCode = $row['bank_code'] ?? '';
            $row['bank_name'] = $banks[$bankCode]['name'] ?? '';
        }
        unset($row); // Break reference

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

            // ✅ CEK OTP COOLDOWN - Prevent spam OTP requests
            // Ambil data OTP yang ada di database untuk user ini
            $userData = $this->db(0)->get_where_row('user', "id_user = {$id}");
            
            if ($userData && !empty($userData['otp_active'])) {
                $now = new \DateTime();
                try {
                    $expiry = new \DateTime($userData['otp_active']);
                    
                    // Cek apakah OTP masih aktif (belum expired)
                    if ($now <= $expiry) {
                        // OTP masih valid - tidak boleh request lagi
                        $remainingMinutes = ceil(($expiry->getTimestamp() - $now->getTimestamp()) / 60);
                        throw new \Exception("OTP masih aktif. Gunakan kode OTP yang sudah dikirim. Berlaku {$remainingMinutes} menit lagi.");
                    }
                    
                    // OTP sudah expired - cek cooldown 30 detik sebelum kirim baru
                    $cooldownSeconds = 30;
                    $timeSinceExpiry = $now->getTimestamp() - $expiry->getTimestamp();
                    
                    if ($timeSinceExpiry < $cooldownSeconds) {
                        $remainingCooldown = $cooldownSeconds - $timeSinceExpiry;
                        throw new \Exception("Tunggu {$remainingCooldown} detik lagi sebelum request OTP baru");
                    }
                    
                } catch (\Exception $e) {
                    // Jika error parsing datetime atau error custom dari cooldown check
                    if (strpos($e->getMessage(), 'OTP') !== false || strpos($e->getMessage(), 'Tunggu') !== false) {
                        // Re-throw untuk cooldown/active OTP errors
                        throw $e;
                    }
                    // Untuk parsing error, lanjutkan generate OTP baru
                }
            }

            // Generate OTP 6 digit
            $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // VERSION CHECK - Jika log ini muncul, berarti file sudah terupload
            $this->log("=== KARYAWAN.PHP VERSION 3.4 - FIX CSW CLOSED OTP UPDATE ===", 'karyawan', 'sendOTP');
            
            // Enkripsi OTP menggunakan custom method
            $otp_enc = $this->encryptOTP($otp);
            $this->log("OTP Plain: '{$otp}' | OTP Encrypted: '{$otp_enc}' (length: " . strlen($otp_enc) . ")", 'karyawan', 'sendOTP');
            
            // Generate expiry 5 menit dari sekarang (untuk digunakan nanti setelah WA sukses)
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            // Log OTP yang akan dikirim
            $this->log("Generated OTP for User ID {$id}: '{$otp}' (length: " . strlen($otp) . ", type: " . gettype($otp) . ")", 'karyawan', 'sendOTP');
            $this->log("Expiry will be: {$expiry}", 'karyawan', 'sendOTP');
            
            // ✅ PENTING: Kirim OTP via WhatsApp API TERLEBIH DAHULU
            // Jangan update database dulu, karena jika CSW closed maka OTP tidak akan terkirim
            // dan user harus menunggu 5 menit untuk tidak ada gunanya
            $sendResult = $this->sendWhatsAppOTP($wa, $otp);
            
            // ✅ HANYA update database JIKA WhatsApp berhasil terkirim
            if ($sendResult['status']) {
                $updateResult = $this->db(0)->update('user', [
                    'otp' => $otp_enc,
                    'otp_active' => $expiry
                ], "id_user = {$id}");
                
                $this->log("WhatsApp sent successfully. Database update result: " . ($updateResult ? 'SUCCESS' : 'FAILED'), 'karyawan', 'sendOTP');
            } else {
                // WhatsApp gagal terkirim (kemungkinan CSW closed)
                // TIDAK update OTP ke database, sehingga user bisa langsung retry tanpa menunggu 5 menit
                $this->log("WhatsApp failed to send. Database NOT updated to avoid unnecessary 5-minute wait.", 'karyawan', 'sendOTP');
            }
            
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
        $nama_pemilik = isset($_POST['nama_pemilik']) ? trim($_POST['nama_pemilik']) : '';
        $bank_code = isset($_POST['bank_code']) ? strtolower(trim($_POST['bank_code'])) : '';
        $bank_account_name = isset($_POST['bank_account_name']) ? trim($_POST['bank_account_name']) : '';
        $bank_account_number = isset($_POST['bank_account_number']) ? preg_replace('/[^0-9]/', '', $_POST['bank_account_number']) : '';

        // Jika salah satu field bank kosong, kosongkan semua field bank (untuk konsistensi)
        if (empty($bank_code) || empty($bank_account_number) || empty($bank_account_name)) {
            $data = [
                'nama_pemilik' => $nama_pemilik,
                'bank_code' => '',
                'bank_account_name' => '',
                'bank_account_number' => ''
            ];
        } else {
            $data = [
                'nama_pemilik' => $nama_pemilik,
                'bank_code' => $bank_code,
                'bank_account_name' => $bank_account_name,
                'bank_account_number' => $bank_account_number
            ];
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
     * Get list of banks from database
     * GET /Karyawan/getBanks
     */
    public function getBanks()
    {
        // Suppress any output before JSON
        @ob_start();
        @ini_set('display_errors', 0);
        
        try {
            @header('Content-Type: application/json');
            
            // Ambil daftar bank dari database db(100) - ambil semua tanpa indexing
            $banks = $this->db(100)->get('banks');
            
            // Clear any buffered output
            ob_end_clean();
            
            if (empty($banks)) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Tidak ada data bank',
                    'data' => []
                ]);
                return;
            }
            
            // Format data bank untuk frontend - skip entry kosong (opsi kosong ditambah di frontend)
            $bankList = [];
            foreach ($banks as $bank) {
                $code = trim($bank['bank_code'] ?? '');
                if ($code === '') continue; // skip agar tidak duplikat di tengah list
                $bankList[] = [
                    'code' => $code,
                    'name' => $bank['name'] ?? $code
                ];
            }
            
            // Sort banks alphabetically by name
            usort($bankList, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            echo json_encode([
                'status' => true,
                'message' => 'Success',
                'data' => $bankList
            ]);
            
        } catch (\Exception $e) {
            // Clear any buffered output
            @ob_end_clean();
            
            echo json_encode([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ]);
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
        // Compose message - sama seperti di Login/req_pin
        $message = "🔐 *Kode OTP Verifikasi*\n\n";
        $message .= "Kode OTP Anda: *{$otp}*\n\n";
        $message .= "Kode ini berlaku selama 5 menit.\n";
        $message .= "⚠️ Jangan bagikan kode ini kepada siapapun.";

        // Log sebelum kirim WA untuk tracking
        $this->log("[sendWhatsAppOTP] Attempting to send OTP to: {$phoneNumber}", 'karyawan', 'whatsapp_otp');
        
        // ✅ GUNAKAN WA_YCloud MODEL - SEPERTI DI Login::req_pin
        // Model ini akan otomatis mengecek CSW dan menggunakan template jika CSW expired
        $res = $this->model('WA_YCloud')->send($phoneNumber, $message);
        
        // Log response lengkap untuk debugging
        $this->log("[sendWhatsAppOTP] WA Response: " . json_encode($res), 'karyawan', 'whatsapp_otp');

        // ✅ VALIDASI: Pastikan WA benar-benar terkirim
        $waSuccess = false;
        $waMessageId = null;
        $errorMessage = null;
        
        // Check: status bisa true (boolean) atau 'success'/'sent' (string)
        $statusOk = ($res['status'] === true || $res['status'] === 'success');
        $httpOk = (($res['code'] ?? 0) == 200);
        
        if ($statusOk && $httpOk) {
            // Jika status true dan http 200, langsung sukses!
            $waSuccess = true;
            
            // Optional: Extract message_id untuk logging (tidak wajib)
            $responseData = $res['data'] ?? [];
            $waMessageId = $responseData['id'] ?? ($responseData['message_id'] ?? null);
            $dataStatus = $responseData['status'] ?? '';
            
            $this->log("[sendWhatsAppOTP] WA Success - Message ID: " . ($waMessageId ?: 'N/A') . ", Status: " . ($dataStatus ?: 'N/A'), 'karyawan', 'whatsapp_otp');
        } else {
            $this->log("[sendWhatsAppOTP] WA Failed - Status: " . json_encode($res['status']) . ", HTTP Code: " . ($res['code'] ?? 'null'), 'karyawan', 'whatsapp_otp');
            
            // Extract error message
            $errorMessage = $res['error'] ?? 'Unknown error';
            
            // Jika CSW expired, berikan pesan yang jelas
            $apiData = $res['data'] ?? [];
            if (($res['code'] ?? 0) == 400 && isset($apiData['csw_expired']) && $apiData['csw_expired']) {
                $hoursElapsed = $apiData['hours_elapsed'] ?? 'N/A';
                $lastMessageAt = $apiData['last_message_at'] ?? 'N/A';
                $errorMessage = "Customer Service Window (CSW) expired. Last message: {$lastMessageAt} ({$hoursElapsed} hours ago). Cannot send free text message.";
                
                $this->log("[sendWhatsAppOTP] CSW Expired - Hours: {$hoursElapsed}, Last Message: {$lastMessageAt}", 'karyawan', 'whatsapp_otp');
            }
        }

        if ($waSuccess) {
            return [
                'status' => true,
                'error' => null,
                'response' => $res['data'] ?? null
            ];
        } else {
            return [
                'status' => false,
                'error' => $errorMessage,
                'response' => $res
            ];
        }
    }


}
