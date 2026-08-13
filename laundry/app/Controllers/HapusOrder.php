<?php

class HapusOrder extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $viewData = 'hapusOrder/hapus_order_main';
      $db = $this->db(0);
      $wc = $this->wCabang;

      $where = $wc . " AND id_pelanggan <> 0 AND bin = 1 ORDER BY id_penjualan DESC LIMIT 50";
      $data_main = $db->get_where('sale', $where);

      $operasi = [];
      $kas = [];
      $surcas = [];
      $notifBon = [];
      $notifSelesai = [];

      if (count($data_main) > 0) {
         $ids = array_values(array_unique(array_map('intval', array_column($data_main, 'id_penjualan'))));
         $refs = array_values(array_unique(array_filter(array_column($data_main, 'no_ref'), static function ($r) {
            return $r !== null && $r !== '';
         })));

         if (count($ids) > 0) {
            $idIn = implode(',', $ids);
            $operasi = $db->get_where('operasi', $wc . " AND id_penjualan IN ($idIn)");
         }

         if (count($refs) > 0) {
            $refIn = implode(',', array_map(static function ($r) use ($db) {
               return "'" . $db->escape((string) $r) . "'";
            }, $refs));

            $kas = $db->get_where('kas', $wc . " AND jenis_transaksi = 1 AND ref_transaksi IN ($refIn)");
            $surcas = $db->get_where('surcas', $wc . " AND no_ref IN ($refIn)");
            $notifBon = $db->get_where('notif', $wc . " AND tipe = 1 AND no_ref IN ($refIn)");
         }
      }

      $this->view($viewData, [
         'data_main' => $data_main,
         'operasi' => $operasi,
         'kas' => $kas,
         'surcas' => $surcas,
         'notif_bon' => $notifBon,
         'notif_selesai' => $notifSelesai
      ]);
   }

   public function hapusRelated()
   {
      $transaksi = $_POST['transaksi'];

      if (isset($_POST['dataRef'])) {
         $dataRef = unserialize($_POST['dataRef']);
         foreach ($dataRef as $a) {

            //KAS (QRIS paid/pending/unknown tidak dihapus)
            $where = $this->wCabang . " AND ref_transaksi = '" . $this->db(0)->escape($a) . "' AND jenis_transaksi = " . (int) $transaksi;
            $this->deleteKasSafe($where, false);

            //NOTIF_BON
            $where = $this->wCabang . " AND no_ref = '" . $a . "' AND tipe = 1";
            $this->db(0)->delete('notif', $where);

            //SURCHARGE
            $where2 = $this->wCabang . " AND no_ref = '" . $a . "' AND transaksi_jenis = 1";
            $this->db(0)->delete('surcas', $where2);
         }
      }
      if (isset($_POST['dataID']) && $transaksi <> 3) {
         $dataID = unserialize($_POST['dataID']);
         foreach ($dataID as $a) {
            $where = $this->wCabang . " AND id_penjualan = '" . $a . "'";
            $this->db(0)->delete('operasi', $where);

            //NOTIF
            $where = $this->wCabang . " AND no_ref = '" . $a . "' AND tipe = 2";
            $this->db(0)->delete('notif', $where);
         }
      }
   }
   public function hapusID()
   {
      $kolomID =  $_POST['kolomID'];
      $table = $_POST['table'];

      switch ($table) {
         case 'sale':
            if (isset($_POST['dataID'])) {
               $dataID = unserialize($_POST['dataID']);
               foreach ($dataID as $a) {
                  $where = $this->wCabang . " AND " . $kolomID . " = '" . $a. "'";
                  $del = $this->db(0)->delete($table, $where);
                  if ($del['errno'] <> 0) {
                     echo $del['error'];
                     exit();
                  }
               }
            }
            echo 0;
            break;
         case 'member':
            if (isset($_POST['dataID'])) {
               $dataID = unserialize($_POST['dataID']);
               foreach ($dataID as $a) {
                  $where = $this->wCabang . " AND " . $kolomID . " = " . $a;
                  $del = $this->db(0)->delete($table, $where);
                  if ($del['errno'] <> 0) {
                     echo $del['error'];
                     exit();
                  }
               }
            }
            echo 0;
            break;
         default:
            echo "No Table selected";
            break;
      }
   }
}
