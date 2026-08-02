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

   public function set_gaji_pengali()
   {
      $id_pengali = (int) $_POST['pengali'];
      if (in_array($id_pengali, [1, 2], true)) {
         echo 'Fee Terima/Kembali global di Gaji → Pengaturan';
         return;
      }
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

      if ($table === 'gaji_laundry') {
         // Fee layanan laundry sekarang global di GajiPengaturan — tidak diedit dari Gaji Bulanan
         return;
      }

      if ($table === 'gaji_pengali') {
         $idPengaliPost = isset($_POST['id_pengali']) ? (int) $_POST['id_pengali'] : 0;
         $this->updateGajiPengaliCell((int) $id, $col, $value, $idPengaliPost);
         return;
      }

      $where = "";
      switch ($table) {
         case 'gaji_pengali_data':
            $where = "id_pengali_data = " . $id;
            break;
      }

      $set = [
         $col => $value
      ];
      $this->db(0)->update($table, $set, $where);
   }

   /**
    * Update fee gaji_pengali per karyawan (Harian/Tunjangan/Cuci/Malam floor).
    * id_pengali 1/2 global di GajiPengaturan — no-op.
    */
   private function updateGajiPengaliCell($idGajiPengali, $col, $value, $idPengaliPost = 0)
   {
      if ($col !== 'gaji_pengali') {
         if ($idGajiPengali < 1) {
            return;
         }
         $this->db(0)->update('gaji_pengali', [$col => $value], 'id_gaji_pengali = ' . $idGajiPengali);
         return;
      }

      $numVal = (int) $value;
      $idPengali = 0;

      if ($idGajiPengali > 0) {
         $ref = $this->db(0)->get_where_row('gaji_pengali', 'id_gaji_pengali = ' . $idGajiPengali);
         if (empty($ref) || !isset($ref['id_pengali'])) {
            return;
         }
         $idPengali = (int) $ref['id_pengali'];
      } elseif ($idPengaliPost > 0) {
         $idPengali = $idPengaliPost;
      } else {
         return;
      }

      // Terima/Kembali global — tidak diedit dari Gaji Bulanan
      if (in_array($idPengali, [1, 2], true)) {
         return;
      }

      if ($idGajiPengali > 0) {
         $this->db(0)->update('gaji_pengali', ['gaji_pengali' => $numVal], 'id_gaji_pengali = ' . $idGajiPengali);
         return;
      }

      // Insert baris baru (Cuci/Malam/dll) jika belum ada
      $idKaryawan = (int) ($_POST['id_karyawan'] ?? 0);
      if ($idKaryawan < 1 || $idPengali < 1) {
         return;
      }
      $ada = $this->db(0)->count_where(
         'gaji_pengali',
         'id_karyawan = ' . $idKaryawan . ' AND id_pengali = ' . $idPengali
      );
      if ($ada > 0) {
         $this->db(0)->update(
            'gaji_pengali',
            ['gaji_pengali' => $numVal],
            'id_karyawan = ' . $idKaryawan . ' AND id_pengali = ' . $idPengali
         );
         return;
      }
      $this->db(0)->insert('gaji_pengali', [
         'id_karyawan' => $idKaryawan,
         'id_pengali' => $idPengali,
         'gaji_pengali' => $numVal,
      ]);
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
    * Proses tunjangan untuk satu user (CUCI, HARIAN, MALAM, TUNJANGAN BULANAN)
    * @param int $userID
    * @param string $date Format: Y-m
    * @return bool true jika sukses, false jika ada error
    */
   private function processUserTunjangan($userID, $date)
   {
      // jenis 0 = Cuci, 1 = Malam, 2/3 = Harian (Delivery/Maintenance)
      $sql = "SELECT 
                  CASE
                     WHEN jenis = 0 THEN 'cuci'
                     WHEN jenis = 1 THEN 'malam'
                     WHEN jenis IN (2, 3) THEN 'harian'
                     ELSE 'lain'
                  END as tipe,
                  COUNT(*) as qty 
               FROM absen 
               WHERE id_karyawan = " . (int)$userID . " 
                  AND tanggal LIKE '" . $this->db(0)->escape($date) . "%'
               GROUP BY CASE
                     WHEN jenis = 0 THEN 'cuci'
                     WHEN jenis = 1 THEN 'malam'
                     WHEN jenis IN (2, 3) THEN 'harian'
                     ELSE 'lain'
                  END";
      
      $absenData = $this->db(0)->query_array($sql);
      if (!is_array($absenData)) {
         $absenData = [];
      }
      
      $absenCount = ['cuci' => 0, 'harian' => 0, 'malam' => 0];
      foreach ($absenData as $row) {
         $tipe = $row['tipe'] ?? '';
         if (isset($absenCount[$tipe])) {
            $absenCount[$tipe] = (int)$row['qty'];
         }
      }

      // CUCI (id_pengali = 6)
      if ($absenCount['cuci'] > 0) {
         $result = $this->insertTunjanganIfNotExists($userID, 6, $absenCount['cuci'], $date);
         if ($result === 404) {
            return ['success' => false, 'error' => 'CUCI'];
         }
      }
      
      // HARIAN (id_pengali = 3) — Delivery + Maintenance saja
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
