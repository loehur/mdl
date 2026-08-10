<?php

class Cabang_List extends Controller
{

   public function __construct()
   {
      $this->operating_data();
   }
   public function index()
   {
      $data_operasi = ['title' => 'Data Cabang'];

      $table = 'cabang';
      // Tampilkan semua cabang termasuk cabang khusus training
      $data_cabang = $this->db(0)->get($table);

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('data_list/cabang', ['data_cabang' => $data_cabang]);
   }

   public function insert()
   {
      $this->session_cek(1);
      $parsed = $this->parseIdGroupFonnte($_POST['id_group_fonnte'] ?? '');
      if (!$parsed['ok']) {
         echo $parsed['error'];
         return;
      }
      $table  = 'cabang';
      $data = [
         'id_kota' => $_POST["kota"],
         'nama' => $_POST["nama"],
         'alamat' => $_POST["alamat"],
         'kode_cabang' => $_POST["kode_cabang"],
         'phone_number' => $_POST["phone_number"],
         'id_group_fonnte' => $parsed['value'],
         'wifi_pass' => $_POST["wifi_pass"] ?? '',
         'print_mode' => 'server',
         'rent' => $_POST["rent"] ?? 0
      ];
      $in = $this->db(0)->insert($table, $data);
      if ($in['errno'] == 0) {
         echo 0;
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      } else {
         echo $in['error'];
      }
   }

   public function selectCabang()
   {
      $this->session_cek(2);
      if ($this->isTrainingMode()) {
         echo "Tidak bisa ganti cabang saat Mode Training";
         return;
      }
      $id_cabang = (int) $_POST['id'];
      $trainId = $this->getTrainingCabangId();
      if ($trainId > 0 && $id_cabang === $trainId) {
         echo "Cabang training tidak bisa dipilih";
         return;
      }
      $table  = 'user';
      $set = [
         'id_cabang' => $id_cabang
      ];
      $where = "id_user = " . $_SESSION[URL::SESSID]['user']['id_user'];
      $this->db(0)->update($table, $set, $where);
      $_SESSION[URL::SESSID]['training']['active'] = false;
      $_SESSION[URL::SESSID]['training']['id_cabang_origin'] = $id_cabang;
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }

   public function selectBook()
   {
      $this->session_cek();
      $book = $_POST['book'];
      $set = [
         'book' => $book
      ];
      $where = "id_user = " . $_SESSION[URL::SESSID]['user']['id_user'];
      $up = $this->db(0)->update('user', $set, $where);
      if ($up['errno'] == 0) {
         echo 0;
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      } else {
         print_r($up);
      }
   }

   public function update()
   {
      $this->session_cek(1);
      $id = (int) ($_POST['id'] ?? 0);
      if ($id <= 0) {
         echo 'ID cabang tidak valid';
         return;
      }

      $parsed = $this->parseIdGroupFonnte($_POST['id_group_fonnte'] ?? '');
      if (!$parsed['ok']) {
         echo $parsed['error'];
         return;
      }

      $set = [
         'id_kota' => $_POST['kota'] ?? '',
         'nama' => $_POST['nama'] ?? '',
         'alamat' => $_POST['alamat'] ?? '',
         'kode_cabang' => $_POST['kode_cabang'] ?? '',
         'phone_number' => $_POST['phone_number'] ?? '',
         'id_group_fonnte' => $parsed['value'],
         'wifi_pass' => $_POST['wifi_pass'] ?? '',
         'rent' => $_POST['rent'] ?? 0,
      ];
      $where = "id_cabang = $id";
      $up = $this->db(0)->update('cabang', $set, $where);
      if ($up['errno'] == 0) {
         echo 0;
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      } else {
         echo $up['error'];
      }
   }

   /**
     * Parse ID group Fonnte (…@g.us). Kosong → null (hapus). Angka saja → tambah @g.us.
     * @return array{ok:bool,value:?string,error:?string}
     */
   private function parseIdGroupFonnte($raw): array
   {
      $v = trim((string) $raw);
      if ($v === '') {
         return ['ok' => true, 'value' => null, 'error' => null];
      }
      if (preg_match('/^\d+$/', $v)) {
         $v .= '@g.us';
      }
      if (!preg_match('/^\d+@g\.us$/i', $v)) {
         return [
            'ok' => false,
            'value' => null,
            'error' => 'ID group Fonnte tidak valid (contoh: 1203630…@g.us)',
         ];
      }
      return ['ok' => true, 'value' => $v, 'error' => null];
   }

   public function updateMaps()
   {
      $this->session_cek(1);
      $id = (int) ($_POST['id'] ?? 0);
      if ($id <= 0) {
         echo 'ID cabang tidak valid';
         return;
      }

      $latt = $_POST['latt'] ?? '';
      $long = $_POST['long'] ?? '';
      $gmaps = trim((string) ($_POST['gmaps'] ?? ''));

      if ($latt === '' || $long === '' || !is_numeric($latt) || !is_numeric($long)) {
         echo 'Latitude / Longitude tidak valid';
         return;
      }

      $set = [
         'latt' => (float) $latt,
         'long' => (float) $long,
         'gmaps' => $gmaps,
      ];
      $where = "id_cabang = $id";
      $up = $this->db(0)->update('cabang', $set, $where);
      if ($up['errno'] == 0) {
         echo 0;
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
      } else {
         echo $up['error'];
      }
   }
}
