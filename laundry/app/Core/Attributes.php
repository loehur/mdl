<?php

trait Attributes
{
    public $v_load, $v_content, $v_viewer;
    public $user_login, $nama_user, $id_cabang, $id_cabang_p, $id_privilege, $wUser, $wCabang, $dKota, $dPrivilege, $dLayanan, $dDurasi, $dPenjualan, $dSatuan, $dItem, $dItemPengeluaran;
    public $dMetodeMutasi, $dStatusMutasi;
    public $user, $userAll, $userCabang, $userMerge, $pelanggan, $pelangganLaundry, $harga, $itemGroup, $surcas, $diskon, $langganan, $cabang_registered;
    public $dLaundry, $dCabang, $listCabang, $surcasPublic, $mdl_setting;
    public $pelanggan_p;
    public $kode_cabang;

    public function operating_data()
    {
        if (isset($_SESSION[URL::SESSID])) {
            if ($_SESSION[URL::SESSID]['login'] == true) {
                $this->user_login = $_SESSION[URL::SESSID]['user'];
                $id_user = $_SESSION[URL::SESSID]['user']['id_user'];
                $this->nama_user = $_SESSION[URL::SESSID]['user']['nama_user'];

                $this->id_cabang = $_SESSION[URL::SESSID]['user']['id_cabang'];
                $this->id_privilege = $_SESSION[URL::SESSID]['user']['id_privilege'];

                $this->wUser = 'id_user = ' . $id_user;
                $this->wCabang = 'id_cabang = ' . $this->id_cabang;

                $this->dPrivilege = $_SESSION[URL::SESSID]['data']['privilege'];
                $this->dLayanan = $_SESSION[URL::SESSID]['data']['layanan'];
                $this->dDurasi = $_SESSION[URL::SESSID]['data']['durasi'];
                $this->dPenjualan = $_SESSION[URL::SESSID]['data']['penjualan_jenis'];
                $this->dSatuan = $_SESSION[URL::SESSID]['data']['satuan'];
                $this->dItem = $_SESSION[URL::SESSID]['data']['item'];
                $this->dKota = $_SESSION[URL::SESSID]['data']['kota'];
                $this->dItemPengeluaran = $_SESSION[URL::SESSID]['data']['item_pengeluaran'];
                $this->dMetodeMutasi = $_SESSION[URL::SESSID]['data']['mutasi_metode'];
                $this->dStatusMutasi = $_SESSION[URL::SESSID]['data']['mutasi_status'];

                $this->user = $_SESSION[URL::SESSID]['order']['user'];
                $this->userCabang = $_SESSION[URL::SESSID]['order']['userCabang'];
                $this->userAll = $_SESSION[URL::SESSID]['order']['userAll'];
                $this->userMerge = array_merge($this->user, $this->userCabang);
                $this->pelanggan = $_SESSION[URL::SESSID]['order']['pelanggan'];
                $this->pelangganLaundry = $_SESSION[URL::SESSID]['order']['pelangganLaundry'];
                $this->harga = $_SESSION[URL::SESSID]['order']['harga'];
                $this->itemGroup = $_SESSION[URL::SESSID]['order']['itemGroup'];
                $this->surcas = $_SESSION[URL::SESSID]['order']['surcas'];
                $this->diskon = $_SESSION[URL::SESSID]['order']['diskon'];

                if (count($_SESSION[URL::SESSID]['mdl_setting']) == 0) {
                    $_SESSION[URL::SESSID]['mdl_setting']['print_ms'] = 0;
                    $_SESSION[URL::SESSID]['mdl_setting']['def_price'] = 0;
                }
                $this->mdl_setting = $_SESSION[URL::SESSID]['mdl_setting'];

                $this->dLaundry = array('nama_laundry' => 'NO LAUNDRY');
                $this->listCabang = $_SESSION[URL::SESSID]['data']['listCabang'];
                $this->dCabang = array('kode_cabang' => '00');
                if (isset($_SESSION[URL::SESSID]['data']['cabang'])) {
                    $this->dCabang = $_SESSION[URL::SESSID]['data']['cabang'];
                }
                if (isset($this->dLayanan['error'])) {
                    $this->parameter($this->user_login);
                    $this->dLayanan = $_SESSION[URL::SESSID]['data']['layanan'];
                    $this->dPrivilege = $_SESSION[URL::SESSID]['data']['privilege'];
                    $this->dDurasi = $_SESSION[URL::SESSID]['data']['durasi'];
                    $this->dPenjualan = $_SESSION[URL::SESSID]['data']['penjualan_jenis'];
                    $this->dSatuan = $_SESSION[URL::SESSID]['data']['satuan'];
                    $this->dItem = $_SESSION[URL::SESSID]['data']['item'];
                    $this->dKota = $_SESSION[URL::SESSID]['data']['kota'];
                    $this->dItemPengeluaran = $_SESSION[URL::SESSID]['data']['item_pengeluaran'];
                    $this->dMetodeMutasi = $_SESSION[URL::SESSID]['data']['mutasi_metode'];
                    $this->dStatusMutasi = $_SESSION[URL::SESSID]['data']['mutasi_status'];
                }
            }
        }
    }

    public function public_data($pelanggan)
    {
        $this->dLayanan = $this->db(0)->get('layanan');
        $this->dDurasi = $this->db(0)->get('durasi');
        $this->dPenjualan = $this->db(0)->get('penjualan_jenis');
        $this->dSatuan = $this->db(0)->get('satuan');
        $this->dItem = $this->db(0)->get("item");
        $this->harga =  $this->db(0)->get_order("harga", "sort ASC");
        $this->itemGroup = $this->db(0)->get("item_group");
        $this->diskon = $this->db(0)->get("diskon_qty");
        $this->dMetodeMutasi = $this->db(0)->get('mutasi_metode');
        $this->dStatusMutasi = $this->db(0)->get('mutasi_status');
        $this->pelanggan_p = $this->db(0)->get_where_row("pelanggan", "id_pelanggan = " . $pelanggan);
        $this->id_cabang_p = $this->pelanggan_p['id_cabang'];
        $this->surcasPublic = $this->db(0)->get('surcas_jenis');
    }

    public function parameter($data_user)
    {
        $_SESSION[URL::SESSID]['user'] = $this->db(0)->get_where_row("user", "id_user = '" . $data_user['id_user'] . "'");
        $_SESSION[URL::SESSID]['order'] = array(
            'user' => $this->db(0)->get_where("user", "en = 1 AND id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'], 'id_user'),
            'userAll' => $this->db(0)->get("user", 'id_user'),
            'userCabang' => $this->db(0)->get_where("user", "en = 1 AND id_cabang <> " . $_SESSION[URL::SESSID]['user']['id_cabang'], 'id_user'),
            'pelanggan' => $this->db(0)->get_where("pelanggan", "id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'] . " ORDER by sort DESC", 'id_pelanggan'),
            'pelangganLaundry' => $this->db(0)->get_order("pelanggan", "sort DESC"),
            'harga' => $this->db(0)->get_order("harga", "sort DESC"),
            'itemGroup' => $this->db(0)->get("item_group"),
            "surcas" => $this->db(0)->get("surcas_jenis"),
            'diskon' => $this->db(0)->get("diskon_qty"),
        );

        $_SESSION[URL::SESSID]['data'] = array(
            'cabang' => $this->db(0)->get_where_row('cabang', 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang']),
            'listCabang' => $this->db(0)->get('cabang'),
            'layanan' => $this->db(0)->get('layanan'),
            'privilege' => $this->db(0)->get('privilege'),
            'durasi' => $this->db(0)->get('durasi'),
            'penjualan_jenis' => $this->db(0)->get('penjualan_jenis'),
            'satuan' => $this->db(0)->get('satuan'),
            'mutasi_metode' => $this->db(0)->get('mutasi_metode'),
            'mutasi_status' => $this->db(0)->get('mutasi_status'),
            'item' => $this->db(0)->get("item"),
            'kota' => $this->db(0)->get("kota"),
            'item_pengeluaran' => $this->db(0)->get("item_pengeluaran"),
        );

        $_SESSION[URL::SESSID]['mdl_setting'] = $this->db(0)->get_where_row('setting', 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang']);

        $_SESSION[URL::SESSID]['user']['book'] = $_SESSION[URL::SESSID]['user']['book'] == "" ? date('Y') : $_SESSION[URL::SESSID]['user']['book'];
    }

    public function dataSynchrone($id_user)
    {
        $where = "id_user = " . $id_user;
        $data_user = $this->db(0)->get_where_row('user', $where);
        $this->parameter($data_user);
        return $data_user;
    }

    function valid_number($number)
    {
        if (!is_numeric($number)) {
            $number = preg_replace('/[^0-9]/', '', $number);
        }

        if (substr($number, 0, 1) == '8') {
            if (strlen($number) >= 7 && strlen($number) <= 14) {
                $fix_number = "0" . $number;
                return $fix_number;
            } else {
                return false;
            }
        } else if (substr($number, 0, 2) == '08') {
            if (strlen($number) >= 8 && strlen($number) <= 15) {
                return $number;
            } else {
                return false;
            }
        } else if (substr($number, 0, 3) == '628') {
            if (strlen($number) >= 9 && strlen($number) <= 16) {
                $fix_number = "0" . substr($number, 2);
                return $fix_number;
            } else {
                return false;
            }
        } else if (substr($number, 0, 4) == '+628') {
            if (strlen($number) >= 10 && strlen($number) <= 17) {
                $fix_number = "0" . substr($number, 3);
                return $fix_number;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    private function updateSalesState($ref_transaksi)
    {
        // Cek apakah ini transaksi Sales (jenis_transaksi = 7)
        // Ambil semua pembayaran untuk ref ini
        $allPayments = $this->db(date('Y'))->get_where('kas', "ref_transaksi = '$ref_transaksi' AND jenis_transaksi = 7");
        
        if (empty($allPayments)) return; // Bukan transaksi sales atau tidak ada data

        // Ambil total tagihan dari barang_mutasi
        $items = $this->db(1)->get_where('barang_mutasi', "ref = '$ref_transaksi'");
        $totalTagihan = 0;
        foreach ($items as $item) {
            $totalTagihan += ($item['harga'] * $item['qty']); // Asumsi kolom harga & qty
        }

        $totalBayar = 0;
        $allPaid = true;
        
        foreach ($allPayments as $p) {
            $totalBayar += $p['jumlah'];
            if ($p['status_mutasi'] != 3) {
                $allPaid = false;
            }
        }
        
        // Update state jika lunas
        if ($totalBayar >= $totalTagihan && $allPaid && $totalTagihan > 0) {
            $this->db(1)->update('barang_mutasi', ['state' => 1], "ref = '$ref_transaksi'");
        }
    }

   public function payment_gateway_logic($ref_finance, $is_public = false)
   {
      $gateway = defined('URL::PAYMENT_GATEWAY') ? URL::PAYMENT_GATEWAY : 'tokopay';
      if ($is_public) $gateway = 'tokopay'; 

      $where = "ref_finance = '" . $ref_finance . "'";
      if (!$is_public && isset($this->wCabang) && !empty($this->wCabang)) {
         $where = $this->wCabang . " AND " . $where;
      }

      $currentYear = date('Y');
      for ($i = 2021; $i <= $currentYear; $i++) {
         $kas = $this->db($i)->get_where_row('kas', $where);
         if ($kas && $kas['status_mutasi'] == 3) {
            echo json_encode(['status' => 'paid']);
            exit();
         } elseif ($kas && $kas['payment_gateway'] == $gateway) {
            $cek_qr_string = $this->db(100)->get_where_row('wh_tokopay', "ref_id = '" . $ref_finance . "'");
            if ($cek_qr_string && $cek_qr_string['qr_string']) {
               echo json_encode([
                  'status' => $cek_qr_string['state'],
                  'qr_string' => $cek_qr_string['qr_string'],
                  'trx_id' => $ref_finance
               ]);
               exit();
            }
         } elseif ($kas && $kas['payment_gateway'] == 'midtrans') {
            $cek_qr_string = $this->db(100)->get_where_row('wh_midtrans', "ref_id = '" . $ref_finance . "'");
            if ($cek_qr_string && $cek_qr_string['qr_string']) {
               echo json_encode([
                  'status' => $cek_qr_string['state'],
                  'qr_string' => $cek_qr_string['qr_string'],
                  'trx_id' => $ref_finance
               ]);
               exit();
            }
         }

      }

      $nominal = isset($_GET['nominal']) ? intval($_GET['nominal']) : 0;
      if ($nominal <= 0 && $is_public && isset($kas) && $kas) {
         $nominal = intval($kas['jumlah']);
      }
      
      if ($nominal <= 0) {
         if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Nominal tidak valid: " . $nominal);
         echo json_encode(['status' => 'error', 'msg' => 'Nominal tidak valid']);
         exit();
      }

      $metode = isset($_GET['metode']) ? $_GET['metode'] : 'QRIS';
      if (strtoupper($metode) <> 'QRIS') {
         if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Metode tidak valid: " . $metode);
         echo json_encode(['status' => 'error', 'msg' => 'Hanya menerima metode QRIS']);
         exit();
      }

      $ref_id = $ref_finance;

      if ($gateway == 'tokopay') {
         $res = $this->model('Tokopay')->createOrder($nominal, $ref_id, 'QRIS');
         $data = json_decode($res, true);

         if (isset($data['status']) && $data['status']) {
            $trx_id = $data['data']['trx_id'] ?? $ref_id;
            $qr_string = '';
            if (isset($data['data']['qr_string']) && !empty($data['data']['qr_string'])) {
               $qr_string = $data['data']['qr_string'];
            } elseif (isset($data['qr_string']) && !empty($data['qr_string'])) {
               $qr_string = $data['qr_string'];
            } else {
               if (!$is_public) $this->model('Log')->write("[payment_gateway_order] QR String not found in response");
               echo json_encode(['status' => 'error', 'msg' => 'QR String not found']);
               exit();
            }

            $error_update = 0;
            for ($i = 2021; $i <= $currentYear; $i++) {
               $up_kas = $this->db($i)->update('kas', ['payment_gateway' => $gateway], "ref_finance = '$ref_finance'");
               if ($up_kas['errno'] <> 0) {
                  $error_update++;
                  $this->model('Log')->write('[payment_gateway_order] Update Payment Gateway Error ' . $i . ': ' . $up_kas['error']);
               }
            }

            if($error_update > 0) {
               exit();
            }

            $in = $this->db(100)->insertReplace('wh_tokopay', [
               'trx_id' => $trx_id,
               'target' => 'kas_laundry',
               'ref_id' => $ref_finance,
               'book' => date('Y'),
               'qr_string' => $qr_string,
               'state' => 'pending'
            ]);

            if ($in['errno'] <> 0) {
               if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Insert WH Error: " . $in['error']);
               echo json_encode(['status' => 'error', 'msg' => 'Failed to insert to tracking table']);
               exit();
            }

            if (isset($data['data']['status']) && (strtolower($data['data']['status']) == 'success' || strtolower($data['data']['status']) == 'paid')) {
               $error_update = 0;
               for ($i = 2021; $i <= $currentYear; $i++) {
                  $update = $this->db($i)->update('kas', ['status_mutasi' => 3], "ref_finance = '$ref_finance'");
                  if ($update['errno'] <> 0) {
                     $error_update++;
                     if (!$is_public) $this->model('Log')->write('[payment_gateway_order] Update Kas Error ' . $i . ': ' . $update['error']);
                  }
               }


               if ($error_update > 0) {
                   echo json_encode(['status' => 'error', 'msg' => 'DB Update Error']);
                   exit();
               }
               
               // Ambil ref_transaksi untuk update state sales
               $kasInfo = $this->db(date('Y'))->get_where_row('kas', "ref_finance = '$ref_finance'");
               if ($kasInfo) {
                   $this->updateSalesState($kasInfo['ref_transaksi']);
               }
               
               echo json_encode(['status' => 'paid']);
               exit();
            } else {
               echo json_encode([
                  'status' => $data['status'],
                  'qr_string' => $qr_string,
                  'trx_id' => $trx_id
               ]);
               exit();
            }
         } else {
            if (!$is_public) $this->model('Log')->write("[payment_gateway_order] API Failed: " . json_encode($data));
            echo json_encode(['status' => 'error', 'msg' => $data]);
            exit();
         }
      } elseif ($gateway == 'midtrans') {
         $midtransResponse = $this->model('Midtrans')->createTransaction($ref_id, $nominal);
         $data = json_decode($midtransResponse, true);

         if (isset($data['transaction_id'])) {

            $error_update = 0;
            for ($i = 2021; $i <= $currentYear; $i++) {
               $up_kas = $this->db($i)->update('kas', ['payment_gateway' => $gateway], "ref_finance = '$ref_finance'");
               if ($up_kas['errno'] <> 0) {
                  $error_update++;
                  $this->model('Log')->write('[payment_gateway_order] Update Payment Gateway Error ' . $i . ': ' . $up_kas['error']);
               }
            }

            if($error_update > 0) {
               exit();
            }

            $trx_id = $data['transaction_id'];
            $qr_string = isset($data['qr_string']) ? $data['qr_string'] : '';

            if (empty($qr_string)) {
               $this->model('Log')->write("[payment_gateway_order] QR String not found in response");
               echo json_encode(['status' => 'error', 'msg' => 'QR String not found']);
               exit();
            }

            $insert = $this->db(0)->insertReplace('wh_midtrans', [
               'trx_id' => $trx_id,
               'target' => 'kas_laundry',
               'ref_id' => $ref_finance,
               'book' => date('Y'),
               'qr_string' => $qr_string,
               'state' => 'pending'
            ]);

            if ($insert['errno'] == 0) {
               echo json_encode([
                  'status' => $data['status'] ?? 'pending',
                  'qr_string' => $qr_string,
                  'trx_id' => $trx_id
               ]);
               exit();
            } else {
               if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Midtrans Insert WH Error: " . $insert['error']);
               echo json_encode(['status' => 'error', 'msg' => $insert['error']]);
               exit();
            }
         } else {
            if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Midtrans API Failed: " . $midtransResponse);
            echo $midtransResponse;
            exit();
         }
      }else{
         if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Payment Gateway not found");
         echo json_encode(['status' => 'error', 'msg' => 'Payment Gateway not found']);
         exit();
      }
   }

   public function payment_gateway_status_logic($ref_finance, $is_public = false)
   {
      $where = "ref_finance = '" . $ref_finance . "'";
      if (!$is_public && isset($this->wCabang) && !empty($this->wCabang)) {
         $where = $this->wCabang . " AND " . $where;
      }
      
      $kas = $this->db(date('Y'))->get_where_row('kas', $where);

      if (!isset($kas['id_kas'])) {
         echo json_encode(['status' => 'ERROR', 'msg' => 'Transaction not found']);
         exit();
      }

      if ($kas['status_mutasi'] == 3) {
         echo json_encode(['status' => 'PAID']);
         exit();
      }

      if ($is_public) {
         $note_trx = isset($kas['note']) ? strtoupper($kas['note']) : '';
         if ($note_trx <> 'QRIS') {
            if ($kas['status_mutasi'] == 3) {
               echo json_encode(['status' => 'PAID']);
            } else {
               echo json_encode(['status' => 'PENDING', 'msg' => 'Menunggu Konfirmasi Admin']);
            }
            exit();
         }
      }

      $gateway = defined('URL::PAYMENT_GATEWAY') ? URL::PAYMENT_GATEWAY : 'midtrans';

      if ($gateway == 'tokopay') {
         $status = $this->model('Tokopay')->checkStatus($ref_finance, $kas['jumlah']);
         $data = json_decode($status, true);

         $isPaid = false;
         if (isset($data['data']['status'])) {
            if (strtolower($data['data']['status']) == 'success' || strtolower($data['data']['status']) == 'paid') {
               $isPaid = true;
            }
         }

         if ($isPaid) {
            $update = $this->db(date('Y'))->update('kas', ['status_mutasi' => 3], "ref_finance = '$ref_finance'");
            if ($update['errno'] == 0) {
               $this->updateSalesState($kas['ref_transaksi']);
               echo json_encode(['status' => 'PAID']);
            } else {
               if (!$is_public) $this->model('Log')->write("[payment_gateway_check_status] Tokopay Update Kas Error: " . $update['error']);
               echo json_encode(['status' => 'ERROR', 'msg' => $update['error']]);
            }
         } else {
            echo json_encode(['status' => 'PENDING', 'data' => $data]);
         }
      } else {
         $status = $this->model('Midtrans')->checkStatus($ref_finance);
         $data = json_decode($status, true);

         $isPaid = false;
         if (isset($data['transaction_status'])) {
            if ($data['transaction_status'] == 'settlement' || $data['transaction_status'] == 'capture') {
               $isPaid = true;
            }
         }

         if ($isPaid) {
            $update = $this->db(date('Y'))->update('kas', ['status_mutasi' => 3], "ref_finance = '$ref_finance'");
            if ($update['errno'] == 0) {
               $this->updateSalesState($kas['ref_transaksi']);
               echo json_encode(['status' => 'PAID']);
            } else {
               if (!$is_public) $this->model('Log')->write("[payment_gateway_check_status] Midtrans Update Kas Error: " . $update['error']);
               echo json_encode(['status' => 'ERROR', 'msg' => $update['error']]);
            }
         } else {
            echo json_encode(['status' => 'PENDING', 'data' => $data]);
         }
      }
   }
}
