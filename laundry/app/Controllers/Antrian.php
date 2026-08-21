<?php

class Antrian extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index($antrian = 1)
   {
      $kas = [];
      $notif = [];
      $notifPenjualan = [];
      $data_main = [];
      $surcas = [];

      switch ($antrian) {
         case 1:
            //DALAM PROSES 10 HARI
            $data_operasi = ['title' => 'Data Order Proses H7-'];
            $viewData = 'antrian/view';
            break;
         case 6:
            //DALAM PROSES > 7 HARI
            $data_operasi = ['title' => 'Data Order Proses H7+'];
            $viewData = 'antrian/view';
            break;
         case 7:
            //DALAM PROSES > 30 HARI dan <= 1 Tahun
            $data_operasi = ['title' => 'Data Order Proses H30+'];
            $viewData = 'antrian/view';
            break;
         case 8:
            //DALAM PROSES > 1 Tahun
            $data_operasi = ['title' => 'Data Order Proses H365+'];
            $viewData = 'antrian/view';
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('antrian/form', [
         'modeView' => $antrian,
      ]);
      $this->view($viewData, [
         'modeView' => $antrian,
         'data_main' => $data_main,
         'kas' => $kas,
         "notif" => $notif,
         'notif_penjualan' => $notifPenjualan,
         "surcas" => $surcas,
      ]);
   }

   public function p($antrian)
   {
      $kas = array();
      $notif = array();
      $notifPenjualan = array();
      $data_main = array();
      $surcas = array();

      switch ($antrian) {
         case 100:
            //DALAM PROSES 10 HARI
            $data_operasi = ['title' => 'Data Piutang'];
            $viewData = 'antrian/view';
            break;
      }

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('antrian/form', [
         'modeView' => $antrian,
      ]);
      $this->view($viewData, [
         'modeView' => $antrian,
         'data_main' => $data_main,
         'kas' => $kas,
         "notif" => $notif,
         'notif_penjualan' => $notifPenjualan,
         "surcas" => $surcas,
      ]);
   }

   public function loadList($antrian)
   {
      $viewData = 'antrian/view_content';
      $orderNewest = ' ORDER BY insertTime DESC, CAST(id_penjualan AS UNSIGNED) DESC';
      $orderOldest = ' ORDER BY insertTime ASC, CAST(id_penjualan AS UNSIGNED) ASC';

      switch ($antrian) {
         case 1:
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) <= (insertTime + INTERVAL 6 DAY)" . $orderNewest;
            break;
         case 6:
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) > (insertTime + INTERVAL 6 DAY) AND DATE(NOW()) <= (insertTime + INTERVAL 30 DAY)" . $orderNewest;
            break;
         case 7:
            // >30 hari sampai 1 tahun
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) > (insertTime + INTERVAL 30 DAY) AND DATE(NOW()) <= (insertTime + INTERVAL 365 DAY)" . $orderNewest;
            break;
         case 8:
            // >1 tahun
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND DATE(NOW()) > (insertTime + INTERVAL 365 DAY)" . $orderNewest;
            break;
         case 100:
            $where = $this->wCabang . " AND id_pelanggan <> 0 AND bin = 0 AND tuntas = 0 AND id_user_ambil <> 0" . $orderOldest;
            break;
      }

      // OPTIMIZED: Single query, extract both keys
      $data_main2 = $this->db(0)->get_where('sale', $where, 'no_ref', 1);
      $this->sortAntrianGroups($data_main2, $antrian != 100);
      $refs = array_keys($data_main2);
      
      // Extract id_penjualan from data_main2 (no duplicate query)
      $numbers = [];
      foreach ($data_main2 as $refBlock) {
         foreach ($refBlock as $row) {
            if (isset($row['id_penjualan'])) $numbers[] = $row['id_penjualan'];
         }
      }

      $operasi = [];
      $kas = [];
      $surcas = [];
      $notif = [];

      // OPTIMIZED: Use implode instead of loop
      if (!empty($refs)) {
         $ref_list = implode(',', $refs);
         $kas = $this->db(0)->get_where('kas', $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi IN ($ref_list)");
         $surcas = $this->db(0)->get_where('surcas', $this->wCabang . " AND no_ref IN ($ref_list)");
         $notif = $this->db(0)->get_where('notif', $this->wCabang . " AND tipe = 1 AND no_ref IN ($ref_list)");
      }

      if (!empty($numbers)) {
         $no_list = "'" . implode("','", $numbers) . "'";
         $operasi = $this->db(0)->get_where('operasi', $this->wCabang . " AND id_penjualan IN ($no_list)");
      }

      $this->view($viewData, [
         'modeView' => $antrian,
         'data_main' => $data_main2,
         'operasi' => $operasi,
         'kas' => $kas,
         'surcas' => $surcas,
         'data_notif' => $notif,
         'karyawan' => $this->userAll,
      ]);
   }

   /** @deprecated Gunakan Estimasi/chat_history — tetap ada sebagai alias JSON. */
   public function chat_history()
   {
      $this->session_cek();
      header('Content-Type: application/json; charset=utf-8');
      $hp = (string) ($_POST['hp'] ?? $_POST['phone'] ?? '');
      try {
         $messages = $this->helper('WaChatHistory')->fetchMessages($this->db(100), $hp, 30);
         echo json_encode(['ok' => 1, 'messages' => $messages]);
      } catch (\Throwable $e) {
         echo json_encode(['ok' => 0, 'messages' => [], 'msg' => $e->getMessage()]);
      }
   }

   public function close_case_request()
   {
      $this->helper('PelangganByPhone');
      $hp = $_POST['hp'];
      $nomor = PelangganByPhone::key($hp);
      if ($nomor === '') {
         echo json_encode(['status' => 'error', 'message' => 'Conversation not found']);
         return;
      }

      $where = PelangganByPhone::likeSql($this->db(100)->escape($nomor), 'wa_number');
      $row = $this->db(100)->get_where_row('wa_conversations', $where);

      if ($row) {
          $cases = json_decode($row['conv_case'] ?? '[]', true);
          $updated = false;
          
          if (is_array($cases)) {
              foreach ($cases as &$c) {
                  // Only close Case 3
                  if (isset($c['case']) && $c['case'] == 3 && isset($c['status']) && $c['status'] === 'open') {
                      $c['status'] = 'closed';
                      unset($c['timestamp']); // Remove timestamp as requested
                      $updated = true;
                  }
              }
          }

          if ($updated) {
              $newJson = json_encode($cases);
              $updateWhere = "id = " . $row['id'];
              $set = ['conv_case' => $newJson];
              
              $res = $this->db(100)->update('wa_conversations', $set, $updateWhere);
              if ($res['errno'] == 0) {
                  // Push to WebSocket
                  $this->pushToWebSocket([
                      'type' => 'case_resolved',
                      'phone' => $row['wa_number'],
                      'case' => 3,
                      'target_id' => '0',
                      'sender_id' => $_SESSION[URL::SESSID]['user']['id_user'] ?? 'system'
                  ]);

                  echo json_encode(['status' => 'success']);
              } else {
                  echo json_encode(['status' => 'error', 'message' => 'DB Update Failed']);
              }
          } else {
              echo json_encode(['status' => 'no_change', 'message' => 'No open case 3 found']);
          }
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Conversation not found']);
      }
   }

   /**
    * Urutkan grup order (per no_ref): terbaru di atas untuk antrian umum, terlama di atas untuk mode ambil (100).
    */
   private function sortAntrianGroups(array &$groups, bool $newestFirst): void
   {
      if (count($groups) < 2) {
         return;
      }

      uasort($groups, function ($a, $b) use ($newestFirst) {
         $maxInsertTime = function ($block) {
            $latest = '';
            foreach ($block as $row) {
               $t = $row['insertTime'] ?? '';
               if ($t !== '' && strcmp($t, $latest) > 0) {
                  $latest = $t;
               }
            }
            return $latest;
         };

         $maxIdPenjualan = function ($block) {
            $max = 0;
            foreach ($block as $row) {
               $id = (int) ($row['id_penjualan'] ?? 0);
               if ($id > $max) {
                  $max = $id;
               }
            }
            return $max;
         };

         $cmp = strcmp($maxInsertTime($a), $maxInsertTime($b));
         if ($cmp === 0) {
            $cmp = $maxIdPenjualan($a) <=> $maxIdPenjualan($b);
         }

         return $newestFirst ? -$cmp : $cmp;
      });
   }

   private function pushToWebSocket($data)
   {
       $url = 'http://127.0.0.1:3003/incoming';
       
       $ch = curl_init($url);
       curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_POST, true);
       curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
       curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
       curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
       curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
       curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
       curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

       $result = curl_exec($ch);
       curl_close($ch);
       return $result;   
   }

   public function clearTuntas()
   {
      if (isset($_POST['data'])) {
         $data = unserialize($_POST['data']);

         foreach ($data as $a) {
            $this->tuntasOrder($a);
         }
      } else {

      }
   }

   public function operasi()
   {
      $karyawan = $_POST['f1'];
      $penjualan = $_POST['f2'];
      $operasi = $_POST['f3'];

      // Get sale data
      $sale = $this->db(0)->get_where_row('sale', "id_penjualan = '$penjualan'");
      if (!$sale) {
         $this->model('Log')->write("[operasi] ERROR: Sale data not found - ID: " . $penjualan);
         echo "Error: Sale data tidak ditemukan";
         exit();
      }

      // Hanya layanan terakhir yang pakai rak + WA notif selesai
      $listLayanan = @unserialize($sale['list_layanan'] ?? '');
      if (!is_array($listLayanan) || count($listLayanan) < 1) {
         $this->model('Log')->write("[operasi] ERROR: list_layanan invalid - ID: " . $penjualan);
         echo "Error: Data layanan tidak valid";
         exit();
      }
      $endLayanan = end($listLayanan);
      $isEndLayanan = ((string) $operasi === (string) $endLayanan);
      $this->model('Log')->write("[operasi] Layanan check - ID: " . $penjualan . " | Operasi: " . $operasi . " | End: " . $endLayanan . " | isEnd: " . ($isEndLayanan ? '1' : '0'));

      $hp = '';
      $text = '';
      if ($isEndLayanan) {
         $id_pelanggan = $sale['id_pelanggan'];
         $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '$id_pelanggan'");
         if (!$pelanggan) {
            $this->model('Log')->write("[operasi] ERROR: Customer data not found - ID Pelanggan: " . $id_pelanggan);
            echo "Error: Data pelanggan tidak ditemukan";
            exit();
         }
         $hp = $pelanggan['nomor_pelanggan'];

         // NOTE: empty() treats 0/"0" as empty; we still want the process to continue
         // even if phone is "0". Only block when truly missing/blank.
         if ($hp === null || trim((string) $hp) === '') {
            $this->model('Log')->write("[operasi] ERROR: Customer phone empty - ID Pelanggan: " . $id_pelanggan);
            echo "Error: Nomor HP pelanggan kosong";
            exit();
         }

         $waGen = $this->helper('WAGenerator');
         $jsonText = $waGen->get_selesai_text($penjualan, $karyawan);
         $objText = json_decode($jsonText, true);
         $text = $objText['text'] ?? "";

         if (empty($text)) {
            $this->model('Log')->write("[operasi] ERROR: Generated text empty - ID Penjualan: " . $penjualan . " | Karyawan: " . $karyawan . " | JSON: " . $jsonText);
            echo "Error: Text notifikasi kosong";
            exit();
         }
      }

      // ===== START TRANSACTION =====
      // Insert operasi (dan notif jika layanan terakhir) harus atomic
      if (!$this->db(0)->beginTransaction()) {
         $this->model('Log')->write("[operasi] CRITICAL: Failed to start transaction for: " . $penjualan);
         echo json_encode(['error' => 1, 'msg' => 'Gagal memulai transaction']);
         exit();
      }
      $this->model('Log')->write("[operasi] Transaction started for: " . $penjualan);

      try {
         $setOne = "id_penjualan = '" . $penjualan . "' AND jenis_operasi = " . $operasi;
         $where = $this->wCabang . " AND " . $setOne;

         $data_main = $this->db(0)->count_where('operasi', $where);

         $operasiInserted = false;
         if ($data_main < 1) {
            $data = [
               'id_operasi' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9),
               'id_cabang' => $this->id_cabang,
               'id_penjualan' => $penjualan,
               'jenis_operasi' => $operasi,
               'id_user_operasi' => $karyawan,
               'insertTime' => $GLOBALS['now']
            ];
            $in = $this->db(0)->insert('operasi', $data);
            if ($in['errno'] <> 0) {
               throw new Exception("Insert Operasi Error: " . $in['error']);
            }
            $operasiInserted = true;
            $this->model('Log')->write("[operasi] Insert Operasi Success - ID: " . $data['id_operasi']);
         } else {
            $this->model('Log')->write("[operasi] Operasi already exists: " . $penjualan . " - " . $operasi);
         }

         // Notif selesai hanya saat penyelesaian layanan terakhir
         $notifInserted = false;
         if ($isEndLayanan) {
            $time = date('Y-m-d H:i:s');
            $whereNotif = $this->wCabang . " AND no_ref = '" . $penjualan . "' AND tipe = 2";
            $data_main = $this->db(0)->count_where('notif', $whereNotif);

            $this->model('Log')->write("[operasi] Check existing notif - Where: " . $whereNotif . " | Count: " . $data_main);

            if ($data_main < 1) {
               $notifData = [
                  'id_notif' => (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9),
                  'insertTime' => $time,
                  'id_cabang' => $this->id_cabang,
                  'no_ref' => $penjualan,
                  'phone' => $hp,
                  'text' => $text,
                  'state' => 'pending',
                  'tipe' => 2
               ];

               $this->model('Log')->write("[operasi] Attempting insert notif - Phone: " . $hp . " | Ref: " . $penjualan);

               $do = $this->db(0)->insert('notif', $notifData);
               if ($do['errno'] <> 0) {
                  throw new Exception("Insert Notif Error: " . $do['error']);
               }
               $notifInserted = true;
               $this->model('Log')->write("[operasi] Insert Notif Success - ID: " . $notifData['id_notif'] . " | Phone: " . $hp . " | State: pending");
            } else {
               $this->model('Log')->write("[operasi] WARNING: Notif already exists - skipped insert for: " . $penjualan);
            }
         } else {
            $this->model('Log')->write("[operasi] Skip notif (bukan layanan terakhir) for: " . $penjualan . " - operasi: " . $operasi);
         }

         // ===== COMMIT TRANSACTION =====
         if (!$this->db(0)->commit()) {
            throw new Exception("Failed to commit transaction");
         }
         $this->model('Log')->write("[operasi] Transaction committed successfully - Operasi: " . ($operasiInserted ? 'inserted' : 'skipped') . " | Notif: " . ($notifInserted ? 'inserted' : 'skipped'));

      } catch (Exception $e) {
         // ===== ROLLBACK TRANSACTION =====
         $rollbackSuccess = $this->db(0)->rollback();
         $error_msg = "[operasi] CRITICAL: Transaction FAILED and " . ($rollbackSuccess ? "ROLLED BACK" : "ROLLBACK FAILED") . " - Error: " . $e->getMessage() . " | Ref: " . $penjualan;
         $this->model('Log')->write($error_msg);

         echo json_encode(['error' => 1, 'msg' => 'Gagal menyimpan data: ' . $e->getMessage()]);
         exit();
      }

      // Rak + kirim WA hanya untuk layanan terakhir
      if ($isEndLayanan && isset($_POST['rak']) && strlen((string) $_POST['rak']) > 0) {
         $rak = $_POST['rak'];
         $pack = $_POST['pack'] ?? 1;
         $hanger = $_POST['hanger'] ?? 0;
         $set = ['letak' => $rak, 'pack' => $pack, 'hanger' => $hanger];
         $where = $this->wCabang . " AND id_penjualan = '" . $penjualan . "'";
         $upResult = $this->db(0)->update('sale', $set, $where);

         if ($upResult['errno'] <> 0) {
            $this->model('Log')->write("[operasi] ERROR: Update rak failed - " . $upResult['error']);
         } else {
            $this->model('Log')->write("[operasi] Update rak success - Rak: " . $rak . " | Pack: " . $pack . " | Hanger: " . $hanger);
         }

         $setOne = "no_ref = '" . $penjualan . "' AND tipe = 2 AND (state = 'pending' || state = 'queue')";
         $data_main = $this->db(0)->count_where('notif', $setOne);

         $this->model('Log')->write("[operasi] Check notif ready to send - Count: " . $data_main . " | Expected: 1");

         if ($data_main == 1) {
            $this->model('Log')->write("[operasi] Calling notifReadySend for: " . $penjualan);
            $this->notifReadySend($penjualan);
         } else {
            $this->model('Log')->write("[operasi] WARNING: Notif not ready or not found - Count: " . $data_main);
         }
      } elseif (!$isEndLayanan) {
         $this->model('Log')->write("[operasi] Skip rak/WA (bukan layanan terakhir) for: " . $penjualan);
      } else {
         $this->model('Log')->write("[operasi] WARNING: Rak kosong, skip notifReadySend for: " . $penjualan);
      }

      // Semua item terikat request Antar non-pending sudah selesai → notif group Delivery
      if ($isEndLayanan) {
         try {
            $this->helper('FonnteService');
            $this->helper('DeliverySiapGroupNotify');
            DeliverySiapGroupNotify::maybeNotify($this->db(0), $this->model('Log'), (int) $penjualan);
         } catch (\Throwable $e) {
            $this->model('Log')->write('[operasi] DeliverySiapGroupNotify: ' . $e->getMessage());
         }
      }

      echo 0;
   }

   public function surcas()
   {
      $jenis = (int) ($_POST['surcas'] ?? 0);
      $jumlah = $_POST['jumlah'];
      $user = $_POST['user'];
      $id_transaksi = $_POST['no_ref'];

      // Jenis 1/2/3 (jemput/antar/gabungan) tidak lagi ditambah dari Operasi
      if (in_array($jenis, [1, 2, 3], true)) {
         echo 'Jenis surcas ini tidak bisa ditambah dari Operasi';
         exit();
      }

      $setOne = "transaksi_jenis = 1 AND no_ref = " . $id_transaksi . " AND id_jenis_surcas = " . $jenis;
      $where = $this->wCabang . " AND " . $setOne;
      $data_main = $this->db(0)->count_where('surcas', $where);

      
      if ($data_main < 1) {
         $data = [
            'id_cabang' => $this->id_cabang,
            'transaksi_jenis' => 1,
            'id_jenis_surcas' => $jenis,
            'jumlah' => $jumlah,
            'id_user' => $user,
            'no_ref' => $id_transaksi
         ];
             $in = $this->db(0)->insert('surcas', $data);
             if ($in['errno'] <> 0) {
                $this->model('Log')->write("[surcas] Insert Surcas Error: " . $in['error']);
                echo $in['error'];
                exit();
             }
      }
      echo 0;
   }

   public function updateRak($mode = 0)
   {
      $rak = $_POST['value'];
      $id = $_POST['id'];
      $totalNotif = $_POST['totalNotif'];
      
      switch ($mode) {
         case 0:
            $set = ['letak' => $rak];
            break;
         case 1:
            $set = ['pack' => $rak];
            break;
         case 2:
            $set = ['hanger' => $rak];
            break;
         default:
            $set = ['letak' => $rak];
            break;
      }
      $where = $this->wCabang . " AND id_penjualan = '" . $id . "'";
      $this->db(0)->update('sale', $set, $where);

      //CEK SUDAH TERKIRIM BELUM
      $setOne = "no_ref = '" . $id . "' AND state = 'queue'";
      $where = $setOne;
      $data_main = $this->db(0)->count_where('notif', $where);
      if ($data_main == 1) {
         $this->notifReadySend($id);
      }
   }

   public function tuntasOrder($ref)
   {
      $ref = trim((string) $ref);
      if ($ref === '' || !$this->refEligibleTuntas($ref)) {
         return;
      }

      $db = $this->db(0);
      $refSql = "'" . $db->escape($ref) . "'";
      $set = ['tuntas' => 1, 'tuntasTime' => $GLOBALS['now'] ?? date('Y-m-d H:i:s')];
      $where = $this->wCabang . " AND no_ref = $refSql AND bin = 0 AND tuntas = 0";
      $db->update('sale', $set, $where);
      $this->hapusKasPembayaranPengecekanOrder($ref);
   }

   public function notifReadySend($idPenjualan)
   {
      $setOne = "no_ref = '" . $idPenjualan . "' AND tipe = 2";
      $where = $this->wCabang . " AND " . $setOne;
      $dm = $this->db(0)->get_where_row('notif', $where);
      
      if (!$dm) {
         $this->model('Log')->write("[notifReadySend] WARNING: Notif tidak ditemukan - ID: " . $idPenjualan);
         return;
      }
      
      // Check state to prevent duplicate sends
      $currentState = $dm['state'] ?? '';
      
      // Skip if already sent or currently processing
      if (in_array($currentState, ['sent', 'processing'])) {
         $this->model('Log')->write("[notifReadySend] WARNING: Notif sudah terkirim atau sedang diproses - ID: " . $idPenjualan . " | State: " . $currentState);
         return;
      }
      
      // Set state to 'processing' as a lock before sending
      $lockSet = ['state' => 'processing'];
      $lockResult = $this->db(0)->update('notif', $lockSet, $where);
      
      if ($lockResult['errno'] <> 0) {
         $this->model('Log')->write("[notifReadySend] ERROR: Gagal lock notif - ID: " . $idPenjualan . " | Error: " . $lockResult['error']);
         return;
      }
      
      $hp = $dm['phone'];
      $text = $dm['text'];
      
      // Validate phone and text
      if (empty($hp)) {
         $this->model('Log')->write("[notifReadySend] ERROR: Phone number empty - ID: " . $idPenjualan);
         // Set back to pending
         $this->db(0)->update('notif', ['state' => 'pending'], $where);
         return;
      }
      
      if (empty($text)) {
         $this->model('Log')->write("[notifReadySend] ERROR: Text empty - ID: " . $idPenjualan);
         // Set back to pending
         $this->db(0)->update('notif', ['state' => 'pending'], $where);
         return;
      }
      
      $this->model('Log')->write("[notifReadySend] Sending WA - ID: " . $idPenjualan . " | Phone: " . $hp);
      
      // Text sudah final dari WAGenerator, tidak perlu replace lagi
      $res = $this->helper('Notif')->send_wa($hp, $text, 'free');

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');

      $where2 = $this->wCabang . " AND no_ref = '" . $idPenjualan . "' AND tipe = 2";
      if ($res['status']) {
         $set = ['state' => 'sent', 'id_api' => $idApi];
         $updateResult = $this->db(0)->update('notif', $set, $where2);
         
         if ($updateResult['errno'] <> 0) {
            $this->model('Log')->write("[notifReadySend] ERROR: Update notif to sent failed - ID: " . $idPenjualan . " | Error: " . $updateResult['error']);
         } else {
            $this->model('Log')->write("[notifReadySend] SUCCESS: WA sent - ID: " . $idPenjualan . " | API ID: " . $idApi . " | Phone: " . $hp);
            $this->helper('Notif')->deleteMatchingWaOutQueue($hp, $text);
         }
      } else {
         $errorMsg = $res['message'] ?? $res['error'] ?? 'Unknown error';
         $this->model('Log')->write("[notifReadySend] ERROR: WA send failed - ID: " . $idPenjualan . " | Phone: " . $hp . " | Error: " . $errorMsg);
         
         $set = ['state' => 'pending'];
         $updateResult = $this->db(0)->update('notif', $set, $where2);
         
         if ($updateResult['errno'] <> 0) {
            $this->model('Log')->write("[notifReadySend] ERROR: Update notif back to pending failed - ID: " . $idPenjualan . " | Error: " . $updateResult['error']);
         }
      }
   }

   public function sendNotif($countMember, $tipe)
   {
      $hp = $_POST['hp'];
      $noref = $_POST['ref'];
      $time =  $_POST['time'];

      $waGen = $this->helper('WAGenerator');
      $jsonText = $waGen->get_nota($noref);
      $objText = json_decode($jsonText, true);
      $text = $objText['text'] ?? "";

      // FIX: Close session before long-running WA operation to prevent blocking other requests
      if (session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      // Cek apakah no HP ada di tabel user dengan berbagai kemungkinan format
      $hpVariations = [];
      $hpClean = preg_replace('/[^0-9]/', '', $hp); // Hapus karakter non-angka
      
      // Buat variasi nomor: +628xxx, 628xxx, 08xxx, 8xxx
      if (substr($hpClean, 0, 2) === '62') {
         // Jika dimulai dengan 62
         $hpVariations[] = "'+62" . substr($hpClean, 2) . "'";
         $hpVariations[] = "'" . $hpClean . "'";
         $hpVariations[] = "'0" . substr($hpClean, 2) . "'";
         $hpVariations[] = "'" . substr($hpClean, 2) . "'";
      } elseif (substr($hpClean, 0, 1) === '0') {
         // Jika dimulai dengan 0
         $hpVariations[] = "'+62" . substr($hpClean, 1) . "'";
         $hpVariations[] = "'62" . substr($hpClean, 1) . "'";
         $hpVariations[] = "'" . $hpClean . "'";
         $hpVariations[] = "'" . substr($hpClean, 1) . "'";
      } else {
         // Jika dimulai dengan 8
         $hpVariations[] = "'+62" . $hpClean . "'";
         $hpVariations[] = "'62" . $hpClean . "'";
         $hpVariations[] = "'0" . $hpClean . "'";
         $hpVariations[] = "'" . $hpClean . "'";
      }

      $whereUser = "no_user IN (" . implode(', ', $hpVariations) . ")";
      $userExists = $this->db(0)->count_where('user', $whereUser);

      // Template WA: hanya jika nomor pelanggan belum pernah ada di wa_messages_out (satu nomor = no_pelanggan / $_POST['hp'])
      $this->helper('PelangganByPhone');
      $matchDigitsWa = PelangganByPhone::key($hpClean);
      $whereWaOut = PelangganByPhone::likeSql($this->db(100)->escape($matchDigitsWa), 'phone');
      $waOutCount = $this->db(100)->count_where('wa_messages_out', $whereWaOut);
      $waOutExists = is_numeric($waOutCount) ? (int) $waOutCount : 0;
      
      // Check if notification already exists to prevent duplicate sends
      $setOne = "no_ref = '" . $noref . "' AND tipe = 1";
      $where = $this->wCabang . " AND " . $setOne;
      $existingNotif = $this->db(0)->count_where('notif', $where);
      
      if ($existingNotif > 0) {
         // Notification already sent, skip sending again
         $this->model('Log')->write("[sendNotif] WARNING: Notif already exists, skipped sending - Ref: " . $noref . " | HP: " . $hp);
         echo json_encode(['status' => 'exists', 'message' => 'Notifikasi sudah pernah dikirim']);
         return;
      }
      
      // INSERT PENDING RECORD FIRST as distributed lock to prevent race condition
      // This ensures that if another request comes in before WA is sent, it will see this record
      $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
      $pendingVals = [
         'id_notif' => $id_notif,
         'insertTime' => $time,
         'id_cabang' => $this->id_cabang,
         'no_ref' => $noref,
         'phone' => $hp,
         'text' => $text,
         'tipe' => $tipe,
         'id_api' => '',
         'state' => 'pending'
      ];
      
      $insertResult = $this->db(0)->insert('notif', $pendingVals);
      if ($insertResult['errno'] <> 0) {
         // Insert failed (might be duplicate key if another request just inserted)
         $this->model('Log')->write("[sendNotif] WARNING: Insert pending failed - likely duplicate - Ref: " . $noref . " | Error: " . $insertResult['error']);
         echo json_encode(['status' => 'exists', 'message' => 'Notifikasi sedang diproses']);
         return;
      }
      
      // User internal → free. Sudah pernah outbound ke nomor ini di wa_messages_out → free (bukan template). Selain itu → template nota.
      if ($userExists > 0) {
         $template_name = 'free';
      } elseif ($waOutExists > 0) {
         $this->model('Log')->write("[sendNotif] wa_messages_out sudah ada untuk nomor pelanggan, pakai free bukan template — Ref: " . $noref . " | HP: " . $hp);
         $template_name = 'free';
      } else {
         $template_name = URL::TEMPLATE_NOTA;
      }
      $res = $this->helper('Notif')->send_wa($hp, $jsonText, $template_name);

      // Mode free hanya boleh jika CSW terbuka (cek di API: wa_conversations.last_in_at).
      // Jika CSW tertutup, API bisa mengembalikan 400 + csw_expired.
      // Fallback template HANYA jika nomor belum pernah ada di wa_messages_out ($waOutExists === 0).
      // Jika kita sengaja pakai free karena wa_messages_out sudah ada, jangan kirim template (sesuai kebijakan bisnis).
      if (!$res['status'] && $template_name === 'free') {
         $apiPayload = $res['data'] ?? [];
         $cswExpired = !empty($apiPayload['data']['csw_expired'])
            || (isset($apiPayload['message']) && stripos((string) $apiPayload['message'], 'CSW') !== false)
            || (isset($apiPayload['message']) && stripos((string) $apiPayload['message'], 'Customer Service Window') !== false)
            || (isset($res['error']) && stripos((string) $res['error'], '24 jam') !== false);
         if ($cswExpired) {
            if ($waOutExists > 0) {
               $this->model('Log')->write("[sendNotif] Free ditolak (CSW tertutup), TIDAK fallback template — nomor sudah pernah di wa_messages_out — Ref: " . $noref . " | HP: " . $hp);
            } else {
               $this->model('Log')->write("[sendNotif] Free ditolak (CSW tertutup), fallback template — Ref: " . $noref . " | HP: " . $hp);
               $res = $this->helper('Notif')->send_wa($hp, $jsonText, URL::TEMPLATE_NOTA);
            }
         }
      }

      $apiData = $res['data']['data'] ?? $res['data'] ?? [];
      $idApi = $apiData['id'] ?? ($apiData['message_id'] ?? '');

      // Update the record with WA API result
      if ($res['status']) {
         $updateVals = [
            'id_api' => $idApi,
            'state' => 'sent'
         ];
         $this->db(0)->update('notif', $updateVals, $where);
         $this->helper('Notif')->deleteMatchingWaOutQueue($hp, $text);
         echo 0;
      } else {
         // WA send failed — tetap pending untuk retry; tanpa alert di UI (response sama seperti sukses agar loadDiv refresh)
         $updateVals = [
            'state' => 'pending'
         ];
         $this->db(0)->update('notif', $updateVals, $where);
         $errorMsg = $res['error'] ?? ($res['message'] ?? 'Gagal mengirim WA');
         $this->model('Log')->write("[sendNotif] WA gagal (state pending, UI diam) — Ref: " . $noref . " | HP: " . $hp . " | " . $errorMsg);
         echo 0;
      }
   }

   public function ambil()
   {
      $karyawan = $_POST['f1'];
      $id = $_POST['f2'];

      $dateNow = date('Y-m-d H:i:s');
      $set = ['tgl_ambil' => $dateNow, 'id_user_ambil' => $karyawan];

      // Jika rak (letak) masih kosong, isi dengan 00
      $setOne = "id_penjualan = '" . $id . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $row = $this->db(0)->get_where_row('sale', $where);
      if ($row && (trim($row['letak'] ?? '') === '')) {
         $set['letak'] = '00';
      }

       $up = $this->db(0)->update('sale', $set, $where);
       if ($up['errno'] <> 0) {
          $this->model('Log')->write("[ambil] Update Sale (Ambil) Error: " . $up['error']);
          echo $up['error'];
      } else {
         echo 0;
      }
   }

   public function hapusRef()
   {
      header('Content-Type: application/json; charset=utf-8');

      $ref = trim((string) ($_POST['ref'] ?? ''));
      $note = trim((string) ($_POST['note'] ?? ''));

      if ($ref === '') {
         echo json_encode(['status' => 'error', 'message' => 'REF tidak valid']);
         return;
      }

      if ($note === '') {
         echo json_encode(['status' => 'error', 'message' => 'Alasan hapus wajib diisi']);
         return;
      }

      $refEsc = $this->db(0)->escape($ref);
      $where = $this->wCabang . " AND no_ref = '" . $refEsc . "' AND bin = 0";
      $count = (int) ($this->db(0)->count_where('sale', $where) ?? 0);
      if ($count <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Nota tidak ditemukan atau sudah dihapus']);
         return;
      }

      /** @var HapusRefAiGuard $guard */
      $guard = $this->helper('HapusRefAiGuard');
      $ai = $guard->validate($note);

      if (empty($ai['ok'])) {
         echo json_encode([
            'status' => 'error',
            'message' => $ai['message'] ?? 'Validasi AI gagal',
            'ai' => $ai,
         ]);
         return;
      }

      if (empty($ai['allowed'])) {
         echo json_encode([
            'status' => 'rejected',
            'message' => $ai['message'] ?? 'Alasan tidak memenuhi syarat hapus nota',
            'alternatives' => $ai['alternatives'] ?? [],
         ]);
         return;
      }

      $up = $this->db(0)->update(
         'sale',
         ['bin' => 1, 'bin_note' => $note],
         $this->wCabang . " AND no_ref = '" . $refEsc . "' AND bin = 0"
      );

      if (isset($up['errno']) && (int) $up['errno'] !== 0) {
         echo json_encode(['status' => 'error', 'message' => $up['error'] ?? 'Gagal menghapus nota']);
         return;
      }

      $this->model('Log')->write('[Antrian::hapusRef] ref=' . $ref . ' note=' . mb_substr($note, 0, 200));
      echo json_encode(['status' => 'success', 'message' => 'Nota #' . $ref . ' diantrekan hapus']);
   }

   public function restoreRef()
   {
      $ref = $_POST['ref'];

      $setOne = "no_ref = '" . $ref . "'";
      $where = $this->wCabang . " AND " . $setOne;
      $set = ['bin' => 0];
      $this->db(0)->update('sale', $set, $where);
   }
}
