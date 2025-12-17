<?php

class Gaji extends Controller
{
   public function __construct()
   {
      $this->operating_data();
   }

   public function index()
   {
      $viewData = 'gaji/rekap_gaji_bulanan';

      $userID = 0;
      $data = [];

      if (isset($_POST['m'])) {
         $userID = $_POST['user_id'];
         $date = $_POST['Y'] . "-" . $_POST['m'];
         $bulan = ['bulan' => $_POST['m'], 'tahun' => $_POST['Y']];
      } else {
         $date = date('Y-m');
         $bulan = ['bulan' => date('m'), 'tahun' => date('Y')];
      }

      $data_operasi = ['title' => 'Gaji Bulanan - Rekap'];

      $data = $this->helper("D_Gaji")->data_olah($userID, $date, $_SESSION[URL::SESSID]['user']['book']);
      $data['tanggal'] = $bulan;
      $data['user']['id'] = $userID;

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData, $data);
   }

   public function set_gaji_laundry()
   {
      $penjualan = $_POST['penjualan_jenis'];
      $id_layanan = $_POST['layanan'];
      $id_user = $_POST['id_user'];
      $fee = $_POST['fee'];
      $target = $_POST['target'];
      $bonus_target = $_POST['bonus_target'];
      $max_target = $_POST['max_target'];

      if ($data_main < 1) {
         $data = [
            'id_karyawan' => $id_user,
            'jenis_penjualan' => $penjualan,
            'id_layanan' => $id_layanan,
            'gaji_laundry' => $fee,
            'target' => $target,
            'bonus_target' => $bonus_target,
            'max_target' => $max_target
         ];
         $do = $this->db(0)->insert('gaji_laundry', $data);
         if ($do['errno'] == 0) {
            echo 1;
         } else {
            echo $do['error'];
         }
      } else {
         echo "DATA SUDAH TER-SET!";
      }
   }

   public function set_gaji_pengali()
   {
      $id_pengali = $_POST['pengali'];
      $id_user = $_POST['id_user'];
      $fee = $_POST['fee'];

      if ($data_main < 1) {
         $data = [
            'id_karyawan' => $id_user,
            'id_pengali' => $id_pengali,
            'gaji_pengali' => $fee
         ];
         $do = $this->db(0)->insert('gaji_pengali', $data);
         if ($do['errno'] == 0) {
            echo 1;
         } else {
            echo $do['error'];
         }
      } else {
         echo "DATA SUDAH TER-SET!";
      }
   }

   public function set_harian_tunjangan()
   {
      $id_pengali = $_POST['pengali'];
      $id_user = $_POST['id_user'];
      $tgl = $_POST['tgl'];
      $qty = $_POST['qty'];
      $data = [
         'id_karyawan' => $id_user,
         'id_pengali' => $id_pengali,
         'qty' => $qty,
         'tgl' => $tgl
      ];
      $where = "id_karyawan = " . $id_user . " AND id_pengali = " . $id_pengali . " AND tgl = '" . $tgl . "'";
      echo $this->tambahTunjangan($data, $where);
   }

   function tambahTunjangan($data, $where)
   {
      $cek = $this->db(0)->count_where('gaji_pengali_data', $where);
      if ($cek < 1) {
         $do = $this->db(0)->insert('gaji_pengali_data', $data);
         if ($do['errno'] == 0) {
            return 1;
         } else {
            return 404;
         }
      } else {
         return "DATA SUDAH TER-SET!";
      }
   }

   public function updateCell()
   {
      $table  = $_POST['table'];
      $id = $_POST['id'];
      $value = $_POST['value'];
      $col = $_POST['col'];

      $where = "";
      switch ($table) {
         case 'gaji_laundry':
            $where = "id_gaji_laundry = " . $id;
            break;
         case 'gaji_pengali':
            $where = "id_gaji_pengali = " . $id;
            break;
         case 'gaji_pengali_data':
            $where = "id_pengali_data = " . $id;
            break;
      }

      $set = [
         $col => $value
      ];
      $this->db(0)->update($table, $set, $where);
   }

   function penetapan($userID, $date, $book)
   {
      $data_olah = $this->helper("D_Gaji")->data_olah($userID, $date, $book);
      $data = $this->helper("D_Gaji")->rekap_final($data_olah, $date, $userID, $book);
      $tetapkan = $this->helper('D_Gaji')->tetapkan($userID, $date, $data);
      return $tetapkan;
   }

   function tambah_harian_malam() {}

   public function tetapkan($mode = 0)
   {
      $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m', strtotime("-1 month"));
      $book = substr($date, 0, 4);

      if ($mode == 1) {
         $userID = $_POST['user_id'];

         //HARIAN
         $qty = $this->db(0)->count_where('absen', "id_karyawan = " . $userID . " AND jenis <> 1 AND tanggal LIKE '" . $date . "%'");
         if ($qty > 0) {
            $id_pengali = 3;
            $data = [
               'id_karyawan' => $userID,
               'id_pengali' => $id_pengali,
               'qty' => $qty,
               'tgl' => $date
            ];
            $where = "id_karyawan = " . $userID . " AND id_pengali = " . $id_pengali . " AND tgl = '" . $date . "'";
            $tambahkan_tunjangan = $this->tambahTunjangan($data, $where);
            if ($tambahkan_tunjangan == 404) {
               echo "ERROR INSERT HARIAN\n";
               exit();
            }
         }

         //MALAM
         $qty = $this->db(0)->count_where('absen', "id_karyawan = " . $userID . " AND jenis = 1 AND tanggal LIKE '" . $date . "%'");
         if ($qty > 0) {
            $id_pengali = 5;
            $data = [
               'id_karyawan' => $userID,
               'id_pengali' => $id_pengali,
               'qty' => $qty,
               'tgl' => $date
            ];
            $where = "id_karyawan = " . $userID . " AND id_pengali = " . $id_pengali . " AND tgl = '" . $date . "'";
            $tambahkan_tunjangan = $this->tambahTunjangan($data, $where);
            if ($tambahkan_tunjangan == 404) {
               echo "ERROR INSERT MALAM\n";
               exit();
            }
         }

         //TUNJANGAN
         $id_pengali = 4;
         $data = [
            'id_karyawan' => $userID,
            'id_pengali' => $id_pengali,
            'qty' => 1,
            'tgl' => $date
         ];
         $where = "id_karyawan = " . $userID . " AND id_pengali = " . $id_pengali . " AND tgl = '" . $date . "'";
         $tambahkan_tunjangan = $this->tambahTunjangan($data, $where);
         if ($tambahkan_tunjangan == 404) {
            echo "ERROR INSERT TUNJANGAN";
            exit();
         }

         $tetapkan = $this->penetapan($userID, $date, $book);
         echo $tetapkan;
      } else {
         $karyawan = $this->db(0)->get_cols_where("user", "id_user", "en = 1", 1);
         foreach ($karyawan as $k) {
            $userID = $k['id_user'];

            //HARIAN
            $qty = $this->db(0)->count_where('absen', "id_karyawan = " . $userID . " AND jenis <> 1 AND tanggal LIKE '" . $date . "%'");
            if ($qty > 0) {
               $id_pengali = 3;
               $data = [
                  'id_karyawan' => $userID,
                  'id_pengali' => $id_pengali,
                  'qty' => $qty,
                  'tgl' => $date
               ];
               $where = "id_karyawan = " . $userID . " AND id_pengali = " . $id_pengali . " AND tgl = '" . $date . "'";
               $tambahkan_tunjangan = $this->tambahTunjangan($data, $where);
               if ($tambahkan_tunjangan == 404) {
                  echo "ERROR INSERT HARIAN\n";
                  exit();
               }
            }

            //MALAM
            $qty = $this->db(0)->count_where('absen', "id_karyawan = " . $userID . " AND jenis = 1 AND tanggal LIKE '" . $date . "%'");
            if ($qty > 0) {
               $id_pengali = 5;
               $data = [
                  'id_karyawan' => $userID,
                  'id_pengali' => $id_pengali,
                  'qty' => $qty,
                  'tgl' => $date
               ];
               $where = "id_karyawan = " . $userID . " AND id_pengali = " . $id_pengali . " AND tgl = '" . $date . "'";
               $tambahkan_tunjangan = $this->tambahTunjangan($data, $where);
               if ($tambahkan_tunjangan == 404) {
                  echo "ERROR INSERT MALAM\n";
                  exit();
               }
            }


            //TUNJANGAN
            $id_pengali = 4;
            $data = [
               'id_karyawan' => $userID,
               'id_pengali' => $id_pengali,
               'qty' => 1,
               'tgl' => $date
            ];
            $where = "id_karyawan = " . $userID . " AND id_pengali = " . $id_pengali . " AND tgl = '" . $date . "'";
            $tambahkan_tunjangan = $this->tambahTunjangan($data, $where);
            if ($tambahkan_tunjangan == 404) {
               echo "ERROR INSERT TUNJANGAN\n";
               exit();
            }

            $tetapkan = $this->penetapan($userID, $date, $book);
         }
         echo "PENETAPAN GAJI PERIODE " . $date . " SELESAI\n";
      }
   }
}
