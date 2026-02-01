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

      $data_operasi = ['title' => 'Gaji Bulanan'];
      $data = $this->helper("D_Gaji")->data_olah($userID, $date);
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

      $data_main = $this->db(0)->count_where('gaji_laundry', "id_karyawan = " . $id_user . " AND jenis_penjualan = " . $penjualan . " AND id_layanan = " . $id_layanan);
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

      $data_main = $this->db(0)->count_where('gaji_pengali', "id_karyawan = " . $id_user . " AND id_pengali = " . $id_pengali);
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

   function penetapan($userID, $date)
   {
      $data_olah = $this->helper("D_Gaji")->data_olah($userID, $date);
      $data = $this->helper("D_Gaji")->rekap_final($data_olah, $date, $userID);
      $tetapkan = $this->helper('D_Gaji')->tetapkan($userID, $date, $data);
      return $tetapkan;
   }

   function tambah_harian_malam() {}

   /**
    * Proses tunjangan untuk satu user (HARIAN, MALAM, TUNJANGAN BULANAN)
    * @param int $userID
    * @param string $date Format: Y-m
    * @return bool true jika sukses, false jika ada error
    */
   private function processUserTunjangan($userID, $date)
   {
      // Gabungkan query HARIAN dan MALAM menjadi 1 query dengan GROUP BY
      // jenis = 1 adalah MALAM, jenis <> 1 adalah HARIAN
      $sql = "SELECT 
                  CASE WHEN jenis = 1 THEN 'malam' ELSE 'harian' END as tipe,
                  COUNT(*) as qty 
               FROM absen 
               WHERE id_karyawan = " . (int)$userID . " 
                  AND tanggal LIKE '" . $this->db(0)->escape($date) . "%'
               GROUP BY CASE WHEN jenis = 1 THEN 'malam' ELSE 'harian' END";
      
      $absenData = $this->db(0)->query_array($sql);
      if (!is_array($absenData)) {
         $absenData = [];
      }
      
      // Proses hasil query - convert ke array associative
      $absenCount = ['harian' => 0, 'malam' => 0];
      foreach ($absenData as $row) {
         $absenCount[$row['tipe']] = (int)$row['qty'];
      }
      
      // HARIAN (id_pengali = 3)
      if ($absenCount['harian'] > 0) {
         $result = $this->insertTunjanganIfNotExists($userID, 3, $absenCount['harian'], $date);
         if ($result === 404) {
            return ['success' => false, 'error' => 'HARIAN'];
         }
      }
      
      // MALAM (id_pengali = 5)
      if ($absenCount['malam'] > 0) {
         $result = $this->insertTunjanganIfNotExists($userID, 5, $absenCount['malam'], $date);
         if ($result === 404) {
            return ['success' => false, 'error' => 'MALAM'];
         }
      }
      
      // TUNJANGAN BULANAN (id_pengali = 4, qty selalu 1)
      $result = $this->insertTunjanganIfNotExists($userID, 4, 1, $date);
      if ($result === 404) {
         return ['success' => false, 'error' => 'TUNJANGAN'];
      }
      
      return ['success' => true];
   }
   
   /**
    * Insert tunjangan jika belum ada (menggunakan INSERT IGNORE untuk efisiensi)
    * @param int $userID
    * @param int $id_pengali
    * @param int $qty
    * @param string $date
    * @return int 1 jika sukses, 404 jika error, "DATA SUDAH TER-SET!" jika sudah ada
    */
   private function insertTunjanganIfNotExists($userID, $id_pengali, $qty, $date)
   {
      $data = [
         'id_karyawan' => $userID,
         'id_pengali' => $id_pengali,
         'qty' => $qty,
         'tgl' => $date
      ];
      $where = "id_karyawan = " . (int)$userID . " AND id_pengali = " . (int)$id_pengali . " AND tgl = '" . $this->db(0)->escape($date) . "'";
      return $this->tambahTunjangan($data, $where);
   }

   public function tetapkan($mode = 0)
   {
      $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m', strtotime("-1 month"));

      if ($mode == 1) {
         // Mode 1: Single user
         $userID = (int)$_POST['user_id'];
         
         $result = $this->processUserTunjangan($userID, $date);
         if (!$result['success']) {
            echo "ERROR INSERT " . $result['error'] . "\n";
            exit();
         }

         $tetapkan = $this->penetapan($userID, $date);
         echo $tetapkan;
      } else {
         // Mode 0: All active users
         $karyawan = $this->db(0)->get_cols_where("user", "id_user", "en = 1", 1);
         
         foreach ($karyawan as $k) {
            $userID = (int)$k['id_user'];
            
            $result = $this->processUserTunjangan($userID, $date);
            if (!$result['success']) {
               echo "ERROR INSERT " . $result['error'] . " untuk user ID: " . $userID . "\n";
               exit();
            }

            $this->penetapan($userID, $date);
         }
         
         echo "PENETAPAN GAJI PERIODE " . $date . " SELESAI\n";
      }
   }
}
