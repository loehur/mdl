<?php

class Kas extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $view = 'kas/kas_main';
      $data_operasi = ['title' => 'Kas'];

      $kredit = 0;
      $where_kredit = $this->wCabang . " AND jenis_mutasi = 1 AND metode_mutasi = 1 AND status_mutasi = 3";
      $cols_kredit = "SUM(jumlah) as jumlah";

      $debit = 0;
      $where_debit = $this->wCabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND status_mutasi <> 4";
      $cols_debit = "SUM(jumlah) as jumlah";

      for ($y = URL::FIRST_YEAR; $y <= date('Y'); $y++) {
         $jumlah_kredit = isset($this->db($y)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah']) ? $this->db($y)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah'] : 0;
         $kredit += $jumlah_kredit;

         $jumlah_debit = isset($this->db($y)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah']) ? $this->db($y)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah'] : 0;
         $debit += $jumlah_debit;
      }
      $saldo = $kredit - $debit;

      $limit = 10;
      if ($this->id_privilege == 100) {
         $limit = 25;
      }
      $where = $this->wCabang . " AND jenis_mutasi = 2 ORDER BY id_kas DESC LIMIT $limit";
      $debit_list = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);

      //KASBON
      $where = $this->wCabang . " AND jenis_transaksi = 5 AND jenis_mutasi = 2 AND status_mutasi = 3 ORDER BY id_kas DESC LIMIT 25";
      $kasbon = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('kas', $where);

      $dataPotong = array();
      foreach ($kasbon as $k) {
         $ref = $k['id_kas'];
         $dataPotong[$ref] = 0;

         $where = "ref = '" . $ref . "' AND tipe = 2";
         $countPotong = $this->db(0)->get_where('gaji_result', $where);
         if (count($countPotong) > 0) {
            foreach ($countPotong as $cp) {
               if (($cp['tgl'] == substr($k['insertTime'], 0, 7) && $k['id_client'] == $cp['id_karyawan'])) {
                  $dataPotong[$ref] = 1;
                  continue;
               }
            }
         }
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, [
         'saldo' => $saldo,
         'debit_list' => $debit_list,
         'kasbon' => $kasbon,
         'dataPotong' => $dataPotong
      ]);
   }

   public function insert()
   {
      //PENARIKAN
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $penarik = $_POST['f3'];
      $today = date('Y-m-d');
      $status_mutasi = 2;

      if ($this->id_privilege == 100) {
         $status_mutasi = 3;
      }

      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'jenis_mutasi' => 2,
            'jenis_transaksi' => 2,
            'metode_mutasi' => 1,
            'note' => $keterangan,
            'status_mutasi' => $status_mutasi,
            'jumlah' => $jumlah,
            'id_user' => $penarik,
            'id_client' => 0,
            'note_primary' => 'Penarikan'
         ];
         $do = $this->db(date('Y'))->insert('kas', $data);
         if ($do['errno'] == 0) {
            echo 1;
         } else {
            $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $do['error']);
         }
      } else {
         echo "Duplicate Entry!";
      }
   }

   public function insert_pengeluaran()
   {
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $penarik = $_POST['f3'];
      $today = date('Y-m-d');
      $jenis = $_POST['f1a'];

      $jenisEXP = explode("<explode>", $jenis);
      $id_jenis = $jenisEXP[0];
      $jenis = $jenisEXP[1];

      $status_mutasi = 2;
      if ($this->id_privilege == 100) {
         $status_mutasi = 3;
      }

      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'jenis_mutasi' => 2,
            'jenis_transaksi' => 4,
            'metode_mutasi' => 1,
            'note' => $keterangan,
            'note_primary' => $jenis,
            'status_mutasi' => $status_mutasi,
            'jumlah' => $jumlah,
            'id_user' => $penarik,
            'id_client' => 0,
            'ref_transaksi' => $id_jenis
         ];
         $do = $this->db(date('Y'))->insert('kas', $data);
         if ($do['errno'] <> 0) {
            $this->helper('Notif')->send_wa(URL::WA_PRIVATE[0], $do['error']);
         }
      }
   }
}
