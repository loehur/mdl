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

      $where = "ref_finance = '" . $ref_finance . "'";
      if (!$is_public && isset($this->wCabang) && !empty($this->wCabang)) {
         $where = $this->wCabang . " AND " . $where;
      }
          
      $kas = $this->db(0)->get_where_row('kas', $where);
      if ($kas && $kas['status_mutasi'] == 3) {
         echo json_encode(['status' => 'paid']);
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
                     
                     // Cek berbagai kemungkinan struktur response TokoPay
                     $status_trx = '';
                     $isPaid = false;
                     $isExpired = false;
                     
                     // Cek apakah response menunjukkan success (bisa berupa boolean true atau string)
                     if (isset($status_data['status'])) {
                        $status_val = $status_data['status'];
                        if ($status_val === true || $status_val === 1 || $status_val === 'true' || strtolower($status_val) === 'success') {
                           // Response menunjukkan success, cek lebih detail di data
                           if (isset($status_data['data'])) {
                              if (isset($status_data['data']['status_pembayaran'])) {
                                 $status_trx = strtolower($status_data['data']['status_pembayaran']);
                              } elseif (isset($status_data['data']['status'])) {
                                 $status_trx = strtolower($status_data['data']['status']);
                              } else {
                                 // Jika status root = success tapi tidak ada detail, anggap paid
                                 $isPaid = true;
                              }
                           } else {
                              // Status root = success dan tidak ada data object, anggap paid
                              $isPaid = true;
                           }
                        }
                     }
                     
                     // Jika belum ketemu, cek di dalam 'data' object
                     if (empty($status_trx) && !$isPaid && isset($status_data['data'])) {
                        if (isset($status_data['data']['status_pembayaran'])) {
                           $status_trx = strtolower($status_data['data']['status_pembayaran']);
                        } elseif (isset($status_data['data']['status'])) {
                           $status_trx = strtolower($status_data['data']['status']);
                        }
                     }
                     
                     // Jika masih belum ketemu, cek di root level
                     if (empty($status_trx) && !$isPaid) {
                        if (isset($status_data['status_pembayaran'])) {
                           $status_trx = strtolower($status_data['status_pembayaran']);
                        } elseif (isset($status_data['status']) && is_string($status_data['status'])) {
                           $status_trx = strtolower($status_data['status']);
                        }
                     }
                     
                     // Cek apakah sudah paid berdasarkan status_trx
                     if (!empty($status_trx) && !$isPaid) {
                        if (in_array($status_trx, ['success', 'paid', 'settlement', 'capture'])) {
                           $isPaid = true;
                        } elseif (in_array($status_trx, ['expired', 'cancelled', 'cancel', 'timeout', 'failed', 'fail'])) {
                           $isExpired = true;
                        }
                     }
                     
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
                        
                        // Pastikan exit dengan benar
                        echo json_encode(['status' => 'paid']);
                        exit();
                     }
                     
                     // 2. Jika EXPIRED/FAILED → generate QR baru
                     if ($isExpired) {
                        // Status expired/failed di TokoPay, lanjut generate QR baru
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] Payment expired/failed in TokoPay for ref: $ref_finance, status: $status_trx - generating new QR");
                        }
                        // Lanjut generate QR baru (tidak exit)
                     } 
                     // 3. Jika masih PENDING atau status lain yang bukan expired → gunakan QR yang ada
                     elseif (!empty($status_trx)) {
                        // Status masih pending/aktif di TokoPay (belum expired/failed), return QR yang ada
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] Payment still active in TokoPay for ref: $ref_finance, status: $status_trx - returning existing QR");
                        }
                        echo json_encode([
                           'status' => $payment_state ?: 'pending',
                           'qr_string' => $payment_qr_string,
                           'trx_id' => $ref_finance
                        ]);
                        exit();
                     } 
                     // 4. Jika tidak ada status atau response tidak valid → generate QR baru (fallback)
                     else {
                        // Response tidak valid atau tidak ada status, log dan lanjut generate QR baru
                        if (!$is_public) {
                           $this->model('Log')->write("[payment_gateway_order] Invalid TokoPay response for ref: $ref_finance - no status found, generating new QR");
                        }
                        // Lanjut generate QR baru (tidak exit)
                     }
                  } catch (Exception $e) {
                     // Jika terjadi error saat cek TokoPay, log dan lanjut generate QR baru
                     if (!$is_public) {
                        $this->model('Log')->write("[payment_gateway_order] Error checking TokoPay status for ref: $ref_finance - " . $e->getMessage() . " - generating new QR");
                     }
                     // Lanjut generate QR baru (tidak exit)
                  }
               }
            }
            // Jika sudah > 5 menit dan tidak ada payment_trx_id atau bukan tokopay, lanjut generate QR baru (tidak exit)
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
         // Generate unique order_id untuk QRIS (ref_finance + timestamp)
         // Ini memungkinkan generate QR baru jika expired, tanpa duplicate order error
         $unique_order_id = $ref_finance . '_' . time();
         
         // Panggil API QRIS untuk generate QR
         $this->helper('QRISApi');
         $qrisApi = new QRISApi();
         $res = $qrisApi->generate($nominal, $unique_order_id, 'QRIS');
         $data = is_array($res) ? $res : json_decode($res, true);

         if (isset($data['status']) && $data['status']) {
            // PENTING: Gunakan unique_order_id yang kita kirim, BUKAN trx_id dari response Tokopay
            // Alasan: Webhook Tokopay akan mengirim reff_id = unique_order_id yang kita kirim
            // Tokopay response trx_id (format TP260112...) berbeda dari reff_id di webhook
            $trx_id = $unique_order_id;
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

            if (isset($data['data']['status']) && (strtolower($data['data']['status']) == 'success' || strtolower($data['data']['status']) == 'paid')) {
               $update = $this->db(0)->update('kas', ['status_mutasi' => 3, 'payment_state' => 'paid'], "ref_finance = '$ref_finance'");
               if ($update['errno'] <> 0) {
                  if (!$is_public) $this->model('Log')->write('[payment_gateway_order] Update Kas Error: ' . $update['error']);
                  echo json_encode(['status' => 'error', 'msg' => 'DB Update Error']);
                  exit();
               }
               
               // Ambil ref_transaksi untuk update state sales
               $kasInfo = $this->db(0)->get_where_row('kas', "ref_finance = '$ref_finance'");
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
         $this->helper('QRISApi');
         $qrisApi = new QRISApi();
         $statusResponse = $qrisApi->checkStatus($ref_finance, $kas['jumlah'], 'QRIS');
         // Convert API response to TokoPay format for compatibility
         if (is_array($statusResponse)) {
            // API returns array, convert to TokoPay format
            $status_detail = isset($statusResponse['status_detail']) ? $statusResponse['status_detail'] : ($statusResponse['status'] ?? 'pending');
            $data = [
               'status' => true,
               'data' => [
                  'status' => $status_detail
               ]
            ];
         } else {
            $data = json_decode($statusResponse, true);
         }

         $isPaid = false;
         if (isset($data['data']['status'])) {
            if (strtolower($data['data']['status']) == 'success' || strtolower($data['data']['status']) == 'paid') {
               $isPaid = true;
            }
         }

         if ($isPaid) {
            $update = $this->db(0)->update('kas', ['status_mutasi' => 3], "ref_finance = '$ref_finance'");
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
}
