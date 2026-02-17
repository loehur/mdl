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
      $karyawan = intval($_POST['f1'] ?? 0);
      $jumlah = intval($_POST['f2'] ?? 0);
      $pembuat = intval($_POST['f3'] ?? 0);
      $today = date('Y-m-d');
      $metode = intval($_POST['metode'] ?? 1);
      $note = $_POST['note'] ?? '';

      if ($metode == 1) {
         $sm = 3;
      } else {
         $sm = 2;
      }

      // Cek duplicate (double-click) - transaksi sama dalam 5 detik terakhir
      $where_dup = $this->wCabang . " AND jenis_transaksi = 5 AND jenis_mutasi = 2 AND jumlah = $jumlah AND id_user = $pembuat AND id_client = $karyawan AND insertTime >= DATE_SUB(NOW(), INTERVAL 5 SECOND)";
      $data_main = $this->db(0)->count_where('kas', $where_dup);

      $ref_f = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);
      if ($data_main < 1) {
         $data = [
            'id_kas' => (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6),
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
         $do = $this->db(0)->insert('kas', $data);
         if ($do['errno'] == 0) {
            echo 1;
         } else {
            $this->model('Log')->write("[Kasbon::insert] Error: " . ($do['error'] ?? '') . " | Query: " . ($do['query'] ?? ''));
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Gagal menyimpan kasbon.']);
         }
      } else {
         header('HTTP/1.1 409 Conflict');
         echo json_encode(['error' => 'Transaksi sudah terinput. Jangan double-click.']);
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
      $this->db(0)->update('kas', $set, $where);
   }

   public function batal_kasbon()
   {
      $id = $_POST['id'];
      $set = "sumber_dana = 0, status_transaksi = 4";
      $where = "id_kasbon = " . $id;
      $this->db(0)->update('kas', $set, $where);
   }
}
