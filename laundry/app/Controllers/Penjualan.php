<?php

class Penjualan extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
      // Hanya harga aktif — view() memanggil operating_data() lagi, jadi filter diulang di view()
      $this->harga = $this->loadHargaAktif();
   }

   /**
    * view() di Controller memanggil operating_data() yang mengisi ulang $this->harga
    * dari session (semua harga). Filter aktif harus diterapkan lagi di sini.
    */
   public function view($file, $data = [])
   {
      $this->operating_data();
      $this->harga = $this->filterHargaAktif($this->harga);
      require_once "app/Views/" . $file . ".php";
   }

   public function index()
   {
      $viewData = 'penjualan/penjualan_main';
      $data_operasi = ['title' => 'Buka Order'];

      // Cek apakah request AJAX
      if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         $this->view($viewData);
      } else {
         $this->view('layout', ['data_operasi' => $data_operasi]);
         $this->view($viewData);
      }
   }

   public function cart()
   {
      $viewData = 'penjualan/cart';
      $where = $this->wCabang . " AND id_pelanggan = 0";
      $data_main = $this->db(0)->get_where('sale', $where);
      $this->view($viewData, ['data_main' => $data_main]);
   }

   public function insert($page)
   {
      if ($this->blockDriverCartAdd(false)) {
         return;
      }
      $id_harga = (int) ($_POST['f1'] ?? 0);
      $qty = round((float) str_replace(',', '.', (string) ($_POST['f2'] ?? '0')), 2);
      $note = $_POST['f3'] ?? '';

      $durasi = null;
      $hari = null;
      $jam = null;
      $item_group = null;
      $harga = null;
      $layanan = null;
      $minOrder = 0;
      $found = false;

      foreach ($this->harga as $a) {
         if ((int) $a['id_harga'] !== $id_harga) {
            continue;
         }
         // $this->harga sudah is_active = 1
         $durasi = $a['id_durasi'];
         $hari = $a['hari'];
         $jam = $a['jam'];
         $item_group = $a['id_item_group'];
         $harga = $this->resolveHargaUnit($a);
         $layanan = $a['list_layanan'];
         $minOrder = round((float) str_replace(',', '.', (string) ($a['min_order'] ?? 0)), 2);
         $found = true;
         break;
      }

      if (!$found) {
         echo "Harga tidak tersedia atau nonaktif.";
         return;
      }

      $diskon_qty = 0;
      foreach ($this->diskon as $a) {
         if ($a['id_penjualan_jenis'] == $page && $a['qty_disc'] > 0) {
            if ($qty >= $a['qty_disc']) {
               $diskon_qty = $a['disc_qty'];
            }
         }
      }

      $yr = date('Y');
      $count_data = $this->db(0)->count('sale') + 1;
      $id_sale = ($yr - 2024) . $count_data;
      $data = [
         'id_penjualan' => $id_sale,
         'id_cabang' => $this->id_cabang,
         'id_item_group' => $item_group,
         'id_penjualan_jenis' => $page,
         'id_durasi' => $durasi,
         'hari' => $hari,
         'jam' => $jam,
         'harga' => $harga,
         'qty' => $qty,
         'note' => $note,
         'list_layanan' => $layanan,
         'diskon_qty' => $diskon_qty,
         'min_order' => $minOrder,
         'id_harga' => $id_harga,
         'insertTime' => $GLOBALS['now']
      ];

      $do = $this->db(0)->insert('sale', $data);
      while ($do['errno'] == 1062) {
         $id_sale++;
         $data['id_penjualan'] = $id_sale;
         $do = $this->db(0)->insert('sale', $data);
      }

      $set = "sort = sort+1";
      $whereSort = "id_harga = " . $id_harga;
      $this->db(0)->update("harga", $set, $whereSort);

      if ($do['errno'] <> 0) {
         print_r($do);
      } else {
         $_SESSION[URL::SESSID]['user']['book'] = date('Y');
         echo 0;
      }
   }

   public function proses()
   {
      $pelanggan = (int) ($_POST['f1'] ?? 0);
      $id_penerima = (int) ($_POST['f2'] ?? 0);

      // Gabung ke no_ref terbuka pelanggan+penerima yang sama hari ini.
      // Dicari langsung per pelanggan (bukan order global terakhir),
      // supaya order pelanggan/user lain yang menyelip tidak membuat nota baru.
      $where = $this->wCabang
         . " AND id_pelanggan = " . $pelanggan
         . " AND id_user = " . $id_penerima
         . " AND no_ref <> ''"
         . " AND bin = 0"
         . " AND tuntas = 0"
         . " AND insertTime LIKE '" . date('Y-m-d') . "%'"
         . " ORDER BY insertTime DESC, id_penjualan DESC LIMIT 1";
      $cek_ref = $this->db(0)->get_where_row('sale', $where);

      $no_ref = (date('Y') - 2024) . date("mdHis") . rand(0, 9) . rand(0, 9);
      if (!empty($cek_ref['no_ref'])) {
         $no_ref = $cek_ref['no_ref'];
      }

      $where = $this->wCabang . " AND id_pelanggan = 0";
      $data = $this->db(0)->get_where('sale', $where);


      $disc_p = 0;

      $nama_pelanggan = "";
      $dp = $this->pelanggan[$pelanggan];

      $disc_p = $dp['disc'];
      $nama_pelanggan = $dp['nama_pelanggan'];
      $cabang_pelanggan = $dp['id_cabang'];

      $saldo = 0;
      $usedMemberQty = []; // Track pemakaian saldo member per id_harga dalam loop ini (agar tidak minus)
      foreach ($data as $a) {
         $saldo = 0;
         $id = $a['id_penjualan'];
         $id_jenis = $a['id_penjualan_jenis'];
         $idHarga = $a['id_harga'];
         $qty = $a['qty'];
         $id_cabang = $a['id_cabang'];

         if ($cabang_pelanggan <> $id_cabang) {
            continue;
         }

         $harga = $a['harga'];
         $total = $harga * $qty;
         $diskon_qty = $a['diskon_qty'];
         $diskon_partner_manual = (float) ($a['diskon_partner'] ?? 0);
         $member = $a['member'];

         //CEK JIKA DISKON KHUSUS
         $where_dk = "id_pelanggan = " . $pelanggan . " AND id_harga = " . $idHarga;
         $diskon_k = $this->db(0)->get_where_row("diskon_khusus", $where_dk);
         if (isset($diskon_k['diskon'])) {
            if ($diskon_k['diskon'] > 0) {
               $disc_p = $diskon_k['diskon'];
            }
         }

         // Prioritas diskon_partner:
         // 1) diskon manual dari cart (per item)
         // 2) diskon khusus pelanggan
         // 3) diskon default pelanggan
         $diskon_partner = $disc_p;
         if ($diskon_partner_manual > 0) {
            $diskon_partner = $diskon_partner_manual;
         }

         if ($member == 0) {
            if ($diskon_qty > 0 && $diskon_partner == 0) {
               $total = $total - ($total * ($diskon_qty / 100));
            } else if ($diskon_qty == 0 && $diskon_partner > 0) {
               $total = $total - ($total * ($diskon_partner / 100));
            } else if ($diskon_qty > 0 && $diskon_partner > 0) {
               $total = $total - ($total * ($diskon_qty / 100));
               $total = $total - ($total * ($diskon_partner / 100));
            } else {
               $total = ($harga * $qty);
            }
         } else {
            $total = 0;
         }

         // Saldo member: total - sudah terpakai di DB - sudah terpakai dalam loop ini
         $where_member = "bin = 0 AND lunas = 1 AND id_pelanggan = $pelanggan AND id_harga = $idHarga";
         $saldoManual = $this->db(0)->get_cols_where('member', 'SUM(qty) as saldo', $where_member, 0)['saldo'] ?? 0;
         $where_sale = $this->wCabang . " AND id_pelanggan = $pelanggan AND member = 1 AND bin = 0 AND id_harga = $idHarga";
         $saldoPengurangan = $this->db(0)->get_cols_where('sale', 'SUM(qty) as saldo', $where_sale, 0)['saldo'] ?? 0;
         $usedInLoop = $usedMemberQty[$idHarga] ?? 0;
         $saldo = $saldoManual - $saldoPengurangan - $usedInLoop;

         // Hanya pakai member jika saldo cukup (jangan sampai minus)
         if ($saldo >= $qty) {
            $usedMemberQty[$idHarga] = $usedInLoop + $qty;
            $set = "id_pelanggan = " . $pelanggan . ", no_ref = " . $no_ref . ", pelanggan = '" . $nama_pelanggan . "', member = 1, diskon_partner = " . $diskon_partner . ", total = 0, id_user = " . $id_penerima;
            $whereSet = "id_penjualan = '" . $id . "'";
            $this->db(0)->update('sale', $set, $whereSet);
         }

         $reset_diskon = "";
         if ($diskon_qty > 0 && $diskon_partner > 0) {
            foreach ($this->diskon as $a) {
               if ($a['id_penjualan_jenis'] == $id_jenis) {
                  if ($a['combo'] == 0) {
                     $reset_diskon = "diskon_qty = 0, ";
                  }
               }
            }
         }
         $where_update = "id_penjualan = '" . $id . "'";
         $totalForUpdate = ($saldo >= $qty) ? 0 : $total;
         $set = $reset_diskon . "id_pelanggan = " . $pelanggan . ", pelanggan = '" . $nama_pelanggan . "', diskon_partner = " . $diskon_partner . ", total = " . $totalForUpdate . ", no_ref = " . $no_ref . ", id_user = " . $id_penerima;
         $this->db(0)->update('sale', $set, $where_update);
      }

      $set = "sort = sort+1";
      $whereSort = "id_pelanggan = " . $pelanggan;
      $this->db(0)->update("pelanggan", $set, $whereSort);
   }

   public function updateCell()
   {
      $id = $_POST['id'];
      $value = $_POST['value'];
      $mode = $_POST['mode'];

      if ($mode == 1) {
         $col = "hari";
      } else if ($mode == 2) {
         $col = "jam";
      }

      $set = $col . " = '" . $value . "'";
      $where = "id_durasi_client  = " . $id;
      $this->db(0)->update('sale', $set, $where);
   }

   public function removeRow()
   {
      $id = $_POST['id'];
      $where = $this->wCabang . " AND id_penjualan = '" . $id . "'";
      $del = $this->db(0)->delete('sale', $where);
      if ($del['errno'] <> 0) {
         echo $del['error'];
      } else {
         echo 0;
      }
   }

   public function addItemForm($id)
   {
      if ($this->blockDriverCartAdd(false)) {
         return;
      }
      $this->view('penjualan/formItemAdd', ['id' => $id]);
   }

   public function orderPenjualanForm($id_penjualan, $id_harga, $saldo = false)
   {
      if ($this->blockDriverCartAdd(false)) {
         return;
      }
      $data[1] = $id_penjualan;
      $data[2] = $id_harga;
      $data[3] = $saldo;
      $this->view('penjualan/formOrder', $data);
   }

   public function addItem($id)
   {
      if ($this->blockDriverCartAdd(false)) {
         return;
      }
      $f1 = $_POST['f1'];
      $f2 = $_POST['f2'];
      $newItem = array($f1 => $f2);
      $item_list =  $this->db(0)->get_where_row('sale', $this->wCabang . " AND id_penjualan  = " . $id)['list_item'];
      if (strlen($item_list) == 0) {
         $value = serialize($newItem);
      } else {
         $arrItemList = unserialize($item_list);
         $arrItemList[$f1] = $f2;
         $value = serialize($arrItemList);
      }
      $set = "list_item = '" . $value . "'";
      $where = $this->wCabang . " AND id_penjualan = '" . $id . "'";
      $this->db(0)->update('sale', $set, $where);
   }

   public function removeItem()
   {
      $id = $_POST['id'];
      $key = $_POST['key'];
      $item_list =  $this->db(0)->get_where_row('sale', $this->wCabang . " AND id_penjualan  = " . $id)['list_item'];
      $arrItemList = unserialize($item_list);
      unset($arrItemList[$key]);
      $value = serialize($arrItemList);
      $set = "list_item = '" . $value . "'";
      $where = $this->wCabang . " AND id_penjualan = '" . $id . "'";
      $this->db(0)->update('sale', $set, $where);
   }

   public function setDiskonHarga()
   {
      $id = $_POST['id'] ?? '';
      $hargaDiskon = (float) ($_POST['harga_diskon'] ?? 0);

      if (strlen($id) == 0) {
         echo "ID tidak valid";
         return;
      }

      $row = $this->db(0)->get_where_row('sale', $this->wCabang . " AND id_penjualan = '" . $id . "'");
      if (!isset($row['harga'])) {
         echo "Data tidak ditemukan";
         return;
      }

      $hargaAsli = (float) $row['harga'];
      if ($hargaAsli <= 0) {
         echo "Harga asli tidak valid";
         return;
      }

      $diskonPartner = 0;
      if ($hargaDiskon > 0 && $hargaDiskon < $hargaAsli) {
         $diskonPartner = (($hargaAsli - $hargaDiskon) / $hargaAsli) * 100;
      }

      $set = "diskon_partner = " . round($diskonPartner, 2);
      $where = $this->wCabang . " AND id_penjualan = '" . $id . "'";
      $do = $this->db(0)->update('sale', $set, $where);

      if ($do['errno'] <> 0) {
         echo $do['error'];
      } else {
         echo 0;
      }
   }

   public function sering($idPelanggan)
   {
      $viewData = 'penjualan/viewSering';
      $idPelanggan = (int) $idPelanggan;
      $where = $this->wCabang . " AND id_harga <> 0 AND bin = 0 AND id_pelanggan = " . $idPelanggan . " GROUP BY id_harga, id_penjualan_jenis, id_item_group, list_layanan, id_durasi ORDER BY count(id_penjualan) DESC limit 10";
      $cols = "id_harga, id_penjualan_jenis, id_item_group, list_layanan, id_durasi, count(id_penjualan)";
      $raw = $this->db(0)->get_cols_where('sale', $cols, $where, 1);
      if (!is_array($raw)) {
         $raw = [];
      }

      $activeIds = [];
      foreach ($this->harga as $h) {
         $activeIds[(int) $h['id_harga']] = true;
      }

      $data = [];
      foreach ($raw as $row) {
         $idHarga = (int) ($row['id_harga'] ?? 0);
         if ($idHarga < 1 || empty($activeIds[$idHarga])) {
            continue;
         }
         $data[] = $row;
         if (count($data) >= 2) {
            break;
         }
      }

      $this->view($viewData, ['data' => $data]);
   }

   function loadPelanggan()
   {
      $z = array('page' => "pelanggan");
      $view = 'data_list/pelanggan';
      $where = $this->wCabang;
      $order = 'id_pelanggan DESC';
      $data_main = $this->db(0)->get_where_order("pelanggan", $where, $order);
      $this->view($view, ['data_main' => $data_main, 'z' => $z]);
   }

   public function tambahPelanggan()
   {
      header('Content-Type: application/json; charset=utf-8');

      $nama = trim($_POST['f1'] ?? '');
      $hpRaw = (string) ($_POST['f2'] ?? '');
      $hp = preg_replace('/\D/', '', $hpRaw);

      if ($nama === '' || $hp === '') {
         echo json_encode(['ok' => 0, 'msg' => 'Nama dan nomor HP wajib diisi']);
         return;
      }

      $namaEsc = addslashes($nama);
      $where = $this->wCabang . " AND nama_pelanggan = '" . $namaEsc . "'";
      if ($this->db(0)->count_where('pelanggan', $where) > 0) {
         echo json_encode(['ok' => 0, 'msg' => 'Gagal! nama ' . strtoupper($nama) . ' sudah digunakan']);
         return;
      }

      $do = $this->db(0)->insert('pelanggan', [
         'id_cabang' => $this->id_cabang,
         'nama_pelanggan' => $nama,
         'nomor_pelanggan' => $hp,
      ]);

      if (($do['errno'] ?? 1) != 0) {
         $this->model('Log')->write("[Penjualan::tambahPelanggan] Error: " . ($do['error'] ?? ''));
         echo json_encode(['ok' => 0, 'msg' => 'Gagal menyimpan pelanggan']);
         return;
      }

      $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);

      $row = $this->db(0)->get_where_order(
         'pelanggan',
         $this->wCabang . " AND nama_pelanggan = '" . $namaEsc . "'",
         'id_pelanggan DESC'
      );
      $new = is_array($row) && isset($row[0]) ? $row[0] : null;
      if (!$new) {
         echo json_encode(['ok' => 0, 'msg' => 'Tersimpan, tetapi ID tidak ditemukan. Refresh halaman.']);
         return;
      }

      echo json_encode([
         'ok' => 1,
         'id' => (int) $new['id_pelanggan'],
         'nama' => strtoupper($new['nama_pelanggan']),
         'hp' => $new['nomor_pelanggan'],
      ]);
   }
}
