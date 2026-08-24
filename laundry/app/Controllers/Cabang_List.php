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
      $id_cabang = (int) ($_POST['id'] ?? 0);
      $result = $this->switchUserCabang($id_cabang);
      if (!$result['ok']) {
         echo $result['message'] ?? 'Gagal ganti cabang';
         return;
      }
      echo 0;
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
     * Menerima format baru (1203630…@g.us) dan lama (62812…-1625…@g.us).
     * @return array{ok:bool,value:?string,error:?string}
     */
   private function parseIdGroupFonnte($raw): array
   {
      $v = trim((string) $raw);
      if ($v === '') {
         return ['ok' => true, 'value' => null, 'error' => null];
      }
      if (preg_match('/^\d+(-\d+)?$/', $v)) {
         $v .= '@g.us';
      }
      if (!preg_match('/^\d+(-\d+)?@g\.us$/i', $v)) {
         return [
            'ok' => false,
            'value' => null,
            'error' => 'ID group Fonnte tidak valid (contoh: 1203630…@g.us atau 62812…-1625…@g.us)',
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

      if ($gmaps === '') {
         echo 'Link Google Maps belum terisi — pilih alamat dari pencarian';
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

   /** GET JSON: Google Maps browser API key (proxy api.nalju.com) */
   public function mapsConfig()
   {
      header('Content-Type: application/json; charset=utf-8');
      $this->session_cek(1);
      $this->helper('MapsConfigApi');
      echo json_encode(MapsConfigApi::get(), JSON_UNESCAPED_UNICODE);
   }

   /** POST JSON: Places autocomplete (proxy) */
   public function mapsAutocomplete()
   {
      header('Content-Type: application/json; charset=utf-8');
      $this->session_cek(1);
      $body = json_decode((string) file_get_contents('php://input'), true);
      if (!is_array($body)) {
         $body = $_POST;
      }
      $body = $this->applyKotaRestrictToPayload($body);
      $this->helper('MapsConfigApi');
      echo json_encode(MapsConfigApi::autocomplete($body), JSON_UNESCAPED_UNICODE);
   }

   /** POST JSON: Places place details (proxy) */
   public function mapsPlaceDetails()
   {
      header('Content-Type: application/json; charset=utf-8');
      $this->session_cek(1);
      $body = json_decode((string) file_get_contents('php://input'), true);
      if (!is_array($body)) {
         $body = $_POST;
      }
      $body = $this->applyKotaRestrictToPayload($body);
      $this->helper('MapsConfigApi');
      echo json_encode(MapsConfigApi::placeDetails($body), JSON_UNESCAPED_UNICODE);
   }

   /**
    * @return array{city_name:string,lat:?float,lng:?float}
    */
   private function resolveKotaRestrict(int $idKota): array
   {
      $out = ['city_name' => '', 'lat' => null, 'lng' => null];
      if ($idKota <= 0 || !is_array($this->dKota ?? null)) {
         return $out;
      }
      foreach ($this->dKota as $k) {
         if ((int) ($k['id_kota'] ?? 0) !== $idKota) {
            continue;
         }
         $out['city_name'] = trim((string) ($k['nama_kota'] ?? ''));
         $lat = (float) ($k['latt'] ?? 0);
         $lng = (float) ($k['longt'] ?? 0);
         if ($lat != 0.0 || $lng != 0.0) {
            $out['lat'] = $lat;
            $out['lng'] = $lng;
         }
         break;
      }
      return $out;
   }

   /**
    * @param array<string,mixed> $body
    * @return array<string,mixed>
    */
   private function applyKotaRestrictToPayload(array $body): array
   {
      $idKota = (int) ($body['id_kota'] ?? 0);
      $kota = $this->resolveKotaRestrict($idKota);
      if ($kota['lat'] !== null && $kota['lng'] !== null) {
         $body['lat'] = $kota['lat'];
         $body['lng'] = $kota['lng'];
         $body['hard_restrict'] = true;
         $body['restrict_radius'] = 25000;
         $body['restrict_lat'] = $kota['lat'];
         $body['restrict_lng'] = $kota['lng'];
      }
      if ($kota['city_name'] !== '') {
         $body['city_name'] = $kota['city_name'];
      }
      return $body;
   }
}
