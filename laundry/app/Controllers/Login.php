<?php
class Login extends Controller
{
   public function index()
   {
      $this->cek_cookie();
      $data = [];
      if (isset($_COOKIE['MDLNUMS'])) {
         $data = unserialize($this->model("Enc")->dec_2($_COOKIE['MDLNUMS']));
      }
      if (isset($_SESSION[URL::SESSID]['login'])) {
         if ($_SESSION[URL::SESSID]['login'] == TRUE) {
            header('Location: ' . URL::BASE_URL . "Antrian");
         } else {
            $this->view('login', $data);
         }
      } else {
         $this->view('login', $data);
      }
   }

   function cek_cookie()
   {
      if (isset($_COOKIE[URL::SESSID])) {
         $cookie_value = $this->model("Enc")->dec_2($_COOKIE[URL::SESSID]);

         $user_data = unserialize($cookie_value);
         if (isset($user_data['username']) && isset($user_data['no_user']) && isset($user_data['device'])) {
            $no_user = $user_data['no_user'];
            $username = $this->model("Enc")->username($no_user);

            $device = $_SERVER['HTTP_USER_AGENT'];
            if ($username == $user_data['username'] && $user_data['device'] == $device) {
               $_SESSION[URL::SESSID]['login'] = TRUE;
               $this->parameter($user_data);
               $this->save_cookie($user_data);
            }
         }
      }
   }

   function save_cookie($data_user)
   {
      $device = $_SERVER['HTTP_USER_AGENT'];
      $data_user['device'] = $device;
      $cookie_value = $this->model("Enc")->enc_2(serialize($data_user));
      setcookie(URL::SESSID, $cookie_value, time() + 86400, "/");
   }

   function save_nums($usernum)
   {
      //simpan list hp
      if (!isset($_COOKIE['MDLNUMS'])) {
         $mdlnums = [1 => $usernum];
         $nums_value = $this->model("Enc")->enc_2(serialize($mdlnums));
         setcookie("MDLNUMS", $nums_value, time() + (86400 * 7), "/");
      } else {
         $max_saved = 6;
         $nums = $this->model("Enc")->dec_2($_COOKIE['MDLNUMS']);
         $nums = unserialize($nums);
         if (is_array($nums)) {
            $cek = [];
            foreach ($nums as $key => $n) {
               if ($n == $usernum) {
                  array_push($cek, $key);
               }
            }

            $max = max(array_keys($nums));

            if (count($cek) > 0) {
               //hapus diri sendiri dulu
               foreach ($cek as $val) {
                  unset($nums[$val]);
               }
               $nums[$max + 1] = $usernum;
            } else {
               if (count($nums) >= $max_saved) {
                  $min = min(array_keys($nums));
                  unset($nums[$min]);
               }
               $nums[$max + 1] = $usernum;
            }
         }
         $nums_value = $this->model("Enc")->enc_2(serialize($nums));
         setcookie("MDLNUMS", $nums_value, time() + (86400 * 7), "/");
      }
   }

   public function cek_login()
   {
      $no_user = $_POST["username"];
      if (strlen($no_user) < 10 || strlen($no_user) > 13) {
         $res = [
            'code' => 0,
            'msg' => "NOMOR WHATSAPP TIDAK VALID"
         ];
         print_r(json_encode($res));
         exit();
      }

      $pin = $_POST["pin"];
      if (strlen($pin) == 0) {
         $res = [
            'code' => 0,
            'msg' => "PIN TIDAK BOLEH KOSONG"
         ];
         print_r(json_encode($res));
         exit();
      }

      $cap = $_POST["cap"];
      if (isset($_SESSION['captcha'])) {
         if ($_SESSION['captcha'] <> $cap) {
            $res = [
               'code' => 10,
               'msg' => "CAPTCHA SALAH"
            ];
            print_r(json_encode($res));
            exit();
         }
      } else {
         $res = [
            'code' => 10,
            'msg' => "CAPTCHA ERROR"
         ];
         print_r(json_encode($res));
         exit();
      }


      $username = $this->model("Enc")->username($no_user);
      $otp = $this->model("Enc")->otp($pin);
      $data_user = $this->helper('User')->pin_today($username, $otp);
      if ($data_user) {
         //cek ada cabang
         $id_outlet = $_POST['outlet'];
         if (strlen($id_outlet) > 0) {
            $cek_cb = $this->db(0)->count_where("cabang", "id_cabang = " . $id_outlet);
            if ($cek_cb > 0) {
               $up = $this->db(0)->update("user", [
                  'id_cabang' => $id_outlet
               ], "id_user = " . $data_user['id_user']);
               if ($up['errno'] == 0) {
                  $data_user = $this->helper('User')->pin_today($username, $otp);
               }
            }
         }
         $this->login_parameter($data_user);
         print_r($this->login_ok($username, $no_user));
      } else {
         $cek = $this->helper('User')->pin_admin_today($otp);
         if ($cek > 0) {
            $data_user = $this->helper('User')->get_data_user($username);
            $this->login_parameter($data_user);
            print_r($this->login_ok($username, $no_user));
         } else {
            $_SESSION['captcha'] = "HJFASD7FD89AS7FSDHFD68FHF7GYG7G47G7G7G674GRGVFTGB7G6R74GHG3Q789631765YGHJ7RGEYBF67";
            $res = [
               'code' => 10,
               'msg' => "NOMOR WHATSAPP DAN PIN TIDAK COCOK"
            ];
            print_r(json_encode($res));
         }
      }
   }

   function login_parameter($data_user)
   {
      $this->parameter($data_user);
      $this->save_cookie($data_user);
   }

   function login_ok($username, $no_user)
   {
      // LAST LOGIN
      $this->helper('User')->last_login($username);
      //LOGIN
      $_SESSION[URL::SESSID]['login'] = TRUE;
      $this->save_nums($no_user);
      $res = [
         'code' => 11,
         'msg' => "Login Success"
      ];
      return json_encode($res);
   }

   function req_pin()
   {
      try {
         // Validasi input POST
         if (!isset($_POST["hp"])) {
            $res_f = [
               'code' => 0,
               'msg' => "NOMOR WHATSAPP TIDAK DITEMUKAN"
            ];
            print_r(json_encode($res_f));
            exit();
         }

         $hp_input = $_POST["hp"];
         $hp = (int) filter_var($hp_input, FILTER_SANITIZE_NUMBER_INT);
         //cek

         if (strlen($hp_input) < 10 || strlen($hp_input) > 13) {
            $res_f = [
               'code' => 0,
               'msg' => "NOMOR WHATSAPP TIDAK VALID"
            ];
            print_r(json_encode($res_f));
            exit();
         }

         $username = $this->model("Enc")->username($hp);
         $where = "username = '" . $username . "' AND en = 1";
         $today = date("Ymd");
         $cek = $this->db(0)->get_where_row('user', $where);
         if (isset($cek['otp_active'])) {
            $id_cabang = $cek['id_cabang'];
            if ($cek['otp_active'] == $today) {
               $cek_deliver = $this->helper('Notif')->cek_deliver($hp_input, $today, $id_cabang);
               if (isset($cek_deliver['text'])) {
                  $hp = $cek['no_user'];
                  $text = $cek_deliver['text'];
                  $res = $this->send_wa_ycloud($hp, $text);
                  if ($res['status']) {
                     $up = $this->db(0)->update('notif', [
                        'id_api_2' => $res['data']['message_id']
                     ], "id_notif = " . $cek_deliver['id_notif']);
                     if ($up['errno'] == 0) {
                        $res_f = [
                           'code' => 1,
                           'msg' => "PERMINTAAN ULANG PIN BERHASIL, AKTIF 1 HARI"
                        ];
                     } else {
                        $res_f = [
                           'code' => 0,
                           'msg' => $up['error']
                        ];
                     }
                  } else {
                     // Cek jika CSW expired
                     if (isset($res['csw_expired']) && $res['csw_expired']) {
                        $phone_sent = isset($res['phone_sent']) ? $res['phone_sent'] : 'unknown';
                        $res_f = [
                           'code' => 0,
                           'msg' => "CSW Expired untuk nomor {$phone_sent}. Pastikan Anda sudah mengirim pesan ke nomor WhatsApp bisnis dalam 24 jam terakhir."
                        ];
                     } else {
                        $res_f = [
                           'code' => 0,
                           'msg' => $res['error']
                        ];
                     }
                  }
               } else {
                  $res_f = [
                     'code' => 1,
                     'msg' => "GUNAKAN PIN HARI INI"
                  ];
               }
            } else {
               $otp = rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);
               $otp_enc = $this->model("Enc")->otp($otp);
               $text = $otp . " (" . $cek['nama_user'] . ") - LAUNDRY";
               $hp = $cek['no_user'];

               $res = $this->send_wa_ycloud($hp, $text);

               if ($res['status']) {
                  $do = $this->helper('Notif')->insertOTP($res, $today, $hp_input, $text, $id_cabang);

                  if ($do['errno'] == 0) {
                     $up = $this->db(0)->update('user', [
                        'otp' => $otp_enc,
                        'otp_active' => $today
                     ], $where);
                     if ($up['errno'] == 0) {
                        $res_f = [
                           'code' => 1,
                           'msg' => "PERMINTAAN PIN BERHASIL, AKTIF 1 HARI"
                        ];
                     } else {
                        $res_f = [
                           'code' => 0,
                           'msg' => $up['error']
                        ];
                     }
                  } else {
                     $res_f = [
                        'code' => 0,
                        'msg' => $do['error']
                     ];
                  }
               } else {
                  // Cek jika CSW expired
                  if (isset($res['csw_expired']) && $res['csw_expired']) {
                     $phone_sent = isset($res['phone_sent']) ? $res['phone_sent'] : 'unknown';
                     $res_f = [
                        'code' => 0,
                        'msg' => "CSW Expired untuk nomor {$phone_sent}. Pastikan Anda sudah mengirim pesan ke nomor WhatsApp bisnis dalam 24 jam terakhir."
                     ];
                  } else {
                     $res_f = [
                        'code' => 0,
                        'msg' => $res['error']
                     ];
                  }
               }
            }
         } else {
            $_SESSION['captcha'] = "HJFASD7FD89AS7FSDHFD68FHF7GYG7G47G7G7G674GRGVFTGB7G6R74GHG3Q789631765YGHJ7RGEYBF67";
            $res_f = [
               'code' => 10,
               'msg' => "NOMOR WHATSAPP TIDAK TERDAFTAR"
            ];
         }
         print_r(json_encode($res_f));
      } catch (Exception $e) {
         // Log the exception
         if (method_exists($this, 'model')) {
            try {
               $this->model('Log')->write("[req_pin] Exception: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            } catch (Exception $logEx) {
               // Log gagal, tidak apa-apa
            }
         }
         
         // Return user-friendly error
         $res_f = [
            'code' => 0,
            'msg' => "TERJADI KESALAHAN SISTEM, SILAHKAN COBA LAGI"
         ];
         print_r(json_encode($res_f));
      }
   }

   /**
    * Kirim WhatsApp menggunakan YCloud API (message_mode free)
    * @param string $phone Nomor telepon dengan format 628xxx
    * @param string $message Pesan yang akan dikirim
    * @return array Response dengan format ['status' => bool, 'data' => array, 'error' => string]
    */
   private function send_wa_ycloud($phone, $message)
   {
      // Normalisasi nomor telepon - support berbagai format:
      // 08xxx, 628xxx, +628xxx -> menjadi +628xxx
      $original_phone = $phone;
      
      // Hapus semua karakter non-digit terlebih dahulu
      $phone = preg_replace('/[^0-9]/', '', $phone);
      
      // Konversi ke format +628xxx
      if (substr($phone, 0, 1) === '0') {
         // 08xxx -> +628xxx
         $phone = '+62' . substr($phone, 1);
      } elseif (substr($phone, 0, 2) === '62') {
         // 628xxx -> +628xxx
         $phone = '+' . $phone;
      } else {
         // Format lain, tambahkan +62 di depan
         $phone = '+62' . $phone;
      }
      
      // Log untuk debugging
      $this->model('Log')->write("[send_wa_ycloud] Original: {$original_phone}, Normalized: {$phone}");
      
      // Ambil last_message_at dari tabel wa_customers (bukan notif)
      // Tabel wa_customers ada di database 100, bukan database 0
      $phone_without_plus = str_replace('+', '', $phone);
      $where = "wa_number = '" . $phone . "' OR wa_number = '" . $phone_without_plus . "'";
      $customer = $this->db(100)->get_where_row('wa_customers', $where);
      
      if ($customer && isset($customer['last_message_at'])) {
         $lastMessageAt = $customer['last_message_at'];
         $this->model('Log')->write("[send_wa_ycloud] Found customer data - Last message: {$lastMessageAt}");
      } else {
         $lastMessageAt = date('Y-m-d H:i:s');
         $this->model('Log')->write("[send_wa_ycloud] No customer data found, using current time: {$lastMessageAt}");
      }
      
      // Prepare data untuk API
      $apiData = [
         'phone' => $phone,
         'message' => $message,
         'message_mode' => 'free',
         'last_message_at' => $lastMessageAt
      ];
      
      // URL API endpoint
      $apiUrl = 'https://api.nalju.com/WhatsApp/send';
      
      // Inisialisasi cURL
      $ch = curl_init($apiUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
         'Content-Type: application/json'
      ]);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      
      // Execute request
      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      curl_close($ch);
      
      // Log request dan response
      $this->model('Log')->write("[send_wa_ycloud] Phone: {$phone}, Request: " . json_encode($apiData) . ", Response: {$response}, HTTP Code: {$httpCode}");
      
      // Handle error cURL
      if ($curlError) {
         return [
            'status' => false,
            'error' => 'cURL Error: ' . $curlError,
            'data' => [],
            'csw_expired' => false
         ];
      }
      
      // Parse response
      $result = json_decode($response, true);
      
      // Check HTTP code dan response
      if ($httpCode == 200 && isset($result['success']) && $result['success']) {
         return [
            'status' => true,
            'data' => [
               'id' => $result['data']['message_id'] ?? '',
               'message_id' => $result['data']['message_id'] ?? '',
               'status' => $result['data']['status'] ?? 'sent'
            ],
            'error' => '',
            'csw_expired' => false
         ];
      } else if ($httpCode == 400 && isset($result['data']['csw_expired']) && $result['data']['csw_expired']) {
         // CSW Expired - Log detail untuk debugging
         $this->model('Log')->write("[send_wa_ycloud] CSW EXPIRED - Phone: {$phone}, Message: {$message}, Response: " . json_encode($result));
         return [
            'status' => false,
            'error' => 'CSW Expired untuk nomor ' . $phone,
            'data' => $result['data'] ?? [],
            'csw_expired' => true,
            'phone_sent' => $phone  // Tambahkan nomor yang dikirim untuk debugging
         ];
      } else {
         // Error lainnya - Log detail lengkap
         $errorMsg = $result['message'] ?? 'Failed to send WhatsApp message';
         $this->model('Log')->write("[send_wa_ycloud] ERROR - Phone: {$phone}, HTTP Code: {$httpCode}, Error: {$errorMsg}, Full Response: " . json_encode($result));
         
         return [
            'status' => false,
            'error' => $errorMsg . " (HTTP {$httpCode})",
            'data' => $result['data'] ?? [],
            'csw_expired' => false,
            'http_code' => $httpCode,
            'full_response' => $result
         ];
      }
   }

   public function logout()
   {
      setcookie(URL::SESSID, 0, time() + 1, "/");
      session_destroy();
      header('Location: ' . URL::BASE_URL . "Penjualan/i");
   }

   public function captcha()
   {
      $captcha_code = rand(0, 9) . rand(0, 9);
      $_SESSION['captcha'] = $captcha_code;

      $target_layer = imagecreatetruecolor(25, 24);
      $captcha_background = imagecolorallocate($target_layer, 255, 255, 255);
      imagefill($target_layer, 0, 0, $captcha_background);
      $captcha_text_color = imagecolorallocate($target_layer, 0, 255, 0);
      imagestring($target_layer, 5, 5, 5, $captcha_code, $captcha_text_color);
      header("Content-type: image/jpeg");
      imagejpeg($target_layer);
   }

   function switchUser()
   {
      $id = $_POST['id'];
      $data_user = $this->dataSynchrone($id);
      $this->save_cookie($data_user);
   }

   public function log_mode()
   {
      $mode = $_POST['mode'];
      unset($_SESSION['log_mode']);
      $_SESSION['log_mode'] = $mode;
   }

   function get_client_ip()
   {
      $ipaddress = '';
      if (isset($_SERVER['HTTP_CLIENT_IP']))
         $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
      else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
         $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
      else if (isset($_SERVER['HTTP_X_FORWARDED']))
         $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
      else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
         $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
      else if (isset($_SERVER['HTTP_FORWARDED']))
         $ipaddress = $_SERVER['HTTP_FORWARDED'];
      else if (isset($_SERVER['REMOTE_ADDR']))
         $ipaddress = $_SERVER['REMOTE_ADDR'];
      else
         $ipaddress = 'UNKNOWN';
      return $ipaddress;
   }
}
