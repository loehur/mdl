<?php

/**
 * Generate Admin Access Key (4 digit).
 * Hanya privilege 100. Wajib request PIN (OTP WA, aktif 5 menit) lalu verifikasi sebelum generate.
 * Key hanya tampil sekali.
 */
class GenerateKey extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $meta = $this->getKeyMeta();
      $pinOk = $this->isPinUnlocked();
      $hp = (string) ($_SESSION[URL::SESSID]['user']['no_user'] ?? '');

      $this->view('layout', ['data_operasi' => ['title' => 'Generate Key']]);
      $this->view('tools/generate_key', [
         'has_key' => !empty($meta),
         'created_at' => $meta['created_at'] ?? null,
         'pin_ok' => $pinOk,
         'hp_mask' => $this->maskHp($hp),
      ]);
   }

   /**
    * Request PIN OTP ke WA user yang sedang login (aktif 5 menit). Sama pola Login/req_pin.
    */
   public function reqPin()
   {
      header('Content-Type: application/json; charset=utf-8');

      $user = $_SESSION[URL::SESSID]['user'] ?? [];
      $hp = (string) ($user['no_user'] ?? '');
      $username = (string) ($user['username'] ?? '');
      $idUser = (int) ($user['id_user'] ?? 0);

      if ($hp === '' || $idUser < 1) {
         echo json_encode(['ok' => 0, 'msg' => 'Session user tidak lengkap']);
         return;
      }
      if ($username === '') {
         $username = $this->model('Enc')->username($hp);
      }

      $where = "id_user = " . $idUser . " AND en = 1";
      $cek = $this->db(0)->get_where_row('user', $where);
      if (empty($cek)) {
         echo json_encode(['ok' => 0, 'msg' => 'User tidak ditemukan']);
         return;
      }

      $now = new DateTime();
      if (!empty($cek['otp_active'])) {
         try {
            $expiry = new DateTime($cek['otp_active']);
            if ($now <= $expiry) {
               echo json_encode([
                  'ok' => 1,
                  'msg' => 'GUNAKAN PIN YANG SUDAH DIKIRIM, MASIH AKTIF (5 menit)',
               ]);
               return;
            }
         } catch (Exception $e) {
            // lanjut generate baru
         }
      }

      $otp = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
      $otpEnc = $this->model('Enc')->otp($otp);
      $nama = (string) ($cek['nama_user'] ?? '');
      $idCabang = (int) ($cek['id_cabang'] ?? 0);

      $text = "🔐 *KODE OTP GENERATE KEY*\n\n";
      $text .= "Kode OTP: *" . $otp . "*\n";
      $text .= "Nama: " . $nama . "\n";
      $text .= "Aplikasi: LAUNDRY\n\n";
      $text .= "⏰ *Kode OTP ini aktif selama 5 menit*\n";
      $text .= "Jangan bagikan kode ini kepada siapapun!";

      $wa = $this->model('WA_YCloud')->send($hp, $text);
      $statusOk = !empty($wa['status']) && ($wa['status'] === true || $wa['status'] === 'success');
      $httpOk = ((int) ($wa['code'] ?? 0) === 200);

      if (!$statusOk || !$httpOk) {
         $err = $wa['error'] ?? 'WhatsApp tidak terkirim';
         $this->model('Log')->write('[GenerateKey/reqPin] WA Failed: ' . $err);
         echo json_encode(['ok' => 0, 'msg' => 'GAGAL KIRIM PIN: ' . $err]);
         return;
      }

      $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
      $today = date('Ymd');
      $waRes = [
         'status' => $wa['status'],
         'data' => $wa['data'] ?? [],
         'error' => $wa['error'] ?? null,
         'http_code' => $wa['code'] ?? 0,
      ];
      $do = $this->helper('Notif')->insertOTP($waRes, $today, $hp, $otp, $idCabang);
      if (($do['errno'] ?? 1) != 0) {
         echo json_encode(['ok' => 0, 'msg' => 'Notif gagal disimpan: ' . ($do['error'] ?? '')]);
         return;
      }

      $up = $this->db(0)->update('user', [
         'otp' => $otpEnc,
         'otp_active' => $expiry,
      ], $where);

      if (($up['errno'] ?? 1) != 0) {
         echo json_encode(['ok' => 0, 'msg' => 'PIN gagal disimpan: ' . ($up['error'] ?? '')]);
         return;
      }

      echo json_encode([
         'ok' => 1,
         'msg' => 'PERMINTAAN PIN BERHASIL, AKTIF 5 MENIT',
      ]);
   }

   /**
    * Verifikasi PIN OTP yang dikirim ke WA sebelum boleh generate.
    */
   public function verifyPin()
   {
      header('Content-Type: application/json; charset=utf-8');

      $pin = trim((string) ($_POST['pin'] ?? ''));
      if ($pin === '' || !preg_match('/^\d{4}$/', $pin)) {
         echo json_encode(['ok' => 0, 'msg' => 'PIN harus 4 digit']);
         return;
      }

      $user = $_SESSION[URL::SESSID]['user'] ?? [];
      $hp = (string) ($user['no_user'] ?? '');
      $username = (string) ($user['username'] ?? '');
      if ($username === '' && $hp !== '') {
         $username = $this->model('Enc')->username($hp);
      }
      if ($username === '') {
         echo json_encode(['ok' => 0, 'msg' => 'Session user tidak lengkap']);
         return;
      }

      $otp = $this->model('Enc')->otp($pin);
      $dataUser = $this->helper('User')->pin_today($username, $otp);
      if (!$dataUser) {
         echo json_encode(['ok' => 0, 'msg' => 'PIN tidak cocok / sudah kadaluarsa. Request PIN dulu.']);
         return;
      }

      $_SESSION[URL::SESSID]['generate_key_pin_ok'] = time();
      echo json_encode(['ok' => 1, 'msg' => 'PIN OK. Silakan generate key.']);
   }

   /**
    * Generate key 4 digit baru. Plaintext hanya di response ini.
    */
   public function generate()
   {
      header('Content-Type: application/json; charset=utf-8');

      if (!$this->isPinUnlocked()) {
         echo json_encode(['ok' => 0, 'msg' => 'Request & verifikasi PIN dulu']);
         return;
      }

      $plain = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
      $hash = $this->model('Enc')->otp($plain);
      $idUser = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
      $now = date('Y-m-d H:i:s');

      $this->db(0)->delete('admin_access_key', 'id > 0');
      $do = $this->db(0)->insert('admin_access_key', [
         'key_hash' => $hash,
         'created_by' => $idUser,
         'created_at' => $now,
      ]);

      if (($do['errno'] ?? 1) != 0) {
         echo json_encode(['ok' => 0, 'msg' => $do['error'] ?? 'Gagal simpan key']);
         return;
      }

      unset($_SESSION[URL::SESSID]['generate_key_pin_ok']);

      echo json_encode([
         'ok' => 1,
         'key' => $plain,
         'created_at' => $now,
         'msg' => 'Simpan key ini sekarang. Tidak bisa dilihat lagi.',
      ]);
   }

   private function isPinUnlocked(): bool
   {
      $ts = (int) ($_SESSION[URL::SESSID]['generate_key_pin_ok'] ?? 0);
      if ($ts < 1) {
         return false;
      }
      return (time() - $ts) <= 300;
   }

   private function getKeyMeta()
   {
      $rows = $this->db(0)->get_order('admin_access_key', 'id DESC');
      if (!is_array($rows)) {
         $rows = $rows ? iterator_to_array($rows) : [];
      }
      if (count($rows) < 1) {
         return null;
      }
      return $rows[0];
   }

   private function maskHp(string $hp): string
   {
      $digits = preg_replace('/\D+/', '', $hp);
      $len = strlen($digits);
      if ($len < 6) {
         return $digits;
      }
      return substr($digits, 0, 4) . str_repeat('*', max(0, $len - 7)) . substr($digits, -3);
   }
}
