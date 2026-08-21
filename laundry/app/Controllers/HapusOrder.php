<?php

class HapusOrder extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $viewData = 'hapusOrder/hapus_order_main';
      $db = $this->db(0);
      $wc = $this->wCabangAll();

      $where = $wc . " AND id_pelanggan <> 0 AND bin = 1 ORDER BY id_penjualan DESC LIMIT 50";
      $data_main = $db->get_where('sale', $where);
      if (!is_array($data_main)) {
         $data_main = [];
      }

      $operasi = [];
      $kas = [];
      $surcas = [];
      $notifBon = [];
      $notifSelesai = [];

      if (count($data_main) > 0) {
         $ids = array_values(array_unique(array_map('intval', array_column($data_main, 'id_penjualan'))));
         $refs = array_values(array_unique(array_filter(array_column($data_main, 'no_ref'), static function ($r) {
            return $r !== null && $r !== '';
         })));

         if (count($ids) > 0) {
            $idIn = implode(',', $ids);
            $operasi = $db->get_where('operasi', $wc . " AND id_penjualan IN ($idIn)");
            if (!is_array($operasi)) {
               $operasi = [];
            }
            $idEscList = [];
            foreach ($ids as $sid) {
               $idEscList[] = "'" . $db->escape((string) $sid) . "'";
            }
            $notifSelesai = $db->get_where(
               'notif',
               $wc . ' AND tipe = 2 AND no_ref IN (' . implode(',', $idEscList) . ')'
            );
            if (!is_array($notifSelesai)) {
               $notifSelesai = [];
            }
         }

         if (count($refs) > 0) {
            $refIn = implode(',', array_map(static function ($r) use ($db) {
               return "'" . $db->escape((string) $r) . "'";
            }, $refs));

            $kas = $db->get_where('kas', $wc . " AND jenis_transaksi = 1 AND ref_transaksi IN ($refIn)");
            $surcas = $db->get_where('surcas', $wc . " AND no_ref IN ($refIn)");
            $notifBon = $db->get_where('notif', $wc . " AND tipe = 1 AND no_ref IN ($refIn)");
            if (!is_array($kas)) {
               $kas = [];
            }
            if (!is_array($surcas)) {
               $surcas = [];
            }
            if (!is_array($notifBon)) {
               $notifBon = [];
            }
         }
      }

      $this->view($viewData, [
         'data_main' => $data_main,
         'operasi' => $operasi,
         'kas' => $kas,
         'surcas' => $surcas,
         'notif_bon' => $notifBon,
         'notif_selesai' => $notifSelesai,
      ]);
   }

   /**
    * Hapus semua item bin=1 di antrean.
    * Urutan: pembayaran (kas) → terkait (notif/surcas) → layanan (operasi/notif selesai) → sale.
    * Jika hapus pembayaran gagal (QRIS paid/pending/dll) → hentikan, layanan tidak dihapus.
    */
   public function hapusSemua()
   {
      header('Content-Type: application/json; charset=utf-8');

      $db = $this->db(0);
      $wc = $this->wCabangAll();
      $rows = $db->get_where('sale', $wc . ' AND id_pelanggan <> 0 AND bin = 1');
      if (!is_array($rows) || count($rows) === 0) {
         echo json_encode(['status' => 'error', 'message' => 'Tidak ada order yang diantrekan hapus']);
         return;
      }

      $ids = [];
      $refsByCabang = [];
      foreach ($rows as $r) {
         $cid = (int) ($r['id_cabang'] ?? 0);
         if ($cid <= 0) {
            continue;
         }
         $id = (int) ($r['id_penjualan'] ?? 0);
         if ($id > 0) {
            $ids[$id] = ['id' => $id, 'id_cabang' => $cid];
         }
         $ref = trim((string) ($r['no_ref'] ?? ''));
         if ($ref !== '') {
            $refsByCabang[$cid][$ref] = $ref;
         }
      }

      // 1) Pembayaran dulu — gagal = stop (per cabang)
      foreach ($refsByCabang as $cid => $refs) {
         $wcBranch = 'id_cabang = ' . (int) $cid;
         foreach ($refs as $ref) {
            $refEsc = $db->escape($ref);
            $whereKas = $wcBranch . " AND ref_transaksi = '" . $refEsc . "' AND jenis_transaksi = 1";
            $kasResult = $this->deleteKasSafe($whereKas, false);
            if (empty($kasResult['ok'])) {
               echo json_encode([
                  'status' => 'error',
                  'message' => $kasResult['msg'] !== ''
                     ? $kasResult['msg']
                     : ('Gagal hapus pembayaran REF #' . $ref),
               ]);
               return;
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
                     $msg = 'Pembayaran QRIS sudah berhasil — tidak bisa dihapus (REF #' . $ref . '). Layanan tidak dihapus.';
                  } elseif ($keptPending > 0) {
                     $msg = 'Pembayaran QRIS masih pending — tidak bisa dihapus (REF #' . $ref . '). Layanan tidak dihapus.';
                  } else {
                     $msg = 'Pembayaran tidak bisa dihapus (REF #' . $ref . '). Layanan tidak dihapus.';
                  }
               } elseif (stripos($msg, 'layanan') === false) {
                  $msg .= ' Layanan tidak dihapus.';
               }
               echo json_encode(['status' => 'error', 'message' => $msg]);
               return;
            }
         }
      }

      // 2) Related non-layanan (notif bon + surcas)
      foreach ($refsByCabang as $cid => $refs) {
         $wcBranch = 'id_cabang = ' . (int) $cid;
         foreach ($refs as $ref) {
            $refEsc = $db->escape($ref);
            $db->delete('notif', $wcBranch . " AND no_ref = '" . $refEsc . "' AND tipe = 1");
            $db->delete('surcas', $wcBranch . " AND no_ref = '" . $refEsc . "' AND transaksi_jenis = 1");
         }
      }

      // 3) Layanan (operasi + notif selesai)
      foreach ($ids as $item) {
         $id = (int) ($item['id'] ?? 0);
         $wcBranch = 'id_cabang = ' . (int) ($item['id_cabang'] ?? 0);
         $idEsc = $db->escape((string) $id);
         $db->delete('operasi', $wcBranch . " AND id_penjualan = '" . $idEsc . "'");
         $db->delete('notif', $wcBranch . " AND no_ref = '" . $idEsc . "' AND tipe = 2");
      }

      // 4) Hapus permanen sale
      foreach ($ids as $item) {
         $id = (int) ($item['id'] ?? 0);
         $wcBranch = 'id_cabang = ' . (int) ($item['id_cabang'] ?? 0);
         $del = $db->delete('sale', $wcBranch . ' AND id_penjualan = ' . $id . ' AND bin = 1');
         if (isset($del['errno']) && (int) $del['errno'] !== 0) {
            echo json_encode([
               'status' => 'error',
               'message' => 'Gagal hapus item #' . $id . ': ' . ($del['error'] ?? ''),
            ]);
            return;
         }
      }

      $refCount = 0;
      foreach ($refsByCabang as $refs) {
         $refCount += count($refs);
      }
      $this->model('Log')->write('[HapusOrder::hapusSemua] hapus ' . count($ids) . ' item, ' . $refCount . ' ref');
      echo json_encode([
         'status' => 'success',
         'message' => 'Berhasil hapus ' . count($ids) . ' item',
      ]);
   }

   /**
    * Hapus permanen satu item (bin=1).
    * Syarat: tidak ada penyelesai di item ini; nota aktif (bin=0) tidak overpay.
    * Notif selesai item + notif bon REF ikut dihapus.
    */
   public function hapusItemPermanent()
   {
      header('Content-Type: application/json; charset=utf-8');
      $id = (int) ($_POST['id'] ?? 0);
      if ($id <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'ID item tidak valid']);
         return;
      }

      $db = $this->db(0);
      $wc = $this->wCabangForApprovalAction('sale', 'id_penjualan', (string) $id, ['bin' => 1]);
      if ($wc === null) {
         echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan di antrean hapus']);
         return;
      }
      $sale = $db->get_where_row('sale', $wc . ' AND id_penjualan = ' . $id . ' AND bin = 1');
      if (!is_array($sale) || empty($sale['id_penjualan'])) {
         echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan di antrean hapus']);
         return;
      }

      $idEsc = $db->escape((string) $id);
      $opCount = (int) ($db->count_where('operasi', $wc . " AND id_penjualan = '" . $idEsc . "'") ?? 0);
      if ($opCount > 0) {
         echo json_encode([
            'status' => 'error',
            'message' => 'Hapus penyelesai item ini dulu sebelum menghapus item',
         ]);
         return;
      }

      $ref = trim((string) ($sale['no_ref'] ?? ''));
      $idCabang = (int) ($sale['id_cabang'] ?? 0);
      if ($ref !== '') {
         $dibayar = $this->sumDibayarRef($ref, $idCabang);
         $tagihanAktif = $this->sumTagihanRefBin0($ref, $idCabang);
         if ($dibayar > $tagihanAktif) {
            echo json_encode([
               'status' => 'error',
               'message' => 'Nota overpay (bayar Rp' . number_format($dibayar)
                  . ' > tagihan aktif Rp' . number_format($tagihanAktif) . ') — item tidak bisa dihapus',
            ]);
            return;
         }
      }

      $db->delete('notif', $wc . " AND no_ref = '" . $idEsc . "' AND tipe = 2");
      if ($ref !== '') {
         $refEsc = $db->escape($ref);
         $db->delete('notif', $wc . " AND no_ref = '" . $refEsc . "' AND tipe = 1");
      }
      $del = $db->delete('sale', $wc . ' AND id_penjualan = ' . $id . ' AND bin = 1');
      if (isset($del['errno']) && (int) $del['errno'] !== 0) {
         echo json_encode(['status' => 'error', 'message' => $del['error'] ?? 'Gagal hapus item']);
         return;
      }

      $this->model('Log')->write('[HapusOrder::hapusItemPermanent] id=' . $id . ' ref=' . $ref);
      echo json_encode(['status' => 'success', 'message' => 'Item #' . $id . ' dihapus permanen']);
   }

   /**
    * Hapus satu pembayaran (kas) dari antrean HapusOrder.
    * Non-QRIS (tunai/saldo/transfer) boleh dihapus termasuk yang sudah lunas.
    * QRIS nontunai tetap lewat deleteKasSafe (cek gateway); jika tidak boleh → error + toast.
    */
   public function hapusPembayaran()
   {
      header('Content-Type: application/json; charset=utf-8');
      $idKas = trim((string) ($_POST['id_kas'] ?? ''));
      if ($idKas === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID pembayaran tidak valid']);
         return;
      }

      $db = $this->db(0);
      $wc = $this->wCabangForApprovalAction('kas', 'id_kas', $idKas, ['jenis_transaksi' => 1]);
      if ($wc === null) {
         echo json_encode(['status' => 'error', 'message' => 'Pembayaran tidak ditemukan']);
         return;
      }
      $idEsc = $db->escape($idKas);
      $where = $wc . " AND id_kas = '" . $idEsc . "' AND jenis_transaksi = 1";
      $kas = $db->get_where_row('kas', $where);
      if (!is_array($kas) || empty($kas['id_kas'])) {
         echo json_encode(['status' => 'error', 'message' => 'Pembayaran tidak ditemukan']);
         return;
      }

      $ref = trim((string) ($kas['ref_transaksi'] ?? ''));
      if ($ref === '') {
         echo json_encode(['status' => 'error', 'message' => 'Pembayaran tidak memiliki REF']);
         return;
      }
      $refEsc = $db->escape($ref);
      $inQueue = (int) ($db->count_where('sale', $wc . " AND no_ref = '" . $refEsc . "' AND bin = 1") ?? 0);
      if ($inQueue <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Pembayaran tidak terkait antrean hapus']);
         return;
      }

      $isQris = strtoupper(trim((string) ($kas['note'] ?? ''))) === 'QRIS';
      $kasResult = $this->deleteKasSafe($where, false);
      if (empty($kasResult['ok'])) {
         echo json_encode([
            'status' => 'error',
            'message' => $kasResult['msg'] !== ''
               ? $kasResult['msg']
               : ($kasResult['error'] !== '' ? $kasResult['error'] : 'Gagal hapus pembayaran'),
         ]);
         return;
      }

      $keptPaid = (int) ($kasResult['kept_paid'] ?? 0);
      $keptPending = (int) ($kasResult['kept_pending'] ?? 0);
      $keptUnknown = (int) ($kasResult['kept_unknown'] ?? 0);
      $keptLunas = (int) ($kasResult['kept_lunas'] ?? 0);
      $kept = $keptPaid + $keptPending + $keptUnknown + $keptLunas;
      $action = (string) ($kasResult['action'] ?? '');
      $blocked = $kept > 0 || in_array($action, ['paid', 'blocked', 'lunas', 'partial'], true);

      if ($blocked) {
         // Tunai/saldo/transfer lunas: di HapusOrder tetap boleh dihapus.
         // QRIS (nontunai) yang paid/pending/unknown/lunas: ditolak.
         if (!$isQris && $keptLunas > 0 && $keptPaid === 0 && $keptPending === 0 && $keptUnknown === 0) {
            $del = $db->delete('kas', $where);
            if (isset($del['errno']) && (int) $del['errno'] !== 0) {
               echo json_encode(['status' => 'error', 'message' => $del['error'] ?? 'Gagal hapus pembayaran']);
               return;
            }
            $this->model('Log')->write('[HapusOrder::hapusPembayaran] id_kas=' . $idKas . ' ref=' . $ref . ' (lunas non-QRIS)');
            echo json_encode(['status' => 'success', 'message' => 'Pembayaran dihapus']);
            return;
         }

         $msg = trim((string) ($kasResult['msg'] ?? ''));
         if ($msg === '') {
            if ($keptPaid > 0 || $keptLunas > 0 || $action === 'paid' || $action === 'lunas') {
               $msg = 'Pembayaran QRIS sudah berhasil — tidak bisa dihapus';
            } elseif ($keptPending > 0) {
               $msg = 'Pembayaran QRIS masih pending — tidak bisa dihapus';
            } else {
               $msg = 'Pembayaran tidak bisa dihapus';
            }
         }
         echo json_encode(['status' => 'error', 'message' => $msg]);
         return;
      }

      if ((int) ($kasResult['deleted'] ?? 0) <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'Pembayaran tidak dihapus']);
         return;
      }

      $this->model('Log')->write('[HapusOrder::hapusPembayaran] id_kas=' . $idKas . ' ref=' . $ref);
      echo json_encode(['status' => 'success', 'message' => 'Pembayaran dihapus']);
   }

   /**
    * Hapus satu baris penyelesai (operasi) dari antrean HapusOrder.
    */
   public function hapusOperasi()
   {
      header('Content-Type: application/json; charset=utf-8');
      $idOperasi = trim((string) ($_POST['id_operasi'] ?? ''));
      if ($idOperasi === '') {
         echo json_encode(['status' => 'error', 'message' => 'ID operasi tidak valid']);
         return;
      }

      $db = $this->db(0);
      $wc = $this->wCabangForApprovalAction('operasi', 'id_operasi', $idOperasi);
      if ($wc === null) {
         echo json_encode(['status' => 'error', 'message' => 'Penyelesai tidak ditemukan']);
         return;
      }
      $idEsc = $db->escape($idOperasi);
      $row = $db->get_where_row('operasi', $wc . " AND id_operasi = '" . $idEsc . "'");
      if (!is_array($row) || empty($row['id_operasi'])) {
         echo json_encode(['status' => 'error', 'message' => 'Penyelesai tidak ditemukan']);
         return;
      }

      $idPenjualan = trim((string) ($row['id_penjualan'] ?? ''));
      $del = $db->delete('operasi', $wc . " AND id_operasi = '" . $idEsc . "'");
      if (isset($del['errno']) && (int) $del['errno'] !== 0) {
         echo json_encode(['status' => 'error', 'message' => $del['error'] ?? 'Gagal hapus penyelesai']);
         return;
      }

      if ($idPenjualan !== '') {
         $saleEsc = $db->escape($idPenjualan);
         $left = (int) ($db->count_where('operasi', $wc . " AND id_penjualan = '" . $saleEsc . "'") ?? 0);
         if ($left === 0) {
            $db->delete('notif', $wc . " AND no_ref = '" . $saleEsc . "' AND tipe = 2");
         }
      }

      $this->model('Log')->write('[HapusOrder::hapusOperasi] id_operasi=' . $idOperasi);
      echo json_encode(['status' => 'success', 'message' => 'Penyelesai dihapus']);
   }

   public function restoreRef()
   {
      header('Content-Type: application/json; charset=utf-8');
      $ref = trim((string) ($_POST['ref'] ?? ''));
      $idCabang = (int) ($_POST['id_cabang'] ?? 0);
      if ($ref === '' || $idCabang <= 0) {
         echo json_encode(['status' => 'error', 'message' => 'REF tidak valid']);
         return;
      }
      $refEsc = $this->db(0)->escape($ref);
      $wc = 'id_cabang = ' . $idCabang;
      $up = $this->db(0)->update(
         'sale',
         ['bin' => 0],
         $wc . " AND no_ref = '" . $refEsc . "' AND bin = 1"
      );
      if (isset($up['errno']) && (int) $up['errno'] !== 0) {
         echo json_encode(['status' => 'error', 'message' => $up['error'] ?? 'Gagal restore']);
         return;
      }
      echo json_encode(['status' => 'success', 'message' => 'REF #' . $ref . ' dikembalikan ke Operasi']);
   }

   /** @deprecated diganti hapusSemua — tetap ada untuk kompatibilitas singkat */
   public function hapusRelated()
   {
      $this->hapusSemua();
   }

   /** @deprecated diganti hapusSemua */
   public function hapusID()
   {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['status' => 'error', 'message' => 'Gunakan Hapus Semua (alur baru)']);
   }

   private function sumDibayarRef(string $ref, int $idCabang = 0): int
   {
      $wc = $idCabang > 0 ? ('id_cabang = ' . $idCabang) : $this->wCabangAll();
      $refEsc = $this->db(0)->escape($ref);
      $kas = $this->db(0)->get_where(
         'kas',
         $wc . " AND jenis_transaksi = 1 AND ref_transaksi = '" . $refEsc . "'"
      );
      if (!is_array($kas)) {
         return 0;
      }
      $sum = 0;
      foreach ($kas as $ka) {
         $st = (int) ($ka['status_mutasi'] ?? 0);
         if ($st === 2 || $st === 3) {
            $sum += (int) ($ka['jumlah'] ?? 0);
         }
      }
      return $sum;
   }

   private function sumTagihanRefBin0(string $ref, int $idCabang = 0): int
   {
      $wc = $idCabang > 0 ? ('id_cabang = ' . $idCabang) : $this->wCabangAll();
      $refEsc = $this->db(0)->escape($ref);
      $sales = $this->db(0)->get_where(
         'sale',
         $wc . " AND no_ref = '" . $refEsc . "' AND bin = 0"
      );
      if (!is_array($sales)) {
         $sales = [];
      }
      $sub = 0;
      foreach ($sales as $s) {
         if ((int) ($s['member'] ?? 0) !== 0) {
            continue;
         }
         $qty = round((float) ($s['qty'] ?? 0), 2);
         $min = round((float) ($s['min_order'] ?? 0), 2);
         $qtyReal = ($qty < $min) ? $min : $qty;
         $total = (float) ($s['harga'] ?? 0) * $qtyReal;
         $dq = (float) ($s['diskon_qty'] ?? 0);
         $dp = (float) ($s['diskon_partner'] ?? 0);
         if ($dq > 0) {
            $total -= $total * ($dq / 100);
         }
         if ($dp > 0) {
            $total -= $total * ($dp / 100);
         }
         $sub += (int) round($total);
      }
      $surcas = $this->db(0)->get_where('surcas', $wc . " AND no_ref = '" . $refEsc . "'");
      if (is_array($surcas)) {
         foreach ($surcas as $sc) {
            $sub += (int) ($sc['jumlah'] ?? 0);
         }
      }
      return (int) round($sub);
   }
}
