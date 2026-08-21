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
    * JSON — analisa AI pengeluaran pending + riwayat 30 hari (jenis sama, status berhasil).
    */
   public function analisaAi()
   {
      @set_time_limit(120);
      while (ob_get_level() > 0) {
         @ob_end_clean();
      }
      header('Content-Type: application/json; charset=utf-8');

      $this->helper('PengeluaranAiLog');
      $reqId = substr(md5((string) microtime(true) . mt_rand()), 0, 8);
      PengeluaranAiLog::info('START', [
         'req' => $reqId,
         'id' => $_POST['id'] ?? $_GET['id'] ?? '',
         'id_cabang' => $_POST['id_cabang'] ?? '',
      ]);

      $pending = null;

      try {
         $id = trim((string) ($_POST['id'] ?? $_GET['id'] ?? ''));
         if ($id === '' || !preg_match('/^[A-Za-z0-9]+$/', $id)) {
            PengeluaranAiLog::error('INVALID_ID', ['req' => $reqId, 'id' => $id]);
            $this->jsonOut(['ok' => false, 'message' => 'ID tidak valid', 'req_id' => $reqId], $reqId);
            return;
         }

         $idEsc = $this->db(0)->escape($id);
         $wc = $this->wCabangForApprovalAction('kas', 'id_kas', $id, ['jenis_transaksi' => 4, 'status_mutasi' => 2]);
         if ($wc === null) {
            PengeluaranAiLog::error('NOT_FOUND_WC', ['req' => $reqId, 'id' => $id]);
            $this->jsonOut(['ok' => false, 'message' => 'Pengeluaran tidak ditemukan atau sudah diproses', 'req_id' => $reqId], $reqId);
            return;
         }

         $where = $wc . " AND id_kas = '" . $idEsc . "' AND jenis_transaksi = 4 AND status_mutasi = 2";
         $pending = $this->db(0)->get_where_row('kas', $where);
         if (!is_array($pending) || empty($pending['id_kas'])) {
            PengeluaranAiLog::error('NOT_FOUND_ROW', ['req' => $reqId, 'id' => $id, 'where' => $where]);
            $this->jsonOut(['ok' => false, 'message' => 'Pengeluaran tidak ditemukan atau sudah diproses', 'req_id' => $reqId], $reqId);
            return;
         }

         /** @var PengeluaranAiReview $review */
         $review = $this->helper('PengeluaranAiReview');
         $kodeFn = [$this, 'cabangKodeById'];
         $jenis = trim((string) ($pending['note_primary'] ?? ''));

         PengeluaranAiLog::info('PENDING', [
            'req' => $reqId,
            'id_kas' => $id,
            'jenis' => $jenis,
            'jumlah' => $pending['jumlah'] ?? '',
            'id_cabang' => $pending['id_cabang'] ?? '',
         ]);

         $history = $review->fetchHistory30Days($this->db(0), $this->wCabangAll(), $jenis, $kodeFn, $id);
         PengeluaranAiLog::info('HISTORY', ['req' => $reqId, 'jenis' => $jenis, 'count' => count($history)]);

         $result = $review->analyze($pending, $history, $kodeFn, $reqId);

         if (empty($result['analysis'])) {
            PengeluaranAiLog::error('NO_ANALYSIS', ['req' => $reqId, 'result' => $result]);
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
               'req_id' => $reqId,
            ];
         }

         $result['req_id'] = $reqId;
         $this->jsonOut($result, $reqId);
      } catch (\Throwable $e) {
         PengeluaranAiLog::error('EXCEPTION', [
            'req' => $reqId,
            'msg' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
         ]);

         try {
            if (!empty($pending) && is_array($pending)) {
               /** @var PengeluaranAiReview $review */
               $review = $this->helper('PengeluaranAiReview');
               $kodeFn = [$this, 'cabangKodeById'];
               $jenis = trim((string) ($pending['note_primary'] ?? ''));
               $history = $review->fetchHistory30Days($this->db(0), $this->wCabangAll(), $jenis, $kodeFn, (string) ($pending['id_kas'] ?? ''));
               $fallbackPayload = $review->pendingPayload($pending, $kodeFn);
               $this->jsonOut([
                  'ok' => true,
                  'analysis' => $review->localFallbackAnalysis($fallbackPayload, $history),
                  'history_count' => count($history),
                  'history_shown' => min(count($history), 60),
                  'pending' => $fallbackPayload,
                  'ai_source' => 'local',
                  'jenis_filter' => $jenis,
                  'message' => 'Error server — analisa otomatis.',
                  'req_id' => $reqId,
               ], $reqId);
               return;
            }
         } catch (\Throwable $ignored) {
            PengeluaranAiLog::error('FALLBACK_FAIL', ['req' => $reqId, 'msg' => $ignored->getMessage()]);
         }

         $this->jsonOut([
            'ok' => false,
            'message' => 'Gagal analisa: ' . $e->getMessage(),
            'req_id' => $reqId,
         ], $reqId);
      }
   }

   /** @param array<string,mixed> $payload */
   private function jsonOut(array $payload, string $reqId): void
   {
      if (isset($payload['analysis']) && is_string($payload['analysis'])) {
         $payload['analysis'] = $this->sanitizeUtf8($payload['analysis']);
      }

      $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
      if ($json === false) {
         PengeluaranAiLog::error('JSON_ENCODE_FAIL', [
            'req' => $reqId,
            'err' => json_last_error_msg(),
         ]);
         $json = json_encode([
            'ok' => true,
            'analysis' => 'Analisa otomatis: respons JSON gagal di-encode. Silakan konfirmasi manual.',
            'ai_source' => 'local',
            'req_id' => $reqId,
         ], JSON_UNESCAPED_UNICODE);
      }

      PengeluaranAiLog::info('RESPONSE', [
         'req' => $reqId,
         'ok' => $payload['ok'] ?? null,
         'ai_source' => $payload['ai_source'] ?? null,
         'bytes' => strlen($json),
      ]);

      echo $json;
   }

   private function sanitizeUtf8(string $text): string
   {
      if ($text === '') {
         return $text;
      }
      if (function_exists('mb_convert_encoding')) {
         $fixed = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
         return is_string($fixed) ? $fixed : $text;
      }

      return $text;
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
