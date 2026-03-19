<?php

class BarangMasuk extends Controller
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
      // - Barang Masuk: type = 2 AND source_id = 0 AND target_id = current_cabang
      $checkouts = $this->db(0)->get_where('barang_mutasi', 
         "state = 0 AND type = 2 AND source_id = 0 AND target_id = '$id_cabang' ORDER BY id DESC");
      
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
               'payments' => [], // Not used but kept for view compatibility
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
      
      // No payment processing for Barang Masuk
      foreach ($grouped as $ref => &$group) {
         $group['total_paid'] = 0;
         $group['sisa'] = $group['total'];
      }
      unset($group);
      
      // Get list cabang (might not be needed but kept for compatibility)
      $listCabang = $this->db(0)->get('cabang');
      
      $data_operasi = ['title' => 'Barang Masuk'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('barang_masuk/index', [
         'data_operasi' => $data_operasi, 
         'checkouts' => $grouped,
         'listCabang' => $listCabang
      ]);
   }

   // Load form order untuk offcanvas
   public function form()
   {
      $barang_data = $this->db(0)->get_where('barang_data','state = 1 ORDER BY sort DESC');
      $this->view('barang_masuk/form', ['barang_data' => $barang_data]);
   }

   // Load barang_sub berdasarkan id_barang
   public function get_sub($id_barang)
   {
      $where = "id_barang = '$id_barang'";
      $barang_sub = $this->db(0)->get_where('barang_sub', $where);
      
      // Get parent barang info
      $barang = $this->db(0)->get_where_row('barang_data', "id_barang = '$id_barang'");
      
      // Get sisa stok (logic sama Sales/Stok controller)
      $id_cabang = intval($_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0);
      $res_in = $this->db(0)->get_cols_where('barang_mutasi',
         "id_barang, SUM(denom * qty) as total_in",
         "target_id = '$id_cabang' AND id_barang = '$id_barang' GROUP BY id_barang",
         1
      );
      $res_out = $this->db(0)->get_cols_where('barang_mutasi',
         "id_barang, SUM(denom * qty) as total_out",
         "source_id = '$id_cabang' AND state != 2 AND id_barang = '$id_barang' GROUP BY id_barang",
         1
      );
      $total_in = (is_array($res_in) && !empty($res_in) && isset($res_in[0]['total_in'])) ? floatval($res_in[0]['total_in']) : 0;
      $total_out = (is_array($res_out) && !empty($res_out) && isset($res_out[0]['total_out'])) ? floatval($res_out[0]['total_out']) : 0;
      $barang['stok'] = $total_in - $total_out;
      
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
      if (!isset($_SESSION['barang_masuk_cart'])) {
         $_SESSION['barang_masuk_cart'] = [];
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
      if (isset($_SESSION['barang_masuk_cart'][$cart_key])) {
         $_SESSION['barang_masuk_cart'][$cart_key]['qty'] += $qty;
      } else {
         $_SESSION['barang_masuk_cart'][$cart_key] = [
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
      echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['barang_masuk_cart'])]);
   }

   // Tambah barang utama ke cart
   public function add_main_to_cart()
   {
      ob_start(); // Capture any unexpected output
      
      $id_barang = $_POST['id_barang'] ?? 0;
      $qty = intval($_POST['qty'] ?? 1);
      
      if (!isset($_SESSION['barang_masuk_cart'])) {
         $_SESSION['barang_masuk_cart'] = [];
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
      
      if (isset($_SESSION['barang_masuk_cart'][$cart_key])) {
         $_SESSION['barang_masuk_cart'][$cart_key]['qty'] += $qty;
      } else {
         $_SESSION['barang_masuk_cart'][$cart_key] = [
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
      echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['barang_masuk_cart'])]);
   }

   // Load cart view
   public function cart()
   {
      $cart = $_SESSION['barang_masuk_cart'] ?? [];
      $this->view('barang_masuk/cart', ['cart' => $cart]);
   }

   // Remove from cart
   public function remove_from_cart()
   {
      $key = $_POST['key'] ?? '';
      if (isset($_SESSION['barang_masuk_cart'][$key])) {
         unset($_SESSION['barang_masuk_cart'][$key]);
      }
      
      session_write_close();
      header('Content-Type: application/json');
      echo json_encode(['status' => 'success', 'cart_count' => count($_SESSION['barang_masuk_cart'] ?? [])]);
   }

   // Clear cart
   public function clear_cart()
   {
      $_SESSION['barang_masuk_cart'] = [];
      
      session_write_close();
      header('Content-Type: application/json');
      echo json_encode(['status' => 'success']);
   }

   // Checkout - insert ke barang_mutasi
   public function checkout()
   {
      ob_start();
      
      $cart = $_SESSION['barang_masuk_cart'] ?? [];
      
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
            'type' => 2, // Transfer/Barang Masuk
            'ref' => $ref,
            'id_barang' => $item['id_barang'],
            'source_id' => 0, // Source = 0 (External)
            'target_id' => $id_cabang, // Target = My Branch
            'denom' => $item['denom'],
            'price' => $item['harga'],
            'qty' => $item['qty'],
            'margin' => $item['margin'] ?? 0,
            'state' => 0, // Pending (waiting 'Terima')
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
            $this->model('Log')->write("[BarangMasuk::checkout] " . $errorMsg . " | Query: " . ($insert['query'] ?? 'N/A'));
         }
         
         // Update sort popularity
         $this->db(0)->query("UPDATE barang_data SET sort = sort + 1 WHERE id_barang = '{$item['id_barang']}'");
      }
      
      if ($success_count > 0) {
         // Clear cart after successful checkout
         $_SESSION['barang_masuk_cart'] = [];
         
         ob_end_clean();
         session_write_close();
         header('Content-Type: application/json');
         echo json_encode([
            'status' => 'success', 
            'message' => "Barang Masuk diproses! Silahkan terima barang.",
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
          
          // Hapus semua item di barang_mutasi dengan ref ini
          $delete = $this->db(0)->delete('barang_mutasi', "ref = '$ref'");
          
          if (isset($delete['errno']) && $delete['errno'] == 0) {
             $response = ['status' => 'success', 'message' => 'Nota berhasil dihapus'];
          } else {
             $errorMsg = $delete['error'] ?? 'Unknown DB Error';
             $this->model('Log')->write("[BarangMasuk::hapusNota] Delete error: " . $errorMsg);
             throw new Exception('Gagal menghapus nota: ' . $errorMsg);
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[BarangMasuk::hapusNota] Error: " . $e->getMessage());
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
             throw new Exception('Nota ini bukan transfer/barang masuk');
          }
          
          // Update state = 1 (diterima/selesai) dengan raw query
          $update = $this->db(0)->query("UPDATE barang_mutasi SET state = 1 WHERE ref = '$ref'");
          
          // Verifikasi update berhasil
          $verify = $this->db(0)->get_where_row('barang_mutasi', "ref = '$ref'");
          
          // Log untuk debugging
          $this->model('Log')->write("[BarangMasuk::terimaBarang] Ref: $ref, State setelah update: " . ($verify['state'] ?? 'null'));
          
          if ($verify && $verify['state'] == 1) {
             $response = ['status' => 'success', 'message' => 'Barang berhasil diterima'];
          } else {
             $this->model('Log')->write("[BarangMasuk::terimaBarang] Update state gagal");
             throw new Exception('Gagal menerima barang');
          }
      } catch (\Throwable $e) {
          $this->model('Log')->write("[BarangMasuk::terimaBarang] Error: " . $e->getMessage());
          $response = ['status' => 'error', 'message' => $e->getMessage()];
      }
      
      ob_end_clean();
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode($response);
   }
}
