<?php

/**
 * Controller Karyawan
 * Menampilkan dan mengelola data karyawan dengan verifikasi OTP dan Bank Inquiry
 */
class Karyawan extends Controller
{
    public function __construct()
    {
        $this->session_cek();
        $this->operating_data();
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
            'id_user, nama_user, no_user, nama_lengkap, bank_code, bank_name, bank_account_name, bank_account_number',
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
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request']);
            return;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $wa = isset($_POST['wa']) ? preg_replace('/[^0-9]/', '', $_POST['wa']) : '';

        if ($id < 1) {
            echo json_encode(['status' => false, 'message' => 'ID tidak valid']);
            return;
        }

        if (strlen($wa) < 9 || strlen($wa) > 15) {
            echo json_encode(['status' => false, 'message' => 'Nomor WhatsApp tidak valid']);
            return;
        }

        // Generate OTP 6 digit
        $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Simpan OTP ke database dengan expiry 5 menit
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $this->db(0)->update('user', [
            'otp' => $otp,
            'otp_active' => $expiry
        ], "id_user = {$id}");

        // Kirim OTP via WhatsApp API
        $sendResult = $this->sendWhatsAppOTP($wa, $otp);
        
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
    }

    /**
     * Verifikasi OTP
     * POST /Karyawan/verifyOTP
     */
    public function verifyOTP()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request']);
            return;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $otp = isset($_POST['otp']) ? preg_replace('/[^0-9]/', '', $_POST['otp']) : '';

        if ($id < 1) {
            echo json_encode(['status' => false, 'message' => 'ID tidak valid']);
            return;
        }

        if (strlen($otp) !== 6) {
            echo json_encode(['status' => false, 'message' => 'Kode OTP harus 6 digit']);
            return;
        }

        // Cek OTP di database
        $user = $this->db(0)->get_where('user', "id_user = {$id}", 1);
        
        if (empty($user)) {
            echo json_encode(['status' => false, 'message' => 'User tidak ditemukan']);
            return;
        }

        $userData = $user[0];
        
        // Cek apakah OTP cocok dan belum expired
        if ($userData['otp'] !== $otp) {
            echo json_encode(['status' => false, 'message' => 'Kode OTP tidak valid']);
            return;
        }

        $now = new DateTime();
        $expiry = new DateTime($userData['otp_active']);
        
        if ($now > $expiry) {
            echo json_encode(['status' => false, 'message' => 'Kode OTP sudah kadaluarsa']);
            return;
        }

        // OTP valid - hapus OTP dari database
        $this->db(0)->update('user', [
            'otp' => '',
            'otp_active' => null
        ], "id_user = {$id}");

        echo json_encode(['status' => true, 'message' => 'OTP berhasil diverifikasi']);
    }

    /**
     * Verifikasi rekening bank via Flip API
     * POST /Karyawan/verifyBank
     */
    public function verifyBank()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request']);
            return;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $bankCode = isset($_POST['bank_code']) ? strtolower(trim($_POST['bank_code'])) : '';
        $accountNumber = isset($_POST['account_number']) ? preg_replace('/[^0-9]/', '', $_POST['account_number']) : '';

        if ($id < 1) {
            echo json_encode(['status' => false, 'message' => 'ID tidak valid']);
            return;
        }

        if (empty($bankCode)) {
            echo json_encode(['status' => false, 'message' => 'Kode bank wajib diisi']);
            return;
        }

        if (strlen($accountNumber) < 5 || strlen($accountNumber) > 20) {
            echo json_encode(['status' => false, 'message' => 'Nomor rekening tidak valid']);
            return;
        }

        // Call Flip API untuk bank inquiry
        require_once __DIR__ . '/../../api/app/Models/Flip.php';
        
        $flip = new \App\Models\Flip();
        $result = $flip->bankInquiry($bankCode, $accountNumber);

        if (isset($result['success']) && $result['success'] && isset($result['account_holder'])) {
            // Verifikasi berhasil
            $status = $result['status'] ?? 'UNKNOWN';
            
            if ($status === 'SUCCESS') {
                echo json_encode([
                    'status' => true,
                    'message' => 'Rekening terverifikasi',
                    'data' => [
                        'bank_code' => $bankCode,
                        'account_number' => $accountNumber,
                        'account_holder' => $result['account_holder']
                    ]
                ]);
            } elseif ($status === 'PENDING') {
                echo json_encode([
                    'status' => false,
                    'message' => 'Verifikasi rekening sedang diproses, silakan coba lagi dalam beberapa saat'
                ]);
            } elseif ($status === 'INVALID_ACCOUNT_NUMBER') {
                echo json_encode([
                    'status' => false,
                    'message' => 'Nomor rekening tidak ditemukan'
                ]);
            } elseif ($status === 'SUSPECTED_ACCOUNT') {
                echo json_encode([
                    'status' => false,
                    'message' => 'Rekening terdeteksi mencurigakan'
                ]);
            } elseif ($status === 'BLACK_LISTED') {
                echo json_encode([
                    'status' => false,
                    'message' => 'Rekening masuk dalam daftar hitam'
                ]);
            } else {
                echo json_encode([
                    'status' => false,
                    'message' => 'Status verifikasi: ' . $status
                ]);
            }
        } else {
            // Verifikasi gagal
            $errorMsg = $result['error'] ?? $result['message'] ?? 'Gagal memverifikasi rekening';
            echo json_encode([
                'status' => false,
                'message' => $errorMsg
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
            'nama_lengkap' => isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '',
            'bank_code' => isset($_POST['bank_code']) ? strtolower(trim($_POST['bank_code'])) : '',
            'bank_name' => isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '',
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
            // Load required files
            $helperPath = __DIR__ . '/../../api/app/Helpers/WhatsAppService.php';
            $configPath = __DIR__ . '/../../api/app/Config/WhatsApp.php';
            
            if (!file_exists($helperPath)) {
                throw new \Exception("WhatsAppService.php not found at: {$helperPath}");
            }
            
            if (!file_exists($configPath)) {
                throw new \Exception("WhatsApp.php config not found at: {$configPath}");
            }
            
            require_once $helperPath;
            require_once $configPath;
            
            $wa = new \App\Helpers\WhatsAppService();
            $result = $wa->sendFreeText($phoneNumber, $message);
            
            // Log the result for debugging
            error_log("[WhatsApp OTP] Result: " . json_encode($result));
            
            if (isset($result['success']) && $result['success']) {
                return [
                    'status' => true,
                    'error' => null,
                    'response' => $result['data'] ?? null
                ];
            } else {
                // Extract detailed error message
                $errorMsg = 'WhatsApp API error';
                
                if (isset($result['error'])) {
                    $errorMsg = $result['error'];
                } elseif (isset($result['data']['error']['message'])) {
                    $errorMsg = $result['data']['error']['message'];
                } elseif (isset($result['data']['error']['code'])) {
                    $errorMsg = 'API Error: ' . $result['data']['error']['code'];
                }
                
                $httpCode = $result['http_code'] ?? 0;
                if ($httpCode > 0) {
                    $errorMsg .= " (HTTP {$httpCode})";
                }
                
                error_log("[WhatsApp OTP Error] " . $errorMsg . " - " . json_encode($result));
                
                return [
                    'status' => false,
                    'error' => $errorMsg,
                    'response' => $result
                ];
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("[WhatsApp OTP Exception] " . $errorMsg);
            error_log("[WhatsApp OTP Exception Trace] " . $e->getTraceAsString());
            
            return [
                'status' => false,
                'error' => $errorMsg,
                'response' => null
            ];
        }
    }


}
