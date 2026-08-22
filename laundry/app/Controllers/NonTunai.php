<?php

class NonTunai extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $limit = 30;
      $view = 'non_tunai/nt_main';
      $cols = "id_cabang, ref_finance, MAX(ref_transaksi) AS ref_transaksi, note, id_user, id_client, status_mutasi, jenis_transaksi, SUM(jumlah) AS total, MIN(insertTime) AS insertTime, MAX(IFNULL(payment_trx_id, '')) AS payment_trx_id";
      $where = $this->wCabangAll() . " AND metode_mutasi = 2 AND status_mutasi = 2 AND ref_finance <> '' GROUP BY id_cabang, ref_finance ORDER BY insertTime DESC LIMIT $limit";
      $cek = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      if (!is_array($cek)) {
         $cek = [];
      }

      $this->view($view, [
         'cek' => $cek,
         'pelangganById' => $this->loadPelangganMapForNonTunai($cek),
      ]);
   }

   /**
    * Nama pelanggan lintas cabang — tidak bergantung session cabang aktif.
    *
    * @param list<array<string,mixed>> $rows
    * @return array<int,array<string,mixed>>
    */
   private function loadPelangganMapForNonTunai(array $rows): array
   {
      $ids = [];
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $jenis = (int) ($row['jenis_transaksi'] ?? 0);
         if (!in_array($jenis, [1, 3, 6], true)) {
            continue;
         }
         $id = (int) ($row['id_client'] ?? 0);
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }

      if ($ids === []) {
         return [];
      }

      $in = implode(',', array_values($ids));
      $map = $this->db(0)->get_where('pelanggan', "id_pelanggan IN ($in)", 'id_pelanggan');

      return is_array($map) ? $map : [];
   }

   /**
    * JSON: daftar mutasi BCA CR belum ter-link (6 hari terakhir + PEND).
    */
   public function mutasiList()
   {
      header('Content-Type: application/json; charset=utf-8');

      $refFinance = trim((string) ($_POST['id'] ?? $_GET['id'] ?? ''));
      if ($refFinance === '') {
         echo json_encode(['ok' => false, 'message' => 'ref_finance wajib']);
         return;
      }

      $idEsc = $this->db(0)->escape($refFinance);
      $wc = $this->wCabangForApprovalAction('kas', 'ref_finance', $refFinance);
      if ($wc === null) {
         echo json_encode(['ok' => false, 'message' => 'Transaksi tidak ditemukan']);
         return;
      }
      $where = $wc . " AND ref_finance = '" . $idEsc . "'";
      $kas = $this->db(0)->get_where_row('kas', $where);
      if (!$kas || empty($kas['id_kas'])) {
         echo json_encode(['ok' => false, 'message' => 'Transaksi tidak ditemukan']);
         return;
      }

      if (strtoupper(trim((string) ($kas['note'] ?? ''))) !== 'BCA') {
         echo json_encode(['ok' => false, 'message' => 'Bukan pembayaran BCA']);
         return;
      }

      $cols = "SUM(jumlah) AS total";
      $agg = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      $total = is_array($agg) && isset($agg[0]['total']) ? (float) $agg[0]['total'] : (float) ($kas['jumlah'] ?? 0);

      $this->helper('BcaMutasiBind');
      $dbMain = $this->db(100);

      try {
         $dbMain->query('SELECT 1 FROM bca_mutasi LIMIT 1');
         $dbMain->query('SELECT 1 FROM bca_mutasi_link LIMIT 1');
      } catch (\Throwable $e) {
         echo json_encode(['ok' => false, 'message' => 'Tabel bca_mutasi belum tersedia. Jalankan migration main.']);
         return;
      }

      $range = BcaMutasiBind::listRange();
      $rows = BcaMutasiBind::listUnlinkedCr($dbMain, $range['start'], $range['end']);
      $kasNominal = BcaMutasiBind::formatNominal($total);

      $items = [];
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $nom = BcaMutasiBind::formatNominal($row['nominal'] ?? 0);
         if (!BcaMutasiBind::isNominalWithinTolerance($kasNominal, $nom)) {
            continue;
         }
         $selisih = BcaMutasiBind::nominalDiff($kasNominal, $nom);
         $ket = trim((string) ($row['keterangan'] ?? ''));
         $ketShort = $ket;
         if (strlen($ketShort) > 120) {
            $ketShort = substr($ketShort, 0, 117) . '…';
         }
         $isPend = strtoupper(trim((string) ($row['tanggal'] ?? ''))) === 'PEND';
         $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'tanggal' => (string) ($row['tanggal'] ?? ''),
            'tanggal_iso' => (string) ($row['tanggal_iso'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'is_pend' => $isPend,
            'nominal' => $nom,
            'nominal_fmt' => number_format((float) $nom, 0, ',', '.'),
            'keterangan' => $ketShort,
            'keterangan_full' => $ket,
            'nominal_match' => ($nom === $kasNominal),
            'selisih' => $selisih,
            'selisih_fmt' => number_format($selisih, 0, ',', '.'),
         ];
      }

      usort($items, static function ($a, $b) {
         $am = !empty($a['nominal_match']) ? 0 : 1;
         $bm = !empty($b['nominal_match']) ? 0 : 1;
         if ($am !== $bm) {
            return $am <=> $bm;
         }
         $as = (float) ($a['selisih'] ?? 0);
         $bs = (float) ($b['selisih'] ?? 0);
         if ($as !== $bs) {
            return $as <=> $bs;
         }
         $ap = !empty($a['is_pend']) ? 0 : 1;
         $bp = !empty($b['is_pend']) ? 0 : 1;
         if ($ap !== $bp) {
            return $ap <=> $bp;
         }
         $aDate = !empty($a['tanggal_iso']) ? (string) $a['tanggal_iso'] : substr((string) ($a['created_at'] ?? ''), 0, 10);
         $bDate = !empty($b['tanggal_iso']) ? (string) $b['tanggal_iso'] : substr((string) ($b['created_at'] ?? ''), 0, 10);
         return strcmp($bDate, $aDate);
      });

      echo json_encode([
         'ok' => true,
         'ref_finance' => $refFinance,
         'kas_nominal' => $kasNominal,
         'kas_nominal_fmt' => number_format((float) $kasNominal, 0, ',', '.'),
         'nominal_tolerance' => BcaMutasiBind::NOMINAL_TOLERANCE,
         'nominal_tolerance_fmt' => number_format(BcaMutasiBind::NOMINAL_TOLERANCE, 0, ',', '.'),
         'range' => array_merge($range, ['pend_start' => BcaMutasiBind::pendLookbackStart()]),
         'count' => count($items),
         'items' => $items,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   /**
    * JSON: daftar transaksi QRIS merchant belum ter-link (6 hari terakhir).
    * Hanya untuk QRIS static (tanpa payment_trx_id / TokoPay).
    */
   public function qrisList()
   {
      header('Content-Type: application/json; charset=utf-8');

      $refFinance = trim((string) ($_POST['id'] ?? $_GET['id'] ?? ''));
      if ($refFinance === '') {
         echo json_encode(['ok' => false, 'message' => 'ref_finance wajib']);
         return;
      }

      $idEsc = $this->db(0)->escape($refFinance);
      $wc = $this->wCabangForApprovalAction('kas', 'ref_finance', $refFinance);
      if ($wc === null) {
         echo json_encode(['ok' => false, 'message' => 'Transaksi tidak ditemukan']);
         return;
      }
      $where = $wc . " AND ref_finance = '" . $idEsc . "'";
      $kas = $this->db(0)->get_where_row('kas', $where);
      if (!$kas || empty($kas['id_kas'])) {
         echo json_encode(['ok' => false, 'message' => 'Transaksi tidak ditemukan']);
         return;
      }

      if (strtoupper(trim((string) ($kas['note'] ?? ''))) !== 'QRIS') {
         echo json_encode(['ok' => false, 'message' => 'Bukan pembayaran QRIS']);
         return;
      }

      if (trim((string) ($kas['payment_trx_id'] ?? '')) !== '') {
         echo json_encode(['ok' => false, 'message' => 'QRIS gateway — tidak perlu bind manual']);
         return;
      }

      $cols = "SUM(jumlah) AS total";
      $agg = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      $total = is_array($agg) && isset($agg[0]['total']) ? (float) $agg[0]['total'] : (float) ($kas['jumlah'] ?? 0);

      $this->helper('BcaQrisBind');
      $dbMain = $this->db(100);

      try {
         $dbMain->query('SELECT 1 FROM bca_qris_transaksi LIMIT 1');
         $dbMain->query('SELECT 1 FROM bca_qris_link LIMIT 1');
      } catch (\Throwable $e) {
         echo json_encode(['ok' => false, 'message' => 'Tabel bca_qris belum tersedia. Jalankan migration main 004 + 005.']);
         return;
      }

      $range = BcaQrisBind::listRange();
      $rows = BcaQrisBind::listUnlinked($dbMain, $range['start'], $range['end']);
      $kasNominal = BcaQrisBind::formatNominal($total);

      $items = [];
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $nom = BcaQrisBind::formatNominal($row['nominal'] ?? 0);
         if (!BcaQrisBind::isNominalWithinTolerance($kasNominal, $nom)) {
            continue;
         }
         $selisih = BcaQrisBind::nominalDiff($kasNominal, $nom);
         $ket = trim((string) ($row['keterangan'] ?? ''));
         $ketShort = $ket;
         if (strlen($ketShort) > 120) {
            $ketShort = substr($ketShort, 0, 117) . '…';
         }
         $tanggal = (string) ($row['tanggal'] ?? '');
         $waktu = trim((string) ($row['waktu'] ?? ''));
         $dateLabel = $tanggal;
         if ($waktu !== '') {
            $dateLabel .= ' ' . $waktu;
         }
         $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'tanggal' => $tanggal,
            'waktu' => $waktu,
            'date_label' => trim($dateLabel),
            'rrn' => (string) ($row['rrn'] ?? ''),
            'nominal' => $nom,
            'nominal_fmt' => number_format((float) $nom, 0, ',', '.'),
            'keterangan' => $ketShort,
            'keterangan_full' => $ket,
            'status' => (string) ($row['status'] ?? ''),
            'outlet_name' => (string) ($row['outlet_name'] ?? ''),
            'nominal_match' => ($nom === $kasNominal),
            'selisih' => $selisih,
            'selisih_fmt' => number_format($selisih, 0, ',', '.'),
         ];
      }

      usort($items, static function ($a, $b) {
         $am = !empty($a['nominal_match']) ? 0 : 1;
         $bm = !empty($b['nominal_match']) ? 0 : 1;
         if ($am !== $bm) {
            return $am <=> $bm;
         }
         $as = (float) ($a['selisih'] ?? 0);
         $bs = (float) ($b['selisih'] ?? 0);
         if ($as !== $bs) {
            return $as <=> $bs;
         }
         return strcmp((string) ($b['tanggal'] ?? ''), (string) ($a['tanggal'] ?? ''));
      });

      echo json_encode([
         'ok' => true,
         'ref_finance' => $refFinance,
         'kas_nominal' => $kasNominal,
         'kas_nominal_fmt' => number_format((float) $kasNominal, 0, ',', '.'),
         'nominal_tolerance' => BcaQrisBind::NOMINAL_TOLERANCE,
         'nominal_tolerance_fmt' => number_format(BcaQrisBind::NOMINAL_TOLERANCE, 0, ',', '.'),
         'range' => $range,
         'count' => count($items),
         'items' => $items,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   public function operasi($tipe)
   {
      $id = $_POST['id'];
      $tipe = (int) $tipe;
      $idEsc = $this->db(0)->escape((string) $id);
      $wc = $this->wCabangForApprovalAction('kas', 'ref_finance', (string) $id);
      if ($wc === null) {
         echo 'Transaksi tidak ditemukan';
         return;
      }
      $where = $wc . " AND ref_finance = '" . $idEsc . "'";
      $kas = $this->db(0)->get_where_row('kas', $where);
      if (!$kas || empty($kas['id_kas'])) {
         echo 'Transaksi tidak ditemukan';
         return;
      }

      $guard = $this->guardQrisStatusChange($kas, $tipe, false);
      if (empty($guard['ok'])) {
         echo $guard['msg'] ?: 'QRIS tidak dapat diubah';
         return;
      }

      $note = strtoupper(trim((string) ($kas['note'] ?? '')));
      $isBca = ($note === 'BCA');
      $isQrisStatic = ($note === 'QRIS' && trim((string) ($kas['payment_trx_id'] ?? '')) === '');

      if ($isBca && $tipe === 3) {
         $this->operasiBcaTerima($id, $where);
         return;
      }

      if ($isQrisStatic && $tipe === 3) {
         $this->operasiQrisTerima($id, $where);
         return;
      }

      $isQrisGateway = ($note === 'QRIS' && trim((string) ($kas['payment_trx_id'] ?? '')) !== '');
      if ($tipe === 3 && $isQrisGateway) {
         if (!$this->applyQrisPaidToKas($kas, $id, false)) {
            echo 'Gagal mengkonfirmasi pembayaran QRIS';
            return;
         }
         $this->runPostConfirmSideEffects($where);
         echo 0;
         return;
      }

      $set = [
         'status_mutasi' => $tipe
      ];
      $up = $this->db(0)->update('kas', $set, $where);
      if ($up['errno'] <> 0) {
         $this->model('Log')->write('[NonTunai::operasi] Update Kas Error: ' . $up['error']);
         echo $up['error'];
         return;
      }

      $this->runPostConfirmSideEffects($where);
      echo 0;
   }

   /**
    * Terima BCA: wajib bind mutasi dulu.
    */
   private function operasiBcaTerima(string $refFinance, string $where): void
   {
      $mutasiId = (int) ($_POST['mutasi_id'] ?? 0);
      $this->helper('BcaMutasiBind');
      $this->helper('KasBcaConfirm');

      $cols = "SUM(jumlah) AS total";
      $agg = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      $kasTotal = is_array($agg) && isset($agg[0]['total']) ? (float) $agg[0]['total'] : 0;
      $kasNominal = BcaMutasiBind::formatNominal($kasTotal);

      $dbMain = $this->db(100);

      $existingLink = $dbMain->get_where_row(
         'bca_mutasi_link',
         "entity_type = '" . $dbMain->escape(BcaMutasiBind::ENTITY_KAS_LAUNDRY) . "'"
         . " AND entity_ref = '" . $dbMain->escape($refFinance) . "'"
      );

      if (!empty($existingLink['bca_mutasi_id'])) {
         $linkedId = (int) $existingLink['bca_mutasi_id'];
         if ($mutasiId > 0 && $mutasiId !== $linkedId) {
            echo 'Transaksi sudah ter-bind ke mutasi lain';
            return;
         }
         $mutasiId = $linkedId;
      } elseif ($mutasiId < 1) {
         echo 'Wajib pilih mutasi BCA terlebih dahulu';
         return;
      } else {
         if (!BcaMutasiBind::bindMutasi($dbMain, $mutasiId, $refFinance, $kasNominal)) {
            $mutasiRow = BcaMutasiBind::getMutasiRow($dbMain, $mutasiId);
            if ($mutasiRow && !BcaMutasiBind::isNominalWithinTolerance($kasNominal, $mutasiRow['nominal'] ?? 0)) {
               echo 'Selisih nominal mutasi melebihi toleransi Rp '
                  . number_format(BcaMutasiBind::NOMINAL_TOLERANCE, 0, ',', '.');
               return;
            }
            echo 'Gagal bind mutasi (tidak valid atau sudah terpakai)';
            return;
         }
      }

      $up = $this->db(0)->update('kas', ['status_mutasi' => 3], $where . " AND status_mutasi = 2");
      if ($up['errno'] <> 0) {
         if (empty($existingLink['id'])) {
            BcaMutasiBind::unbindEntity($dbMain, $refFinance);
         }
         $this->model('Log')->write('[NonTunai::operasiBcaTerima] Update Kas Error: ' . $up['error']);
         echo $up['error'];
         return;
      }

      $kasRows = $this->db(0)->get_where('kas', $where);
      if (!is_array($kasRows)) {
         $kasRows = [];
      }

      KasBcaConfirm::afterApprove($this->db(0), $dbMain, $refFinance, $kasRows);
      echo 0;
   }

   /**
    * Terima QRIS static (merchant BCA): wajib bind transaksi QRMS dulu.
    */
   private function operasiQrisTerima(string $refFinance, string $where): void
   {
      $qrisId = (int) ($_POST['qris_id'] ?? 0);
      $this->helper('BcaQrisBind');
      $this->helper('KasBcaConfirm');

      $cols = "SUM(jumlah) AS total";
      $agg = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
      $kasTotal = is_array($agg) && isset($agg[0]['total']) ? (float) $agg[0]['total'] : 0;
      $kasNominal = BcaQrisBind::formatNominal($kasTotal);

      $dbMain = $this->db(100);

      $existingLink = $dbMain->get_where_row(
         'bca_qris_link',
         "entity_type = '" . $dbMain->escape(BcaQrisBind::ENTITY_KAS_LAUNDRY) . "'"
         . " AND entity_ref = '" . $dbMain->escape($refFinance) . "'"
      );

      if (!empty($existingLink['bca_qris_id'])) {
         $linkedId = (int) $existingLink['bca_qris_id'];
         if ($qrisId > 0 && $qrisId !== $linkedId) {
            echo 'Transaksi sudah ter-bind ke QRIS lain';
            return;
         }
         $qrisId = $linkedId;
      } elseif ($qrisId < 1) {
         echo 'Wajib pilih transaksi QRIS terlebih dahulu';
         return;
      } else {
         if (!BcaQrisBind::bindQris($dbMain, $qrisId, $refFinance, $kasNominal)) {
            $qrisRow = BcaQrisBind::getQrisRow($dbMain, $qrisId);
            if ($qrisRow && !BcaQrisBind::isNominalWithinTolerance($kasNominal, $qrisRow['nominal'] ?? 0)) {
               echo 'Selisih nominal QRIS melebihi toleransi Rp '
                  . number_format(BcaQrisBind::NOMINAL_TOLERANCE, 0, ',', '.');
               return;
            }
            echo 'Gagal bind QRIS (tidak valid atau sudah terpakai)';
            return;
         }
      }

      $up = $this->db(0)->update('kas', ['status_mutasi' => 3], $where . " AND status_mutasi = 2");
      if ($up['errno'] <> 0) {
         if (empty($existingLink['id'])) {
            BcaQrisBind::unbindEntity($dbMain, $refFinance);
         }
         $this->model('Log')->write('[NonTunai::operasiQrisTerima] Update Kas Error: ' . $up['error']);
         echo $up['error'];
         return;
      }

      $kasRows = $this->db(0)->get_where('kas', $where);
      if (!is_array($kasRows)) {
         $kasRows = [];
      }

      KasBcaConfirm::afterApprove($this->db(0), $dbMain, $refFinance, $kasRows);
      echo 0;
   }

   private function runPostConfirmSideEffects(string $where): void
   {
      try {
         $kasData = $this->db(0)->get_where_row('kas', $where);

         if ($kasData && isset($kasData['id_client'])) {
            $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '{$kasData['id_client']}'");

            if ($pelanggan && !empty($pelanggan['nomor_pelanggan'])) {
               $this->helper('PelangganByPhone');
               $nomor = PelangganByPhone::key($pelanggan['nomor_pelanggan']);
               $phonePlus62 = $nomor !== '' ? ('62' . $nomor) : '';

               if ($nomor !== '') {
                  $this->db(100)->query(
                     "UPDATE wa_conversations SET priority = 0 WHERE priority = 2 AND "
                     . PelangganByPhone::likeSql($this->db(100)->escape($nomor), 'wa_number')
                  );
               }

               $payload = [
                  'type' => 'priority_updated',
                  'phone' => $phonePlus62,
                  'priority' => 0,
                  'target_id' => '0',
                  'sender_id' => 'system'
               ];

               $this->model('Log')->write('[NonTunai::operasi] Attempting WebSocket push. Payload: ' . json_encode($payload) . ' | Phone: ' . $phonePlus62);

               $wsResult = $this->pushToWebSocket($payload);

               $this->model('Log')->write('[NonTunai::operasi] WebSocket push result: ' . ($wsResult ? $wsResult : 'NULL/EMPTY'));
            }
         }
      } catch (\Exception $e) {
         $this->model('Log')->write("[NonTunai::operasi] WA conversation error: " . $e->getMessage());
      } catch (\Error $e) {
         $this->model('Log')->write("[NonTunai::operasi] WA conversation fatal error: " . $e->getMessage());
      }
   }

   private function pushToWebSocket($data)
   {
      $url = 'http://127.0.0.1:3003/incoming';
      
      $this->model('Log')->write('[NonTunai::pushToWebSocket] Starting request to: ' . $url . ' | Data: ' . json_encode($data));
      
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
      curl_setopt($ch, CURLOPT_TIMEOUT, 3);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      
      $result = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      
      if (curl_errno($ch)) {
         $this->model('Log')->write('[NonTunai::pushToWebSocket] cURL Error [' . curl_errno($ch) . ']: ' . $curlError);
      } else {
         $this->model('Log')->write('[NonTunai::pushToWebSocket] Success - HTTP Code: ' . $httpCode . ' | Response: ' . ($result ? $result : 'EMPTY'));
      }
      
      curl_close($ch);
      return $result;
   }
}
