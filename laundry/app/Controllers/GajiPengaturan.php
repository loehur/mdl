<?php

/**
 * Acuan global fee layanan laundry (gaji_laundry_ref)
 * + fee Laundry Terima / Kembali (gaji_pengali_ref id 1 & 2)
 * + rumus fee snapshot Cuci / Jaga Malam (gaji_fee_formula).
 */
class GajiPengaturan extends Controller
{
   private $formulaDefaults = [
      'malam' => ['pengali' => 1.0, 'clamp_min' => 14000, 'clamp_max' => 32000],
      'cuci' => ['pengali' => 4.0, 'clamp_min' => 65000, 'clamp_max' => 85000],
   ];

   public function __construct()
   {
      $this->session_cek(1);
      $this->operating_data();
   }

   public function index()
   {
      $rows = $this->db(0)->get_order('gaji_laundry_ref', 'jenis_penjualan ASC, id_layanan ASC');
      if (!is_array($rows)) {
         $rows = $rows ? iterator_to_array($rows) : [];
      }

      $pengaliRefRaw = $this->db(0)->get('gaji_pengali_ref');
      if (!is_array($pengaliRefRaw)) {
         $pengaliRefRaw = $pengaliRefRaw ? iterator_to_array($pengaliRefRaw) : [];
      }
      $pengaliRef = [1 => 0, 2 => 0];
      foreach ($pengaliRefRaw as $pr) {
         $idP = (int) ($pr['id_pengali'] ?? 0);
         if ($idP === 1 || $idP === 2) {
            $pengaliRef[$idP] = (int) ($pr['gaji_pengali'] ?? 0);
         }
      }

      $this->view('layout', ['data_operasi' => ['title' => 'Pengaturan Gaji']]);
      $this->view('gaji/pengaturan', [
         'rows' => $rows,
         'pengali_ref' => $pengaliRef,
         'fee_formula' => $this->loadFeeFormulas(),
      ]);
   }

   private function loadFeeFormulas(): array
   {
      $out = $this->formulaDefaults;
      $raw = $this->db(0)->get('gaji_fee_formula');
      if (!is_array($raw)) {
         $raw = $raw ? iterator_to_array($raw) : [];
      }
      foreach ($raw as $row) {
         $kode = (string) ($row['kode'] ?? '');
         if (!isset($out[$kode])) {
            continue;
         }
         $out[$kode] = [
            'pengali' => (float) ($row['pengali'] ?? $out[$kode]['pengali']),
            'clamp_min' => (int) ($row['clamp_min'] ?? $out[$kode]['clamp_min']),
            'clamp_max' => (int) ($row['clamp_max'] ?? $out[$kode]['clamp_max']),
         ];
      }
      return $out;
   }

   /**
    * Update rumus fee snapshot (malam / cuci).
    */
   public function updateFormula()
   {
      header('Content-Type: text/plain; charset=utf-8');

      $kode = (string) ($_POST['kode'] ?? '');
      $col = (string) ($_POST['col'] ?? '');
      $valueRaw = $_POST['value'] ?? '';

      if (!isset($this->formulaDefaults[$kode])) {
         echo 'Kode tidak valid';
         return;
      }
      if (!in_array($col, ['pengali', 'clamp_min', 'clamp_max'], true)) {
         echo 'Kolom tidak valid';
         return;
      }

      $defaults = $this->formulaDefaults[$kode];
      $row = $this->db(0)->get_where_row('gaji_fee_formula', "kode = '" . $this->db(0)->escape($kode) . "'");
      $pengali = isset($row['pengali']) ? (float) $row['pengali'] : (float) $defaults['pengali'];
      $clampMin = isset($row['clamp_min']) ? (int) $row['clamp_min'] : (int) $defaults['clamp_min'];
      $clampMax = isset($row['clamp_max']) ? (int) $row['clamp_max'] : (int) $defaults['clamp_max'];

      if ($col === 'pengali') {
         $pengali = (float) $valueRaw;
         if (!is_finite($pengali) || $pengali <= 0) {
            echo 'Pengali harus > 0';
            return;
         }
      } else {
         $intVal = (int) $valueRaw;
         if ($intVal < 0) {
            echo 'Clamp tidak boleh negatif';
            return;
         }
         if ($col === 'clamp_min') {
            $clampMin = $intVal;
         } else {
            $clampMax = $intVal;
         }
      }

      if ($clampMin > $clampMax) {
         echo 'Clamp min tidak boleh lebih besar dari clamp max';
         return;
      }

      $payload = [
         'pengali' => $pengali,
         'clamp_min' => $clampMin,
         'clamp_max' => $clampMax,
      ];

      $ada = $this->db(0)->count_where('gaji_fee_formula', "kode = '" . $this->db(0)->escape($kode) . "'");
      if ($ada > 0) {
         $do = $this->db(0)->update(
            'gaji_fee_formula',
            $payload,
            "kode = '" . $this->db(0)->escape($kode) . "'"
         );
      } else {
         $payload['kode'] = $kode;
         $do = $this->db(0)->insert('gaji_fee_formula', $payload);
      }

      if (($do['errno'] ?? 1) == 0) {
         echo 1;
      } else {
         echo $do['error'] ?? 'Gagal menyimpan';
      }
   }

   /**
    * Upsert fee global Terima (1) / Kembali (2).
    */
   public function upsertPengali()
   {
      header('Content-Type: text/plain; charset=utf-8');

      $idPengali = (int) ($_POST['id_pengali'] ?? 0);
      $fee = (int) ($_POST['gaji_pengali'] ?? 0);

      if (!in_array($idPengali, [1, 2], true)) {
         echo 'Hanya Laundry Terima / Kembali';
         return;
      }
      if ($fee < 0) {
         echo 'Nilai tidak boleh negatif';
         return;
      }

      $ada = $this->db(0)->count_where('gaji_pengali_ref', 'id_pengali = ' . $idPengali);
      if ($ada > 0) {
         $do = $this->db(0)->update(
            'gaji_pengali_ref',
            ['gaji_pengali' => $fee],
            'id_pengali = ' . $idPengali
         );
      } else {
         $do = $this->db(0)->insert('gaji_pengali_ref', [
            'id_pengali' => $idPengali,
            'gaji_pengali' => $fee,
         ]);
      }

      if (($do['errno'] ?? 1) == 0) {
         echo 1;
      } else {
         echo $do['error'] ?? 'Gagal menyimpan';
      }
   }

   public function insert()
   {
      header('Content-Type: text/plain; charset=utf-8');

      $jenis = (int) ($_POST['jenis_penjualan'] ?? 0);
      $layanan = (int) ($_POST['id_layanan'] ?? 0);
      $fee = (int) ($_POST['gaji_laundry'] ?? 0);
      $target = (int) ($_POST['target'] ?? 0);
      $maxTarget = (int) ($_POST['max_target'] ?? 0);
      $bonus = (int) ($_POST['bonus_target'] ?? 0);

      if ($jenis < 1 || $layanan < 1) {
         echo 'Jenis penjualan dan layanan wajib diisi';
         return;
      }
      if ($fee < 0 || $target < 0 || $maxTarget < 0 || $bonus < 0) {
         echo 'Nilai tidak boleh negatif';
         return;
      }

      $ada = $this->db(0)->count_where(
         'gaji_laundry_ref',
         'jenis_penjualan = ' . $jenis . ' AND id_layanan = ' . $layanan
      );
      if ($ada > 0) {
         echo 'DATA SUDAH ADA';
         return;
      }

      $do = $this->db(0)->insert('gaji_laundry_ref', [
         'jenis_penjualan' => $jenis,
         'id_layanan' => $layanan,
         'gaji_laundry' => $fee,
         'target' => $target,
         'max_target' => $maxTarget,
         'bonus_target' => $bonus,
      ]);

      if (($do['errno'] ?? 1) == 0) {
         echo 1;
      } else {
         echo $do['error'] ?? 'Gagal menyimpan';
      }
   }

   public function update()
   {
      header('Content-Type: text/plain; charset=utf-8');

      $id = (int) ($_POST['id'] ?? 0);
      $col = (string) ($_POST['col'] ?? '');
      $value = (int) ($_POST['value'] ?? 0);

      $allowed = ['gaji_laundry', 'target', 'max_target', 'bonus_target'];
      if ($id < 1 || !in_array($col, $allowed, true)) {
         echo 'Invalid';
         return;
      }
      if ($value < 0) {
         echo 'Nilai tidak boleh negatif';
         return;
      }

      $do = $this->db(0)->update('gaji_laundry_ref', [$col => $value], 'id = ' . $id);
      if (($do['errno'] ?? 1) == 0) {
         echo 1;
      } else {
         echo $do['error'] ?? 'Gagal update';
      }
   }

   public function delete()
   {
      header('Content-Type: text/plain; charset=utf-8');

      $id = (int) ($_POST['id'] ?? 0);
      if ($id < 1) {
         echo 'Invalid';
         return;
      }

      $do = $this->db(0)->delete('gaji_laundry_ref', 'id = ' . $id);
      if (($do['errno'] ?? 1) == 0) {
         echo 1;
      } else {
         echo $do['error'] ?? 'Gagal hapus';
      }
   }

   /**
    * Preview fee snapshot semua cabang untuk rumus malam/cuci.
    * Fee bulan Y-m dihitung dari pendapatan snapshot mode=2 bulan sebelumnya.
    * GET/POST: kode=malam|cuci, ym=YYYY-MM (opsional, default bulan ini).
    */
   public function previewFeeCabang()
   {
      header('Content-Type: application/json; charset=utf-8');

      $kode = (string) ($_REQUEST['kode'] ?? '');
      if (!isset($this->formulaDefaults[$kode])) {
         echo json_encode(['ok' => 0, 'msg' => 'Kode tidak valid']);
         return;
      }

      $ym = trim((string) ($_REQUEST['ym'] ?? date('Y-m')));
      if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
         $ym = date('Y-m');
      }
      $periodeLalu = date('Y-m', strtotime($ym . '-01 -1 month'));

      $formulas = $this->loadFeeFormulas();
      $f = $formulas[$kode];
      $pengali = (float) $f['pengali'];
      $clampMin = (int) $f['clamp_min'];
      $clampMax = (int) $f['clamp_max'];

      $dGaji = $this->helper('D_Gaji');
      $listCabang = $this->getCabangOperasional();
      if (!is_array($listCabang)) {
         $listCabang = [];
      }

      $rows = [];
      foreach ($listCabang as $c) {
         $idCabang = (int) ($c['id_cabang'] ?? 0);
         if ($idCabang < 1) {
            continue;
         }
         $pendapatan = $dGaji->getSnapshotTotalPendapatanCabang($idCabang, $periodeLalu);
         $hasSnapshot = ($pendapatan !== null);
         if ($kode === 'malam') {
            $fee = $dGaji->feeMalamDariPendapatan($pendapatan);
         } else {
            $fee = $dGaji->feeCuciDariPendapatan($pendapatan);
         }

         $raw = null;
         if ($hasSnapshot) {
            $raw = (int) round((((float) $pendapatan) / 1000) * $pengali);
         }

         $rows[] = [
            'id_cabang' => $idCabang,
            'kode_cabang' => (string) ($c['kode_cabang'] ?? ('#' . $idCabang)),
            'alamat' => (string) ($c['alamat'] ?? ''),
            'pendapatan' => $hasSnapshot ? (int) $pendapatan : null,
            'fee_raw' => $raw,
            'fee' => (int) $fee,
            'has_snapshot' => $hasSnapshot ? 1 : 0,
            'clamped' => ($hasSnapshot && $raw !== null && ($raw < $clampMin || $raw > $clampMax)) ? 1 : 0,
         ];
      }

      usort($rows, function ($a, $b) {
         return strcmp($a['kode_cabang'], $b['kode_cabang']);
      });

      $label = $kode === 'malam' ? 'Jaga Malam' : 'Cuci';
      echo json_encode([
         'ok' => 1,
         'kode' => $kode,
         'label' => $label,
         'ym' => $ym,
         'periode_lalu' => $periodeLalu,
         'pengali' => $pengali,
         'clamp_min' => $clampMin,
         'clamp_max' => $clampMax,
         'rows' => $rows,
      ]);
   }
}
