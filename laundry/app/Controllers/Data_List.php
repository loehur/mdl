<?php

class Data_List extends Controller
{
   public function __construct()
   {
      $this->operating_data();
   }

   public function i($page)
   {
      $d2 = array();
      $z = array();
      $data_main = array();
      $z = array('page' => $page);

      switch ($page) {
         case "item":
            $this->session_cek(1);
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Item Laundry'];
            $table = $page;
            $order = 'item ASC';
            $data_main = $this->db(0)->get_order($table, $order);
            break;
         case "item_pengeluaran":
            $this->session_cek(1);
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Item Pengeluaran'];
            $table = $page;
            $order = 'id_item_pengeluaran ASC';
            $data_main = $this->db(0)->get_order($table, $order);
            break;
         case "surcas":
            $this->session_cek(1);
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Surcharge'];
            $table = "surcas_jenis";
            $order = 'id_surcas_jenis ASC';
            $data_main = $this->db(0)->get_order($table, $order);
            break;
         case "user":
            $this->session_cek(1);
            $view = 'data_list/' . $page;
            $z['mode'] = "aktif";
            $data_operasi = ['title' => 'Karyawan Aktif'];
            $table = $page;
            $d2 = $this->db(0)->get('cabang');
            $where = "en = 1 ORDER BY id_cabang ASC";
            $data_main = $this->db(0)->get_where($table, $where);
            break;
         case "userDisable":
            $this->session_cek(1);
            $view = 'data_list/user';
            $z['mode'] = "nonaktif";
            $data_operasi = ['title' => 'Karyawan Non Aktif'];
            $table = "user";
            $d2 = $this->db(0)->get('cabang');
            $where = "en = 0 ORDER BY id_cabang ASC";
            $data_main = $this->db(0)->get_where($table, $where);
            break;
         case "pelanggan":
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Data Pelanggan'];
            $table = $page;
            $where = $this->wCabang;
            $order = 'id_pelanggan DESC';
            $data_main = $this->db(0)->get_where_order($table, $where, $order);
            break;
         case "karyawan":
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Karyawan Mac Address'];
            $table = $page;
            $cols = 'id_user, nama_user, mac, mac_2';
            $where = $this->wCabang . " AND en = 1";
            $data_main = $this->db(0)->get_cols_where("user", $cols, $where, 1);
            break;
         case "barang":
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Master Barang'];
            $table = 'barang_data';
            $order = 'id_barang DESC';
            $data_main = $this->db(0)->get_order($table, $order);
            $z['data_satuan'] = $this->db(0)->get('barang_unit');
            break;
         case "barang_sub":
            $view = 'data_list/' . $page;
            $data_operasi = ['title' => 'Sub Barang'];
            $table = 'barang_sub';
            $order = 'id DESC';
            $data_main = $this->db(0)->get_order($table, $order);
            $z['data_master'] = $this->db(0)->get('barang_data'); 
            break;
      }
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($view, ['data_main' => $data_main, 'd2' => $d2, 'z' => $z]);
   }

   public function insert($page)
   {
      $table  = $page;
      switch ($page) {
         case "item":
            $this->session_cek(1);
            $cols = 'item';
            $f1 = $_POST['f1'];
            $vals = "'" . $f1 . "'";
            $where = "item = '" . $f1 . "'";
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $data = [
                  'item' => $f1
               ];
               $this->db(0)->insert($table, $data);
               $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
            }
            break;
         case "item_pengeluaran":
            $this->session_cek(1);
            $cols = 'item_pengeluaran';
            $f1 = $_POST['f1'];
            $is_expense = isset($_POST['is_expense']) ? intval($_POST['is_expense']) : 1;
            $vals = "'" . $f1 . "'";
            $where = "item_pengeluaran = '" . $f1 . "'";
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $data = [
                  'item_pengeluaran' => $f1,
                  'is_expense' => $is_expense
               ];
               $this->db(0)->insert($table, $data);
               $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
            }
            break;
         case "surcas":
            $this->session_cek(1);
            $table = "surcas_jenis";
            $cols = 'surcas_jenis';
            $f1 = $_POST['f1'];
            $vals = "'" . $f1 . "'";
            $where = "surcas_jenis = '" . $f1 . "'";
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $data = [
                  'surcas_jenis' => $f1
               ];
               $this->db(0)->insert($table, $data);
               $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
            }
            break;
         case "pelanggan":
            $cols = 'id_cabang, nama_pelanggan, nomor_pelanggan';
            $nama_pelanggan = trim($_POST['f1'] ?? '');
            $vals = $this->id_cabang . ",'" . $nama_pelanggan . "','" . $_POST['f2'] . "'";
            $setOne = "nama_pelanggan = '" . $nama_pelanggan . "'";
            $where = $this->wCabang . " AND " . $setOne;
            $data_main = $this->db(0)->count_where($table, $where);
            if ($data_main < 1) {
               $data = [
                  'id_cabang' => $this->id_cabang,
                  'nama_pelanggan' => $nama_pelanggan,
                  'nomor_pelanggan' => preg_replace('/\D/', '', $_POST['f2'])
               ];
               $do = $this->db(0)->insert($table, $data);

               if ($do['errno'] <> 0) {
                  $this->model('Log')->write("[Data_List::insert/pelanggan] Error: " . $do['error'] . " | Query: " . $do['query']);
               }

               $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
               echo 1;
            } else {
               $text =  "Gagal! nama " . strtoupper($nama_pelanggan) . " sudah digunakan";
               echo $text;
            }
            break;
         case "user":
            $this->session_cek(1);
            $privilege = $_POST['f4'] ?? 0;
            if ($privilege == 100) {
               exit();
            }
            $no_user = $_POST['f2'] ?? '';
            $username = $this->model("Enc")->username($no_user);
            $data = [
               'username' => $username,
               'id_cabang' => $_POST['f3'] ?? 0,
               'no_user' => $no_user,
               'nama_user' => trim($_POST['f1'] ?? ''),
               'nama_pemilik' => '',
               'id_privilege' => $privilege,
            ];
            
            // Debug log
            $this->model('Log')->write("[Data_List::insert/user] POST: " . json_encode($_POST) . " | Data: " . json_encode($data));
            
            $do = $this->db(0)->insert($table, $data);
            if ($do['errno'] == 0) {
               echo 0;
            } else {
               $this->model('Log')->write("[Data_List::insert/user] Error: " . $do['error'] . " | Query: " . $do['query']);
               echo $do['error'];
            }
            break;
         case "barang":
            $this->session_cek(1);
            $table = 'barang_data';
            $data = [
               'code' => $_POST['f1'],
               'brand' => $_POST['f2'],
               'model' => $_POST['f3'],
               'description' => $_POST['f4'],
               'price' => $_POST['f5'],
               'margin' => 0,
               'unit' => $_POST['f_unit'],
               'sort' => 0,
               'state' => 1
            ];
            $this->db(0)->insert($table, $data);
            break;
         case "barang_sub":
            $this->session_cek(1);
            $table = 'barang_sub';
            $id_barang = intval($_POST['f_master'] ?? 0);
            $nama = $_POST['f_nama'] ?? '';
            $qty = floatval($_POST['f_qty'] ?? 0);
            $price = preg_replace('/[^0-9.-]/', '', $_POST['f_price'] ?? 0);
            if ($id_barang < 1 || $nama === '' || $qty <= 0) {
               header('Content-Type: application/json');
               http_response_code(400);
               echo json_encode(['ok' => 0, 'error' => 'Data tidak lengkap']);
               exit();
            }
            $id_val = $id_barang * 10 + $qty;
            $barang = $this->db(0)->get_where_row('barang_data', "id_barang = $id_barang");
            if ($barang && isset($barang['brand']) && isset($barang['model'])) {
               $brand = $this->db(0)->escape($barang['brand']);
               $model = $this->db(0)->escape($barang['model']);
               $all_barang = $this->db(0)->get_where('barang_data', "brand = '$brand' AND model = '$model'");
               $qty_esc = $this->db(0)->escape($qty);
               foreach ($all_barang as $b) {
                  $bid = intval($b['id_barang']);
                  $exists = $this->db(0)->count_where($table, "id_barang = $bid AND qty = '$qty_esc'");
                  if ($exists < 1) {
                     $sub_id = $bid * 10 + $qty;
                     $do = $this->db(0)->insert($table, [
                        'id' => $sub_id,
                        'id_barang' => $bid,
                        'nama' => $nama,
                        'qty' => $qty,
                        'price' => $price
                     ]);
                     if (isset($do['errno']) && $do['errno'] != 0 && $do['errno'] != 1062) {
                        $this->model('Log')->write("[Data_List::insert/barang_sub] Error: " . ($do['error'] ?? '') . " | Query: " . ($do['query'] ?? ''));
                        header('Content-Type: application/json');
                        http_response_code(500);
                        echo json_encode(['ok' => 0, 'error' => $do['error'] ?? 'Insert gagal']);
                        exit();
                     }
                  }
               }
            } else {
               $do = $this->db(0)->insert($table, [
                  'id' => $id_val,
                  'id_barang' => $id_barang,
                  'nama' => $nama,
                  'qty' => $qty,
                  'price' => $price
               ]);
               if (isset($do['errno']) && $do['errno'] != 0 && $do['errno'] != 1062) {
                  $this->model('Log')->write("[Data_List::insert/barang_sub] Error: " . ($do['error'] ?? '') . " | Query: " . ($do['query'] ?? ''));
                  header('Content-Type: application/json');
                  http_response_code(500);
                  echo json_encode(['ok' => 0, 'error' => $do['error'] ?? 'Insert gagal']);
                  exit();
               }
            }
            header('Content-Type: application/json');
            echo json_encode(['ok' => 1]);
            exit();
            break;
      }
   }

   public function updateCell($page)
   {
      $table  = $page;
      $id = $_POST['id'] ?? '';
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      switch ($page) {
         case "item":
            $this->session_cek(1);
            if ($mode == 1) {
               $col = "item";
            }
            $where = "id_item = " . $id;
            break;
         case "item_pengeluaran":
            $this->session_cek(1);
            if ($mode == 1) {
               $col = "item_pengeluaran";
            } else if ($mode == 2) {
               $col = "is_expense";
            }
            $where = "id_item_pengeluaran = " . $id;
            break;
         case "surcas_jenis":
            $this->session_cek(1);
            if ($mode == 1) {
               $col = "surcas_jenis";
            }
            $where = "id_surcas_jenis = " . $id;
            break;
         case "pelanggan":
            switch ($mode) {
               case "1":
                  $col = "nama_pelanggan";
                  break;
               case "2":
                  $col = "nomor_pelanggan";
                  break;
               case "4":
                  $col = "alamat";
                  break;
               case "5":
                  $this->session_cek(1);
                  $col = "disc";
                  if ($value > 100) {
                     $value = 100;
                  }
                  break;
            }
            $where = $this->wCabang . " AND id_pelanggan = " . $id;
            break;
         case "user":
            $this->session_cek(1);
            $table  = $page;
            $id = $_POST['id'];
            $value = $_POST['value'];
            $mode = $_POST['mode'];

            switch ($mode) {
               case "1":
                  $col = "nama_pemilik";
                  break;
               case "2":
                  $col = "nama_user";
                  break;
               case "4":
                  $col = "id_cabang";
                  break;
               case "5":
                  $col = "id_privilege";
                  break;
               case "6":
                  $col = "no_user";
                  break;
            }
            $where = "id_user = $id";
            break;
         case "karyawan":
            $table  = "user";
            $id = $_POST['id'];
            $value = $_POST['value'];
            $mode = $_POST['mode'];

            switch ($mode) {
               case "2":
                  $col = "mac";
                  break;
               case "3":
                  $col = "mac_2";
                  break;
            }
            $where = "id_user = $id";
            break;
         case "barang":
            $this->session_cek(1);
            $table = "barang_data";
            $mode = intval($_POST['mode'] ?? 0);
            $value = $_POST['value'] ?? '';
            if ($mode == 5 || $mode == 6) {
               $value = preg_replace('/[^0-9.-]/', '', $value);
            }
            switch ($mode) {
               case 1: $col = 'code'; break;
               case 2: $col = 'brand'; break;
               case 3: $col = 'model'; break;
               case 4: $col = 'description'; break;
               case 5: $col = 'price'; break;
               case 6: $col = 'margin'; break;
               case 7: $col = 'unit'; break;
               case 8: $col = 'sort'; break;
               case 9: $col = 'state'; break;
               default:
                  echo 'Mode tidak valid';
                  exit();
            }
            $id_int = intval($id);
            $ids_barang_sama = [];
            if ($mode == 5 || $mode == 6) {
               $row = $this->db(0)->get_where_row($table, "id_barang = $id_int");
               if ($row && isset($row['brand']) && isset($row['model'])) {
                  $brand = $this->db(0)->escape($row['brand']);
                  $model = $this->db(0)->escape($row['model']);
                  $where = "brand = '$brand' AND model = '$model'";
                  $all_barang = $this->db(0)->get_where('barang_data', $where);
                  foreach ($all_barang as $b) {
                     $ids_barang_sama[] = intval($b['id_barang']);
                  }
               } else {
                  $where = "id_barang = $id_int";
                  $ids_barang_sama = [$id_int];
               }
            } else {
               $where = "id_barang = $id_int";
            }
            $set = [$col => $value];
            $up = $this->db(0)->update($table, $set, $where);
            if ($up['errno'] == 0 && $mode == 5 && !empty($ids_barang_sama)) {
               $where_sub = "id_barang IN (" . implode(',', $ids_barang_sama) . ")";
               $this->db(0)->update('barang_sub', ['price' => $value], $where_sub);
            }
            echo $up['errno'] == 0 ? '0' : $up['error'];
            exit();
            break;
         case "barang_sub":
            $this->session_cek(1);
            header('Content-Type: application/json; charset=utf-8');
            $table = "barang_sub";
            $id_raw = trim($_POST['id'] ?? '');
            $id = $id_raw;
            $mode = intval($_POST['mode'] ?? 0);
            $value = $_POST['value'] ?? '';
            switch ($mode) {
               case 1: $col = 'id_barang'; break;
               case 2: $col = 'nama'; break;
               case 3: $col = 'qty'; break;
               case 4: $col = 'price'; break;
               default:
                  echo json_encode(['ok' => 0, 'error' => 'Mode tidak valid']);
                  exit();
            }
            if ($mode == 4) {
               $id_esc = $this->db(0)->escape($id);
               $row = $this->db(0)->get_where_row($table, "id = '$id_esc'");
               if ($row && isset($row['id_barang']) && isset($row['qty'])) {
                  $id_barang = intval($row['id_barang']);
                  $qty_num = floatval($row['qty']);
                  $barang = $this->db(0)->get_where_row('barang_data', "id_barang = $id_barang");
                  if ($barang && isset($barang['brand']) && isset($barang['model'])) {
                     $brand = $this->db(0)->escape($barang['brand']);
                     $model = $this->db(0)->escape($barang['model']);
                     $all_barang = $this->db(0)->get_where('barang_data', "brand = '$brand' AND model = '$model'");
                     $ids = [];
                     foreach ($all_barang as $b) {
                        $ids[] = intval($b['id_barang']);
                     }
                     $qty_cond = "ABS(qty - $qty_num) < 0.0001";
                     $where = !empty($ids)
                        ? "id_barang IN (" . implode(',', $ids) . ") AND $qty_cond"
                        : "id_barang = $id_barang AND $qty_cond";
                  } else {
                     $where = "id_barang = $id_barang AND ABS(qty - $qty_num) < 0.0001";
                  }
               } else {
                  $where = "id = '$id_esc'";
               }
               $value = preg_replace('/[^0-9.-]/', '', $value);
            } else {
               $id_esc = $this->db(0)->escape($id);
               $where = "id = '$id_esc'";
            }
            $set = [$col => $value];
            $up = $this->db(0)->update($table, $set, $where);
            $resp = [
               'ok' => ($up['errno'] == 0 ? 1 : 0),
               'error' => $up['errno'] != 0 ? ($up['error'] ?? '') : ''
            ];
            echo json_encode($resp);
            exit();
            break;
      }


      if ($page == "user" && $col == "id_privilege") {
         if ($value == 100) {
            exit();
         }
      }

      if (isset($col) && ($col === 'nama_pelanggan' || $col === 'nama_user' || $col === 'nama_pemilik')) {
         $value = is_string($value) ? trim($value) : $value;
      }

      $set = [
         $col => $value
      ];
      $up = $this->db(0)->update($table, $set, $where);
      echo $up['errno'] == 0 ? 0 : $up['error'];

      if ($page == "user" && $col == "no_user") {
         $username = $this->model("Enc")->username($value);
         $set = "username = '" . $username . "', otp_active = ''";
         $this->db(0)->update($table, $set, $where);
      }
   }

   public function enable($bol)
   {
      $this->session_cek(1);
      $table  = 'user';
      $id = $_POST['id'];
      $where = "id_user = " . $id;
      $set = [
         'en' => $bol
      ];
      $this->db(0)->update($table, $set, $where);
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }

   public function synchrone()
   {
      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
   }

   public function delete($page)
   {
      $this->session_cek(1);
      $id = $_POST['id'];
      if ($page == 'barang') {
         $where = "id_barang = $id";
         $this->db(0)->delete('barang_data', $where);
      } else if ($page == 'barang_sub') {
         $id_esc = $this->db(0)->escape(trim($id));
         $where = "id = '$id_esc'";
         $this->db(0)->delete('barang_sub', $where);
      }
   }
}
