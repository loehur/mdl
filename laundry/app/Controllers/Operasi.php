<?php

class Operasi extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function i($modeOperasi, $id_pelanggan)
   {
      $viewData = 'operasi/form_proses';
      
      // Year data for tuntas mode
      $currentYear = intval(date('Y'));
      $minYear = 2021;
      $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;
      if ($selectedYear < $minYear) $selectedYear = $minYear;
      if ($selectedYear > $currentYear) $selectedYear = $currentYear;
      
      $formData = array(
         'id_pelanggan' => $id_pelanggan, 
         'mode' => $modeOperasi,
         'currentYear' => $currentYear,
         'selectedYear' => $selectedYear,
         'minYear' => $minYear
      );
      
      switch ($modeOperasi) {
         case 0:
            //DALAM PROSES
            $data_operasi = ['title' => 'Operasi Order Proses'];
            break;
         case 1:
            //TUNTAS
            $data_operasi = ['title' => 'Operasi Order Tuntas'];
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData, $formData);
   }

   public function loadData($id_pelanggan, $mode = 0)
   {
      $pelanggan = $this->pelanggan[$id_pelanggan] ?? 0;
      if ($pelanggan == 0) {
         echo "<div class='text-center mt-5'>
            <i class='fa-solid fa-circle-check text-success mb-3' style='font-size: 4rem;'></i>
            <h5 class='text-secondary'>Semua data pelanggan sudah tuntas!</h5>
         </div>";
         exit;
      }

      // Year handling
      $currentYear = intval(date('Y'));
      $minYear = 2021;
      $year = isset($_GET['year']) ? max($minYear, min($currentYear, intval($_GET['year']))) : $currentYear;

      $modeView = ($mode == 1) ? 2 : 1;
      $whereSale = $this->wCabang . " AND id_pelanggan = $id_pelanggan AND bin = 0 AND tuntas = " . ($mode == 1 ? "1 AND YEAR(insertTime) = $year" : "0") . " ORDER BY id_penjualan DESC";

      $data_main2 = $this->db(0)->get_where('sale', $whereSale, 'no_ref', 1);

      // Extract IDs and refs in single loop
      $sale_ids = [];
      $sale_refs = [];
      foreach ($data_main2 as $key_ref => $dm_group) {
         $sale_refs[] = $key_ref;
         foreach ($dm_group as $dm) {
            $sale_ids[] = $dm['id_penjualan'];
         }
      }

      // Batch queries for sale-related data
      $operasi = $notifSelesai = $kas = $notifBon = $surcas = [];
      if (!empty($sale_ids)) {
         $ids_in = "'" . implode("','", $sale_ids) . "'";
         $operasi = $this->db(0)->get_where('operasi', $this->wCabang . " AND id_penjualan IN ($ids_in)");
         $notifSelesai = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 2 AND no_ref IN ($ids_in)");
      }
      if (!empty($sale_refs)) {
         $refs_in = "'" . implode("','", $sale_refs) . "'";
         $kas = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi IN ($refs_in)");
         $notifBon = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 1 AND no_ref IN ($refs_in)");
         $surcas = $this->db(0)->get_where('surcas', $this->wCabang . " AND no_ref IN ($refs_in)");
      }

      // MEMBER - OPTIMIZED: batch query instead of N+1
      $data_member = $this->db(0)->get_where('member', $this->wCabang . " AND bin = 0 AND id_pelanggan = $id_pelanggan AND lunas = 0");
      
      $member_ids = array_column($data_member, 'id_member');
      $notif_member = $kas_member = [];
      
      if (!empty($member_ids)) {
         $member_in = "'" . implode("','", $member_ids) . "'";
         
         // Batch query kas_member
         $all_kas_member = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 3 AND ref_transaksi IN ($member_in)");
         foreach ($all_kas_member as $km) {
            $idm = $km['ref_transaksi'];
            $kas_member[$idm][] = $km;
         }
         
         // Batch query notif_member
         $notif_member = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 3 AND no_ref IN ($member_in)");
         
         // Check lunas status
         foreach ($data_member as $dme) {
            $idm = $dme['id_member'];
            $harga = $dme['harga'];
            $totalBayar = 0;
            
            if (isset($kas_member[$idm])) {
               foreach ($kas_member[$idm] as $k) {
                  if ($k['status_mutasi'] == 3) $totalBayar += $k['jumlah'];
               }
               if ($totalBayar >= $harga) {
                  $lunas = $this->db(0)->update('member', ['lunas' => 1], 'id_member = ' . $idm);
                  if ($lunas['errno'] <> 0) {
                     $this->model('Log')->write("[loadData] ERROR UPDATE PAID, MEMBER ID $idm Error: " . $lunas['error']);
                  }
               }
            }
         }
      }

      // Finance history - optimized merge
      $finance_history = [];
      $all_kas = array_merge($kas, array_merge(...array_values($kas_member ?: [[]])));
      foreach ($all_kas as $k) {
         if (empty($k['ref_finance'])) continue;
         $rf = $k['ref_finance'];
         if (!isset($finance_history[$rf])) {
            $finance_history[$rf] = ['ref_finance' => $rf, 'total' => 0, 'status' => $k['status_mutasi'], 'metode' => $k['metode_mutasi'], 'note' => $k['note'], 'insertTime' => $k['insertTime']];
         }
         $finance_history[$rf]['total'] += intval($k['jumlah']);
         if ($k['insertTime'] > $finance_history[$rf]['insertTime']) {
            $finance_history[$rf] = array_merge($finance_history[$rf], ['insertTime' => $k['insertTime'], 'status' => $k['status_mutasi'], 'metode' => $k['metode_mutasi'], 'note' => $k['note']]);
         }
      }
      $finance_history = array_filter($finance_history, fn($item) => $item['status'] == 2);

      // Saldo deposit - 3 queries combined concept (kept as is for accuracy)
      $topup = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3") ?? 0;
      $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3") ?? 0;
      $usage = $this->db(0)->sum_col_where('kas', 'jumlah', "id_client = '$id_pelanggan' AND metode_mutasi = 3 AND jenis_mutasi = 2") ?? 0;

      $this->view('operasi/view_load', [
         'modeView' => $modeView, 'pelanggan' => $pelanggan, 'data_main' => $data_main2,
         'operasi' => $operasi, 'kas' => $kas, 'notif_bon' => $notifBon, 'notif_selesai' => $notifSelesai,
         'notif_member' => $notif_member, 'formData' => [], 'idOperan' => '', 'surcas' => $surcas,
         'data_member' => $data_member, 'kas_member' => $kas_member, 'saldoTunai' => $topup - $topup_out - $usage,
         'users' => $this->db(0)->get('user', 'id_user'), 'finance_history' => $finance_history,
         'selectedYear' => $year, 'currentYear' => $currentYear, 'minYear' => $minYear
      ]);
   }

   public function payment_gateway_order($ref_finance)
   {
      $this->payment_gateway_logic($ref_finance, false);
   }

   public function payment_gateway_check_status($ref_finance)
   {
      $this->payment_gateway_status_logic($ref_finance, false);
   }

   public function payment_gateway_status_poll($ref_finance)
   {
      $this->payment_gateway_status_db($ref_finance, false);
   }

   /**
    * Soft-delete one item from a multi-item nota.
    * The checks are intentionally repeated server-side; UI eligibility is only a convenience.
    */
   public function hapusItem()
   {
      header('Content-Type: application/json; charset=utf-8');

      $idPenjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      $note = trim((string) ($_POST['note'] ?? ''));
      if ($idPenjualan === '' || $note === '') {
         echo json_encode(['status' => 'error', 'message' => 'Item dan alasan hapus wajib diisi.']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($idPenjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $ref = trim((string) ($sale['no_ref'] ?? ''));
      if ($ref === '') {
         echo json_encode(['status' => 'error', 'message' => 'Nota item tidak ditemukan.']);
         return;
      }
      $refEsc = $this->db(0)->escape($ref);
      $saleWhere = $this->wCabang . " AND no_ref = '$refEsc' AND bin = 0";
      $items = $this->db(0)->get_where('sale', $saleWhere);
      if (!is_array($items) || count($items) <= 1) {
         echo json_encode(['status' => 'error', 'message' => 'Item tunggal tidak dapat dihapus. Gunakan tombol hapus nota untuk menghapus seluruh nota.']);
         return;
      }

      // Semua pembayaran selain yang gagal (status 4) mengunci perubahan nota.
      $payments = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = '$refEsc'");
      foreach ((array) $payments as $payment) {
         if ((int) ($payment['status_mutasi'] ?? 0) !== 4) {
            echo json_encode(['status' => 'error', 'message' => 'Item tidak dapat dihapus karena nota sudah memiliki pembayaran.']);
            return;
         }
      }

      $ids = [];
      foreach ($items as $item) {
         if ((int) ($item['tuntas'] ?? 0) !== 0) {
            echo json_encode(['status' => 'error', 'message' => 'Item tidak dapat dihapus karena nota sudah tuntas.']);
            return;
         }
         $ids[] = "'" . $this->db(0)->escape($this->normalizeSaleId($item['id_penjualan'])) . "'";
      }
      if (!empty($ids)) {
         $operationCount = $this->db(0)->count_where('operasi', $this->wCabang . ' AND id_penjualan IN (' . implode(',', $ids) . ')');
         if (!is_numeric($operationCount)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memeriksa status operasi. Silakan coba lagi.']);
            return;
         }
         if ((int) $operationCount > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Item tidak dapat dihapus karena sudah ada layanan yang diselesaikan pada nota ini.']);
            return;
         }
      }

      $update = $this->db(0)->update('sale', ['bin' => 1, 'bin_note' => $note], $this->whereSaleById($idPenjualan) . ' AND bin = 0');
      if (($update['errno'] ?? 1) != 0) {
         $this->model('Log')->write("[Operasi::hapusItem] Gagal hapus item id=$idPenjualan: " . ($update['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus item. Silakan coba lagi.']);
         return;
      }

      $this->resetBonNotif($ref);
      $this->model('Log')->write("[Operasi::hapusItem] Item id=$idPenjualan dari nota $ref dihapus. Alasan: $note");
      echo json_encode(['status' => 'success', 'message' => 'Item berhasil dihapus.']);
   }

   public function bayarMulti($karyawan, $idPelanggan, $metode = 2, $note = "")
   {
      $rekap = isset($_POST['rekap']) ? $_POST['rekap'][0] : [];
      $dibayar = isset($_POST['dibayar']) ? $_POST['dibayar'] : 0;
      $idPenanggung = isset($_POST['id_penanggung_bayar']) ? (int) $_POST['id_penanggung_bayar'] : 0;
      $idSaldoClient = 0;

      $note = str_replace('_SPACE_', ' ', (string) $note);

      if ((int) $metode === 3 && $idPenanggung > 0) {
         if ($idPenanggung === (int) $idPelanggan) {
            echo 'Penanggung bayar harus berbeda dari pelanggan order';
            return;
         }
         if (!isset($this->pelanggan[$idPenanggung])) {
            echo 'Penanggung bayar tidak ditemukan di cabang ini';
            return;
         }
         $saldoPenanggung = $this->helper('Saldo')->getSaldoTunai($idPenanggung);
         if ($saldoPenanggung <= 0) {
            echo 'Saldo penanggung bayar tidak mencukupi';
            return;
         }
         $idSaldoClient = $idPenanggung;
         $namaOrder = $this->pelanggan[$idPelanggan]['nama_pelanggan'] ?? $idPelanggan;
         $namaPenanggung = $this->pelanggan[$idPenanggung]['nama_pelanggan'] ?? $idPenanggung;
         $note = 'TB:' . strtoupper($namaOrder);
         $userName = $_SESSION[URL::SESSID]['user']['nama_user'] ?? $karyawan;
         $this->model('Log')->write(
            "[TanggungBayar] Order#$idPelanggan ($namaOrder) saldo dari #$idPenanggung ($namaPenanggung) oleh user $karyawan ($userName)"
         );
      }

      $res = $this->model('KasModel')->bayarMulti(
         $rekap,
         $dibayar,
         $idPelanggan,
         $this->id_cabang,
         $karyawan,
         $metode,
         $note,
         1,
         $idSaldoClient
      );

      echo $res;
   }

   /**
    * Daftar pelanggan cabang yang memiliki saldo tunai > 0 (untuk modal Tanggung Bayar).
    */
   public function listPenanggungBayar($excludePelanggan = 0)
   {
      header('Content-Type: application/json; charset=utf-8');

      $excludePelanggan = (int) $excludePelanggan;

      $where = $this->wCabang . " AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
      $where2 = $this->wCabang . " AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
      $cols = "id_client, SUM(jumlah) as saldo";

      $data = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      $data3 = $this->db(0)->get_cols_where('kas', $cols, $where2, 1);

      $saldo = [];
      $pakai = [];

      foreach ($data as $a) {
         $saldo[$a['id_client']] = $a['saldo'];
         $pakai[$a['id_client']] = 0;
      }

      if (count($saldo) > 0) {
         $wherePakai = $this->wCabang . " AND metode_mutasi = 3 AND jenis_mutasi = 2";
         $colsPakai = "id_client, SUM(jumlah) as pakai";
         $dataPakai = $this->db(0)->get_cols_where('kas', $colsPakai, $wherePakai . " GROUP BY id_client", 1);
         foreach ($dataPakai as $dp) {
            if (isset($saldo[$dp['id_client']])) {
               $pakai[$dp['id_client']] = $dp['pakai'];
            }
         }
      }

      foreach ($data3 as $a2) {
         $idClient = $a2['id_client'];
         if (isset($pakai[$idClient])) {
            $pakai[$idClient] += $a2['saldo'];
         } else {
            $pakai[$idClient] = $a2['saldo'];
         }
      }

      $result = [];
      foreach ($saldo as $idClient => $topupAmt) {
         $sisa = (int) round($topupAmt - ($pakai[$idClient] ?? 0));
         if ($sisa <= 0) {
            continue;
         }
         $idClient = (int) $idClient;
         if ($excludePelanggan > 0 && $idClient === $excludePelanggan) {
            continue;
         }
         if (!isset($this->pelanggan[$idClient])) {
            continue;
         }
         $p = $this->pelanggan[$idClient];
         $result[] = [
            'id_pelanggan' => $idClient,
            'nama_pelanggan' => $p['nama_pelanggan'],
            'nomor_pelanggan' => $p['nomor_pelanggan'] ?? '',
            'saldo' => $sisa,
         ];
      }

      usort($result, function ($a, $b) {
         return strcasecmp($a['nama_pelanggan'], $b['nama_pelanggan']);
      });

      echo json_encode(['ok' => 1, 'data' => $result]);
   }

   public function ganti_operasi()
   {
      if ((int) ($this->id_privilege ?? 0) !== 100) {
         echo 'Unauthorized: Hanya admin yang dapat mengubah penyelesai.';
         return;
      }

      $karyawan = $_POST['f1'];
      $id = $_POST['id'] ?? '';

      // id_operasi bisa alfanumerik (mis. 51681407012 atau A168070), harus di-quote agar tidak error "Truncated incorrect DOUBLE value"
      $idEsc = $this->db(0)->escape(trim((string) $id));
      $set = ['id_user_operasi' => $karyawan];
      $where = $this->wCabang . " AND id_operasi = '" . $idEsc . "'";
      $in = $this->db(0)->update('operasi', $set, $where); // Changed to db(0)
      if ($in['errno'] <> 0) {
         $this->model('Log')->write("[ganti_operasi] Update Operasi Error: " . $in['error']);
         echo $in['error'];
      } else {
         echo 0;
      }
   }

   public function cancel_payment($ref_finance)
   {
      // Check if transaction exists with cabang filter
      $where = $this->wCabang . " AND ref_finance = '" . $ref_finance . "'";
      $kas = $this->db(0)->get_where_row('kas', $where); // Changed to db(0)

      if (!isset($kas['id_kas'])) {
         echo json_encode(['status' => 'error', 'msg' => 'Transaksi tidak ditemukan']);
         exit();
      }

      // Reject if status_mutasi == 3 (already successful)
      if ($kas['status_mutasi'] == 3) {
         echo json_encode(['status' => 'error', 'msg' => 'Transaksi sudah berhasil, tidak dapat dibatalkan']);
         exit();
      }

      // Delete from kas table
      $deleteKas = $this->db(0)->delete('kas', $this->wCabang . " AND ref_finance = '$ref_finance'"); // Changed to db(0)
      if ($deleteKas['errno'] != 0) {
         $this->model('Log')->write("[cancel_payment] Delete Kas Error: " . $deleteKas['error']);
         echo json_encode(['status' => 'error', 'msg' => 'Gagal menghapus data kas: ' . $deleteKas['error']]);
         exit();
      }

      // Note: wh_tokopay not used anymore - payment info is now in kas table
      // Delete from wh_midtrans (ignore if table doesn't exist)
      try {
         $this->db(100)->delete('wh_midtrans', "ref_id = '$ref_finance'");
      } catch (Exception $e) {
      } // Changed to db(0)

      // Note: Moota integration removed - no wh_moota cleanup needed

      echo json_encode(['status' => 'success', 'msg' => 'Pembayaran berhasil dibatalkan']);
   }

   public function durasi_options()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? $_GET['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item member tidak dapat diubah durasinya']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      $currentItemTotal = $this->calcSaleItemTotal($sale);

      $kategori = '';
      foreach ($this->itemGroup as $ig) {
         if ($ig['id_item_group'] == $sale['id_item_group']) {
            $kategori = $ig['item_kategori'];
         }
      }

      $options = [];
      foreach ($this->harga as $h) {
         if (($h['is_active'] ?? 1) != 1) {
            continue;
         }
         if ($h['id_penjualan_jenis'] != $sale['id_penjualan_jenis']) {
            continue;
         }
         if ($h['id_item_group'] != $sale['id_item_group']) {
            continue;
         }
         if ($h['list_layanan'] != $sale['list_layanan']) {
            continue;
         }

         $durasiName = '';
         foreach ($this->dDurasi as $d) {
            if ($d['id_durasi'] == $h['id_durasi']) {
               $durasiName = strtoupper($d['durasi']);
            }
         }

         $unitPrice = $this->hargaUnitPrice($h);
         $minOrder = round((float) ($h['min_order'] ?? $sale['min_order'] ?? 0), 2);
         $newSubTotal = $this->getRefSubTotal($ref, [
            $id_penjualan => [
               'harga' => $unitPrice,
               'min_order' => $minOrder,
            ],
         ]);
         $newItemTotal = $this->calcSaleItemTotal(array_merge($sale, [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ]));

         $canSelect = ($dibayar <= 0) || ($newSubTotal >= $dibayar);

         $options[] = [
            'id_harga' => (int) $h['id_harga'],
            'id_durasi' => (int) $h['id_durasi'],
            'durasi' => $durasiName,
            'hari' => (int) ($h['hari'] ?? 0),
            'jam' => (int) ($h['jam'] ?? 0),
            'harga' => $unitPrice,
            'item_total' => $newItemTotal,
            'ref_total' => $newSubTotal,
            'can_select' => $canSelect,
            'selected' => ((int) $h['id_durasi'] === (int) $sale['id_durasi']),
         ];
      }

      if (empty($options)) {
         echo json_encode(['status' => 'error', 'message' => 'Tidak ada pilihan durasi untuk item ini']);
         return;
      }

      $currentDurasi = '';
      foreach ($this->dDurasi as $d) {
         if ($d['id_durasi'] == $sale['id_durasi']) {
            $currentDurasi = strtoupper($d['durasi']);
         }
      }

      echo json_encode([
         'status' => 'success',
         'id_penjualan' => $id_penjualan,
         'ref' => $ref,
         'kategori' => $kategori,
         'current_durasi' => $currentDurasi,
         'current_item_total' => $currentItemTotal,
         'current_ref_total' => $currentSubTotal,
         'dibayar' => $dibayar,
         'options' => $options,
      ]);
   }

   public function ubah_durasi()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      $id_harga = (int) ($_POST['id_harga'] ?? 0);

      if ($id_penjualan === '' || $id_harga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item member tidak dapat diubah durasinya']);
         return;
      }

      $hargaRow = null;
      foreach ($this->harga as $h) {
         if ((int) $h['id_harga'] === $id_harga) {
            $hargaRow = $h;
            break;
         }
      }

      if (!$hargaRow || ($hargaRow['is_active'] ?? 1) != 1) {
         echo json_encode(['status' => 'error', 'message' => 'Harga tidak ditemukan']);
         return;
      }

      if ($hargaRow['id_penjualan_jenis'] != $sale['id_penjualan_jenis']
         || $hargaRow['id_item_group'] != $sale['id_item_group']
         || $hargaRow['list_layanan'] != $sale['list_layanan']) {
         echo json_encode(['status' => 'error', 'message' => 'Durasi tidak sesuai dengan item order']);
         return;
      }

      if ((int) $hargaRow['id_durasi'] === (int) $sale['id_durasi']) {
         echo json_encode(['status' => 'error', 'message' => 'Durasi sama dengan yang sekarang']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $unitPrice = $this->hargaUnitPrice($hargaRow);
      $minOrder = round((float) ($hargaRow['min_order'] ?? $sale['min_order'] ?? 0), 2);
      $newSubTotal = $this->getRefSubTotal($ref, [
         $id_penjualan => [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ],
      ]);

      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $set = [
         'id_durasi' => (int) $hargaRow['id_durasi'],
         'hari' => (int) ($hargaRow['hari'] ?? 0),
         'jam' => (int) ($hargaRow['jam'] ?? 0),
         'harga' => $unitPrice,
         'min_order' => $minOrder,
         'id_harga' => $id_harga,
      ];
      $where = $this->whereSaleById($id_penjualan);
      $up = $this->db(0)->update('sale', $set, $where);
      if ($up['errno'] != 0) {
         $this->model('Log')->write("[ubah_durasi] Update sale error id=$id_penjualan: " . ($up['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . ($up['error'] ?? 'Unknown error')]);
         return;
      }

      $this->resetBonNotif($ref);

      echo json_encode(['status' => 'success', 'message' => 'Durasi berhasil diubah']);
   }

   public function layanan_options()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? $_GET['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateLayananChangeable($sale, $id_penjualan);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      $currentItemTotal = $this->calcSaleItemTotal($sale);

      $kategori = '';
      foreach ($this->itemGroup as $ig) {
         if ($ig['id_item_group'] == $sale['id_item_group']) {
            $kategori = $ig['item_kategori'];
         }
      }

      $options = [];
      foreach ($this->harga as $h) {
         if (($h['is_active'] ?? 1) != 1) {
            continue;
         }
         if ($h['id_penjualan_jenis'] != $sale['id_penjualan_jenis']) {
            continue;
         }
         if ($h['id_item_group'] != $sale['id_item_group']) {
            continue;
         }
         if ((int) $h['id_durasi'] !== (int) $sale['id_durasi']) {
            continue;
         }
         if ($h['list_layanan'] === $sale['list_layanan']) {
            continue;
         }

         $layananLabel = $this->layananLabelFromSerialized($h['list_layanan']);
         $unitPrice = $this->hargaUnitPrice($h);
         $minOrder = round((float) str_replace(',', '.', (string) ($h['min_order'] ?? $sale['min_order'] ?? 0)), 2);
         $newSubTotal = $this->getRefSubTotal($ref, [
            $id_penjualan => [
               'harga' => $unitPrice,
               'min_order' => $minOrder,
            ],
         ]);
         $newItemTotal = $this->calcSaleItemTotal(array_merge($sale, [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ]));

         $canSelect = ($dibayar <= 0) || ($newSubTotal >= $dibayar);

         $options[] = [
            'id_harga' => (int) $h['id_harga'],
            'layanan' => $layananLabel,
            'harga' => $unitPrice,
            'item_total' => $newItemTotal,
            'ref_total' => $newSubTotal,
            'can_select' => $canSelect,
            'selected' => ($h['list_layanan'] === $sale['list_layanan']),
         ];
      }

      if (empty($options)) {
         echo json_encode(['status' => 'error', 'message' => 'Tidak ada pilihan layanan lain untuk item ini']);
         return;
      }

      $currentDurasi = '';
      foreach ($this->dDurasi as $d) {
         if ($d['id_durasi'] == $sale['id_durasi']) {
            $currentDurasi = strtoupper($d['durasi']);
         }
      }

      echo json_encode([
         'status' => 'success',
         'id_penjualan' => $id_penjualan,
         'ref' => $ref,
         'kategori' => $kategori,
         'current_layanan' => $this->layananLabelFromSerialized($sale['list_layanan']),
         'current_durasi' => $currentDurasi,
         'current_item_total' => $currentItemTotal,
         'current_ref_total' => $currentSubTotal,
         'dibayar' => $dibayar,
         'options' => $options,
      ]);
   }

   public function ubah_layanan()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      $id_harga = (int) ($_POST['id_harga'] ?? 0);

      if ($id_penjualan === '' || $id_harga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateLayananChangeable($sale, $id_penjualan);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $hargaRow = null;
      foreach ($this->harga as $h) {
         if ((int) $h['id_harga'] === $id_harga) {
            $hargaRow = $h;
            break;
         }
      }

      if (!$hargaRow || ($hargaRow['is_active'] ?? 1) != 1) {
         echo json_encode(['status' => 'error', 'message' => 'Harga tidak ditemukan']);
         return;
      }

      if ($hargaRow['id_penjualan_jenis'] != $sale['id_penjualan_jenis']
         || $hargaRow['id_item_group'] != $sale['id_item_group']
         || (int) $hargaRow['id_durasi'] !== (int) $sale['id_durasi']) {
         echo json_encode(['status' => 'error', 'message' => 'Layanan tidak sesuai dengan item order']);
         return;
      }

      if ($hargaRow['list_layanan'] === $sale['list_layanan']) {
         echo json_encode(['status' => 'error', 'message' => 'Layanan sama dengan yang sekarang']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $unitPrice = $this->hargaUnitPrice($hargaRow);
      $minOrder = round((float) str_replace(',', '.', (string) ($hargaRow['min_order'] ?? $sale['min_order'] ?? 0)), 2);
      $newSubTotal = $this->getRefSubTotal($ref, [
         $id_penjualan => [
            'harga' => $unitPrice,
            'min_order' => $minOrder,
         ],
      ]);

      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $set = [
         'list_layanan' => $hargaRow['list_layanan'],
         'harga' => $unitPrice,
         'min_order' => $minOrder,
         'id_harga' => $id_harga,
      ];
      $where = $this->whereSaleById($id_penjualan);
      $up = $this->db(0)->update('sale', $set, $where);
      if ($up['errno'] != 0) {
         $this->model('Log')->write("[ubah_layanan] Update sale error id=$id_penjualan: " . ($up['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . ($up['error'] ?? 'Unknown error')]);
         return;
      }

      $this->resetBonNotif($ref);

      echo json_encode(['status' => 'success', 'message' => 'Layanan berhasil diubah']);
   }

   private function hargaUnitPrice($hargaRow)
   {
      if ($this->mdl_setting['def_price'] == 0) {
         $harga = $hargaRow['harga'];
      } else {
         $harga = $hargaRow['harga_b'];
         if ($harga == 0) {
            $harga = $hargaRow['harga'];
         }
      }
      return (int) $harga;
   }

   private function calcSaleItemTotal($sale)
   {
      if (($sale['member'] ?? 0) != 0) {
         return 0;
      }

      $qty = round((float) ($sale['qty'] ?? 0), 2);
      $minOrder = round((float) ($sale['min_order'] ?? 0), 2);
      $qtyReal = ($qty < $minOrder) ? $minOrder : $qty;
      $total = (float) ($sale['harga'] ?? 0) * $qtyReal;

      $diskonQty = (float) ($sale['diskon_qty'] ?? 0);
      $diskonPartner = (float) ($sale['diskon_partner'] ?? 0);

      if ($diskonQty > 0) {
         $total -= $total * ($diskonQty / 100);
      }
      if ($diskonPartner > 0) {
         $total -= $total * ($diskonPartner / 100);
      }

      return (int) round($total);
   }

   private function getRefSubTotal($ref, $saleOverrides = [])
   {
      $refEsc = $this->db(0)->escape($ref);
      $sales = $this->db(0)->get_where('sale', $this->wCabang . " AND no_ref = '$refEsc' AND bin = 0");
      if (!is_array($sales)) {
         $sales = [];
      }

      $normalizedOverrides = [];
      foreach ($saleOverrides as $oid => $override) {
         $normalizedOverrides[$this->normalizeSaleId($oid)] = $override;
      }

      $subTotal = 0;
      foreach ($sales as $s) {
         $id = $this->normalizeSaleId($s['id_penjualan']);
         if (isset($normalizedOverrides[$id])) {
            $s = array_merge($s, $normalizedOverrides[$id]);
         }
         $subTotal += $this->calcSaleItemTotal($s);
      }

      $surcasList = $this->db(0)->get_where('surcas', $this->wCabang . " AND no_ref = '$refEsc'");
      if (is_array($surcasList)) {
         foreach ($surcasList as $sc) {
            $subTotal += (int) ($sc['jumlah'] ?? 0);
         }
      }

      return (int) round($subTotal);
   }

   private function getRefDibayar($ref)
   {
      $refEsc = $this->db(0)->escape($ref);
      $kas = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = '$refEsc'");
      if (!is_array($kas)) {
         return 0;
      }

      $dibayar = 0;
      foreach ($kas as $ka) {
         $st = (int) ($ka['status_mutasi'] ?? 0);
         if ($st === 2 || $st === 3) {
            $dibayar += (int) ($ka['jumlah'] ?? 0);
         }
      }

      return $dibayar;
   }

   private function validateOrderModifiable($sale)
   {
      if (!$sale || !is_array($sale)) {
         return 'Order tidak ditemukan';
      }
      if (($sale['bin'] ?? 0) != 0) {
         return 'Order tidak dapat diubah';
      }
      if (($sale['tuntas'] ?? 0) != 0) {
         return 'Order sudah tuntas';
      }
      return null;
   }

   private function validatePaymentAfterChange($ref, $newSubTotal)
   {
      $dibayar = $this->getRefDibayar($ref);
      if ($dibayar > 0 && (int) round($newSubTotal) < $dibayar) {
         return 'Total order setelah perubahan (' . number_format($newSubTotal) . ') kurang dari pembayaran Cek/Berhasil (' . number_format($dibayar) . ')';
      }
      return null;
   }

   private function validateLayananChangeable($sale, $id_penjualan)
   {
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         return $err;
      }
      if (($sale['member'] ?? 0) != 0) {
         return 'Item member tidak dapat diubah layanannya';
      }
      if (($sale['id_user_ambil'] ?? 0) > 0) {
         return 'Layanan tidak dapat diubah karena sudah diambil';
      }
      if ($this->saleHasOperasi($id_penjualan)) {
         return 'Layanan tidak dapat diubah karena sudah ada layanan yang diselesaikan';
      }
      return null;
   }

   private function saleHasOperasi($id_penjualan)
   {
      $idEsc = $this->db(0)->escape($this->normalizeSaleId($id_penjualan));
      $count = $this->db(0)->count_where('operasi', $this->wCabang . " AND id_penjualan = '$idEsc'");
      return is_numeric($count) && (int) $count > 0;
   }

   private function layananLabelFromSerialized($listLayanan)
   {
      $arr = @unserialize((string) $listLayanan);
      if (!is_array($arr)) {
         return '';
      }

      $label = '';
      foreach ($arr as $lid) {
         foreach ($this->dLayanan as $c) {
            if ($c['id_layanan'] == $lid) {
               $label .= ($label === '' ? '' : ' + ') . $c['layanan'];
            }
         }
      }

      return $label;
   }

   public function member_options()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? $_GET['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item sudah menggunakan member']);
         return;
      }

      $idPelanggan = (int) ($sale['id_pelanggan'] ?? 0);
      $idHarga = (int) ($sale['id_harga'] ?? 0);
      if ($idPelanggan <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Pelanggan tidak ditemukan pada order ini']);
         return;
      }
      if ($idHarga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item tidak memiliki paket member yang sesuai']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $qty = round((float) ($sale['qty'] ?? 0), 2);
      $saldo = $this->getMemberSaldo($idPelanggan, $idHarga);
      $unit = $this->helper('Saldo')->unit_by_idHarga($idHarga);
      $dibayar = $this->getRefDibayar($ref);
      $currentSubTotal = $this->getRefSubTotal($ref);
      $newSubTotal = $this->getRefSubTotal($ref, [$id_penjualan => ['member' => 1]]);

      $kategori = '';
      foreach ($this->itemGroup as $ig) {
         if ($ig['id_item_group'] == $sale['id_item_group']) {
            $kategori = $ig['item_kategori'];
         }
      }

      $durasi = '';
      foreach ($this->dDurasi as $d) {
         if ($d['id_durasi'] == $sale['id_durasi']) {
            $durasi = strtoupper($d['durasi']);
         }
      }

      $canConvert = ($saldo >= $qty);
      $message = '';
      if (!$canConvert) {
         $message = 'Saldo member M' . $idHarga . ' tidak cukup (tersedia ' . $this->fmtDecMax2($saldo) . $unit . ', dibutuhkan ' . $this->fmtDecMax2($qty) . $unit . ')';
      } else {
         $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
         if ($payErr !== null) {
            $canConvert = false;
            $message = $payErr;
         }
      }

      echo json_encode([
         'status' => 'success',
         'id_penjualan' => $id_penjualan,
         'ref' => $ref,
         'id_harga' => $idHarga,
         'kategori' => $kategori,
         'durasi' => $durasi,
         'qty' => $qty,
         'qty_fmt' => $this->fmtDecMax2($qty),
         'unit' => $unit,
         'saldo' => $saldo,
         'saldo_fmt' => $this->fmtDecMax2($saldo),
         'current_ref_total' => $currentSubTotal,
         'new_ref_total' => $newSubTotal,
         'dibayar' => $dibayar,
         'can_convert' => $canConvert,
         'message' => $message,
      ]);
   }

   public function ubah_member()
   {
      header('Content-Type: application/json');
      $id_penjualan = $this->normalizeSaleId($_POST['id'] ?? '');
      if ($id_penjualan === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
         return;
      }

      $sale = $this->db(0)->get_where_row('sale', $this->whereSaleById($id_penjualan) . ' AND bin = 0');
      $err = $this->validateOrderModifiable($sale);
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      if (($sale['member'] ?? 0) != 0) {
         echo json_encode(['status' => 'error', 'message' => 'Item sudah menggunakan member']);
         return;
      }

      $idPelanggan = (int) ($sale['id_pelanggan'] ?? 0);
      $idHarga = (int) ($sale['id_harga'] ?? 0);
      if ($idPelanggan <= 0 || $idHarga <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Data order tidak lengkap untuk ubah member']);
         return;
      }

      $qty = round((float) ($sale['qty'] ?? 0), 2);
      $saldo = $this->getMemberSaldo($idPelanggan, $idHarga);
      if ($saldo < $qty) {
         echo json_encode(['status' => 'error', 'message' => 'Saldo member tidak cukup']);
         return;
      }

      $id_penjualan = $this->normalizeSaleId($sale['id_penjualan']);
      $ref = $sale['no_ref'];
      $newSubTotal = $this->getRefSubTotal($ref, [$id_penjualan => ['member' => 1]]);
      $payErr = $this->validatePaymentAfterChange($ref, $newSubTotal);
      if ($payErr !== null) {
         echo json_encode(['status' => 'error', 'message' => $payErr]);
         return;
      }

      $set = [
         'member' => 1,
         'total' => 0,
      ];
      $where = $this->whereSaleById($id_penjualan);
      $up = $this->db(0)->update('sale', $set, $where);
      if ($up['errno'] != 0) {
         $this->model('Log')->write("[ubah_member] Update sale error id=$id_penjualan: " . ($up['error'] ?? ''));
         echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . ($up['error'] ?? 'Unknown error')]);
         return;
      }

      $this->resetBonNotif($ref);

      echo json_encode(['status' => 'success', 'message' => 'Berhasil diubah ke member']);
   }

   private function normalizeSaleId($id)
   {
      return trim((string) $id);
   }

   private function whereSaleById($id_penjualan)
   {
      $idEsc = $this->db(0)->escape($this->normalizeSaleId($id_penjualan));
      return $this->wCabang . " AND id_penjualan = '$idEsc'";
   }

   /**
    * Hapus notif bon (tipe=1) agar nota WA bisa dikirim ulang setelah perubahan order.
    */
   private function resetBonNotif($ref)
   {
      $ref = trim((string) $ref);
      if ($ref === '') {
         return;
      }

      $refEsc = $this->db(0)->escape($ref);
      $where = $this->wCabang . " AND no_ref = '$refEsc' AND tipe = 1";
      $del = $this->db(0)->delete('notif', $where);
      if (isset($del['errno']) && $del['errno'] != 0) {
         $this->model('Log')->write("[resetBonNotif] Delete notif error ref=$ref: " . ($del['error'] ?? ''));
      }
   }

   private function getMemberSaldo($idPelanggan, $idHarga)
   {
      $idPelanggan = (int) $idPelanggan;
      $idHarga = (int) $idHarga;

      $whereMember = "bin = 0 AND id_pelanggan = $idPelanggan AND id_harga = $idHarga";
      $saldoManual = $this->db(0)->get_cols_where('member', 'SUM(qty) as saldo', $whereMember, 0)['saldo'] ?? 0;

      $whereSale = $this->wCabang . " AND id_pelanggan = $idPelanggan AND member = 1 AND bin = 0 AND id_harga = $idHarga";
      $saldoPengurangan = $this->db(0)->get_cols_where('sale', 'SUM(qty) as saldo', $whereSale, 0)['saldo'] ?? 0;

      return round((float) $saldoManual - (float) $saldoPengurangan, 2);
   }
}
