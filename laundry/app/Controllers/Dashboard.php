<?php

class Dashboard extends Controller
{
   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $data_operasi = ['title' => 'Dashboard'];
      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view('dashboard/index');
   }

   public function loadCuci()
   {
      $idCuci = $this->resolveLayananIdByName('Cuci');
      $cabangMap = $this->cabangOperasionalMap();
      $cabangIds = array_keys($cabangMap);

      $today = date('Y-m-d');
      $yesterday = date('Y-m-d', strtotime('-1 day'));

      $rows = [];
      $totals = [
         'today' => ['qty' => 0.0, 'nota' => 0],
         'yesterday' => ['qty' => 0.0, 'nota' => 0],
         'total' => ['qty' => 0.0, 'nota' => 0],
      ];

      foreach ($cabangIds as $idCabang) {
         $rows[$idCabang] = [
            'id_cabang' => $idCabang,
            'label' => $this->cabangLabel($cabangMap[$idCabang]),
            'today' => ['qty' => 0.0, 'refs' => []],
            'yesterday' => ['qty' => 0.0, 'refs' => []],
         ];
      }

      if ($idCuci > 0 && count($cabangIds) > 0) {
         $inCabang = implode(',', array_map('intval', $cabangIds));
         $where = "bin = 0 AND id_cabang IN ($inCabang)"
            . " AND (DATE(insertTime) = '$today' OR DATE(insertTime) = '$yesterday')";
         $sales = $this->db(0)->get_where('sale', $where);
         if (!is_array($sales)) {
            $sales = [];
         }

         foreach ($sales as $sale) {
            if (!$this->listLayananContains((string) ($sale['list_layanan'] ?? ''), $idCuci)) {
               continue;
            }

            $idCabang = (int) ($sale['id_cabang'] ?? 0);
            if (!isset($rows[$idCabang])) {
               continue;
            }

            $dayKey = (substr((string) ($sale['insertTime'] ?? ''), 0, 10) === $today) ? 'today' : 'yesterday';
            $qty = round((float) ($sale['qty'] ?? 0), 2);
            $ref = (string) ($sale['no_ref'] ?? '');

            $rows[$idCabang][$dayKey]['qty'] += $qty;
            if ($ref !== '') {
               $rows[$idCabang][$dayKey]['refs'][$ref] = true;
            }
         }
      }

      $outRows = [];
      foreach ($rows as $idCabang => $row) {
         $todayNota = count($row['today']['refs']);
         $yesterdayNota = count($row['yesterday']['refs']);
         $todayQty = $row['today']['qty'];
         $yesterdayQty = $row['yesterday']['qty'];
         $totalQty = $todayQty + $yesterdayQty;
         $totalNota = count($row['today']['refs'] + $row['yesterday']['refs']);

         $outRows[] = [
            'id_cabang' => $idCabang,
            'label' => $row['label'],
            'today_qty' => $todayQty,
            'today_nota' => $todayNota,
            'yesterday_qty' => $yesterdayQty,
            'yesterday_nota' => $yesterdayNota,
            'total_qty' => $totalQty,
            'total_nota' => $totalNota,
         ];

         $totals['today']['qty'] += $todayQty;
         $totals['today']['nota'] += $todayNota;
         $totals['yesterday']['qty'] += $yesterdayQty;
         $totals['yesterday']['nota'] += $yesterdayNota;
         $totals['total']['qty'] += $totalQty;
         $totals['total']['nota'] += $totalNota;
      }

      usort($outRows, function ($a, $b) {
         return strcasecmp($a['label'], $b['label']);
      });

      $this->view('dashboard/cuci', [
         'rows' => $outRows,
         'totals' => $totals,
         'today' => $today,
         'yesterday' => $yesterday,
         'layanan_ok' => $idCuci > 0,
      ]);
   }

   public function loadSetrikaPack()
   {
      $idSetrika = $this->resolveLayananIdByName('Setrika');
      $cabangMap = $this->cabangOperasionalMap();
      $cabangIds = array_keys($cabangMap);

      $today = date('Y-m-d');
      $tomorrow = date('Y-m-d', strtotime('+1 day'));

      $rows = [];
      $totals = [
         'due' => ['qty' => 0.0, 'nota' => 0],
         'besok' => ['qty' => 0.0, 'nota' => 0],
      ];

      foreach ($cabangIds as $idCabang) {
         $rows[$idCabang] = [
            'id_cabang' => $idCabang,
            'label' => $this->cabangLabel($cabangMap[$idCabang]),
            'due' => ['qty' => 0.0, 'refs' => []],
            'besok' => ['qty' => 0.0, 'refs' => []],
         ];
      }

      if ($idSetrika > 0 && count($cabangIds) > 0) {
         $inCabang = implode(',', array_map('intval', $cabangIds));
         $where = "bin = 0 AND tuntas = 0 AND id_pelanggan <> 0 AND id_cabang IN ($inCabang)";
         $sales = $this->db(0)->get_where('sale', $where);
         if (!is_array($sales)) {
            $sales = [];
         }

         $ids = [];
         foreach ($sales as $sale) {
            if (isset($sale['id_penjualan'])) {
               $ids[] = (int) $sale['id_penjualan'];
            }
         }

         $operasiDone = [];
         if (count($ids) > 0) {
            $inIds = implode(',', $ids);
            $operasiRows = $this->db(0)->get_where(
               'operasi',
               "id_penjualan IN ($inIds) AND jenis_operasi = " . (int) $idSetrika
            );
            if (!is_array($operasiRows)) {
               $operasiRows = [];
            }
            foreach ($operasiRows as $op) {
               $operasiDone[(int) $op['id_penjualan']] = true;
            }
         }

         foreach ($sales as $sale) {
            $list = $this->unserializeLayanan((string) ($sale['list_layanan'] ?? ''));
            if (count($list) === 0) {
               continue;
            }

            $endLayanan = (int) end($list);
            if ($endLayanan !== $idSetrika) {
               continue;
            }

            $idPenjualan = (int) ($sale['id_penjualan'] ?? 0);
            $letak = trim((string) ($sale['letak'] ?? ''));
            $setrikaDone = isset($operasiDone[$idPenjualan]);

            // Pending setrika OR pack/RAK (setrika done, letak empty)
            $isPending = !$setrikaDone;
            $isRak = $setrikaDone && $letak === '';
            if (!$isPending && !$isRak) {
               continue;
            }

            $deadlineDate = $this->deadlineDate(
               (string) ($sale['insertTime'] ?? ''),
               (int) ($sale['hari'] ?? 0),
               (int) ($sale['jam'] ?? 0)
            );
            if ($deadlineDate === '') {
               continue;
            }

            $bucket = null;
            if ($deadlineDate <= $today) {
               $bucket = 'due';
            } elseif ($deadlineDate === $tomorrow) {
               $bucket = 'besok';
            }
            if ($bucket === null) {
               continue;
            }

            $idCabang = (int) ($sale['id_cabang'] ?? 0);
            if (!isset($rows[$idCabang])) {
               continue;
            }

            $qty = round((float) ($sale['qty'] ?? 0), 2);
            $ref = (string) ($sale['no_ref'] ?? '');
            $rows[$idCabang][$bucket]['qty'] += $qty;
            if ($ref !== '') {
               $rows[$idCabang][$bucket]['refs'][$ref] = true;
            }
         }
      }

      $outRows = [];
      foreach ($rows as $idCabang => $row) {
         $dueQty = $row['due']['qty'];
         $dueNota = count($row['due']['refs']);
         $besokQty = $row['besok']['qty'];
         $besokNota = count($row['besok']['refs']);

         $outRows[] = [
            'id_cabang' => $idCabang,
            'label' => $row['label'],
            'due_qty' => $dueQty,
            'due_nota' => $dueNota,
            'besok_qty' => $besokQty,
            'besok_nota' => $besokNota,
         ];

         $totals['due']['qty'] += $dueQty;
         $totals['due']['nota'] += $dueNota;
         $totals['besok']['qty'] += $besokQty;
         $totals['besok']['nota'] += $besokNota;
      }

      usort($outRows, function ($a, $b) {
         $aScore = $a['due_qty'] + $a['besok_qty'];
         $bScore = $b['due_qty'] + $b['besok_qty'];
         if ($aScore == $bScore) {
            return strcasecmp($a['label'], $b['label']);
         }
         return ($aScore < $bScore) ? 1 : -1;
      });

      $this->view('dashboard/setrika_pack', [
         'rows' => $outRows,
         'totals' => $totals,
         'today' => $today,
         'tomorrow' => $tomorrow,
         'layanan_ok' => $idSetrika > 0,
      ]);
   }

   private function resolveLayananIdByName($name)
   {
      if (!is_array($this->dLayanan)) {
         return 0;
      }
      foreach ($this->dLayanan as $layanan) {
         if (!is_array($layanan)) {
            continue;
         }
         $nama = isset($layanan['layanan']) ? trim((string) $layanan['layanan']) : '';
         if ($nama !== '' && strcasecmp($nama, $name) === 0) {
            return (int) ($layanan['id_layanan'] ?? 0);
         }
      }
      return 0;
   }

   private function cabangOperasionalMap()
   {
      $list = $this->getCabangOperasional();
      if (!is_array($list)) {
         return [];
      }
      $map = [];
      foreach ($list as $cabang) {
         if (!is_array($cabang) || !isset($cabang['id_cabang'])) {
            continue;
         }
         $map[(int) $cabang['id_cabang']] = $cabang;
      }
      return $map;
   }

   private function cabangLabel($cabang)
   {
      $kode = trim((string) ($cabang['kode_cabang'] ?? ''));
      $nama = trim((string) ($cabang['nama'] ?? ''));
      if ($kode !== '' && $nama !== '') {
         return strtoupper($kode) . ' — ' . $nama;
      }
      if ($nama !== '') {
         return $nama;
      }
      if ($kode !== '') {
         return strtoupper($kode);
      }
      return 'Cabang #' . (int) ($cabang['id_cabang'] ?? 0);
   }

   private function unserializeLayanan($raw)
   {
      if ($raw === '') {
         return [];
      }
      $list = @unserialize($raw);
      if (!is_array($list)) {
         return [];
      }
      $out = [];
      foreach ($list as $id) {
         $out[] = (int) $id;
      }
      return $out;
   }

   private function listLayananContains($raw, $idLayanan)
   {
      $list = $this->unserializeLayanan($raw);
      return in_array((int) $idLayanan, $list, true);
   }

   private function deadlineDate($insertTime, $hari, $jam)
   {
      if ($insertTime === '') {
         return '';
      }
      $deadline = date('Y-m-d', strtotime($insertTime . ' + ' . (int) $hari . ' days'));
      $deadline = date('Y-m-d H:i:s', strtotime($deadline . ' + ' . (int) $jam . ' hours'));
      return date('Y-m-d', strtotime($deadline));
   }
}
