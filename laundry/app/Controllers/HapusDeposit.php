<?php

class HapusDeposit extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $viewData = 'member/viewDataHapus';
      $db = $this->db(0);
      $wc = $this->wCabangAll();

      $data_manual = $db->get_where_order('member', $wc . ' AND bin = 1', 'id_member DESC');
      if (!is_array($data_manual)) {
         $data_manual = [];
      }

      $kas = [];
      if (count($data_manual) > 0) {
         $ids = array_values(array_unique(array_map('intval', array_column($data_manual, 'id_member'))));
         if (count($ids) > 0) {
            $idIn = implode(',', $ids);
            $kas = $db->get_where('kas', $wc . " AND jenis_transaksi = 3 AND ref_transaksi IN ($idIn)");
            if (!is_array($kas)) {
               $kas = [];
            }
         }
      }

      $this->view($viewData, [
         'data_manual' => $data_manual,
         'kas' => $kas,
      ]);
   }

   /**
    * Hapus semua deposit bin=1.
    * Urutan: pembayaran (kas jenis 3) dulu → member.
    * Jika hapus pembayaran gagal → hentikan, member tidak dihapus.
    */
   public function hapusSemua()
   {
      header('Content-Type: application/json; charset=utf-8');

      $db = $this->db(0);
      $wc = $this->wCabangAll();
      $rows = $db->get_where('member', $wc . ' AND bin = 1');
      if (!is_array($rows) || count($rows) === 0) {
         echo json_encode(['status' => 'error', 'message' => 'Tidak ada deposit di antrean hapus']);
         return;
      }

      $ids = [];
      foreach ($rows as $r) {
         $id = (int) ($r['id_member'] ?? 0);
         if ($id > 0) {
            $ids[$id] = ['id' => $id, 'id_cabang' => (int) ($r['id_cabang'] ?? 0)];
         }
      }

      foreach ($ids as $item) {
         $err = $this->deleteKasForMember((int) $item['id'], (int) $item['id_cabang']);
         if ($err !== null) {
            echo json_encode(['status' => 'error', 'message' => $err]);
            return;
         }
      }

      foreach ($ids as $item) {
         $id = (int) $item['id'];
         $wcBranch = 'id_cabang = ' . (int) $item['id_cabang'];
         $del = $db->delete('member', $wcBranch . ' AND id_member = ' . $id . ' AND bin = 1');
         if (isset($del['errno']) && (int) $del['errno'] !== 0) {
            echo json_encode([
               'status' => 'error',
               'message' => 'Gagal hapus deposit #' . $id . ': ' . ($del['error'] ?? ''),
            ]);
            return;
         }
      }

      $this->model('Log')->write('[HapusDeposit::hapusSemua] hapus ' . count($ids) . ' deposit');
      echo json_encode([
         'status' => 'success',
         'message' => 'Berhasil hapus ' . count($ids) . ' deposit',
      ]);
   }

   public function hapusItem()
   {
      header('Content-Type: application/json; charset=utf-8');
      $id = (int) ($_POST['id'] ?? 0);
      if ($id <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'ID deposit tidak valid']);
         return;
      }

      $db = $this->db(0);
      $wc = $this->wCabangForApprovalAction('member', 'id_member', (string) $id, ['bin' => 1]);
      if ($wc === null) {
         echo json_encode(['status' => 'error', 'message' => 'Deposit tidak ditemukan di antrean']);
         return;
      }
      $row = $db->get_where_row('member', $wc . ' AND id_member = ' . $id . ' AND bin = 1');
      if (!is_array($row) || empty($row['id_member'])) {
         echo json_encode(['status' => 'error', 'message' => 'Deposit tidak ditemukan di antrean']);
         return;
      }

      $err = $this->deleteKasForMember($id, (int) ($row['id_cabang'] ?? 0));
      if ($err !== null) {
         echo json_encode(['status' => 'error', 'message' => $err]);
         return;
      }

      $del = $db->delete('member', $wc . ' AND id_member = ' . $id . ' AND bin = 1');
      if (isset($del['errno']) && (int) $del['errno'] !== 0) {
         echo json_encode(['status' => 'error', 'message' => $del['error'] ?? 'Gagal hapus deposit']);
         return;
      }

      $this->model('Log')->write('[HapusDeposit::hapusItem] id=' . $id);
      echo json_encode(['status' => 'success', 'message' => 'Deposit #' . $id . ' dihapus']);
   }

   public function restoreRef()
   {
      header('Content-Type: application/json; charset=utf-8');
      $id = (int) ($_POST['id'] ?? 0);
      if ($id <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'ID deposit tidak valid']);
         return;
      }

      $idCabang = (int) ($_POST['id_cabang'] ?? 0);
      $wc = $idCabang > 0
         ? ('id_cabang = ' . $idCabang)
         : $this->wCabangForApprovalAction('member', 'id_member', (string) $id, ['bin' => 1]);
      if ($wc === null) {
         echo json_encode(['status' => 'error', 'message' => 'Deposit tidak ditemukan di antrean']);
         return;
      }

      $up = $this->db(0)->update(
         'member',
         ['bin' => 0],
         $wc . ' AND id_member = ' . $id . ' AND bin = 1'
      );
      if (isset($up['errno']) && (int) $up['errno'] !== 0) {
         echo json_encode(['status' => 'error', 'message' => $up['error'] ?? 'Gagal restore']);
         return;
      }

      echo json_encode(['status' => 'success', 'message' => 'Deposit #' . $id . ' dikembalikan']);
   }

   /** @return string|null error message, or null on success */
   private function deleteKasForMember(int $idMember, int $idCabang = 0): ?string
   {
      $wc = $idCabang > 0 ? ('id_cabang = ' . $idCabang) : $this->wCabangAll();
      $whereKas = $wc
         . " AND ref_transaksi = '" . $this->db(0)->escape((string) $idMember) . "'"
         . ' AND jenis_transaksi = 3';
      $kasResult = $this->deleteKasSafe($whereKas, false);

      if (empty($kasResult['ok'])) {
         return $kasResult['msg'] !== ''
            ? $kasResult['msg']
            : ('Gagal hapus pembayaran deposit #' . $idMember);
      }

      $keptPaid = (int) ($kasResult['kept_paid'] ?? 0);
      $keptPending = (int) ($kasResult['kept_pending'] ?? 0);
      $keptUnknown = (int) ($kasResult['kept_unknown'] ?? 0);
      $keptLunas = (int) ($kasResult['kept_lunas'] ?? 0);
      $kept = $keptPaid + $keptPending + $keptUnknown + $keptLunas;
      $action = (string) ($kasResult['action'] ?? '');

      if ($kept > 0 || in_array($action, ['paid', 'blocked', 'lunas', 'partial'], true)) {
         $msg = trim((string) ($kasResult['msg'] ?? ''));
         if ($msg === '') {
            if ($keptPaid > 0 || $keptLunas > 0 || $action === 'paid' || $action === 'lunas') {
               $msg = 'Pembayaran QRIS sudah berhasil — tidak bisa dihapus (deposit #' . $idMember . '). Deposit tidak dihapus.';
            } elseif ($keptPending > 0) {
               $msg = 'Pembayaran QRIS masih pending — tidak bisa dihapus (deposit #' . $idMember . '). Deposit tidak dihapus.';
            } else {
               $msg = 'Pembayaran tidak bisa dihapus (deposit #' . $idMember . '). Deposit tidak dihapus.';
            }
         } elseif (stripos($msg, 'deposit') === false) {
            $msg .= ' Deposit tidak dihapus.';
         }
         return $msg;
      }

      return null;
   }
}
