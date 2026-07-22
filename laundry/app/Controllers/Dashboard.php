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
      foreach ($cabangIds as $idCabang) {
         $rows[$idCabang] = [
            'id_cabang' => $idCabang,
            'label' => $this->cabangKode($cabangMap[$idCabang]),
            'today_qty' => 0.0,
            'yesterday_qty' => 0.0,
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

            $dayKey = (substr((string) ($sale['insertTime'] ?? ''), 0, 10) === $today) ? 'today_qty' : 'yesterday_qty';
            $rows[$idCabang][$dayKey] += round((float) ($sale['qty'] ?? 0), 2);
         }
      }

      $outRows = array_values($rows);
      usort($outRows, function ($a, $b) {
         return strcasecmp($a['label'], $b['label']);
      });

      $this->view('dashboard/cuci', [
         'rows' => $outRows,
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
      foreach ($cabangIds as $idCabang) {
         $rows[$idCabang] = [
            'id_cabang' => $idCabang,
            'label' => $this->cabangKode($cabangMap[$idCabang]),
            'lewat_qty' => 0.0,
            'today_qty' => 0.0,
            'besok_qty' => 0.0,
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
            if ($deadlineDate < $today) {
               $bucket = 'lewat_qty';
            } elseif ($deadlineDate === $today) {
               $bucket = 'today_qty';
            } elseif ($deadlineDate === $tomorrow) {
               $bucket = 'besok_qty';
            }
            if ($bucket === null) {
               continue;
            }

            $idCabang = (int) ($sale['id_cabang'] ?? 0);
            if (!isset($rows[$idCabang])) {
               continue;
            }

            $rows[$idCabang][$bucket] += round((float) ($sale['qty'] ?? 0), 2);
         }
      }

      $outRows = array_values($rows);
      usort($outRows, function ($a, $b) {
         $aScore = $a['lewat_qty'] + $a['today_qty'] + $a['besok_qty'];
         $bScore = $b['lewat_qty'] + $b['today_qty'] + $b['besok_qty'];
         if ($aScore == $bScore) {
            return strcasecmp($a['label'], $b['label']);
         }
         return ($aScore < $bScore) ? 1 : -1;
      });

      $this->view('dashboard/setrika_pack', [
         'rows' => $outRows,
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

   private function cabangKode($cabang)
   {
      $kode = trim((string) ($cabang['kode_cabang'] ?? ''));
      if ($kode !== '') {
         return strtoupper($kode);
      }
      return 'C' . (int) ($cabang['id_cabang'] ?? 0);
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
