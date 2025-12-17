<?php

class Filter extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function i($filter)
   {
      $kas = array();
      $notif = array();
      $notifPenjualan = array();
      $data_main = array();
      $surcas = array();

      switch ($filter) {
         case 1:
            //PENGAMBILAN
            $data_operasi = ['title' => 'Order Filter Pengambilan'];
            $viewData = 'filter/view';
            break;
         case 2:
            //PENGANTARAN
            $data_operasi = ['title' => 'Order Filter Pengantaran'];
            $viewData = 'filter/view';
            break;
         default:
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('filter/form', [
         'modeView' => $filter,
      ]);
      $this->view($viewData, [
         'modeView' => $filter,
         'data_main' => $data_main,
         'kas' => $kas,
         "notif" => $notif,
         'notif_penjualan' => $notifPenjualan,
         "surcas" => $surcas,
      ]);
   }

   public function loadList($filter, $from = "", $to = "")
   {
      $data_main = array();
      $viewData = 'filter/view_content';

      switch ($filter) {
         case 1:
            //PENGAMBILAN
            if ($from <> "") {
               $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND SUBSTRING(tgl_ambil, 1, 10) >= '$from' AND SUBSTRING(tgl_ambil, 1, 10) <= '$to' ORDER BY id_penjualan DESC";
               $data_main = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('sale', $where);
            }
            break;
         case 2:
            //PENGANTARAN
            if ($from <> "") {
               $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND SUBSTRING(insertTime, 1, 10) >= '$from' AND SUBSTRING(insertTime, 1, 10) <= '$to' ORDER BY id_penjualan DESC";
               $data_main = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('sale', $where);
            }
            break;
         default:
            break;
      }

      $operasi = [];
      $kas = [];
      $surcas = [];
      $notif = [];

      if ($from <> "") {

         $numbers = array_column($data_main, 'id_penjualan');
         $refs = array_unique(array_column($data_main, 'no_ref'));

         foreach ($numbers as $id) {
            //OPERASI
            $where = $this->wCabang . " AND id_penjualan = " . $id;
            $ops = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where('operasi', $where);
            if (count($ops) > 0) {
               foreach ($ops as $opsv) {
                  array_push($operasi, $opsv);
               }
            }
         }

         foreach ($refs as $rf) {
            //KAS
            $where = $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = '" . $rf . "'";
            $ks = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where_row('kas', $where);
            if (count($ks) > 0) {
               array_push($kas, $ks);
            }

            //SURCAS
            $where = $this->wCabang . " AND no_ref = '" . $rf . "'";
            $sc = $this->db(0)->get_where_row('surcas', $where);
            if (count($sc) > 0) {
               array_push($surcas, $sc);
            }

            //NOTIF BON
            $where = $this->wCabang . " AND tipe = 1 AND no_ref = '" . $rf . "'";
            $nf = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_where_row('notif', $where);
            if (count($nf) > 0) {
               array_push($notif, $nf);
            }
         }
      }

      $this->view($viewData, [
         'modeView' => $filter,
         'data_main' => $data_main,
         'operasi' => $operasi,
         'kas' => $kas,
         "surcas" => $surcas,
         'notif_bon' => $notif,
      ]);
   }

   public function clearTuntas()
   {
      if (isset($_POST['data'])) {
         $data = unserialize($_POST['data']);
         foreach ($data as $a) {
            $this->tuntasOrder($a);
         }
      }
   }

   public function surcas()
   {
      $jenis = $_POST['surcas'];
      $jumlah = $_POST['jumlah'];
      $user = $_POST['user'];
      $id_transaksi = $_POST['no_ref'];

      $setOne = "transaksi_jenis = 1 AND no_ref = " . $id_transaksi . " AND id_jenis_surcas = " . $jenis;
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(0)->count_where('surcas', $where);
      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'transaksi_jenis' => 1,
            'id_jenis_surcas' => $jenis,
            'jumlah' => $jumlah,
            'id_user' => $user,
            'no_ref' => $id_transaksi
         ];
         $this->db(0)->insert('surcas', $data);
      }
   }

   public function updateRak()
   {
      $rak = $_POST['value'];
      $id = $_POST['id'];

      $set = [
         'letak' => $rak
      ];
      $where = $this->wCabang . " AND id_penjualan = " . $id;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);

      //CEK SUDAH TERKIRIM BELUM
      $setOne = "no_ref = '" . $id . "' AND proses <> '' AND tipe = 2";
      $where = $setOne;
      $data_main = $this->db(date('Y'))->count_where('notif', $where);
      if ($data_main < 1) {
         $this->notifReadySend($id);
      }
   }

   public function tuntasOrder($ref)
   {
      $set = [
         'tuntas' => 1
      ];
      $where = $this->wCabang . " AND no_ref = " . $ref;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function notifReadySend($idPenjualan)
   {
      $setOne = "no_ref = '" . $idPenjualan . "' AND tipe = 2";
      $where = $this->wCabang . " AND " . $setOne;
      $dm = $this->db(0)->get_where_row('notif', $where);
      $hp = $dm['phone'];
      $text = $dm['text'];
      $res = $this->helper('Notif')->send_wa($hp, $text, false);

      foreach ($res["id"] as $k => $v) {
         $status = $res['data']['status'];
         $set = [
            'status' => 1,
            'proses' => $status,
            'id_api' => $res['data']['id']
         ];
         $where2 = $this->wCabang . " AND no_ref = '" . $idPenjualan . "' AND tipe = 2";
         $this->db($_SESSION[URL::SESSID]['user']['book'])->update('notif', $set, $where2);
      }
   }

   public function directWA($countMember)
   {
      $noref = $_POST['ref'];
      $text = $_POST['text'];
      $idPelanggan = $_POST['idPelanggan'];

      if ($countMember > 0) {
         $textMember = $this->textSaldoNotif($idPelanggan);
         $text = $text . $textMember;
      }

      $set = "direct_wa = 1";
      $setOne = "no_ref = '" . $noref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);

      echo $text;
   }

   public function textSaldoNotif($idPelanggan)
   {
      $textSaldo = "";
      $where = $this->wCabang . " AND bin = 0 AND id_pelanggan = " . $idPelanggan . " GROUP BY id_harga";
      $cols = "id_harga, SUM(qty) as saldo";
      $data = $this->db(0)->get_cols_where('member', $cols, $where, 1);

      foreach ($data as $a) {
         $saldoPengurangan = 0;
         $idHarga = $a['id_harga'];
         $where = $this->wCabang . " AND id_pelanggan = " . $idPelanggan . " AND id_harga = " . $idHarga . " AND member = 1";
         $cols = "SUM(qty) as saldo";
         $data2 = $this->db($_SESSION[URL::SESSID]['user']['book'])->get_cols_where('sale', $cols, $where, 0);

         if (isset($data2['saldo'])) {
            $saldoPengurangan = $data2['saldo'];
            $pakai[$idHarga] = $saldoPengurangan;
         } else {
            $pakai[$idHarga] = 0;
         }
      }
      foreach ($data as $z) {
         $id = $z['id_harga'];
         $unit = "";
         if ($z['saldo'] > 0) {
            foreach ($this->harga as $a) {
               if ($a['id_harga'] == $id) {
                  foreach ($this->dPenjualan as $dp) {
                     if ($dp['id_penjualan_jenis'] == $a['id_penjualan_jenis']) {
                        foreach ($this->dSatuan as $ds) {
                           if ($ds['id_satuan'] == $dp['id_satuan']) {
                              $unit = $ds['nama_satuan'];
                           }
                        }
                     }
                  }
                  $saldoAwal = $z['saldo'];
                  $saldoAkhir = $saldoAwal - $pakai[$id];
               }
            }
         }
         $textSaldo = $textSaldo . " | M" . $id . " " . number_format($saldoAkhir, 2) . $unit;
      }
      return $textSaldo;
   }

   public function ambil()
   {
      $karyawan = $_POST['f1'];
      $id = $_POST['f2'];
      $dateNow = date('Y-m-d H:i:s');
      $set = [
         'tgl_ambil' => $dateNow,
         'id_user_ambil' => $karyawan
      ];
      $setOne = "id_penjualan = '" . $id . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function hapusRef()
   {
      $ref = $_POST['ref'];
      $note = $_POST['note'];
      $setOne = "no_ref = '" . $ref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = [
         'bin' => 1,
         'bin_note' => $note
      ];
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }

   public function restoreRef()
   {
      $ref = $_POST['ref'];
      $setOne = "no_ref = '" . $ref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = "bin = 0";
      $this->db($_SESSION[URL::SESSID]['user']['book'])->update('sale', $set, $where);
   }
}
