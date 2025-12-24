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
      
      // Get checkout list 
      // Filter: 
      // - Hide Piutang (state = 3) -> Sudah ada di Sales Operasi > Piutang
      // - Hide Pemakaian (type = 3) -> Sudah ada di Sales Operasi > Pakai
      // - Tampilkan: 
      //   1. Sales/Penjualan (type=1) yang belum bayar/progress (state=0)
      //   2. Transfer (type=2) yang belum selesai (state=0)
      $checkouts = $this->db(0)->get_where('barang_mutasi', 
         "state = 0 AND type IN (1,2) AND (source_id = '$id_cabang' OR target_id = '$id_cabang') ORDER BY id DESC");
      
      // Group by ref
      $grouped = [];
      foreach ($checkouts as $item) {
         $ref = $item['ref'];
         if (!isset($grouped[$ref])) {
            $grouped[$ref] = [
               'ref' => $ref,
               'date' => $item['created_at'] ?? date('Y-m-d H:i:s'),
               'type' => $item['type'], // Simpan type
               'items' => [],
               'total' => 0,
               'payments' => [],
               'total_paid' => 0
            ];
         }
         // Get barang name
         $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '{$item['id_barang']}'");
         $item['nama_barang'] = $barang['nama'] ?? strtoupper(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? ''));
         $grouped[$ref]['items'][] = $item;
         $margin = $item['margin'] ?? 0;
         $grouped[$ref]['total'] += (($item['price'] + $margin) * $item['qty']);
      }
      
      // Get payment history for each ref
      foreach ($grouped as $ref => &$group) {
         $payments = [];
         $payments = $this->db(0)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
         $group['payments'] = $payments ?: [];
         
         // Calculate total paid
         $totalPaid = 0;
         $allPaid = true;
         foreach ($group['payments'] as $payment) {
            $totalPaid += $payment['jumlah'];
            if ($payment['status_mutasi'] != 3) {
               $allPaid = false;
            }
         }
         $group['total_paid'] = $totalPaid;
         $group['sisa'] = $group['total'] - $totalPaid;
         
         // Self-healing: Jika sudah lunas tapi masih muncul (state=0), update jadi state=1
         if ($group['sisa'] <= 0 && $allPaid && count($group['payments']) > 0) {
            $this->db(0)->update('barang_mutasi', ['state' => 1], "ref = '$ref'");
            unset($grouped[$ref]);
         }
      }
      unset($group);
      
      // Get list cabang untuk modal transfer
      $listCabang = $this->db(0)->get('cabang');
      
      $data_operasi = ['title' => 'Sales Order'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('sales/index', [
         'data_operasi' => $data_operasi, 
         'checkouts' => $grouped,
         'listCabang' => $listCabang
      ]);
   }

   // Load form order untuk offcanvas
   public function form()
   {
      $barang_data = $this->db(0)->get_where('barang_data','state = 1 ORDER BY sort DESC');
      $this->view('sales/form', ['barang_data' => $barang_data]);
   }

   // Load barang_sub berdasarkan id_barang
   public function get_sub($id_barang)
   {
      $where = "id_barang = '$id_barang'";
      $barang_sub = $this->db(0)->get_where('barang_sub', $where);
      
      // Get parent barang info
      $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '$id_barang'");
      
      // Get unit name
      $unit_nama = '';
      if (isset($barang['unit'])) {
          $unit = $this->db(0)->get_where_row('barang_unit', "id = '{$barang['unit']}'");
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
         $item = $this->db(0)->get_where_row('barang_sub', "id = '$id_sub'");
         $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '$id_barang'");
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
         $item = $this->db(0)->get_where_row('barang_data', "id_barang = '$id_barang'");
         
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
      
      $item = $this->db(0)->get_where_row('barang_data', "id_barang = '$id_barang'");
      
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
         
         $insert = $this->db(0)->insert('barang_mutasi', $data);
         
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
         $this->db(0)->query("UPDATE barang_data SET sort = sort + 1 WHERE id_barang = '{$item['id_barang']}'");
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
      // Clean previous buffer if any
      if (ob_get_length()) ob_clean();
      ob_start();
      
      try {
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
          // Allow state 0 (belum bayar) dan state 3 (piutang)
          $items = $this->db(0)->get_where('barang_mutasi', "ref = '$ref' AND state IN (0,3)");
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
          // Use unique Ref Finance for every payment transaction to allow multiple/partial payments
          $ref_finance = (date('Y') - 2024) . date("mdHis") . rand(0, 9) . rand(0, 9);
          
          // Insert ke tabel kas
          $dataKas = [
             'id_kas' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
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
          
          $insertKas = $this->db(0)->insert('kas', $dataKas);
          
          if (isset($insertKas['errno']) && $insertKas['errno'] == 0) {
             // Cek apakah sudah lunas sepenuhnya (total bayar >= total tagihan DAN semua status_mutasi = 3)
             // Hanya cek database tahun ini (0) untuk menghindari access denied
             $allPayments = $this->db(0)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
             
             $totalBayar = 0;
             $allPaid = true;
             
             foreach ($allPayments as $p) {
                $totalBayar += $p['jumlah'];
                if ($p['status_mutasi'] != 3) {
                   $allPaid = false;
                }
             }
             
             if ($totalBayar >= $total && $allPaid) {
                $this->db(0)->update('barang_mutasi', ['state' => 1], "ref = '$ref'");
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
                
                // Safe checking for MOOTA_BANK_ID
                $bank_acc_id = '';
                if (defined('URL::MOOTA_BANK_ID')) {
                    $moota_ids = constant('URL::MOOTA_BANK_ID');
                    $bank_acc_id = isset($moota_ids[$note]) ? $moota_ids[$note] : '';
                }
                
                if (!empty($bank_acc_id)) {
                   // Update payment_gateway di kas
                   $this->db(0)->update('kas', ['payment_gateway' => 'moota'], "ref_finance = '$ref_finance'");
                   
                   // Insert ke wh_moota untuk tracking (Wrap in try catch)
                   try {
                       $this->db(100)->insert('wh_moota', [
                          'trx_id' => $ref_finance,
                          'bank_id' => $bank_acc_id,
                          'amount' => $dibayar,
                          'target' => 'kas_laundry',
                          'book' => date('Y'),
                          'state' => 'pending'
                       ]);
                   } catch (\Throwable $e) {
                       $this->model('Log')->write("[Sales::bayar] Error insert wh_moota: " . $e->getMessage());
                   }
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
             $errorMsg = $insertKas['error'] ?? 'Unknown error';
             $this->model('Log')->write("[Sales::bayar] Insert kas error: " . $errorMsg . " | Query: " . ($insertKas['query'] ?? 'N/A'));
             ob_end_clean();
             header('Content-Type: application/json');
             echo json_encode([
                'status' => 'error',
                'message' => 'Gagal menyimpan pembayaran: ' . $errorMsg
             ]);
          }
      } catch (\Throwable $t) {
          $this->model('Log')->write("[Sales::bayar] Exception: " . $t->getMessage() . " File: " . $t->getFile() . ":" . $t->getLine());
          ob_end_clean();
          if (!headers_sent()) header('Content-Type: application/json');
          
          echo json_encode([
             'status' => 'error', 
             'message' => 'Terjadi kesalahan sistem: ' . $t->getMessage() . " (Line " . $t->getLine() . ")"
          ]);
      }
   }

   // Hapus riwayat pembayaran
   public function hapusPayment()
   {
      // Bersihkan output buffer sebelumnya jika ada (untuk mencegah HTML error masuk JSON)
      if (ob_get_length()) ob_clean();
      ob_start();
      
      $response = ['status' => 'error', 'message' => 'Unknown error'];

      try {
          $id_kas = $_POST['id_kas'] ?? 0;
          
          if (empty($id_kas)) {
             throw new Exception('ID Kas tidak valid');
          }
          
          // Cek apakah status_mutasi = 3 (tidak boleh dihapus)
          $kas = $this->db(0)->get_where_row('kas', "id_kas = '$id_kas'");
          
          if (!$kas) {
             throw new Exception('Data tidak ditemukan');
          }
          
          // Cek field status_mutasi
          if (isset($kas['status_mutasi']) && $kas['status_mutasi'] == 3) {
             throw new Exception('Tidak dapat menghapus pembayaran yang sudah LUNAS');
          }
          
          // Hapus data
          $delete = $this->db(0)->delete('kas', "id_kas = '$id_kas'");
          
          if (isset($delete['errno']) && $delete['errno'] == 0) {
             $response = ['status' => 'success', 'message' => 'Pembayaran berhasil dihapus'];
          } else {
             $errorMsg = $delete['error'] ?? 'Unknown DB Error';
             $this->model('Log')->write("[Sales::hapusPayment] Delete error: " . $errorMsg);
             throw new Exception('Gagal menghapus pembayaran: ' . $errorMsg);
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[Sales::hapusPayment] Error: " . $e->getMessage());
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode($response);
   }

   // Hapus nota (semua item dengan ref tertentu)
   public function hapusNota()
   {
      if (ob_get_length()) ob_clean();
      ob_start();
      
      $response = ['status' => 'error', 'message' => 'Unknown error'];
      
      try {
          $ref = $_POST['ref'] ?? '';
          
          if (empty($ref)) {
             throw new Exception('Ref tidak valid');
          }
          
          // Cek apakah ada pembayaran untuk ref ini
          $payments = $this->db(0)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
          
          if (!empty($payments)) {
             throw new Exception('Tidak dapat menghapus nota yang sudah memiliki riwayat pembayaran');
          }
          
          // Hapus semua item di barang_mutasi dengan ref ini
          $delete = $this->db(0)->delete('barang_mutasi', "ref = '$ref'");
          
          if (isset($delete['errno']) && $delete['errno'] == 0) {
             $response = ['status' => 'success', 'message' => 'Nota berhasil dihapus'];
          } else {
             $errorMsg = $delete['error'] ?? 'Unknown DB Error';
             $this->model('Log')->write("[Sales::hapusNota] Delete error: " . $errorMsg);
             throw new Exception('Gagal menghapus nota: ' . $errorMsg);
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[Sales::hapusNota] Error: " . $e->getMessage());
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode($response);
   }

   // Pakai - ubah type = 3, state = 0
   public function pakai()
   {
      if (ob_get_length()) ob_clean();
      ob_start();
      
      $response = ['status' => 'error', 'message' => 'Unknown error'];
      
      try {
          $ref = $_POST['ref'] ?? '';
          
          if (empty($ref)) {
             throw new Exception('Ref tidak valid');
          }
          
          // Cek apakah ada pembayaran untuk ref ini
          $payments = $this->db(0)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
          
          if (!empty($payments)) {
             throw new Exception('Tidak dapat mengubah nota yang sudah memiliki riwayat pembayaran');
          }
          
          // Cek apakah ref ada di barang_mutasi
          $items = $this->db(0)->get_where('barang_mutasi', "ref = '$ref'");
          
          if (empty($items)) {
             throw new Exception('Data nota tidak ditemukan');
          }
          
          // Update type = 3, state = 0
          $update = $this->db(0)->update('barang_mutasi', ['type' => 3, 'state' => 0], "ref = '$ref'");
          
          if (isset($update['errno']) && $update['errno'] == 0) {
             $response = ['status' => 'success', 'message' => 'Nota berhasil diubah ke status Pakai (Type=3, State=0)'];
          } else {
             $errorMsg = $update['error'] ?? 'Unknown DB Error';
             $this->model('Log')->write("[Sales::pakai] Update error: " . $errorMsg);
             throw new Exception('Gagal mengubah nota: ' . $errorMsg);
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[Sales::pakai] Error: " . $e->getMessage());
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode($response);
   }

   // Transfer - ubah target_id = id_cabang tujuan
   public function transfer()
   {
      if (ob_get_length()) ob_clean();
      ob_start();
      
      $response = ['status' => 'error', 'message' => 'Unknown error'];
      
      try {
          $ref = $_POST['ref'] ?? '';
          $target_id = $_POST['target_id'] ?? 0;
          
          if (empty($ref)) {
             throw new Exception('Ref tidak valid');
          }
          
          if (empty($target_id)) {
             throw new Exception('Cabang tujuan tidak valid');
          }
          
          // Cek apakah ada pembayaran untuk ref ini
          $payments = $this->db(0)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
          
          if (!empty($payments)) {
             throw new Exception('Tidak dapat transfer nota yang sudah memiliki riwayat pembayaran');
          }
          
          // Cek apakah ref ada di barang_mutasi
          $items = $this->db(0)->get_where('barang_mutasi', "ref = '$ref'");
          
          if (empty($items)) {
             throw new Exception('Data nota tidak ditemukan');
          }
          
          // Cek apakah cabang tujuan valid
          $cabang = $this->db(0)->get_where_row('cabang', "id_cabang = '$target_id'");
          
          if (!$cabang) {
             throw new Exception('Cabang tujuan tidak ditemukan');
          }
          
          // Update target_id
          $update1 = $this->db(0)->update('barang_mutasi', ['target_id' => $target_id], "ref = '$ref'");
          
          // Update type = 2 (transfer/peninjauan) - dengan raw query sebagai backup
          $update2 = $this->db(0)->update('barang_mutasi', ['type' => 2], "ref = '$ref'");
          
          // Jika update method gagal, coba dengan raw query
          if (!isset($update2['errno']) || $update2['errno'] != 0) {
             $this->db(0)->query("UPDATE barang_mutasi SET type = 2 WHERE ref = '$ref'");
          }
          
          // Verifikasi update berhasil
          $verify = $this->db(0)->get_where_row('barang_mutasi', "ref = '$ref'");
          
          // Log untuk debugging
          $this->model('Log')->write("[Sales::transfer] Ref: $ref, Target: $target_id, Type setelah update: " . ($verify['type'] ?? 'null') . ", Target_id: " . ($verify['target_id'] ?? 'null'));
          
          if ($verify && $verify['type'] == 2 && $verify['target_id'] == $target_id) {
             $cabangNama = $cabang['kode_cabang'] . ' - ' . $cabang['nama'];
             $response = ['status' => 'success', 'message' => 'Nota berhasil ditransfer ke ' . $cabangNama];
          } else {
             $errorMsg = ($update1['error'] ?? '') . ' | ' . ($update2['error'] ?? 'Unknown DB Error');
             $this->model('Log')->write("[Sales::transfer] Update error: " . $errorMsg);
             throw new Exception('Gagal transfer nota: ' . $errorMsg);
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[Sales::transfer] Error: " . $e->getMessage());
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode($response);
   }
   
   // Terima Barang - ubah state = 1 (diterima)
   public function terimaBarang()
   {
      if (ob_get_length()) ob_clean();
      ob_start();
      
      $response = ['status' => 'error', 'message' => 'Unknown error'];
      
      try {
          $ref = $_POST['ref'] ?? '';
          
          if (empty($ref)) {
             throw new Exception('Ref tidak valid');
          }
          
          // Cek apakah ref ada di barang_mutasi
          $items = $this->db(0)->get_where('barang_mutasi', "ref = '$ref'");
          
          if (empty($items)) {
             throw new Exception('Data nota tidak ditemukan');
          }
          
          // Cek apakah type = 2 (transfer)
          if ($items[0]['type'] != 2) {
             throw new Exception('Nota ini bukan transfer barang');
          }
          
          // Update state = 1 (diterima/selesai) dengan raw query
          $update = $this->db(0)->query("UPDATE barang_mutasi SET state = 1 WHERE ref = '$ref'");
          
          // Verifikasi update berhasil
          $verify = $this->db(0)->get_where_row('barang_mutasi', "ref = '$ref'");
          
          // Log untuk debugging
          $this->model('Log')->write("[Sales::terimaBarang] Ref: $ref, State setelah update: " . ($verify['state'] ?? 'null'));
          
          if ($verify && $verify['state'] == 1) {
             $response = ['status' => 'success', 'message' => 'Barang berhasil diterima'];
          } else {
             $this->model('Log')->write("[Sales::terimaBarang] Update state gagal");
             throw new Exception('Gagal menerima barang');
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[Sales::terimaBarang] Error: " . $e->getMessage());
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode($response);
   }
   
   // Piutang - ubah state = 3 (piutang)
   public function piutang()
   {
      if (ob_get_length()) ob_clean();
      ob_start();
      
      $response = ['status' => 'error', 'message' => 'Unknown error'];
      
      try {
          $ref = $_POST['ref'] ?? '';
          
          if (empty($ref)) {
             throw new Exception('Ref tidak valid');
          }
          
          // Cek apakah ref ada di barang_mutasi
          $items = $this->db(0)->get_where('barang_mutasi', "ref = '$ref'");
          
          if (empty($items)) {
             throw new Exception('Data nota tidak ditemukan');
          }
          
          // Update state = 3 (piutang) dengan raw query
          $update = $this->db(0)->query("UPDATE barang_mutasi SET state = 3 WHERE ref = '$ref'");
          
          // Verifikasi update berhasil
          $verify = $this->db(0)->get_where_row('barang_mutasi', "ref = '$ref'");
          
          // Log untuk debugging
          $this->model('Log')->write("[Sales::piutang] Ref: $ref, State setelah update: " . ($verify['state'] ?? 'null'));
          
          if ($verify && $verify['state'] == 3) {
             $response = ['status' => 'success', 'message' => 'Berhasil dicatat sebagai piutang'];
          } else {
             $this->model('Log')->write("[Sales::piutang] Update state gagal");
             throw new Exception('Gagal mencatat piutang');
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[Sales::piutang] Error: " . $e->getMessage());
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode($response);
   }
   
   // ========== SALES OPERASI - PAKAI ==========
   public function operasi_pakai()
   {
      $id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0;
      
      // Get tanggal dari filter
      $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
      $endDate = $_GET['end'] ?? date('Y-m-d');
      
      // Validasi rentang maksimal 1 minggu (7 hari)
      $diffDays = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
      if ($diffDays > 7) {
         $endDate = date('Y-m-d', strtotime($startDate . ' +7 days'));
      }
      
      // Get data barang yang sudah dipakai (type = 3)
      $items = $this->db(0)->get_where('barang_mutasi', 
         "type = 3 AND source_id = '$id_cabang' AND DATE(created_at) >= '$startDate' AND DATE(created_at) <= '$endDate' ORDER BY created_at DESC");
      
      // Pastikan $items adalah array
      if (!is_array($items)) {
         $items = [];
      }
      
      // Group by ref
      $grouped = [];
      foreach ($items as $item) {
         // Skip jika item tidak valid atau tidak punya ref
         if (!isset($item['ref']) || empty($item['ref'])) {
            continue;
         }
         
         $ref = $item['ref'];
         if (!isset($grouped[$ref])) {
            $grouped[$ref] = [
               'ref' => $ref,
               'date' => $item['created_at'] ?? date('Y-m-d H:i:s'),
               'state' => $item['state'] ?? 0,
               'items' => [],
               'total' => 0
            ];
         }
         
         // Get barang name
         $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '{$item['id_barang']}'");
         $item['nama_barang'] = $barang['nama'] ?? strtoupper(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? ''));
         $grouped[$ref]['items'][] = $item;
         
         $margin = $item['margin'] ?? 0;
         $grouped[$ref]['total'] += (($item['price'] + $margin) * $item['qty']);
      }
      
      $data_operasi = ['title' => 'Barang Dipakai'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('sales/operasi_pakai', [
         'grouped' => $grouped,
         'startDate' => $startDate,
         'endDate' => $endDate
      ]);
   }
   
   // ========== SALES OPERASI - TRANSFER ==========
   public function operasi_transfer()
   {
      $id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0;
      
      // Get tanggal dari filter
      $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
      $endDate = $_GET['end'] ?? date('Y-m-d');
      
      // Validasi rentang maksimal 1 minggu (7 hari)
      $diffDays = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
      if ($diffDays > 7) {
         $endDate = date('Y-m-d', strtotime($startDate . ' +7 days'));
      }
      
      // Get data barang transfer (type = 2)
      // Tampilkan yang dikirim (source) dan diterima (target)
      $items = $this->db(0)->get_where('barang_mutasi', 
         "type = 2 AND (source_id = '$id_cabang' OR target_id = '$id_cabang') AND DATE(created_at) >= '$startDate' AND DATE(created_at) <= '$endDate' ORDER BY created_at DESC");
      
      if (!is_array($items)) { $items = []; }

      // Group by ref
      $grouped = [];
      foreach ($items as $item) {
         if (!isset($item['ref']) || empty($item['ref'])) continue;

         $ref = $item['ref'];
         if (!isset($grouped[$ref])) {
            $grouped[$ref] = [
               'ref' => $ref,
               'date' => $item['created_at'] ?? '-',
               'state' => $item['state'] ?? 0,
               'source_id' => $item['source_id'] ?? 0,
               'target_id' => $item['target_id'] ?? 0,
               'items' => [],
               'total' => 0
            ];
         }
         
         // Get barang name
         $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '{$item['id_barang']}'");
         $item['nama_barang'] = $barang['nama'] ?? strtoupper(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? ''));
         $grouped[$ref]['items'][] = $item;
         
         $margin = $item['margin'] ?? 0;
         $grouped[$ref]['total'] += (($item['price'] + $margin) * $item['qty']);
      }
      
      // Get nama cabang untuk display
      $cabangList = $this->db(0)->get('cabang');
      $cabangMap = [];
      if (is_array($cabangList)) {
         foreach ($cabangList as $cb) {
            $cabangMap[$cb['id_cabang']] = $cb['kode_cabang'];
         }
      }
      
      $data_operasi = ['title' => 'Transfer Barang'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('sales/operasi_transfer', [
         'grouped' => $grouped,
         'cabangMap' => $cabangMap,
         'startDate' => $startDate,
         'endDate' => $endDate
      ]);
   }
   
   // ========== SALES OPERASI - PIUTANG ==========
   public function operasi_piutang()
   {
      $id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0;
      
      // Get data piutang (state = 3)
      // Tidak perlu filter tanggal, tampilkan semua piutang aktif
      $items = $this->db(0)->get_where('barang_mutasi', 
         "state = 3 AND source_id = '$id_cabang' ORDER BY created_at DESC");
      
      if (!is_array($items)) { $items = []; }

      // Group by ref
      $grouped = [];
      foreach ($items as $item) {
         if (!isset($item['ref']) || empty($item['ref'])) continue;

         $ref = $item['ref'];
         if (!isset($grouped[$ref])) {
            $grouped[$ref] = [
               'ref' => $ref,
               'date' => $item['created_at'] ?? '-',
               'type' => $item['type'] ?? 0,
               'items' => [],
               'total' => 0,
               'total_paid' => 0
            ];
         }
         
         // Get barang name
         $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '{$item['id_barang']}'");
         $item['nama_barang'] = $barang['nama'] ?? strtoupper(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? ''));
         $grouped[$ref]['items'][] = $item;
         
         $margin = $item['margin'] ?? 0;
         $grouped[$ref]['total'] += (($item['price'] + $margin) * $item['qty']);
      }
      
      // Get payment history untuk setiap ref
      foreach ($grouped as $ref => &$group) {
         $payments = $this->db(0)->get_where('kas', "ref_transaksi = '$ref' AND jenis_transaksi = 7");
         // Safety check for payments
         if (!is_array($payments)) { $payments = []; }
         
         $totalPaid = 0;
         foreach ($payments as $payment) {
            $totalPaid += ($payment['jumlah'] ?? 0);
         }
         $group['total_paid'] = $totalPaid;
         $group['sisa'] = $group['total'] - $totalPaid;
      }
      unset($group);
      
      $data_operasi = ['title' => 'Daftar Piutang'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('sales/operasi_piutang', [
         'grouped' => $grouped
      ]);
   }

   public function operasi_tuntas()
   {
      $id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0;
      
      // Get tanggal dari filter
      $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-6 days')); // Default 7 hari terakhir (termasuk hari ini)
      $endDate = $_GET['end'] ?? date('Y-m-d');
      
      // Validasi rentang maksimal 1 minggu (7 hari)
      $diffDays = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
      if ($diffDays > 7) {
         $endDate = date('Y-m-d', strtotime($startDate . ' +7 days'));
      }
      
      // Get data penjualan tuntas (type = 1, state = 1)
      $items = $this->db(0)->get_where('barang_mutasi', 
         "state = 1 AND type = 1 AND source_id = '$id_cabang' AND DATE(created_at) >= '$startDate' AND DATE(created_at) <= '$endDate' ORDER BY created_at DESC");
      
      if (!is_array($items)) { $items = []; }

      // Group by ref
      $grouped = [];
      foreach ($items as $item) {
         if (!isset($item['ref']) || empty($item['ref'])) continue;

         $ref = $item['ref'];
         if (!isset($grouped[$ref])) {
            $grouped[$ref] = [
               'ref' => $ref,
               'date' => $item['created_at'] ?? '-',
               'items' => [],
               'total' => 0
            ];
         }
         
         // Get barang name
         $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '{$item['id_barang']}'");
         $item['nama_barang'] = $barang['nama'] ?? strtoupper(($barang['brand'] ?? '') . ' ' . ($barang['model'] ?? ''));
         $grouped[$ref]['items'][] = $item;
         
         $margin = $item['margin'] ?? 0;
         $grouped[$ref]['total'] += (($item['price'] + $margin) * $item['qty']);
      }
      
      $data_operasi = ['title' => 'Order Selesai'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('sales/operasi_tuntas', [
         'grouped' => $grouped,
         'startDate' => $startDate,
         'endDate' => $endDate
      ]);
   }
}
