<?php

class Absen extends Controller
{
   public function __construct()
   {
      $this->session_cek();
      $this->operating_data();
   }

   public function index()
   {
      $data_operasi = ['title' => 'Karyawan Absen'];
      $viewData = __CLASS__ . '/form';

      $this->view('layout', ['data_operasi' => $data_operasi]);
      $this->view($viewData);
   }

   public function load()
   {
      $viewData = __CLASS__ . '/content';
      $tgl = date('Y-m-d');
      $data['hari_ini'] = $this->db(0)->get_where('absen', 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND tanggal LIKE '" . $tgl . "%'");

      $tgl_kemarin = date('Y-m-d', strtotime("-1 day"));
      $data['kemarin'] = $this->db(0)->get_where('absen', 'id_cabang = ' . $_SESSION[URL::SESSID]['user']['id_cabang'] . " AND tanggal LIKE '" . $tgl_kemarin . "%'");

      $this->view($viewData, $data);
   }

   /** Riwayat absen untuk modal Perbaikan Absen di halaman Gaji (read-only untuk periode lama). */
   public function gaji_list()
   {
      header('Content-Type: application/json; charset=utf-8');
      $idKaryawan = (int) ($_GET['id_karyawan'] ?? 0);
      $periode = substr(trim((string) ($_GET['periode'] ?? '')), 0, 7);
      $jenis = isset($_GET['jenis']) && $_GET['jenis'] !== '' ? (int) $_GET['jenis'] : -1;
      if ($idKaryawan < 1 || !preg_match('/^\d{4}-\d{2}$/', $periode) || !in_array($jenis, [0, 1, 2, 3], true)) {
         echo json_encode(['code' => 0, 'msg' => 'Parameter perbaikan absen tidak valid']);
         return;
      }
      $rows = $this->db(0)->query_array(
         "SELECT a.id, a.id_karyawan, a.id_cabang, a.jenis, a.tanggal, a.jam, u.nama_user, c.kode_cabang
          FROM absen a INNER JOIN user u ON u.id_user = a.id_karyawan
          LEFT JOIN cabang c ON c.id_cabang = a.id_cabang
          WHERE a.id_karyawan = $idKaryawan
            AND a.jenis = $jenis AND a.tanggal LIKE '" . $this->db(0)->escape($periode) . "%'
          ORDER BY a.tanggal DESC, a.jam DESC, a.id DESC"
      );
      echo json_encode(['code' => 1, 'data' => is_array($rows) ? $rows : []]);
   }

   /** Ubah jenis tugas absen dengan batas tanggal dan access key yang sama seperti hapus. */
   public function ubah()
   {
      header('Content-Type: application/json; charset=utf-8');
      $id = (int) ($_POST['id'] ?? 0);
      $jenis = (int) ($_POST['jenis'] ?? -1);
      $accessKey = (string) ($_POST['access_key'] ?? '');
      $dariGaji = in_array((string) ($_POST['gaji_koreksi'] ?? '0'), ['1', 'true'], true);
      $idCabang = (int) $_SESSION[URL::SESSID]['user']['id_cabang'];
      $rowWhere = 'id = ' . $id . ($dariGaji ? '' : ' AND id_cabang = ' . $idCabang);
      $row = $id > 0 ? $this->db(0)->get_where_row('absen', $rowWhere) : null;
      if (!$row || !in_array($jenis, [0, 1, 2, 3], true)) {
         echo json_encode(['code' => 0, 'msg' => 'GAGAL - DATA ABSEN ATAU JENIS TUGAS TIDAK VALID']); return;
      }
      $tgl = substr((string) ($row['tanggal'] ?? ''), 0, 10);
      $idCabang = (int) ($row['id_cabang'] ?? $idCabang);
      $batas = date('Y-m-d', strtotime('-31 days'));
      if (($dariGaji && ($tgl < $batas || $tgl > date('Y-m-d'))) || (!$dariGaji && (!in_array($tgl, [date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))], true) || substr($tgl, 0, 7) !== date('Y-m')))) {
         echo json_encode(['code' => 0, 'msg' => $dariGaji ? 'GAGAL - KOREKSI GAJI MAKSIMAL 31 HARI KE BELAKANG' : 'GAGAL - PERBAIKAN HANYA UNTUK ABSEN HARI INI ATAU KEMARIN DI BULAN INI']); return;
      }
      $idKaryawan = (int) ($row['id_karyawan'] ?? 0);
      $ok = (bool) $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
      if (!$ok && (int) ($this->id_privilege ?? 0) === 100) {
         $ok = (bool) $this->helper('User')->by_id_access_key((int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0), $accessKey);
      }
      if (!$ok) { echo json_encode(['code' => 0, 'msg' => 'GAGAL - ACCESS KEY TIDAK COCOK']); return; }
      $sameGroup = $jenis === 1 ? 'jenis = 1' : 'jenis IN (0,2,3)';
      $duplicate = $this->db(0)->count_where('absen', "id <> $id AND id_karyawan = $idKaryawan AND $sameGroup AND tanggal = '" . $this->db(0)->escape($tgl) . "'");
      if ($duplicate > 0) { echo json_encode(['code' => 0, 'msg' => 'GAGAL - SUDAH ADA ABSEN PADA GRUP TUGAS TERSEBUT']); return; }
      if ($jenis === 0 || $jenis === 1) {
         $cabang = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . $idCabang);
         $max = (int) ($cabang[$jenis . '_max'] ?? 0);
         $used = $this->db(0)->count_where('absen', "id <> $id AND id_cabang = $idCabang AND jenis = $jenis AND tanggal = '" . $this->db(0)->escape($tgl) . "'");
      } else {
         $max = 1;
         $used = $this->db(0)->count_where('absen', "id <> $id AND jenis = $jenis AND tanggal = '" . $this->db(0)->escape($tgl) . "'");
      }
      if ($used >= $max) { echo json_encode(['code' => 0, 'msg' => 'GAGAL - MELEBIHI BATAS ABSEN HARIAN']); return; }
      $up = $this->db(0)->update('absen', ['jenis' => $jenis], 'id = ' . $id . ' AND id_cabang = ' . $idCabang);
      echo json_encode(['code' => empty($up['errno']) ? 1 : 0, 'msg' => empty($up['errno']) ? 'ABSEN BERHASIL DIPERBAIKI' : ($up['error'] ?? 'GAGAL MEMPERBAIKI ABSEN')]);
   }

   function hapus()
   {
      header('Content-Type: application/json; charset=utf-8');

      $id = (int) ($_POST['id'] ?? 0);
      $accessKey = $_POST['access_key'] ?? '';
      if ($id <= 0) {
         echo json_encode(['code' => 0, 'msg' => 'GAGAL - ID ABSEN TIDAK VALID']);
         exit;
      }

      $dariGaji = in_array((string) ($_POST['gaji_koreksi'] ?? '0'), ['1', 'true'], true);
      $idCabang = (int) $_SESSION[URL::SESSID]['user']['id_cabang'];
      $row = $this->db(0)->get_where_row('absen', 'id = ' . $id . ($dariGaji ? '' : ' AND id_cabang = ' . $idCabang));
      if (!$row || empty($row['id'])) {
         echo json_encode(['code' => 0, 'msg' => 'GAGAL - DATA ABSEN TIDAK DITEMUKAN']);
         exit;
      }

      $tglAbsen = substr(trim((string) ($row['tanggal'] ?? '')), 0, 10);
      $hariIni = date('Y-m-d');
      $kemarin = date('Y-m-d', strtotime('-1 day'));
      $idCabang = (int) ($row['id_cabang'] ?? $idCabang);
      $batasKoreksi = date('Y-m-d', strtotime('-31 days'));
      if (!$dariGaji && $tglAbsen !== $hariIni && $tglAbsen !== $kemarin) {
         echo json_encode(['code' => 0, 'msg' => 'GAGAL - HANYA ABSEN HARI INI ATAU KEMARIN YANG BOLEH DIHAPUS']);
         exit;
      }
      if ($dariGaji && ($tglAbsen < $batasKoreksi || $tglAbsen > $hariIni)) {
         echo json_encode(['code' => 0, 'msg' => 'GAGAL - KOREKSI GAJI MAKSIMAL 31 HARI KE BELAKANG']);
         exit;
      }
      if (!$dariGaji && date('Y-m', strtotime($tglAbsen)) !== date('Y-m')) {
         echo json_encode(['code' => 0, 'msg' => 'GAGAL - HAPUS ABSEN HANYA UNTUK BULAN INI']);
         exit;
      }

      $idKaryawan = (int) ($row['id_karyawan'] ?? 0);
      $okKaryawan = (bool) $this->helper('User')->by_id_access_key($idKaryawan, $accessKey);
      $okAdmin = false;
      // Admin yang sedang login boleh hapus dengan access_key miliknya sendiri
      if (!$okKaryawan && (int) ($this->id_privilege ?? 0) === 100) {
         $idAdmin = (int) ($_SESSION[URL::SESSID]['user']['id_user'] ?? 0);
         if ($idAdmin > 0) {
            $okAdmin = (bool) $this->helper('User')->by_id_access_key($idAdmin, $accessKey);
         }
      }
      if (!$okKaryawan && !$okAdmin) {
         $msg = ((int) ($this->id_privilege ?? 0) === 100)
            ? 'GAGAL - ACCESS KEY TIDAK COCOK (KARYAWAN YANG ABSEN ATAU ADMIN YANG LOGIN)'
            : 'GAGAL - ACCESS KEY TIDAK COCOK DENGAN KARYAWAN YANG ABSEN';
         echo json_encode(['code' => 0, 'msg' => $msg]);
         exit;
      }

      $del = $this->db(0)->delete('absen', 'id = ' . $id . ' AND id_cabang = ' . $idCabang);
      if (isset($del['errno']) && (int) $del['errno'] === 0) {
         echo json_encode(['code' => 1, 'msg' => 'ABSEN BERHASIL DIHAPUS']);
      } else {
         echo json_encode(['code' => 0, 'msg' => $del['error'] ?? 'GAGAL MENGHAPUS ABSEN']);
      }
      exit;
   }

   function absen()
   {
      $id_karyawan = (int) $_POST['karyawan'];
      $jenis = (int) $_POST['jenis'];
      $tgl_post = $_POST['tgl'];
      $dariGaji = in_array((string) ($_POST['gaji_koreksi'] ?? '0'), ['1', 'true'], true);
      $idCabang = (int) $_SESSION[URL::SESSID]['user']['id_cabang'];
      if ($dariGaji && isset($_POST['id_cabang'])) {
         $targetCabang = (int) $_POST['id_cabang'];
         if ($targetCabang > 0 && $this->db(0)->count_where('cabang', 'id_cabang = ' . $targetCabang) > 0) {
            $idCabang = $targetCabang;
         }
      }
      $cabangAbsen = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . $idCabang);

      $user_absen = $this->db(0)->get_where_row('user', "id_user = " . $id_karyawan . " AND en = 1");

      $tgl = date('Y-m-d');
      if ($dariGaji) {
         $tglKoreksi = trim((string) ($_POST['tanggal_koreksi'] ?? ''));
         $batasKoreksi = date('Y-m-d', strtotime('-31 days'));
         if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglKoreksi) || $tglKoreksi < $batasKoreksi || $tglKoreksi > $tgl) {
            print_r(json_encode(['code' => 0, 'msg' => 'GAGAL - KOREKSI GAJI MAKSIMAL 31 HARI KE BELAKANG']));
            exit();
         }
         $tgl = $tglKoreksi;
      } elseif ($tgl_post == 1) {
         $tgl = date('Y-m-d', strtotime("-1 days"));
      }

      $jam = date('H:i');

      if ($user_absen) {
         $cols = "id_karyawan,jenis,tanggal,jam,id_cabang";
         $vals = $user_absen['id_user'] . "," . $jenis . ",'" . $tgl . "','" . $jam . "'," . $_SESSION[URL::SESSID]['user']['id_cabang'];


         if (!in_array($jenis, [0, 1, 2, 3], true)) {
            $res = [
               'code' => 0,
               'msg' => "GAGAL - JENIS TUGAS TIDAK VALID"
            ];
            print_r(json_encode($res));
            exit();
         }

         // Grup absen per karyawan per hari (lintas cabang):
         // - jenis 1 (Jaga Malam): maks 1x per hari
         // - jenis 0, 2, 3 (Cuci/Delivery/Maintenance): maks 1x gabungan per hari
         if ($jenis === 1) {
            $where_user = "id_karyawan = " . $user_absen['id_user'] . " AND jenis = 1 AND tanggal = '" . $tgl . "'";
            $msg_duplikat = "GAGAL - SUDAH ABSEN JAGA MALAM PADA TANGGAL TERSEBUT";
         } else {
            $where_user = "id_karyawan = " . $user_absen['id_user'] . " AND jenis IN (0, 2, 3) AND tanggal = '" . $tgl . "'";
            $msg_duplikat = "GAGAL - SUDAH ABSEN CUCI/DELIVERY/MAINTENANCE PADA TANGGAL TERSEBUT";
         }

         $cek_user = $this->db(0)->count_where('absen', $where_user);
         if ($cek_user > 0) {
            $res = [
               'code' => 0,
               'msg' => $msg_duplikat
            ];
            print_r(json_encode($res));
            exit();
         }

         //CEK MAX PER CABANG
         if ($jenis == 0) {
            $where = "id_cabang = " . $idCabang . " AND jenis = " . $jenis . " AND tanggal = '" . $tgl . "'";
            $max = $cabangAbsen[$jenis . '_max'] ?? 0;
         } else if ($jenis == 1) {
            $where = "id_cabang = " . $idCabang . " AND jenis = " . $jenis . " AND tanggal = '" . $tgl . "'";
            $max = $cabangAbsen[$jenis . '_max'] ?? 0;
         } else if ($jenis == 2 || $jenis == 3) {
            $where = "jenis = " . $jenis . " AND tanggal = '" . $tgl . "'";
            $max = 1;
         }
         $cek = $this->db(0)->count_where('absen', $where);

         if ($cek < $max) {
            $data = [
               'id_karyawan' => $user_absen['id_user'],
               'jenis' => $jenis,
               'tanggal' => $tgl,
               'jam' => $jam,
               'id_cabang' => $idCabang
            ];
            $in = $this->db(0)->insert('absen', $data);
            if ($in['errno'] == 0) {
               $res = [
                  'code' => 1,
                  'msg' => "ABSEN SUKSES"
               ];
               print_r(json_encode($res));
            } else {
               $res = [
                  'code' => 0,
                  'msg' => $in['error']
               ];
               print_r(json_encode($res));
            }
         } else {
            $res = [
               'code' => 0,
               'msg' => "GAGAL - MELEBIHI BATAS ABSEN HARIAN"
            ];
            print_r(json_encode($res));
         }
      } else {
         $res = [
            'code' => 0,
            'msg' => "GAGAL - KARYAWAN TIDAK DITEMUKAN"
         ];
         print_r(json_encode($res));
      }
   }
}
