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
            header('Location: ' . URL::BASE_URL . "Penjualan");
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
      if (isset($_SESSION[URL::SESSID]['captcha'])) {
         if ($_SESSION[URL::SESSID]['captcha'] <> $cap) {
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
      $data_user = $this->data('User')->pin_today($username, $otp);
      if ($data_user) {
         $this->login_parameter($data_user);
         print_r($this->login_ok($username, $no_user));
      } else {
         $cek = $this->data('User')->pin_admin_today($otp);
         if ($cek > 0) {
            $data_user = $this->data('User')->get_data_user($username);
            $this->login_parameter($data_user);
            print_r($this->login_ok($username, $no_user));
         } else {
            $_SESSION[URL::SESSID]['captcha'] = "HJFASD7FD89AS7FSDHFD68FHF7GYG7G47G7G7G674GRGVFTGB7G6R74GHG3Q789631765YGHJ7RGEYBF67";
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
      $this->data('User')->last_login($username);
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
            $res_f = [
               'code' => 1,
               'msg' => "GUNAKAN PIN HARI INI"
            ];
         } else {
            $otp = rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);
            $otp_enc = $this->model("Enc")->otp($otp);
            $text = $otp . " (" . $cek['nama_user'] . ") - " . URL::APP_SNAME;
            $hp = $cek['no_user'];

            $res = $this->model('WA_YCloud')->send($hp, $text);

            if ($res['status']) {
               $do = $this->data('Notif')->insertOTP($res, $today, $hp_input, $text, $id_cabang);

               if ($do['errno'] == 0) {
                  $set = "otp = '" . $otp_enc . "', otp_active = '" . $today . "'";
                  $up = $this->db(0)->update('user', $set, $where);
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
               $res_f = [
                  'code' => 0,
                  'msg' => $res['error']
               ];
            }
         } //ok
      } else {
         $_SESSION[URL::SESSID]['captcha'] = "HJFASD7FD89AS7FSDHFD68FHF7GYG7G47G7G7G674GRGVFTGB7G6R74GHG3Q789631765YGHJ7RGEYBF67";
         $res_f = [
            'code' => 10,
            'msg' => "NOMOR WHATSAPP TIDAK TERDAFTAR"
         ];
      }
      print_r(json_encode($res_f));
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
      $_SESSION[URL::SESSID]['captcha'] = $captcha_code;

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
      $_SESSION[URL::SESSID]['log_mode'] = 0;
      $data_user = $this->dataSynchrone($id);
      $this->save_cookie($data_user);
      session_destroy();
   }

   public function log_mode()
   {
      $mode = $_POST['mode'];
      unset($_SESSION[URL::SESSID]['log_mode']);
      $_SESSION[URL::SESSID]['log_mode'] = $mode;
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
