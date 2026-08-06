<?php

class Cabang_Lokasi extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function content()
   {
      $log = $this->dCabang;
      $data['geo'] = [
         'lat' => floatval($log['latt'] ?? 0),
         'long' => floatval($log['long'] ?? 0)
      ];
      $data['kec'] = $log['area'] ?? ['' => 'Pilih Kecamatan'];
      if (!is_array($data['kec'])) {
         $data['kec'] = ['' => 'Pilih Kecamatan'];
      }
      $this->view('cabang/content', $data);
   }

   public function update()
   {
      $id_cabang = $this->id_cabang;
      if (empty($id_cabang)) {
         echo "Cabang tidak valid";
         return;
      }

      $set = [
         'latt' => $_POST['lat'] ?? 0,
         'long' => $_POST['long'] ?? 0,
         'gmaps' => trim((string) ($_POST['gmaps'] ?? '')),
         'nama' => $_POST['nama'] ?? '',
         'hp' => $_POST['hp'] ?? '',
         'alamat' => $_POST['alamat'] ?? '',
         'rent' => $_POST['rent'] ?? 0
      ];

      if (isset($_POST['kecamatan']) && $_POST['kecamatan'] !== '') {
         $set['area'] = $_POST['kecamatan'];
      }

      $where = "id_cabang = " . $id_cabang;
      $result = $this->db(0)->update('cabang', $set, $where);

      if (isset($result['errno']) && $result['errno'] == 0) {
         $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
         echo 0;
      } else {
         echo $result['error'] ?? 'Gagal update';
      }
   }
}
