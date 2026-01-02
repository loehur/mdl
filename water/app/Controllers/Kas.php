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
      $layout = ['title' => 'Kas'];

      $kredit = 0;
      $where_kredit = $this->wCabang . " AND jenis_mutasi = 1 AND metode_mutasi = 1 AND status_mutasi = 1";
      $cols_kredit = "SUM(jumlah) as jumlah";

      $debit = 0;
      $where_debit = $this->wCabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND status_mutasi <> 2";
      $cols_debit = "SUM(jumlah) as jumlah";
     
      $jumlah_kredit = isset($this->db(0)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah']) ? $this->db(0)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah'] : 0;
      $kredit += $jumlah_kredit;

      $jumlah_debit = isset($this->db(0)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah']) ? $this->db(0)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah'] : 0;
      $debit += $jumlah_debit;

      $saldo = $kredit - $debit;

      $limit = 25;

      $where = $this->wCabang . " AND jenis_mutasi = 2 ORDER BY id DESC LIMIT $limit"; //pengeluaran
      $debit_list = $this->db(0)->get_where('kas', $where);
      $jenis_pengeluaran = $this->db(0)->get_order("item_pengeluaran", "freq DESC");

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", [
         'saldo' => $saldo,
         'debit_list' => $debit_list,
         'pengeluaran_jenis' => $jenis_pengeluaran
      ]);
   }

   public function insert()
   {
      //PENARIKAN
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $today = date('Y-m-d');
      $status_mutasi = 0;

      if ($this->id_privilege == 100) {
         $status_mutasi = 1;
      } else {
         $status_mutasi = 0;
      }

      $cols = 'id_cabang, jenis_mutasi, jenis_transaksi, metode_mutasi, note, status_mutasi, jumlah, id_user, id_client, note_primary';
      $vals = $this->id_cabang . ",2,2,1,'" . $keterangan . "'," . $status_mutasi . "," . $jumlah . "," . $this->id_user . ",0,'Penarikan'";

      $setOne = "note = '" . $keterangan . "' AND jumlah = " . $jumlah . " AND insertTime LIKE '" . $today . "%'";
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(0)->count_where('kas', $where);

      if ($data_main < 1) {
         $do = $this->db(0)->insertCols('kas', $cols, $vals);
         if ($do['errno'] == 0) {
            echo 0;
         } else {
            echo $do['error'];
         }
      } else {
         echo "Duplicate Entry!";
      }
   }

   public function insert_pengeluaran()
   {
      $keterangan = $_POST['f1'];
      $jumlah = $_POST['f2'];
      $today = date('Y-m-d');
      $jenis = $_POST['f1a'];

      $jenisEXP = explode("<explode>", $jenis);
      $id_jenis = $jenisEXP[0];
      $jenis = $jenisEXP[1];

      $status_mutasi = 0;
      if ($this->id_privilege == 100) {
         $status_mutasi = 1;
      }

      $setOne = "note = '" . $keterangan . "' AND jumlah = " . $jumlah . " AND insertTime LIKE '" . $today . "%'";
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(0)->count_where('kas', $where);

      if ($data_main < 1) {
         //update freq
         $this->db(0)->update('item_pengeluaran', "freq = freq + 1", "id_item_pengeluaran = " . $id_jenis);
         $cols = 'id_cabang, jenis_mutasi, jenis_transaksi, metode_mutasi, note, note_primary, status_mutasi, jumlah, id_user, id_client, ref';
         $vals = $this->id_cabang . ",2,4,1,'" . $keterangan . "','" . $jenis . "'," . $status_mutasi . "," . $jumlah . "," . $this->id_user . ",0," . $id_jenis;
         $in = $this->db(0)->insertCols('kas', $cols, $vals);
         echo $in['errno'] == 0 ? 0 : $in['error'];
      }
   }
}
