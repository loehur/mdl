<?php

class Setoran extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $view = 'setoran/setoran_main';
      $db = $this->db(0);
      $base = $this->wCabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 2";

      // Pisah pending vs riwayat agar LIMIT 20 tidak menelan antrean approve
      $pending = $db->get_where('kas', $base . " AND status_mutasi = 2 ORDER BY insertTime DESC LIMIT 20");
      $riwayat = $db->get_where('kas', $base . " AND status_mutasi <> 2 ORDER BY insertTime DESC LIMIT 20");
      $list = array_merge(is_array($pending) ? $pending : [], is_array($riwayat) ? $riwayat : []);

      $this->view($view, ['list' => $list]);
   }

   public function operasi($tipe)
   {
      $tipe = (int) $tipe;
      if (!in_array($tipe, [3, 4], true)) {
         echo 'Tipe tidak valid';
         return;
      }

      $id = trim((string) ($_POST['id'] ?? ''));
      if ($id === '' || !preg_match('/^[A-Za-z0-9]+$/', $id)) {
         echo 'ID tidak valid';
         return;
      }

      $idEsc = $this->db(0)->escape($id);
      $where = $this->wCabang . " AND id_kas = '" . $idEsc . "' AND jenis_transaksi = 2 AND status_mutasi = 2";
      $count = $this->db(0)->count_where('kas', $where);
      if ($count == 0) {
         echo 'Setoran tidak ditemukan atau sudah diproses';
         return;
      }

      $up = $this->db(0)->update('kas', ['status_mutasi' => $tipe], $where);
      if ($up['errno'] == 0) {
         echo '0';
      } else {
         echo $up['error'];
      }
   }
}
