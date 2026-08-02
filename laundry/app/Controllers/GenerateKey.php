<?php

/**
 * Generate Admin Access Key (4 digit).
 * Hanya privilege 100. Wajib PIN sebelum generate. Key hanya tampil sekali.
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

      $this->view('layout', ['data_operasi' => ['title' => 'Generate Key']]);
      $this->view('tools/generate_key', [
         'has_key' => !empty($meta),
         'created_at' => $meta['created_at'] ?? null,
         'pin_ok' => $pinOk,
      ]);
   }

   /**
    * Verifikasi PIN (OTP login) sebelum boleh generate.
    */
   public function verifyPin()
   {
      header('Content-Type: application/json; charset=utf-8');

      $pin = trim((string) ($_POST['pin'] ?? ''));
      if ($pin === '' || !preg_match('/^\d{4,8}$/', $pin)) {
         echo json_encode(['ok' => 0, 'msg' => 'PIN tidak valid']);
         return;
      }

      $hp = (string) ($_SESSION[URL::SESSID]['user']['no_user'] ?? '');
      if ($hp === '') {
         // fallback: beberapa session menyimpan nomor di field lain
         $hp = (string) ($_SESSION[URL::SESSID]['user']['nomor'] ?? '');
      }
      $username = (string) ($_SESSION[URL::SESSID]['user']['username'] ?? '');
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
         $cekAdmin = $this->helper('User')->pin_admin_today($otp);
         if ($cekAdmin < 1) {
            echo json_encode(['ok' => 0, 'msg' => 'PIN tidak cocok / sudah kadaluarsa']);
            return;
         }
      }

      $_SESSION[URL::SESSID]['generate_key_pin_ok'] = time();
      echo json_encode(['ok' => 1, 'msg' => 'PIN OK']);
   }

   /**
    * Generate key 4 digit baru. Plaintext hanya di response ini.
    */
   public function generate()
   {
      header('Content-Type: application/json; charset=utf-8');

      if (!$this->isPinUnlocked()) {
         echo json_encode(['ok' => 0, 'msg' => 'Masukkan PIN dulu']);
         return;
      }

      $plain = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
      $hash = $this->model('Enc')->otp($plain);
      $idUser = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
      $now = date('Y-m-d H:i:s');

      // Hanya satu key aktif: hapus lama
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

      // Wajib PIN lagi untuk generate berikutnya
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
      // PIN unlock berlaku 5 menit
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
}
