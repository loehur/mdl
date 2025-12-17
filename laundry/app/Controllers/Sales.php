<?php

class Sales extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0;
      
      // Get checkout list (state = 0, type = 1) grouped by ref
      $checkouts = $this->db(1)->get_where('barang_mutasi', "state = 0 AND type = 1 AND source_id = '$id_cabang' ORDER BY id DESC");
      
      // Group by ref
      $grouped = [];
      foreach ($checkouts as $item) {
         $ref = $item['ref'];
         if (!isset($grouped[$ref])) {
            $grouped[$ref] = [
               'ref' => $ref,
               'date' => $item['created_at'] ?? date('Y-m-d H:i:s'),
               'items' => [],
               'total' => 0,
               'payments' => [],
               'total_paid' => 0
            ];
         }
         // Get barang name
         $barang = $this->db(1)->get_where_row('barang_data', "id_barang = '{$item['id_barang']}'");
         $item['nama_barang'] = $barang['nama'] ?? strtoupper(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? ''));
         $grouped[$ref]['items'][] = $item;
         $margin = $item['margin'] ?? 0;
         $grouped[$ref]['total'] += (($item['price'] + $margin) * $item['qty']);
      }
      
      // Get payment history for each ref
      foreach ($grouped as $ref => &$group) {
         $payments = [];
         $currentYear = (int)date('Y');
         for ($year = 2025; $year <= $currentYear; $year++) {
            $yearPayments = $this->db($year)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7 ORDER BY id_kas DESC");
            if ($yearPayments) {
               $payments = array_merge($payments, $yearPayments);
            }
         }
         // Re-sort payments by id_kas DESC if they came from multiple years
         usort($payments, function($a, $b) {
             return $b['id_kas'] <=> $a['id_kas'];
         });
         $group['payments'] = $payments ?: [];
         
         // Calculate total paid
         $totalPaid = 0;
         foreach ($group['payments'] as $payment) {
            $totalPaid += $payment['jumlah'];
         }
         $group['total_paid'] = $totalPaid;
         $group['sisa'] = $group['total'] - $totalPaid;
      }
      unset($group);
      
      $data_operasi = ['title' => 'Sales Order'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('sales/index', ['data_operasi' => $data_operasi, 'checkouts' => $grouped]);
   }

   // Load form order untuk offcanvas
   public function form()
   {
      $barang_data = $this->db(1)->get_where('barang_data','state = 1 ORDER BY sort DESC');
      $this->view('sales/form', ['barang_data' => $barang_data]);
   }

   // Load barang_sub berdasarkan id_barang
   public function get_sub($id_barang)
   {
      $where = "id_barang = '$id_barang'";
      $barang_sub = $this->db(1)->get_where('barang_sub', $where);
      
      // Get parent barang info
      $barang = $this->db(1)->get_where_row('barang_data', "id_barang = '$id_barang'");
      
      // Get unit name
      $unit_nama = '';
      if (isset($barang['unit'])) {
          $unit = $this->db(1)->get_where_row('barang_unit', "id = '{$barang['unit']}'");
          $unit_nama = $unit['nama'] ?? '';
      }
      $barang['unit_nama'] = $unit_nama;
      
      // Add margin for main item
      $barang['margin'] = floatval($barang['margin'] ?? 0);
      
      // Calculate margin for each sub item
      $barang_harga = floatval($barang['harga'] ?? $barang['price'] ?? 0);
      foreach ($barang_sub as &$sub) {
          $sub_denom = floatval($sub['qty'] ?? 1);
          $sub_price = floatval($sub['price'] ?? $sub['harga'] ?? 0);
          // Margin: ((1/denom) * sub_price) - barang_data.harga
          $sub['margin'] = ($sub_denom > 0) ? ((1 / $sub_denom) * $sub_price) - $barang_harga : 0;
      }
      unset($sub);

      
      header('Content-Type: application/json');
      echo json_encode([
         'barang' => $barang,
         'sub' => $barang_sub
      ]);
   }

   // Tambah ke cart
   public function add_to_cart()
   {
      ob_start(); // Capture any unexpected output
      
      $id_barang = $_POST['id_barang'] ?? 0;
      $id_sub = $_POST['id_sub'] ?? 0;
      $qty = intval($_POST['qty'] ?? 1);
      
      // Initialize cart session if not exists
      if (!isset($_SESSION['sales_cart'])) {
         $_SESSION['sales_cart'] = [];
      }
      
      // Get item info
      if ($id_sub > 0) {
         $item = $this->db(1)->get_where_row('barang_sub', "id = '$id_sub'");
         $barang = $this->db(1)->get_where_row('barang_data', "id_barang = '$id_barang'");
         $barang_harga = floatval($barang['price'] ?? 0);         
         
         if (!$item || !$barang) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
            return;
         }
         
         $denom = floatval($item['qty'] ?? 1);
         $multiple = 1 / $denom;
         
         $nama = ($barang['nama'] ?? strtoupper($barang['brand'].' '.$barang['model'])) . ' - ' . $item['nama'];
         $harga = $item['price'] ?? 0;
        
         $margin = (($harga*$multiple)-$barang_harga)/$multiple;
         $harga = $harga-$margin;
         
         $cart_key = 'sub_' . $id_sub;
      } else {
         $item = $this->db(1)->get_where_row('barang_data', "id_barang = '$id_barang'");
         
         if (!$item) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
            return;
         }
         
         $nama = $item['nama'] ?? strtoupper(implode(' ', array_filter([$item['brand'] ?? '', $item['model'] ?? '', $item['description'] ?? ''])));
         $harga = $item['price'] ?? $item['harga'] ?? 0;
         $denom = 1; // Main item denom = 1
         $margin = floatval($item['margin'] ?? 0); // Margin dari barang_data.margin
         $cart_key = 'main_' . $id_barang;
      }
      
      // Add to cart or update qty
      if (isset($_SESSION['sales_cart'][$cart_key])) {
         $_SESSION['sales_cart'][$cart_key]['qty'] += $qty;
      } else {
         $_SESSION['sales_cart'][$cart_key] = [
            'id_barang' => $id_barang,
            'nama' => $nama,
            'harga' => $harga,
            'qty' => $qty,
            'denom' => $denom,
            'margin' => $margin
         ];
      }
      
      ob_end_clean();
      session_write_close();
      header('Content-Type: application/json');
      echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['sales_cart'])]);
   }

   // Tambah barang utama ke cart
   public function add_main_to_cart()
   {
      ob_start(); // Capture any unexpected output
      
      $id_barang = $_POST['id_barang'] ?? 0;
      $qty = intval($_POST['qty'] ?? 1);
      
      if (!isset($_SESSION['sales_cart'])) {
         $_SESSION['sales_cart'] = [];
      }
      
      $item = $this->db(1)->get_where_row('barang_data', "id_barang = '$id_barang'");
      
      if (!$item) {
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
         return;
      }
      
      // Construct name
      $nama = $item['nama'] ?? '';
      if (empty($nama) && !empty($item['brand'])) {
          $nama = strtoupper(implode(' ', array_filter([$item['brand'] ?? '', $item['model'] ?? '', $item['description'] ?? ''])));
      }
      
      $harga = $item['price'] ?? $item['harga'] ?? 0;
      $margin = floatval($item['margin'] ?? 0); // Margin dari barang_data.margin
      $cart_key = 'main_' . $id_barang;
      
      if (isset($_SESSION['sales_cart'][$cart_key])) {
         $_SESSION['sales_cart'][$cart_key]['qty'] += $qty;
      } else {
         $_SESSION['sales_cart'][$cart_key] = [
            'id_barang' => $id_barang,
            'nama' => $nama,
            'harga' => $harga,
            'qty' => $qty,
            'denom' => 1, // Main item denom = 1
            'margin' => $margin
         ];
      }
      
      ob_end_clean();
      session_write_close();
      header('Content-Type: application/json');
      echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['sales_cart'])]);
   }

   // Load cart view
   public function cart()
   {
      $cart = $_SESSION['sales_cart'] ?? [];
      $this->view('sales/cart', ['cart' => $cart]);
   }

   // Remove from cart
   public function remove_from_cart()
   {
      $key = $_POST['key'] ?? '';
      if (isset($_SESSION['sales_cart'][$key])) {
         unset($_SESSION['sales_cart'][$key]);
      }
      
      session_write_close();
      header('Content-Type: application/json');
      echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['sales_cart'] ?? [])]);
   }

   // Clear cart
   public function clear_cart()
   {
      $_SESSION['sales_cart'] = [];
      
      session_write_close();
      header('Content-Type: application/json');
      echo json_encode(['status' => 'success']);
   }

   // Checkout - insert ke barang_mutasi
   public function checkout()
   {
      ob_start();
      
      $cart = $_SESSION['sales_cart'] ?? [];
      
      if (empty($cart)) {
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Keranjang kosong']);
         return;
      }
      
      // Generate ref: (tahun - 2024) + bulan + hari + jam + menit + detik + random digit
      $ref = (date('Y') - 2024) . date("mdHis") . rand(0, 9). rand(0, 9);
      
      $id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0;
      $id_user = $_SESSION[URL::SESSID]['user']['id_user'] ?? 0;
      
      $success_count = 0;
      $errors = [];
      
      foreach ($cart as $key => $item) {
         $data = [
            'type' => 1,
            'ref' => $ref,
            'id_barang' => $item['id_barang'],
            'source_id' => $id_cabang,
            'target_id' => 0,
            'denom' => $item['denom'],
            'price' => $item['harga'],
            'qty' => $item['qty'],
            'margin' => $item['margin'] ?? 0,
            'state' => 0,
            'id_user' => $id_user
         ];
         
         $insert = $this->db(1)->insert('barang_mutasi', $data);
         
         // insert() returns array with 'error' and 'errno'
         // errno = 0 means success
         if (isset($insert['errno']) && $insert['errno'] == 0) {
            $success_count++;
         } else {
            $errorMsg = "Gagal insert item: " . $item['nama'] . " - " . ($insert['error'] ?? 'Unknown error');
            $errors[] = $errorMsg;
            $this->model('Log')->write("[Sales::checkout] " . $errorMsg . " | Query: " . ($insert['query'] ?? 'N/A'));
         }
         
         // Update sort popularity
         $this->db(1)->query("UPDATE barang_data SET sort = sort + 1 WHERE id_barang = '{$item['id_barang']}'");
      }
      
      if ($success_count > 0) {
         // Clear cart after successful checkout
         $_SESSION['sales_cart'] = [];
         
         ob_end_clean();
         session_write_close();
         header('Content-Type: application/json');
         echo json_encode([
            'status' => 'success', 
            'message' => "Checkout berhasil! $success_count item disimpan.",
            'ref' => $ref
         ]);
      } else {
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode([
            'status' => 'error', 
            'message' => 'Gagal checkout',
            'errors' => $errors
         ]);
      }
   }

   // Bayar - proses pembayaran
   public function bayar()
   {
      ob_start();
      
      $ref = $_POST['ref'] ?? '';
      $karyawan = $_POST['karyawan'] ?? '';
      $metode = $_POST['metode'] ?? 1;

      $note = $_POST['note'] ?? '';
      $dibayar = $_POST['dibayar'] ?? 0;

      if($dibayar <= 0){
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Jumlah pembayaran harus lebih dari 0']);
         return;
      }
      
      $target = $_POST['target'] ?? 'kas_laundry';

      if($metode == 1){
         $status_mutasi = 3;
      }else{
         if($note == ''){
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Wajib Pilih Tujuan Bayar']);
            return;
         }
         $status_mutasi = 2;
      }
      
      if (empty($ref) || empty($karyawan) || empty($dibayar)) {
         $this->model('Log')->write("[Sales::bayar] Data tidak lengkap. Ref: $ref, Karyawan: $karyawan, Bayar: $dibayar");
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
         return;
      }
      
      $id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0;
      
      // Get total dari barang_mutasi dengan ref ini
      $items = $this->db(1)->get_where('barang_mutasi', "ref = '$ref' AND state = 0");
      if (empty($items)) {
         $this->model('Log')->write("[Sales::bayar] Data barang_mutasi tidak ditemukan atau sudah dibayar. Ref: $ref");
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan atau sudah dibayar']);
         return;
      }
      
      $total = 0;
      foreach ($items as $item) {
         $margin = $item['margin'] ?? 0;
         $total += (($item['price'] + $margin) * $item['qty']);
      }
      
      // Generate ref_finance untuk QRIS
      $ref_finance = $ref;
      
      // Insert ke tabel kas
      $dataKas = [
         'id_cabang' => $id_cabang,
         'id_user' => $karyawan,
         'ref_transaksi' => $ref,
         'ref_finance' => $ref_finance,
         'jenis_mutasi' => 1,
         'jenis_transaksi' => 7, //sales
         'metode_mutasi' => $metode,
         'note' => $note,
         'jumlah' => $dibayar,
         'status_mutasi' => $status_mutasi //lunas
      ];
      
      $insertKas = $this->db(date('Y'))->insert('kas', $dataKas);
      
      if (isset($insertKas['errno']) && $insertKas['errno'] == 0) {
         // Cek apakah sudah lunas sepenuhnya (total bayar >= total tagihan DAN semua status_mutasi = 3)
         $allPayments = [];
         $currentYear = (int) date('Y');
         $startYear = 2025;

         for ($year = $startYear; $year <= $currentYear; $year++) {
            $payments = $this->db($year)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
            if (!empty($payments)) {
               $allPayments = array_merge($allPayments, $payments);
            }
         }
         
         $totalBayar = 0;
         $allPaid = true;
         
         foreach ($allPayments as $p) {
            $totalBayar += $p['jumlah'];
            if ($p['status_mutasi'] != 3) {
               $allPaid = false;
            }
         }
         
         if ($totalBayar >= $total && $allPaid) {
            $this->db(1)->update('barang_mutasi', ['state' => 1], "ref = '$ref'");
         }
         
         // Jika QRIS, tidak perlu generate langsung, nanti saja saat klik tombol QR
         if (strtoupper($note) == 'QRIS') {
            ob_end_clean();
            session_write_close();
            header('Content-Type: application/json');
            echo json_encode([
               'status' => 'success',
               'message' => 'Pembayaran QRIS dicatat! Silahkan klik tombol QR di riwayat untuk scan.',
               'ref_finance' => $ref_finance
            ]);
         } elseif ($metode == 2 && strtoupper($note) != 'QRIS') {
            // Transfer Bank - insert ke wh_moota
            $bank_acc_id = isset(URL::MOOTA_BANK_ID[$note]) ? URL::MOOTA_BANK_ID[$note] : '';
            
            if (!empty($bank_acc_id)) {
               // Update payment_gateway di kas
               $book = $_SESSION[URL::SESSID]['user']['book'] ?? date('Y');
               $this->db($book)->update('kas', ['payment_gateway' => 'moota'], "ref_finance = '$ref_finance'");
               
               // Insert ke wh_moota untuk tracking
               $this->db(100)->insert('wh_moota', [
                  'trx_id' => $ref_finance,
                  'bank_id' => $bank_acc_id,
                  'amount' => $dibayar,
                  'target' => 'kas_laundry',
                  'book' => date('Y'),
                  'state' => 'pending'
               ]);
            }
            
            ob_end_clean();
            session_write_close();
            header('Content-Type: application/json');
            echo json_encode([
               'status' => 'success',
               'message' => 'Pembayaran Transfer berhasil dicatat! Ref: #' . $ref
            ]);
         } else {
            // Tunai atau lainnya  
            ob_end_clean();
            session_write_close();
            header('Content-Type: application/json');
            echo json_encode([
               'status' => 'success',
               'message' => 'Pembayaran berhasil! Ref: #' . $ref
            ]);
         }
      } else {
         $this->model('Log')->write("[Sales::bayar] Insert kas error: " . ($insertKas['error'] ?? 'Unknown') . " | Query: " . ($insertKas['query'] ?? 'N/A'));
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menyimpan pembayaran'
         ]);
      }
   }

   // Hapus riwayat pembayaran
   // Hapus riwayat pembayaran
   public function hapusPayment()
   {
      // Bersihkan output buffer sebelumnya jika ada (untuk mencegah HTML error masuk JSON)
      if (ob_get_length()) ob_clean();
      ob_start();
      
      try {
          $id_kas = $_POST['id_kas'] ?? 0;
          $book = $_SESSION[URL::SESSID]['user']['book'] ?? date('Y');
          
          if (empty($id_kas)) {
             throw new Exception('ID Kas tidak valid');
          }
          
          // Cek apakah status_mutasi = 3 (tidak boleh dihapus)
          $kas = $this->db($book)->get_where_row('kas', "id_kas = '$id_kas'");
          
          if (!$kas) {
             throw new Exception('Data tidak ditemukan');
          }
          
          // Cek field status_mutasi
          if (isset($kas['status_mutasi']) && $kas['status_mutasi'] == 3) {
             throw new Exception('Tidak dapat menghapus pembayaran yang sudah LUNAS');
          }
          
          // Hapus data
          $delete = $this->db($book)->delete('kas', "id_kas = '$id_kas'");
          
          if (isset($delete['errno']) && $delete['errno'] == 0) {
             $response = ['status' => 'success', 'message' => 'Pembayaran berhasil dihapus'];
          } else {
             $this->model('Log')->write("[Sales::hapusPayment] Delete error: " . ($delete['error'] ?? 'Unknown'));
             throw new Exception('Gagal menghapus pembayaran (DB Error)');
          }
      } catch (Exception $e) {
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      header('Content-Type: application/json');
      echo json_encode($response);
   }

   // Hapus nota (semua item dengan ref tertentu)
   public function hapusNota()
   {
      ob_start();
      
      $ref = $_POST['ref'] ?? '';
      
      if (empty($ref)) {
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Ref tidak valid']);
         return;
      }
      
      // Cek apakah ada pembayaran untuk ref ini
      $book = $_SESSION[URL::SESSID]['user']['book'] ?? date('Y');
      $payments = $this->db($book)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
      
      if (!empty($payments)) {
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Tidak dapat menghapus nota yang sudah memiliki riwayat pembayaran']);
         return;
      }
      
      // Hapus semua item di barang_mutasi dengan ref ini
      $delete = $this->db(1)->delete('barang_mutasi', "ref = '$ref'");
      
      if (isset($delete['errno']) && $delete['errno'] == 0) {
         ob_end_clean();
         session_write_close();
         header('Content-Type: application/json');
         echo json_encode([
            'status' => 'success',
            'message' => 'Nota berhasil dihapus'
         ]);
      } else {
         $this->model('Log')->write("[Sales::hapusNota] Delete error: " . ($delete['error'] ?? 'Unknown'));
         ob_end_clean();
         header('Content-Type: application/json');
         echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus nota'
         ]);
      }
   }
}
