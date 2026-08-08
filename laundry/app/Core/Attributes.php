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
    /** @var array<int,int> id_harga => harga override cabang */
    public $hargaCabang = [];
    /** @var array<int,int> id_harga_paket => harga override cabang */
    public $hargaPaketCabang = [];
    /** @var bool Mode Training aktif (cabang virtual) */
    public $isTrainingMode = false;

    /**
     * Map override harga unit untuk satu cabang: id_harga => harga.
     */
    public function loadHargaCabangMap($idCabang)
    {
        $map = [];
        $idCabang = (int) $idCabang;
        if ($idCabang <= 0) {
            return $map;
        }
        try {
            $rows = $this->db(0)->get_where('harga_cabang', 'id_cabang = ' . $idCabang);
        } catch (\Throwable $e) {
            return $map;
        }
        if (!is_array($rows)) {
            return $map;
        }
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $idHarga = (int) ($r['id_harga'] ?? 0);
            $harga = (int) round((float) ($r['harga'] ?? 0), 0);
            if ($idHarga > 0 && $harga > 0) {
                $map[$idHarga] = $harga;
            }
        }
        return $map;
    }

    /**
     * Map override harga paket untuk satu cabang: id_harga_paket => harga.
     */
    public function loadHargaPaketCabangMap($idCabang)
    {
        $map = [];
        $idCabang = (int) $idCabang;
        if ($idCabang <= 0) {
            return $map;
        }
        try {
            $rows = $this->db(0)->get_where('harga_paket_cabang', 'id_cabang = ' . $idCabang);
        } catch (\Throwable $e) {
            return $map;
        }
        if (!is_array($rows)) {
            return $map;
        }
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $idPaket = (int) ($r['id_harga_paket'] ?? 0);
            $harga = (int) round((float) ($r['harga'] ?? 0), 0);
            if ($idPaket > 0 && $harga > 0) {
                $map[$idPaket] = $harga;
            }
        }
        return $map;
    }

    /**
     * Harga unit efektif: override cabang jika > 0, else harga global.
     */
    public function resolveHargaUnit($hargaRow)
    {
        if (!is_array($hargaRow)) {
            return 0;
        }
        $idHarga = (int) ($hargaRow['id_harga'] ?? 0);
        $global = (int) round((float) str_replace(',', '.', (string) ($hargaRow['harga'] ?? 0)), 0);
        $self = (int) ($this->hargaCabang[$idHarga] ?? 0);
        return $self > 0 ? $self : $global;
    }

    /**
     * Hanya harga is_active = 1 (untuk pilihan order / penjualan).
     * @param array|null $rows
     * @return array
     */
    public function filterHargaAktif($rows = null)
    {
        if ($rows === null) {
            $rows = is_array($this->harga) ? $this->harga : [];
        }
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['is_active'] ?? 0) === 1) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * Load harga aktif langsung dari DB (sort ASC).
     */
    public function loadHargaAktif()
    {
        $rows = $this->db(0)->get_where_order('harga', 'is_active = 1', 'sort ASC');
        return is_array($rows) ? $rows : [];
    }

    /**
     * Harga paket efektif: override cabang jika > 0, else harga global paket.
     */
    public function resolveHargaPaketUnit($paketRow)
    {
        if (!is_array($paketRow)) {
            return 0;
        }
        $idPaket = (int) ($paketRow['id_harga_paket'] ?? 0);
        $global = (int) round((float) str_replace(',', '.', (string) ($paketRow['harga'] ?? 0)), 0);
        $self = (int) ($this->hargaPaketCabang[$idPaket] ?? 0);
        return $self > 0 ? $self : $global;
    }

    /**
     * Apakah session sedang Mode Training.
     */
    public function isTrainingMode()
    {
        return !empty($_SESSION[URL::SESSID]['training']['active']);
    }

    /**
     * id_cabang virtual training (0 jika belum di-seed).
     */
    public function getTrainingCabangId()
    {
        $ids = $this->getTrainingCabangIds();
        return count($ids) > 0 ? (int) $ids[0] : 0;
    }

    /**
     * Semua id_cabang dengan is_training = 1.
     * @return int[]
     */
    public function getTrainingCabangIds()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = [];
        try {
            $rows = $this->db(0)->get_where('cabang', 'is_training = 1');
            if (!is_array($rows)) {
                $rows = $rows ? iterator_to_array($rows) : [];
            }
            foreach ($rows as $r) {
                $id = (int) ($r['id_cabang'] ?? 0);
                if ($id > 0) {
                    $cached[] = $id;
                }
            }
        } catch (\Throwable $e) {
            $cached = [];
        }
        return $cached;
    }

    /**
     * True jika id_cabang adalah cabang training.
     */
    public function isTrainingCabangId($idCabang)
    {
        $idCabang = (int) $idCabang;
        return $idCabang > 0 && in_array($idCabang, $this->getTrainingCabangIds(), true);
    }

    /**
     * Fragmen SQL untuk mengecualikan cabang training, mis. "id_cabang NOT IN (12) AND ".
     * String kosong jika tidak ada cabang training.
     */
    public function sqlExcludeTrainingCabang($column = 'id_cabang')
    {
        $ids = $this->getTrainingCabangIds();
        if (count($ids) < 1) {
            return '';
        }
        return $column . ' NOT IN (' . implode(',', array_map('intval', $ids)) . ') AND ';
    }

    /**
     * Semua cabang operasional (tanpa cabang virtual training).
     */
    public function getCabangOperasional()
    {
        $all = $this->db(0)->get('cabang');
        return $this->filterCabangOperasional($all);
    }

    /**
     * Filter array baris cabang: buang is_training = 1.
     */
    public function filterCabangOperasional($rows)
    {
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $key => $c) {
            if (!is_array($c)) {
                continue;
            }
            if (!empty($c['is_training'])) {
                continue;
            }
            $out[$key] = $c;
        }
        return array_values($out);
    }

    /**
     * Filter daftar pelanggan sesuai mode Live / Training.
     */
    protected function filterPelangganByMode($rows, $trainingMode, $trainId)
    {
        if (!is_array($rows) || $trainId <= 0) {
            return is_array($rows) ? $rows : [];
        }
        $out = [];
        foreach ($rows as $key => $p) {
            if (!is_array($p)) {
                continue;
            }
            $cid = (int) ($p['id_cabang'] ?? 0);
            if ($trainingMode) {
                if ($cid === $trainId) {
                    $out[$key] = $p;
                }
            } else {
                if ($cid !== $trainId) {
                    $out[$key] = $p;
                }
            }
        }
        return $out;
    }

    public function operating_data()
    {
        if (isset($_SESSION[URL::SESSID])) {
            if ($_SESSION[URL::SESSID]['login'] == true) {
                $this->user_login = $_SESSION[URL::SESSID]['user'];
                $id_user = $_SESSION[URL::SESSID]['user']['id_user'];
                $this->nama_user = $_SESSION[URL::SESSID]['user']['nama_user'];

                $this->isTrainingMode = $this->isTrainingMode();
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
                $this->hargaCabang = isset($_SESSION[URL::SESSID]['order']['hargaCabang']) && is_array($_SESSION[URL::SESSID]['order']['hargaCabang'])
                    ? $_SESSION[URL::SESSID]['order']['hargaCabang']
                    : $this->loadHargaCabangMap($this->id_cabang);
                $this->hargaPaketCabang = isset($_SESSION[URL::SESSID]['order']['hargaPaketCabang']) && is_array($_SESSION[URL::SESSID]['order']['hargaPaketCabang'])
                    ? $_SESSION[URL::SESSID]['order']['hargaPaketCabang']
                    : $this->loadHargaPaketCabangMap($this->id_cabang);

                if (count($_SESSION[URL::SESSID]['mdl_setting']) == 0) {
                    $_SESSION[URL::SESSID]['mdl_setting']['print_ms'] = 0;
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
        $this->hargaCabang = $this->loadHargaCabangMap($this->id_cabang_p);
        $this->hargaPaketCabang = $this->loadHargaPaketCabangMap($this->id_cabang_p);
        $this->surcasPublic = $this->db(0)->get('surcas_jenis');
    }

    public function parameter($data_user)
    {
        $userRow = $this->db(0)->get_where_row("user", "id_user = '" . $data_user['id_user'] . "'");
        if (!is_array($userRow) || empty($userRow['id_user'])) {
            return;
        }

        if (!isset($_SESSION[URL::SESSID]['training']) || !is_array($_SESSION[URL::SESSID]['training'])) {
            $_SESSION[URL::SESSID]['training'] = ['active' => false, 'id_cabang_origin' => (int) $userRow['id_cabang']];
        }

        $trainId = $this->getTrainingCabangId();
        $trainingActive = !empty($_SESSION[URL::SESSID]['training']['active']);

        // Jangan biarkan mode training tanpa cabang seed
        if ($trainingActive && $trainId <= 0) {
            $_SESSION[URL::SESSID]['training']['active'] = false;
            $trainingActive = false;
        }

        $realCabangId = (int) $userRow['id_cabang'];
        // Jika DB user kebetulan menunjuk cabang TRAIN, pakai origin / cabang operasional pertama
        if ($trainId > 0 && $realCabangId === $trainId) {
            $origin = (int) ($_SESSION[URL::SESSID]['training']['id_cabang_origin'] ?? 0);
            if ($origin <= 0 || $origin === $trainId) {
                $ops = $this->getCabangOperasional();
                $origin = !empty($ops[0]['id_cabang']) ? (int) $ops[0]['id_cabang'] : 0;
            }
            $realCabangId = $origin > 0 ? $origin : $realCabangId;
        }

        if (!$trainingActive) {
            $_SESSION[URL::SESSID]['training']['id_cabang_origin'] = $realCabangId;
        } else {
            if (empty($_SESSION[URL::SESSID]['training']['id_cabang_origin'])) {
                $_SESSION[URL::SESSID]['training']['id_cabang_origin'] = $realCabangId;
            }
        }

        $effectiveCabangId = $trainingActive ? $trainId : $realCabangId;

        $_SESSION[URL::SESSID]['user'] = $userRow;
        // Override session saja — tidak menulis TRAIN ke tabel user
        $_SESSION[URL::SESSID]['user']['id_cabang'] = $effectiveCabangId;

        $pelangganCabang = $this->db(0)->get_where("pelanggan", "id_cabang = " . $effectiveCabangId . " ORDER by sort DESC", 'id_pelanggan');
        $pelangganLaundry = $this->filterPelangganByMode(
            $this->db(0)->get_order("pelanggan", "sort DESC"),
            $trainingActive,
            $trainId
        );

        $_SESSION[URL::SESSID]['order'] = array(
            'user' => $this->db(0)->get_where("user", "en = 1 AND id_cabang = " . $effectiveCabangId, 'id_user'),
            'userAll' => $this->db(0)->get("user", 'id_user'),
            'userCabang' => $this->db(0)->get_where("user", "en = 1 AND id_cabang <> " . $effectiveCabangId, 'id_user'),
            'pelanggan' => $pelangganCabang,
            'pelangganLaundry' => $pelangganLaundry,
            'harga' => $this->db(0)->get_order("harga", "sort DESC"),
            'hargaCabang' => $this->loadHargaCabangMap($effectiveCabangId),
            'hargaPaketCabang' => $this->loadHargaPaketCabangMap($effectiveCabangId),
            'itemGroup' => $this->db(0)->get("item_group"),
            "surcas" => $this->db(0)->get("surcas_jenis"),
            'diskon' => $this->db(0)->get("diskon_qty"),
        );

        $cabangRow = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . $effectiveCabangId);
        if ($trainingActive && is_array($cabangRow)) {
            $cabangRow['kode_cabang'] = 'TRAINING';
            $cabangRow['alamat'] = $cabangRow['alamat'] ?: 'Mode Training';
        }

        $_SESSION[URL::SESSID]['data'] = array(
            'cabang' => $cabangRow,
            'listCabang' => $this->getCabangOperasional(),
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

        $setting = $this->db(0)->get_where_row('setting', 'id_cabang = ' . $effectiveCabangId);
        $_SESSION[URL::SESSID]['mdl_setting'] = is_array($setting) ? $setting : [];
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
        $allPayments = $this->db(0)->get_where('kas', "ref_transaksi = '$ref_transaksi' AND jenis_transaksi = 7");
        
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

      // PENTING: Bersihkan ref_finance dari timestamp jika ada (untuk menghindari double)
      // ref_finance seharusnya hanya ID transaksi, bukan dengan timestamp
      $clean_ref_finance = $ref_finance;
      if (strpos($ref_finance, '_') !== false) {
         $parts = explode('_', $ref_finance);
         $last_part = end($parts);
         // Jika bagian terakhir adalah timestamp (10 digit angka), ambil hanya ref asli
         if (is_numeric($last_part) && strlen($last_part) == 10) {
            array_pop($parts);
            $clean_ref_finance = implode('_', $parts);
         }
      }

      $where = "ref_finance = '" . $clean_ref_finance . "'";
      if (!$is_public && isset($this->wCabang) && !empty($this->wCabang)) {
         $where = $this->wCabang . " AND " . $where;
      }
          
      $kas = $this->db(0)->get_where_row('kas', $where);
      
      // Update ref_finance dengan yang bersih untuk digunakan selanjutnya
      $ref_finance = $clean_ref_finance;
      if ($kas && $kas['status_mutasi'] == 3) {
         // Kas sudah lunas: tetap retry aktivasi Instant (jt=10) bila Biteship belum dibuat.
         // Tanpa ini, klik "Bayar QRIS" ulang hanya toast sukses tanpa create order.
         $instant = $this->activateInstantKurirPayment($kas);
         $payload = ['status' => 'paid'];
         if (is_array($instant)) {
            $payload['instant_activated'] = !empty($instant['ok']);
            if (!empty($instant['biteship_order_id'])) {
               $payload['biteship_order_id'] = $instant['biteship_order_id'];
            }
            if (empty($instant['ok']) && !empty($instant['message'])) {
               $payload['msg'] = $instant['message'];
            }
         }
         echo json_encode($payload);
         exit();
      } else if ($kas) {
         // Check QR from kas table directly (no longer using wh_tokopay)
         $payment_qr_string = isset($kas['payment_qr_string']) ? $kas['payment_qr_string'] : '';
         $payment_created_at = isset($kas['payment_created_at']) ? $kas['payment_created_at'] : '';
         $payment_state = isset($kas['payment_state']) ? $kas['payment_state'] : '';
         $payment_trx_id = isset($kas['payment_trx_id']) ? $kas['payment_trx_id'] : '';
         
         if (!empty($payment_qr_string)) {
            // Check if QR is older than 5 minutes
            $created_at = !empty($payment_created_at) ? strtotime($payment_created_at) : 0;
            $now = time();
            $diff_minutes = ($now - $created_at) / 60;
            
            if ($diff_minutes < 5) {
               // QR masih fresh, return existing
               echo json_encode([
                  'status' => $payment_state ?: 'pending',
                  'qr_string' => $payment_qr_string,
                  'trx_id' => $ref_finance
               ]);
               exit();
            }
            
            // Jika sudah > 5 menit, cek dulu ke TokoPay apakah benar-benar expired
            if (!empty($payment_trx_id) && $gateway == 'tokopay') {
               $nominal_check = isset($_GET['nominal']) ? intval($_GET['nominal']) : 0;
               if ($nominal_check <= 0 && isset($kas['jumlah'])) {
                  $nominal_check = intval($kas['jumlah']);
               }
               
               if ($nominal_check > 0) {
                  try {
                     // Cek status ke API QRIS
                     $this->helper('QRISApi');
                     $qrisApi = new QRISApi();
                     $res = $qrisApi->checkStatus($payment_trx_id, $nominal_check, 'QRIS');
                     $status_data = is_array($res) ? $res : json_decode($res, true);
                     
                     // Log response untuk debugging
                     if (!$is_public) {
                        $this->model('Log')->write("[payment_gateway_order] TokoPay checkStatus response for ref: $ref_finance, trx_id: $payment_trx_id - Full response: " . json_encode($status_data));
                     }
                     
                     // Format response API terbaru: {status, trx_id, ref_id, payment_status, trx_status}
                     $payment_status = isset($status_data['payment_status']) ? strtolower(trim($status_data['payment_status'])) : '';
                     $status_trx = isset($status_data['trx_status']) ? strtolower(trim($status_data['trx_status'])) : '';
                     $hasValidResponse = (isset($status_data['status']) && $status_data['status'] === true);
                     
                     // Fallback: parsing format lama jika API belum ter-update
                     if (empty($payment_status)) {
                        if (isset($status_data['status_detail']) && !empty($status_data['status_detail'])) {
                           $status_trx = strtolower($status_data['status_detail']);
                           $hasValidResponse = true;
                        } elseif (isset($status_data['data']['status_detail']) && !empty($status_data['data']['status_detail'])) {
                           $status_trx = strtolower($status_data['data']['status_detail']);
                           $hasValidResponse = true;
                        } elseif (isset($status_data['data']['status_pembayaran']) && !empty($status_data['data']['status_pembayaran'])) {
                           $status_trx = strtolower($status_data['data']['status_pembayaran']);
                           $hasValidResponse = true;
                        } elseif (isset($status_data['data']['status']) && !empty($status_data['data']['status'])) {
                           $status_trx = strtolower($status_data['data']['status']);
                           $hasValidResponse = true;
                        } elseif (isset($status_data['status']) && ($status_data['status'] === true || $status_data['status'] === 1)) {
                           $status_trx = 'pending';
                           $hasValidResponse = true;
                        }
                     }
                     
                     $isPaid = ($payment_status === 'paid') || in_array($status_trx, ['success', 'paid', 'settlement', 'capture', 'completed'], true);
                     $isExpired = ($payment_status === 'expired') || in_array($status_trx, ['expired', 'cancelled', 'cancel', 'timeout', 'failed', 'fail'], true);
                     
                     // Log untuk debugging
                     if (!$is_public) {
                        $this->model('Log')->write("[payment_gateway_order] Status check result for ref: $ref_finance - status_trx: $status_trx, isPaid: " . ($isPaid ? 'true' : 'false') . ", isExpired: " . ($isExpired ? 'true' : 'false') . ", hasValidResponse: " . ($hasValidResponse ? 'true' : 'false'));
                     }
                     
                     // VALIDASI KETAT: Generate QR baru HANYA jika semua kondisi terpenuhi:
                     // 1. ✓ Sudah lebih dari 5 menit (sudah dicek sebelumnya)
                     // 2. ✓ Status di database belum success (sudah dicek di awal dengan status_mutasi == 3)
                     // 3. ✓ Status dari TokoPay adalah expired/cancel/failed
                     
                     // LOGIKA UTAMA:
                     // 1. Jika sudah PAID → update database dan return paid
                     if ($isPaid) {
                        // Update kas sebagai paid
                        $update_result = $this->db(0)->update('kas', [
                           'status_mutasi' => 3,
                           'payment_state' => 'paid'
                        ], "ref_finance = '$ref_finance'");
                        
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] Payment already paid in TokoPay for ref: $ref_finance, status: $status_trx, update_result: " . ($update_result['errno'] == 0 ? 'success' : $update_result['error']));
                        }
                        
                        // Update sales state jika perlu
                        if (isset($kas['ref_transaksi'])) {
                           $this->updateSalesState($kas['ref_transaksi']);
                        }
                        $this->activateInstantKurirPayment($kas);
                        
                        // Pastikan exit dengan benar
                        echo json_encode(['status' => 'paid']);
                        exit();
                     }
                     
                     // 2. HANYA jika EXPIRED/FAILED/CANCEL dari TokoPay → generate QR baru
                     if ($isExpired && $hasValidResponse) {
                        // Semua kondisi terpenuhi:
                        // - Sudah lebih dari 5 menit ✓
                        // - Status di database belum success ✓ (sudah dicek di awal)
                        // - Status dari TokoPay adalah expired/cancel/failed ✓
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] VALIDATED: Payment expired/failed in TokoPay for ref: $ref_finance, status: $status_trx - generating new QR");
                        }
                        // Lanjut generate QR baru (tidak exit)
                     } 
                     // 3. Jika masih PENDING atau status lain yang bukan expired → gunakan QR yang ada
                     elseif (!empty($status_trx) && !$isExpired) {
                        // Status masih pending/aktif di TokoPay (belum expired/failed), return QR yang ada
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] Payment still active in TokoPay for ref: $ref_finance, status: $status_trx - returning existing QR");
                        }
                        echo json_encode([
                           'status' => $payment_state ?: 'pending',
                           'qr_string' => $payment_qr_string,
                           'trx_id' => $payment_trx_id ?: $ref_finance
                        ]);
                        exit();
                     } 
                     // 4. Jika response valid tapi tidak ada status detail → anggap pending dan return QR yang ada
                     elseif ($hasValidResponse || (isset($status_data['status']) && $status_data['status'] !== false)) {
                        // Response valid dari API, meskipun tidak ada status detail yang jelas
                        // Anggap pending dan return QR yang ada (TIDAK generate baru karena tidak ada konfirmasi expired)
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] Valid API response but no clear expired status for ref: $ref_finance - returning existing QR. Status: " . ($status_trx ?: 'unknown'));
                        }
                        echo json_encode([
                           'status' => $payment_state ?: 'pending',
                           'qr_string' => $payment_qr_string,
                           'trx_id' => $payment_trx_id ?: $ref_finance
                        ]);
                        exit();
                     }
                     // 5. Jika response tidak valid atau error → TIDAK generate QR baru, return QR yang ada
                     else {
                        // Response tidak valid atau error, TIDAK generate QR baru karena tidak ada konfirmasi expired
                        // Lebih aman return QR yang ada daripada generate baru tanpa konfirmasi
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] Invalid/error TokoPay response for ref: $ref_finance - NOT generating new QR (no expired confirmation). Returning existing QR. Response: " . json_encode($status_data));
                        }
                        // Return QR yang ada, TIDAK generate baru
                        echo json_encode([
                           'status' => $payment_state ?: 'pending',
                           'qr_string' => $payment_qr_string,
                           'trx_id' => $payment_trx_id ?: $ref_finance
                        ]);
                        exit();
                     }
                  } catch (Exception $e) {
                     // Jika terjadi error saat cek TokoPay, TIDAK generate QR baru
                     // Return QR yang ada karena tidak ada konfirmasi expired dari TokoPay
                     if (!$is_public) {
                        $this->model('Log')->write("[payment_gateway_order] Error checking TokoPay status for ref: $ref_finance - " . $e->getMessage() . " - NOT generating new QR (no expired confirmation). Returning existing QR");
                     }
                     // Return QR yang ada, TIDAK generate baru
                     echo json_encode([
                        'status' => $payment_state ?: 'pending',
                        'qr_string' => $payment_qr_string,
                        'trx_id' => $payment_trx_id ?: $ref_finance
                     ]);
                     exit();
                  }
               }
            }
            // Jika sudah > 5 menit tapi tidak ada payment_trx_id atau bukan tokopay
            // TIDAK generate QR baru karena tidak ada konfirmasi expired dari TokoPay
            // Return QR yang ada untuk menghindari generate tanpa validasi
            if (!$is_public) {
               $this->model('Log')->write("[payment_gateway_order] QR > 5 minutes but no payment_trx_id or not tokopay for ref: $ref_finance - NOT generating new QR (no expired confirmation). Returning existing QR");
            }
            echo json_encode([
               'status' => $payment_state ?: 'pending',
               'qr_string' => $payment_qr_string,
               'trx_id' => $payment_trx_id ?: $ref_finance
            ]);
            exit();
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
         // Panggil API QRIS untuk generate QR
         // API menerima ref_id (bersih), API akan generate unique_order_id internally
         $this->helper('QRISApi');
         $qrisApi = new QRISApi();
         $res = $qrisApi->generate($nominal, $ref_finance, 'QRIS');
         $data = is_array($res) ? $res : json_decode($res, true);

         if (isset($data['status']) && $data['status']) {
            // Response format baru API: {status, trx_id, ref_id, qr_string} di root level
            $trx_id = isset($data['trx_id']) ? $data['trx_id'] : $ref_finance . '_' . time();
            $qr_string = isset($data['qr_string']) ? trim($data['qr_string']) : '';
            
            if (empty($qr_string)) {
               if (!$is_public) $this->model('Log')->write("[payment_gateway_order] QR String not found in response");
               echo json_encode(['status' => 'error', 'msg' => 'QR String not found']);
               exit();
            }

            // Update kas dengan payment info (langsung ke tabel kas, tidak ke wh_tokopay)
            $payment_data = [
               'payment_gateway' => $gateway,
               'payment_trx_id' => $trx_id,
               'payment_qr_string' => $qr_string,
               'payment_state' => 'pending',
               'payment_created_at' => date('Y-m-d H:i:s')
            ];
            
            $up_kas = $this->db(0)->update('kas', $payment_data, "ref_finance = '$ref_finance'");
            if ($up_kas['errno'] <> 0) {
               $this->model('Log')->write('[payment_gateway_order] Update Payment Info Error: ' . $up_kas['error']);
               echo json_encode(['status' => 'error', 'msg' => 'Failed to update payment info']);
               exit();
            }

            // PENTING: Jangan cek status paid saat generate QRIS
            // Status 'success' di response generate berarti order berhasil dibuat, BUKAN pembayaran sudah paid
            // Status paid hanya dicek di payment_gateway_status_logic, bukan di sini saat generate
            // Langsung return QR string dengan status pending
            
            echo json_encode([
               'status' => 'pending',
               'qr_string' => $qr_string,
               'trx_id' => $trx_id
            ]);
            exit();
         } else {
            if (!$is_public) $this->model('Log')->write("[payment_gateway_order] API Failed: " . json_encode($data));
            echo json_encode(['status' => 'error', 'msg' => $data]);
            exit();
         }
      } elseif ($gateway == 'midtrans') {
         // Generate unique order_id untuk Midtrans (ref_finance + timestamp)
         $unique_order_id = $ref_finance . '_' . time();
         
         $midtransResponse = $this->model('Midtrans')->createTransaction($unique_order_id, $nominal);
         $data = json_decode($midtransResponse, true);

         if (isset($data['transaction_id'])) {
            $trx_id = $data['transaction_id'];
            $qr_string = isset($data['qr_string']) ? $data['qr_string'] : '';

            if (empty($qr_string)) {
               $this->model('Log')->write("[payment_gateway_order] QR String not found in response");
               echo json_encode(['status' => 'error', 'msg' => 'QR String not found']);
               exit();
            }

            // Update kas dengan payment info (langsung ke tabel kas, tidak ke wh_midtrans)
            $payment_data = [
               'payment_gateway' => $gateway,
               'payment_trx_id' => $trx_id,
               'payment_qr_string' => $qr_string,
               'payment_state' => 'pending',
               'payment_created_at' => date('Y-m-d H:i:s')
            ];
            
            $up_kas = $this->db(0)->update('kas', $payment_data, "ref_finance = '$ref_finance'");
            if ($up_kas['errno'] <> 0) {
               $this->model('Log')->write('[payment_gateway_order] Update Payment Info Error: ' . $up_kas['error']);
               echo json_encode(['status' => 'error', 'msg' => 'Failed to update payment info']);
               exit();
            }

            echo json_encode([
               'status' => $data['status'] ?? 'pending',
               'qr_string' => $qr_string,
               'trx_id' => $trx_id
            ]);
            exit();
         } else {
            if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Midtrans API Failed: " . $midtransResponse);
            echo $midtransResponse;
            exit();
         }
      } else {
         if (!$is_public) $this->model('Log')->write("[payment_gateway_order] Payment Gateway not found");
         echo json_encode(['status' => 'error', 'msg' => 'Payment Gateway not found']);
         exit();
      }
   }

   /**
    * Polling status dari DB saja (tanpa panggil API).
    * Untuk auto-refresh saat QR ditampilkan - webhook akan update kas saat pembayaran diterima.
    */
   public function payment_gateway_status_db($ref_finance, $is_public = false)
   {
      $where = "ref_finance = '" . $ref_finance . "'";
      if (!$is_public && isset($this->wCabang) && !empty($this->wCabang)) {
         $where = $this->wCabang . " AND " . $where;
      }
      
      $kas = $this->db(0)->get_where_row('kas', $where);

      if (!isset($kas['id_kas'])) {
         echo json_encode(['status' => 'ERROR', 'msg' => 'Transaction not found']);
         exit();
      }

      if ($kas['status_mutasi'] == 3) {
         // Saat poll: jika Instant belum ke Biteship, aktifkan di sini (webhook bisa gagal diam-diam).
         $instant = $this->activateInstantKurirPayment($kas);
         $payload = ['status' => 'PAID'];
         if (is_array($instant)) {
            $payload['instant_activated'] = !empty($instant['ok']);
            if (!empty($instant['biteship_order_id'])) {
               $payload['biteship_order_id'] = $instant['biteship_order_id'];
            }
            if (empty($instant['ok']) && !empty($instant['message'])) {
               $payload['msg'] = $instant['message'];
            }
         }
         echo json_encode($payload);
         exit();
      }

      echo json_encode(['status' => 'PENDING']);
      exit();
   }

   public function payment_gateway_status_logic($ref_finance, $is_public = false)
   {
      $where = "ref_finance = '" . $ref_finance . "'";
      if (!$is_public && isset($this->wCabang) && !empty($this->wCabang)) {
         $where = $this->wCabang . " AND " . $where;
      }
      
      $kas = $this->db(0)->get_where_row('kas', $where);

      if (!isset($kas['id_kas'])) {
         echo json_encode(['status' => 'ERROR', 'msg' => 'Transaction not found']);
         exit();
      }

      if ($kas['status_mutasi'] == 3) {
         $instant = $this->activateInstantKurirPayment($kas);
         $payload = ['status' => 'PAID'];
         if (is_array($instant)) {
            $payload['instant_activated'] = !empty($instant['ok']);
            if (!empty($instant['biteship_order_id'])) {
               $payload['biteship_order_id'] = $instant['biteship_order_id'];
            }
            if (empty($instant['ok']) && !empty($instant['message'])) {
               $payload['msg'] = $instant['message'];
            }
         }
         echo json_encode($payload);
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
         // PENTING: Untuk QRIS, cek status HANYA jika QR pernah digenerate (ada payment_trx_id).
         // Tanpa payment_trx_id, order belum pernah dibuat di TokoPay - jangan panggil API
         // karena bisa mengembalikan respons salah dan salah anggap "paid".
         $payment_trx_id = isset($kas['payment_trx_id']) ? trim($kas['payment_trx_id']) : '';
         if (empty($payment_trx_id)) {
            // QR belum pernah digenerate = user belum lihat kode QR, pasti masih pending
            echo json_encode(['status' => 'PENDING', 'msg' => 'Silakan klik tombol untuk melihat kode QR terlebih dahulu']);
            exit();
         }
         // Gunakan payment_trx_id (order_id yang dikirim ke TokoPay), BUKAN ref_finance
         $this->helper('QRISApi');
         $qrisApi = new QRISApi();
         $statusResponse = $qrisApi->checkStatus($payment_trx_id, $kas['jumlah'], 'QRIS');
         $data = is_array($statusResponse) ? $statusResponse : json_decode($statusResponse, true);

         // Format response API terbaru: {status, trx_id, ref_id, payment_status, trx_status}
         $payment_status = isset($data['payment_status']) ? strtolower(trim($data['payment_status'])) : '';
         $trx_status = isset($data['trx_status']) ? strtolower(trim($data['trx_status'])) : '';
         
         // Fallback format lama
         if (empty($payment_status)) {
            $payment_status = isset($data['status_detail']) ? strtolower(trim($data['status_detail'])) : '';
            if (empty($payment_status) && isset($data['data']['status'])) {
               $payment_status = is_string($data['data']['status']) ? strtolower(trim($data['data']['status'])) : '';
            }
         }
         
         $isPaid = ($payment_status === 'paid') || in_array($trx_status ?: $payment_status, ['success', 'paid', 'settlement', 'capture', 'completed'], true);

         if ($isPaid) {
            $update = $this->db(0)->update('kas', ['status_mutasi' => 3], "ref_finance = '$ref_finance'");
            if ($update['errno'] == 0) {
               $this->updateSalesState($kas['ref_transaksi']);
               $this->activateInstantKurirPayment($kas);
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
            $update = $this->db(0)->update('kas', ['status_mutasi' => 3], "ref_finance = '$ref_finance'");
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

   /**
    * Setelah kas QRIS lunas: jika Kurir Instant (jt=10), aktifkan order Biteship via API.
    * @return array|null hasil activate (ok/message/...), atau null jika bukan jt=10
    */
   private function activateInstantKurirPayment($kas)
   {
      $jt = (int) ($kas['jenis_transaksi'] ?? 0);
      if ($jt !== 10) {
         return null;
      }
      $refFinance = trim((string) ($kas['ref_finance'] ?? ''));
      if ($refFinance === '') {
         return ['ok' => false, 'message' => 'ref_finance kosong'];
      }
      try {
         $api = $this->helper('BiteshipApi');
         $res = $api->activate(['ref_finance' => $refFinance]);
         if (!is_array($res)) {
            $res = ['ok' => false, 'message' => 'Respons aktivasi tidak valid'];
         }
         $this->model('Log')->write('[InstantKurir] activate ' . $refFinance . ' ' . json_encode($res));
         return $res;
      } catch (\Throwable $e) {
         $this->model('Log')->write('[InstantKurir] activate err ' . $e->getMessage());
         return ['ok' => false, 'message' => $e->getMessage()];
      }
   }
}
