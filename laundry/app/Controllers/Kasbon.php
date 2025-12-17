<?php

class Kasbon extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function insert()
   {
      $karyawan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $pembuat = $_POST['f3'];
      $today = date('Y-m-d');
      $metode = $_POST['metode'];
      $note = $_POST['note'];

      if ($metode == 1) {
         $sm = 3;
      } else {
         $sm = 2;
      }

      $ref_f = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'jenis_mutasi' => 2,
            'jenis_transaksi' => 5,
            'metode_mutasi' => $metode,
            'status_mutasi' => $sm,
            'jumlah' => $jumlah,
            'id_user' => $pembuat,
            'id_client' => $karyawan,
            'note_primary' => 'Kasbon',
            'note' => $note,
            'ref_finance' => $ref_f
         ];
         print_r($this->db(date('Y'))->insert('kas', $data));
      } else {
         echo "Tidak dapat Cashbon 2x/Hari";
      }
   }

   public function tarik_kasbon()
   {
      $id = $_POST['id'];
      $set = [
         'sumber_dana' => 2,
         'status_transaksi' => 2
      ];
      $where = "id_kasbon = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('kas', $set, $where);
   }

   public function batal_kasbon()
   {
      $id = $_POST['id'];
      $set = "sumber_dana = 0, status_transaksi = 4";
      $where = "id_kasbon = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('kas', $set, $where);
   }
}
