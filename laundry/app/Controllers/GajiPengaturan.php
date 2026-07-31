<?php

/**
 * Acuan global fee layanan laundry (gaji_laundry_ref).
 * Satu set fee/target/max/bonus per jenis penjualan + layanan untuk semua karyawan.
 */
class GajiPengaturan extends Controller
{
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

      $this->view('layout', ['data_operasi' => ['title' => 'Pengaturan Gaji']]);
      $this->view('gaji/pengaturan', ['rows' => $rows]);
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
}
