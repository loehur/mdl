<?php

class Pengeluaran extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $view = 'admin_approval/pengeluaran';

      $where = $this->wCabangAll() . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND jenis_transaksi = 4 AND status_mutasi = 2 ORDER BY insertTime DESC LIMIT 50";
      $list = $this->db(0)->get_where('kas', $where);
      $this->view($view, ['list' => is_array($list) ? $list : []]);
   }

   /**
    * JSON — analisa AI pengeluaran pending + riwayat 30 hari.
    */
   public function analisaAi()
   {
      @set_time_limit(120);
      header('Content-Type: application/json; charset=utf-8');

      try {
         $pending = null;
         $id = trim((string) ($_POST['id'] ?? $_GET['id'] ?? ''));
         if ($id === '' || !preg_match('/^[A-Za-z0-9]+$/', $id)) {
            echo json_encode(['ok' => false, 'message' => 'ID tidak valid'], JSON_UNESCAPED_UNICODE);
            return;
         }

         $idEsc = $this->db(0)->escape($id);
         $wc = $this->wCabangForApprovalAction('kas', 'id_kas', $id, ['jenis_transaksi' => 4, 'status_mutasi' => 2]);
         if ($wc === null) {
            echo json_encode(['ok' => false, 'message' => 'Pengeluaran tidak ditemukan atau sudah diproses'], JSON_UNESCAPED_UNICODE);
            return;
         }

         $where = $wc . " AND id_kas = '" . $idEsc . "' AND jenis_transaksi = 4 AND status_mutasi = 2";
         $pending = $this->db(0)->get_where_row('kas', $where);
         if (!is_array($pending) || empty($pending['id_kas'])) {
            echo json_encode(['ok' => false, 'message' => 'Pengeluaran tidak ditemukan atau sudah diproses'], JSON_UNESCAPED_UNICODE);
            return;
         }

         /** @var PengeluaranAiReview $review */
         $review = $this->helper('PengeluaranAiReview');
         $kodeFn = [$this, 'cabangKodeById'];
         $jenis = trim((string) ($pending['note_primary'] ?? ''));
         $history = $review->fetchHistory30Days($this->db(0), $this->wCabangAll(), $jenis, $kodeFn, $id);
         $result = $review->analyze($pending, $history, $kodeFn);

         if (empty($result['analysis'])) {
            $pendingPayload = $review->pendingPayload($pending, $kodeFn);
            $result = [
               'ok' => true,
               'analysis' => $review->localFallbackAnalysis($pendingPayload, $history),
               'history_count' => count($history),
               'history_shown' => min(count($history), 60),
               'pending' => $pendingPayload,
               'ai_source' => 'local',
               'jenis_filter' => $jenis,
               'message' => $result['message'] ?? 'Menampilkan analisa otomatis.',
            ];
         }

         echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
      } catch (\Throwable $e) {
         $this->model('Log')->write('[Pengeluaran::analisaAi] ' . $e->getMessage());
         $fallbackPayload = null;
         $history = [];
         try {
            if (!empty($pending) && is_array($pending)) {
               /** @var PengeluaranAiReview $review */
               $review = $this->helper('PengeluaranAiReview');
               $kodeFn = [$this, 'cabangKodeById'];
               $jenis = trim((string) ($pending['note_primary'] ?? ''));
               $history = $review->fetchHistory30Days($this->db(0), $this->wCabangAll(), $jenis, $kodeFn, (string) ($pending['id_kas'] ?? ''));
               $fallbackPayload = $review->pendingPayload($pending, $kodeFn);
               echo json_encode([
                  'ok' => true,
                  'analysis' => $review->localFallbackAnalysis($fallbackPayload, $history),
                  'history_count' => count($history),
                  'history_shown' => min(count($history), 60),
                  'pending' => $fallbackPayload,
                  'ai_source' => 'local',
                  'jenis_filter' => $jenis,
                  'message' => 'Error server — analisa otomatis.',
               ], JSON_UNESCAPED_UNICODE);
               return;
            }
         } catch (\Throwable $ignored) {
         }
         echo json_encode([
            'ok' => false,
            'message' => 'Gagal analisa: ' . $e->getMessage(),
         ], JSON_UNESCAPED_UNICODE);
      }
   }

   public function operasi($tipe)
   {
      $tipe = (int) $tipe;
      if (!in_array($tipe, [3, 4], true)) {
         echo 'Tipe tidak valid';
         return;
      }

      $id = trim((string) ($_POST['id'] ?? ''));
      if ($id === '' || !preg_match('/^[A-Za-z0-9]+$/', $id)) {
         echo 'ID tidak valid';
         return;
      }

      $idEsc = $this->db(0)->escape($id);
      $wc = $this->wCabangForApprovalAction('kas', 'id_kas', $id, ['jenis_transaksi' => 4, 'status_mutasi' => 2]);
      if ($wc === null) {
         echo 'Pengeluaran tidak ditemukan atau sudah diproses';
         return;
      }
      $where = $wc . " AND id_kas = '" . $idEsc . "' AND jenis_transaksi = 4 AND status_mutasi = 2";
      $count = $this->db(0)->count_where('kas', $where);
      if ($count == 0) {
         echo 'Pengeluaran tidak ditemukan atau sudah diproses';
         return;
      }

      $up = $this->db(0)->update('kas', ['status_mutasi' => $tipe], $where);
      if ($up['errno'] == 0) {
         echo '0';
      } else {
         echo $up['error'];
      }
   }
}
