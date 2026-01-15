<?php

class Penjualan extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $layout = ['title' => 'Buka Order'];
      $data['kat'] =  $_SESSION['resto_kat'];
      $data['order_0'] = $this->db(0)->get_where('ref', "step = 0 AND mode = 0", "nomor");
      $data['order_1'] = $this->db(0)->get_where('ref', "step = 0 AND mode = 1", "nomor");
      $this->view('layout', $layout);
      $this->view(__CLASS__ . "/main", $data);
   }

   public function cart($mode = 0, $nomor = 0)
   {
      $viewData = __CLASS__ . '/cart';
      $data['mode'] = $mode;
      $data['nomor'] = $nomor;

      $cek = $this->db(0)->get_where_row('ref', "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['menu'] = $_SESSION['resto_menu'];
         $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
         $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $cek['id'] . "' AND status_mutasi <> 2");
         
         // AUTO-FIX: Cek apakah pembayaran sudah lunas dan verified
         // Jika ya, update step ke 1 (order selesai)
         $total_tagihan = 0;
         foreach ($data['order'] as $dk) {
            $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
            $total_tagihan += $subTotal;
         }
         
         $total_dibayar = 0;
         $total_verified = 0;
         $has_pending = false;
         foreach ($data['bayar'] as $b) {
            $total_dibayar += $b['jumlah'];
            if ($b['status_mutasi'] == 1) {
               $total_verified += $b['jumlah'];
            } else {
               $has_pending = true;
            }
         }
         
         // Jika sudah lunas dan semua verified, auto-fix step
         if ($total_tagihan > 0 && $total_dibayar >= $total_tagihan && $total_verified >= $total_tagihan && !$has_pending) {
            $this->db(0)->update('ref', "step = 1", "id = '" . $cek['id'] . "'");
            // Reload dengan data kosong karena order sudah selesai
            $data['order'] = [];
            $data['bayar'] = [];
            $cek = [];
         }
      } else {
         $data['order'] = [];
         $data['bayar'] = [];
      }

      $data['ref'] = $cek;
      $this->view($viewData, $data);
   }

   public function menu($id_kat = 0, $mode = 0, $nomor = 0)
   {
      $viewData = __CLASS__ . '/menu';
      if ($id_kat == 0) {
         $data['menu'] = $_SESSION['resto_menu'];
      } else {
         $menu_byKat =  $_SESSION['resto_menu_byKat'];
         $data['menu'] = isset($menu_byKat[$id_kat]) ? $menu_byKat[$id_kat] : [];
      }

      $cek = $this->db(0)->get_where_row('ref', "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
      } else {
         $data['order'] = [];
      }

      $this->view($viewData, $data);
   }

   public function ubah($mode = 0, $nomor = 0)
   {
      $viewData = __CLASS__ . '/ubah';
      $data['menu'] = $this->db(0)->get_where('menu_item', $this->wCabang . " ORDER BY freq DESC", 'id');

      $cek = $this->db(0)->get_where_row('ref', "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $cek['id'] . "'", "id_menu");
      } else {
         $data['order'] = [];
      }

      $this->view($viewData, $data);
   }

   public function bayar()
   {
      $uang_diterima = $_POST['dibayar'];
      $metode = $_POST['metode'];
      $ref = $_POST['ref'];
      $ref_bayar = (date('Y') - 2024) . date('mdHis') . $this->id_cabang;

      // DEBUG: Log nilai yang diterima
      Log::write("ref: $ref, dibayar: $uang_diterima, metode: $metode");

      // Metode 1 = CASH (verified)
      // Metode 2 = QRIS (pending, akan di-verify via Tokopay)
      // Lainnya = pending (manual check)
      if ($metode == 1) {
         $st_mutasi = 1; // CASH = verified
      } else {
         $st_mutasi = 0; // QRIS/Non-CASH = pending
      }

      $order = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");

      $total_tagihan = 0;
      foreach ($order as $dk) {
         $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
         $total_tagihan += $subTotal;
      }

      // Hitung pembayaran yang sudah ada
      $total_dibayar = 0;
      $total_verified = 0;
      $has_pending = false;
      $cek_dibayar = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $ref . "'");
      foreach ($cek_dibayar as $b) {
         $total_dibayar += $b['jumlah'];
         if ($b['status_mutasi'] == 1) {
            $total_verified += $b['jumlah'];
         } else {
            $has_pending = true;
         }
      }

      $sisa_tagihan = $total_tagihan - $total_dibayar;

      if ($sisa_tagihan > 0) {
         $kembali = $uang_diterima - $sisa_tagihan;
         if ($kembali < 0) {
            $kembali = 0;
         }

         if ($uang_diterima >= $sisa_tagihan) {
            $jumlah_bayar = $sisa_tagihan;
         } else {
            $jumlah_bayar = $uang_diterima;
         }

         // DEBUG: Log kalkulasi
         Log::write("sisa_tagihan: $sisa_tagihan, uang_diterima: $uang_diterima, jumlah_bayar: $jumlah_bayar");

         $cols = "id_cabang, jenis_mutasi, jenis_transaksi, ref, metode_mutasi, status_mutasi, jumlah, id_user, dibayar, kembali, ref_bayar";
         $vals = $this->id_cabang . ",1,1,'" . $ref . "'," . $metode . "," . $st_mutasi . "," . $jumlah_bayar . "," . $this->id_user . "," . $uang_diterima . "," . $kembali . ",'" . $ref_bayar . "'";
         $in = $this->db(0)->insertCols("kas", $cols, $vals);
         
         if ($in['errno'] == 0) {
            // Untuk QRIS (metode=2), return JSON dengan ref_bayar agar frontend bisa panggil generate_qris
            if ($metode == 2) {
               header('Content-Type: application/json');
               echo json_encode([
                  'status' => 'qris_pending',
                  'ref_bayar' => $ref_bayar,
                  'ref' => $ref,
                  'nominal' => $jumlah_bayar
               ]);
               return;
            }

            // Untuk non-QRIS, hitung ulang step seperti biasa
            $new_total_dibayar = $total_dibayar + $jumlah_bayar;
            $new_total_verified = $total_verified + ($st_mutasi == 1 ? $jumlah_bayar : 0);
            $new_has_pending = $has_pending || ($st_mutasi == 0);

            if ($new_total_dibayar >= $total_tagihan) {
               // Tentukan step berdasarkan status pembayaran
               if ($new_total_verified >= $total_tagihan && !$new_has_pending) {
                  // Semua verified, tutup order
                  $step = 1;
               } else {
                  // Ada pending, perlu pengecekan manual
                  $step = 4;
               }
               $up = $this->db(0)->update('ref', "step = " . $step, "id = '" . $ref . "'");
               echo $up['errno'] == 0 ? 0 : $up['error'];
            } else {
               // Belum lunas, order tetap terbuka
               echo 1;
            }
         } else {
            echo $in['error'];
         }
      }
   }

   public function hapus_bayar()
   {
      // Cek privilege (hanya kasir level atas)
      if ($_SESSION['resto_user']['id_privilege'] < 30) {
         echo "Anda tidak memiliki akses";
         return;
      }

      $id = $_POST['id'];

      // Cek apakah pembayaran ada
      $cek = $this->db(0)->get_where_row('kas', "id = " . $id);
      if (count($cek) == 0) {
         echo "Pembayaran tidak ditemukan";
         return;
      }

      // Hapus pembayaran (soft delete dengan status_mutasi = 2)
      $del = $this->db(0)->update("kas", "status_mutasi = 2", "id = " . $id);
      
      if ($del['errno'] == 0) {
         // Update step ref kembali ke 0 (order aktif)
         $this->db(0)->update('ref', "step = 0", "id = '" . $cek['ref'] . "'");
         
         Log::write("Hapus pembayaran id: $id, ref: " . $cek['ref'] . ", jumlah: " . $cek['jumlah']);
         echo 0;
      } else {
         echo $del['error'];
      }
   }

   public function piutang()
   {
      $pelanggan = $_POST['pelanggan'];
      $ref = $_POST['ref'];

      if ($pelanggan <= 0) {
         echo "Pelanggan tidak ditemukan";
         exit();
      }

      $order = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");

      $total = 0;
      foreach ($order as $dk) {
         $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
         $total += $subTotal;
      }

      if ($total > 0) {
         $up = $this->db(0)->update('ref', "step = 3, pelanggan = " . $pelanggan, "id = '" . $ref . "'");
         echo $up['errno'] == 0 ? 0 : $up['error'];
      }
   }

   public function cek_bayar($ref)
   {
      $viewData = __CLASS__ . '/bayar';
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $data['ref'] = $ref;

      // Cek QRIS Pending
      $qris_pending = $this->db(0)->get_where_row('kas', 
         "ref = '" . $ref . "' AND metode_mutasi = 2 AND status_mutasi = 0 AND payment_state = 'pending'"
      );

      if ($qris_pending && !empty($qris_pending['payment_qr_string'])) {
         $created_at = strtotime($qris_pending['payment_created_at']);
         $elapsed = time() - $created_at;
         
         $data['qris_pending'] = [
            'qr_string' => $qris_pending['payment_qr_string'],
            'trx_id' => $qris_pending['payment_trx_id'],
            'ref_bayar' => $qris_pending['ref_bayar'],
            'nominal' => $qris_pending['jumlah'],
            'elapsed' => $elapsed
         ];
      }

      $this->view($viewData, $data);
   }

   public function cek_piutang($ref)
   {
      $viewData = __CLASS__ . '/piutang';
      $data['order'] = $this->db(0)->get_where('pesanan', "ref = '" . $ref . "'", "id_menu");
      $data['bayar'] = $this->db(0)->get_where('kas', "ref = '" . $ref . "' AND status_mutasi <> 2");
      $data['pelanggan'] = $this->db(0)->get("pelanggan");
      $data['ref'] = $ref;
      $this->view($viewData, $data);
   }

   function add_manual($mode, $nomor)
   {
      $p = $_POST;
      $num_qty = preg_replace('/[^0-9]/', '', $p['qty']);
      $cek = $this->db(0)->get_where_row("ref", "mode = " . $mode . " AND nomor = " . $nomor . " AND step = 0");
      if (count($cek) > 0) {
         $where = "id_menu = " . $p['id'] . " AND ref = '" . $cek['id'] . "'";
         $cek_menu = $this->db(0)->get_where_row("pesanan", $where);
         if (count($cek_menu) > 0) {
            if ($num_qty <= 0) {
               $del = $this->db(0)->delete_where("pesanan", $where);
               if ($del['errno'] == 0) {
                  $hitung_menu = $this->db(0)->count_where("pesanan", "ref = '" . $cek_menu['ref'] . "'");
                  if ($hitung_menu == 0) {
                     //update freq
                     $this->db(0)->update("menu_item", "freq = freq - 1", "id = " . $p['id']);
                     $this->db(0)->update("menu_kategori", "freq = freq - 1", "id = " . $p['id_kat']);

                     $del = $this->db(0)->delete_where("ref", "id = '" . $cek_menu['ref'] . "'");
                     echo $del['errno'] == 0 ? 1 : $del['error'];
                  } else {
                     echo 0;
                  }
               } else {
                  echo $del['error'];
               }
            } else {
               $up = $this->db(0)->update("pesanan", "qty = " . $num_qty, $where);
               //update freq
               $this->db(0)->update("menu_item", "freq = freq + 1", "id = " . $p['id']);
               $this->db(0)->update("menu_kategori", "freq = freq + 1", "id = " . $p['id_kat']);
               echo $up['errno'] == 0 ? 0 : $up['error'];
            }
         } else {
            $cols = "ref, id_menu, qty, harga";
            $vals = "'" . $cek['id'] . "'," . $p['id'] . "," . $num_qty . "," . $_SESSION['resto_menu'][$p['id']]['harga'];
            $in = $this->db(0)->insertCols("pesanan", $cols, $vals);
            //update freq
            $this->db(0)->update("menu_item", "freq = freq + 1", "id = " . $p['id']);
            $this->db(0)->update("menu_kategori", "freq = freq + 1", "id = " . $p['id_kat']);
            echo $in['errno'] == 0 ? 0 : $in['error'];
         }
      } else {
         if ($num_qty <= 0) {
            echo "Qty 0 diabaikan";
            exit();
         }

         $ref = (date('Y') - 2024) . date('mdHis') . $this->id_cabang;
         $cols = "id, mode, nomor, tgl, jam, id_cabang";
         $vals = "'" . $ref . "'," . $mode . "," . $nomor . ",'" . date('Y-m-d') . "','" . date("H:i") . "'," . $this->id_cabang;
         $in = $this->db(0)->insertCols("ref", $cols, $vals);
         if ($in['errno'] == 0) {
            $p = $_POST;
            $cols = "ref, id_menu, qty, harga";
            $vals = "'" . $ref . "'," . $p['id'] . "," . $num_qty . "," . $_SESSION['resto_menu'][$p['id']]['harga'];
            $in = $this->db(0)->insertCols("pesanan", $cols, $vals);
            //update freq
            $this->db(0)->update("menu_item", "freq = freq + 1", "id = " . $p['id']);
            $this->db(0)->update("menu_kategori", "freq = freq + 1", "id = " . $p['id_kat']);
            echo $in['errno'] == 0 ? 0 : $in['error'];
         } else {
            echo $in['error'];
         }
      }
   }

   function set_diskon()
   {
      $p = $_POST;
      $where = "id = " . $p['id'];
      $cek_menu = $this->db(0)->get_where_row("pesanan", $where);
      $max_diskon = $cek_menu['harga'] * $cek_menu['qty'];
      if ($p['diskon'] > $max_diskon) {
         echo "Dikon melebihi Total";
         exit();
      }
      $up = $this->db(0)->update("pesanan", "diskon = " . $p['diskon'], $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];
   }

   function set_harga()
   {
      $p = $_POST;
      $where = "id = " . $p['id'];
      $cek_menu = $this->db(0)->get_where_row("pesanan", $where);
      $min_harga = $cek_menu['harga'];
      if ($p['harga'] < $min_harga) {
         echo "Harga harus lebih mahal dari harga awal";
         exit();
      }
      $up = $this->db(0)->update("pesanan", "harga = " . $p['harga'], $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];
   }

   /**
    * Generate QRIS untuk pembayaran
    * Dipanggil setelah bayar() meng-insert kas record
    * ref_bayar dari bayar() digunakan untuk lookup kas
    */
   public function generate_qris()
   {
      header('Content-Type: application/json');

      $ref_bayar = $_POST['ref_bayar'] ?? '';
      $ref = $_POST['ref'] ?? '';
      $nominal = intval($_POST['nominal'] ?? 0);

      if (empty($ref_bayar) || empty($ref) || $nominal <= 0) {
         echo json_encode(['status' => 'error', 'msg' => 'Data tidak lengkap']);
         return;
      }

      // Cari kas record berdasarkan ref_bayar
      $kas_record = $this->db(0)->get_where_row('kas', "ref_bayar = '" . $ref_bayar . "'");
      
      if (!$kas_record) {
         echo json_encode(['status' => 'error', 'msg' => 'Kas record tidak ditemukan']);
         return;
      }

      // Cek apakah sudah ada QR yang masih fresh (< 5 menit)
      if (!empty($kas_record['payment_qr_string']) && $kas_record['payment_state'] == 'pending') {
         $created_at = strtotime($kas_record['payment_created_at']);
         $elapsed = time() - $created_at;
         
         if ($elapsed < 300) {
            // QR masih fresh, return existing
            echo json_encode([
               'status' => 'success',
               'qr_string' => $kas_record['payment_qr_string'],
               'trx_id' => $kas_record['payment_trx_id'],
               'ref_bayar' => $ref_bayar
            ]);
            return;
         }
         // Jika expired, lanjut generate baru
      }

      // Generate unique order_id: RESTOKAS_ref_bayar_timestamp
      // Timestamp ditambahkan agar unik saat regenerate (menghindari error Duplicate Order ID dari Tokopay)
      $unique_order_id = 'RESTOKAS_' . $ref_bayar . '_' . time();

      // Panggil Tokopay API
      $tokopay = $this->model('Tokopay');
      $res = $tokopay->createOrder($nominal, $unique_order_id, 'QRIS');
      $data = json_decode($res, true);

      Log::write("QRIS Generate - RefBayar: $ref_bayar, Nominal: $nominal, OrderID: $unique_order_id, Response: $res");

      if (isset($data['status']) && $data['status']) {
         $qr_string = '';
         if (isset($data['data']['qr_string']) && !empty($data['data']['qr_string'])) {
            $qr_string = $data['data']['qr_string'];
         } elseif (isset($data['qr_string']) && !empty($data['qr_string'])) {
            $qr_string = $data['qr_string'];
         }

         if (empty($qr_string)) {
            echo json_encode(['status' => 'error', 'msg' => 'QR String not found']);
            return;
         }

         // UPDATE kas record dengan info QR
         $qr_escaped = addslashes($qr_string);
         $update = $this->db(0)->update('kas', 
            "payment_trx_id = '" . $unique_order_id . "', payment_qr_string = '" . $qr_escaped . "', payment_state = 'pending', payment_created_at = '" . date('Y-m-d H:i:s') . "'",
            "ref_bayar = '" . $ref_bayar . "'"
         );

         if ($update['errno'] == 0) {
            echo json_encode([
               'status' => 'success',
               'qr_string' => $qr_string,
               'trx_id' => $unique_order_id,
               'ref_bayar' => $ref_bayar
            ]);
         } else {
            Log::write("QRIS Update Failed - " . $update['error']);
            echo json_encode(['status' => 'error', 'msg' => 'Gagal update QRIS', 'error' => $update['error']]);
         }
      } else {
         Log::write("QRIS Generate Failed - " . json_encode($data));
         echo json_encode(['status' => 'error', 'msg' => 'Gagal generate QRIS', 'data' => $data]);
      }
   }

   /**
    * Cek status pembayaran QRIS
    */
   public function check_qris_status()
   {
      header('Content-Type: application/json');

      $ref = $_POST['ref'] ?? '';
      $ref_bayar = $_POST['ref_bayar'] ?? '';

      if (empty($ref_bayar)) {
         echo json_encode(['status' => 'error', 'msg' => 'Ref bayar tidak valid']);
         return;
      }

      // Ambil dari tabel kas berdasarkan ref_bayar
      $qris_record = $this->db(0)->get_where_row('kas', "ref_bayar = '" . $ref_bayar . "'");
      
      if (!$qris_record) {
         echo json_encode(['status' => 'error', 'msg' => 'Data QRIS tidak ditemukan']);
         return;
      }

      // Cek apakah sudah dibayar (mungkin webhook sudah update duluan)
      if ($qris_record['status_mutasi'] == 1 && $qris_record['payment_state'] == 'paid') {
         // Sudah paid, tidak perlu call Tokopay lagi
         echo json_encode(['status' => 'paid']);
         return;
      }

      // Cek apakah sudah canceled/expired
      if ($qris_record['status_mutasi'] == 2) {
         echo json_encode(['status' => 'expired', 'msg' => 'Transaksi dibatalkan']);
         return;
      }

      // Belum ada payment_trx_id (belum generate QR)
      if (empty($qris_record['payment_trx_id'])) {
         echo json_encode(['status' => 'error', 'msg' => 'QR belum di-generate']);
         return;
      }

      // THROTTLING: Cek ke Tokopay API maksimal 15 detik sekali per ref_bayar
      $last_check_key = 'last_qris_check_' . $ref_bayar;
      $last_check_time = $_SESSION[$last_check_key] ?? 0;
      $current_time = time();

      // Jika belum waktunya cek API (dan belum ada hasil local), return pending (dari local DB)
      if (($current_time - $last_check_time) < 15) {
         $created_at = strtotime($qris_record['payment_created_at']);
         $elapsed = time() - $created_at;
         echo json_encode(['status' => 'pending', 'elapsed' => $elapsed, 'source' => 'local_db']);
         return;
      }

      // Update timestamp cek terakhir
      $_SESSION[$last_check_key] = $current_time;

      // Cek ke Tokopay API
      $tokopay = $this->model('Tokopay');
      $res = $tokopay->checkStatus($qris_record['payment_trx_id'], $qris_record['jumlah'], 'QRIS');
      $data = json_decode($res, true);

      $isPaid = false;
      if (isset($data['data']['status'])) {
         $statusLower = strtolower($data['data']['status']);
         if ($statusLower == 'success' || $statusLower == 'paid') {
            $isPaid = true;
         }
      }

      if ($isPaid) {
         // Update kas record: status_mutasi=1 (verified), payment_state=paid
         $update = $this->db(0)->update('kas', 
            "status_mutasi = 1, payment_state = 'paid'", 
            "id = " . $qris_record['id']
         );

         if ($update['errno'] == 0) {
            // Gunakan ref dari kas record, bukan dari POST (lebih reliable)
            $actual_ref = $qris_record['ref'];
            
            // Hitung total tagihan
            $order = $this->db(0)->get_where('pesanan', "ref = '" . $actual_ref . "'", "id_menu");
            $total_tagihan = 0;
            foreach ($order as $dk) {
               $subTotal = ($dk['harga'] * $dk['qty']) - $dk['diskon'];
               $total_tagihan += $subTotal;
            }

            // Hitung total pembayaran dan status
            $total_dibayar = 0;
            $total_verified = 0;
            $has_pending = false;
            $cek_dibayar = $this->db(0)->get_where('kas', "status_mutasi <> 2 AND jenis_transaksi = 1 AND ref = '" . $actual_ref . "'");
            foreach ($cek_dibayar as $b) {
               $total_dibayar += $b['jumlah'];
               if ($b['status_mutasi'] == 1) {
                  $total_verified += $b['jumlah'];
               } else {
                  $has_pending = true;
               }
            }

            Log::write("QRIS Step Check - Ref: $actual_ref, Tagihan: $total_tagihan, Dibayar: $total_dibayar, Verified: $total_verified, HasPending: " . ($has_pending ? 'true' : 'false'));

            // Tentukan step berdasarkan pembayaran
            if ($total_dibayar >= $total_tagihan) {
               if ($total_verified >= $total_tagihan && !$has_pending) {
                  // Semua verified, tutup order
                  $step_update = $this->db(0)->update('ref', "step = 1", "id = '" . $actual_ref . "'");
                  Log::write("QRIS Step Update to 1 - Result: " . ($step_update['errno'] == 0 ? 'success' : $step_update['error']));
               } else {
                  // Ada pending, perlu pengecekan manual
                  $step_update = $this->db(0)->update('ref', "step = 4", "id = '" . $actual_ref . "'");
                  Log::write("QRIS Step Update to 4 - Result: " . ($step_update['errno'] == 0 ? 'success' : $step_update['error']));
               }
            }
            // Jika belum lunas, step tetap 0 (order terbuka)

            Log::write("QRIS Paid - Ref: $actual_ref, Nominal: " . $qris_record['jumlah'] . ", TrxID: " . $qris_record['payment_trx_id']);
            echo json_encode(['status' => 'paid']);
         } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal update pembayaran']);
         }
      } else {
         // Cek apakah expired (> 5 menit)
         $created_at = strtotime($qris_record['payment_created_at']);
         $elapsed = time() - $created_at;
         
         if ($elapsed > 300) {
            // QR expired - mark as cancelled, client akan auto-regenerate
            $this->db(0)->update('kas', "status_mutasi = 2, payment_state = 'expired'", "id = " . $qris_record['id']);
            echo json_encode(['status' => 'expired', 'msg' => 'QR sudah expired', 'can_regenerate' => true]);
         } else {
            echo json_encode(['status' => 'pending', 'elapsed' => $elapsed]);
         }
      }
   }
}
