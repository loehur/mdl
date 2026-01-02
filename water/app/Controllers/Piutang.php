<?php

class Piutang extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Piutang'];
      $data['ref'] = [];
      $data['ref'] = $this->db(0)->get_where('ref', "step = 3", 'id');
      $data['pelanggan'] = $this->db(0)->get("pelanggan", "id");

      $order = [];
      $data_ = [];

      $tgl_order = [];
      foreach ($data['ref'] as $key => $r) {
         if (!isset($tgl_order[$r['pelanggan']])) $tgl_order[$r['pelanggan']] = [];
         array_push($tgl_order[$r['pelanggan']], $r['tgl']);

         $order[$key] = $this->db(0)->get_where('pesanan', "ref = '" . $key . "'");
         foreach ($order[$key] as $dk) {
            $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
            if (isset($data_[$r['pelanggan']])) {
               $data_[$r['pelanggan']] += $subTotal;
            } else {
               $data_[$r['pelanggan']] = $subTotal;
            }
         }

         $cek_dibayar[$key] = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $key . "'");
         foreach ($cek_dibayar[$key] as $b) {
            $data_[$r['pelanggan']] -= $b['jumlah'];
         }
      }

      $data['tgl_order'] = $tgl_order;
      $data['order'] = $order;
      $data['data'] = $data_;

      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($pelanggan = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data_pelanggan = $this->db(0)->get_where("pelanggan", "id = " . $pelanggan, "id");
      $data['order'] = $this->db(0)->get_where('ref', "pelanggan = " . $pelanggan . " AND step = 3", "tgl", 1);
      $data['order_ref'] = $this->db(0)->get_where('ref', "pelanggan = " . $pelanggan . " AND step = 3", "id");

      foreach ($data['order_ref'] as $key => $r) {
         $order[$key] = $this->db(0)->get_where('pesanan', "ref = '" . $key . "'");
         foreach ($order[$key] as $dk) {
            $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
            if (isset($total[$r['tgl']])) {
               $total[$r['tgl']] += $subTotal;
            } else {
               $total[$r['tgl']] = $subTotal;
            }
         }

         $cek_dibayar[$key] = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $key . "'");
         foreach ($cek_dibayar[$key] as $b) {
            $total[$r['tgl']] -= $b['jumlah'];
         }
      }

      $data['total'] = $total;
      $data['pelanggan'] = $pelanggan;
      $data['nama_pelanggan'] = $data_pelanggan[$pelanggan]['nama'];
      $this->view($viewData, $data);
   }

   public function cart2($pelanggan, $tgl)
   {
      $viewData = __CLASS__ . '/cart2';
      $data['menu'] = $_SESSION[URL::SESSID]['menu'];
      $data['ref'] = $this->db(0)->get_where('ref', "pelanggan = " . $pelanggan . " AND step = 3 AND tgl = '" . $tgl . "'", 'id');
      foreach ($data['ref'] as $key => $d) {
         $data['order'][$key] = $this->db(0)->get_where('pesanan', "ref = '" . $key . "'", "id_menu");
         $data['bayar'][$key] = [];
         $data['bayar'][$key] = $this->db(0)->get_where('kas', "ref = '" . $key . "' AND status_mutasi <> 2");
      }

      $this->view($viewData, $data);
   }

   function cek_bayar($pelanggan)
   {
      $viewData = __CLASS__ . '/bayar';
      $data_pelanggan = $this->db(0)->get_where("pelanggan", "id = " . $pelanggan, "id");
      $data['order'] = $this->db(0)->get_where('ref', "pelanggan = " . $pelanggan . " AND step = 3", "tgl", 1);
      $data['order_ref'] = $this->db(0)->get_where('ref', "pelanggan = " . $pelanggan . " AND step = 3", "id");

      foreach ($data['order_ref'] as $key => $r) {
         $order[$key] = $this->db(0)->get_where('pesanan', "ref = '" . $key . "'");
         foreach ($order[$key] as $dk) {
            $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
            if (isset($total[$r['tgl']])) {
               $total[$r['tgl']] += $subTotal;
            } else {
               $total[$r['tgl']] = $subTotal;
            }
         }
         $cek_dibayar[$key] = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $key . "'");
         foreach ($cek_dibayar[$key] as $b) {
            $total[$r['tgl']] -= $b['jumlah'];
         }
      }

      $data['total'] = $total;
      $data['pelanggan'] = $pelanggan;
      $data['nama_pelanggan'] = $data_pelanggan[$pelanggan]['nama'];
      $this->view($viewData, $data);
   }

   function bayar($pelanggan)
   {
      $p = $_POST;
      if (!isset($p['list_tgl'])) {
         echo "Tidak ada tanggal yang dipilih";
         exit();
      }
      if (count($p['list_tgl']) > 0) {
         $ref_bayar = (date('Y') - 2024) . date('mdHis') . $this->id_cabang;
         $jumBayar = $p['jumBayar'];
         $metode = $p['metode'];
         if ($metode == 1 || $this->id_privilege == 100) {
            $st_mutasi = 1;
            $step = 1;
         } else {
            $st_mutasi = 0;
            $step = 4;
         }

         foreach ($p['list_tgl'] as $tgl) {
            if ($jumBayar == 0) {
               echo 0;
               exit();
            }

            $data = $this->db(0)->get_where('ref', "pelanggan = " . $pelanggan . " AND step = 3 AND tgl = '" . $tgl . "'", "id");

            foreach ($data as $ref => $r) {
               $order[$ref] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'");
               $lunas[$ref] = false;

               $sisa_tagihan[$ref] = 0;
               foreach ($order[$ref] as $dk) {
                  $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
                  $sisa_tagihan[$ref] += $subTotal;
               }

               $cek_dibayar[$ref] = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $ref . "'");
               foreach ($cek_dibayar[$ref] as $b) {
                  $sisa_tagihan[$ref] -= $b['jumlah'];
               }

               if ($sisa_tagihan[$ref] > 0) {
                  if ($jumBayar >= $sisa_tagihan[$ref]) {
                     $lunas[$ref] = true;
                     $jumlah_bayar[$ref] = $sisa_tagihan[$ref];
                     $jumBayar -= $sisa_tagihan[$ref];
                  } else {
                     $jumlah_bayar[$ref] = $jumBayar;
                     $jumBayar = 0;
                  }

                  $cols = "id_cabang, jenis_mutasi, jenis_transaksi, ref, metode_mutasi, status_mutasi, jumlah, id_user, dibayar, kembali, ref_bayar, id_client, note_primary, note";
                  $vals = $this->id_cabang . ",1,1,'" . $ref . "'," . $metode . "," . $st_mutasi . "," . $jumlah_bayar[$ref] . "," . $this->id_user . "," . $jumlah_bayar[$ref] . ",0,'" . $ref_bayar . "'," . $pelanggan . ",'P','" . $tgl . "'";
                  $in = $this->db(0)->insertCols("kas", $cols, $vals);

                  if ($in['errno'] == 0) {
                     if ($lunas[$ref] == true) {
                        $up = $this->db(0)->update('ref', "step = " . $step, "id = '" . $ref . "'");
                        echo $up['errno'] == 0 ? 0 : $up['error'];
                     } else {
                        echo 0;
                     }
                  } else {
                     echo $in['error'];
                  }
               } else {
                  if ($sisa_tagihan[$ref] == 0) {
                     $up = $this->db(0)->update('ref', "step = " . $step, "id = '" . $ref . "'");
                     echo $up['errno'] == 0 ? 0 : $up['error'];
                  }
               }
            }
         }
      }
   }
}
