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

         $operasiDone = $this->operasiDoneMap($sales);

         foreach ($sales as $sale) {
            if (!$this->listLayananContains((string) ($sale['list_layanan'] ?? ''), $idCuci)) {
               continue;
            }

            // Tidak hitung jika layanan paling akhir sudah ceklist
            if ($this->isEndLayananDone($sale, $operasiDone)) {
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
      // Layanan akhir yang namanya mengandung "setrika" atau "pack".
      // Hari Ini = deadline hari ini + yang lewat; Besok = deadline esok.
      $idSetrikaPack = $this->resolveLayananIdsContaining(['setrika', 'pack']);
      $cabangMap = $this->cabangOperasionalMap();
      $cabangIds = array_keys($cabangMap);

      $today = date('Y-m-d');
      $tomorrow = date('Y-m-d', strtotime('+1 day'));

      $rows = [];
      foreach ($cabangIds as $idCabang) {
         $rows[$idCabang] = [
            'id_cabang' => $idCabang,
            'label' => $this->cabangKode($cabangMap[$idCabang]),
            'today_qty' => 0.0,
            'besok_qty' => 0.0,
         ];
      }

      if (count($idSetrikaPack) > 0 && count($cabangIds) > 0) {
         $inCabang = implode(',', array_map('intval', $cabangIds));
         $where = "bin = 0 AND tuntas = 0 AND id_pelanggan <> 0 AND id_cabang IN ($inCabang)";
         $sales = $this->db(0)->get_where('sale', $where);
         if (!is_array($sales)) {
            $sales = [];
         }

         $operasiDone = $this->operasiDoneMap($sales);

         foreach ($sales as $sale) {
            $list = $this->unserializeLayanan((string) ($sale['list_layanan'] ?? ''));
            if (count($list) === 0) {
               continue;
            }

            $endLayanan = (int) end($list);
            if (!isset($idSetrikaPack[$endLayanan])) {
               continue;
            }

            // Tidak hitung jika layanan paling akhir sudah ceklist (sama rekap operasi)
            if ($this->isEndLayananDone($sale, $operasiDone)) {
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
               $bucket = 'today_qty'; // termasuk terlewat
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
         $aScore = $a['today_qty'] + $a['besok_qty'];
         $bScore = $b['today_qty'] + $b['besok_qty'];
         if ($aScore == $bScore) {
            return strcasecmp($a['label'], $b['label']);
         }
         return ($aScore < $bScore) ? 1 : -1;
      });

      $this->view('dashboard/setrika_pack', [
         'rows' => $outRows,
         'layanan_ok' => count($idSetrikaPack) > 0,
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

   /**
    * Map id_layanan => true untuk nama layanan yang mengandung salah satu keyword.
    * @param string[] $needles
    * @return array<int,bool>
    */
   private function resolveLayananIdsContaining(array $needles)
   {
      $map = [];
      if (!is_array($this->dLayanan) || count($needles) === 0) {
         return $map;
      }
      $needlesLower = [];
      foreach ($needles as $n) {
         $n = strtolower(trim((string) $n));
         if ($n !== '') {
            $needlesLower[] = $n;
         }
      }
      if (count($needlesLower) === 0) {
         return $map;
      }

      foreach ($this->dLayanan as $layanan) {
         if (!is_array($layanan)) {
            continue;
         }
         $nama = strtolower(trim((string) ($layanan['layanan'] ?? '')));
         if ($nama === '') {
            continue;
         }
         foreach ($needlesLower as $needle) {
            if (strpos($nama, $needle) !== false) {
               $id = (int) ($layanan['id_layanan'] ?? 0);
               if ($id > 0) {
                  $map[$id] = true;
               }
               break;
            }
         }
      }
      return $map;
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

   /**
    * Map operasi selesai: "id_penjualan|jenis_operasi" => true
    */
   private function operasiDoneMap($sales)
   {
      $map = [];
      $ids = [];
      if (!is_array($sales)) {
         return $map;
      }
      foreach ($sales as $sale) {
         if (isset($sale['id_penjualan'])) {
            $ids[(int) $sale['id_penjualan']] = true;
         }
      }
      if (count($ids) === 0) {
         return $map;
      }

      $inIds = implode(',', array_keys($ids));
      $operasiRows = $this->db(0)->get_where('operasi', "id_penjualan IN ($inIds)");
      if (!is_array($operasiRows)) {
         return $map;
      }

      foreach ($operasiRows as $op) {
         $key = (int) ($op['id_penjualan'] ?? 0) . '|' . (int) ($op['jenis_operasi'] ?? 0);
         $map[$key] = true;
      }
      return $map;
   }

   private function isEndLayananDone($sale, $operasiDone)
   {
      $list = $this->unserializeLayanan((string) ($sale['list_layanan'] ?? ''));
      if (count($list) === 0) {
         return false;
      }
      $endLayanan = (int) end($list);
      $idPenjualan = (int) ($sale['id_penjualan'] ?? 0);
      return isset($operasiDone[$idPenjualan . '|' . $endLayanan]);
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
