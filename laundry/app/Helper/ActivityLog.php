<?php

/**
 * Log universal aktifitas penting (delivery, sales, kas, dll).
 * Tabel: activity_log
 */
class ActivityLog extends Controller
{
   /**
    * Tulis satu baris activity_log.
    * Wajib: modul, aksi. Lainnya opsional.
    *
    * @param array $data
    * @return array|int|false hasil insert DB
    */
   public function write(array $data)
   {
      $modul = trim((string) ($data['modul'] ?? ''));
      $aksi = trim((string) ($data['aksi'] ?? ''));
      if ($modul === '' || $aksi === '') {
         return false;
      }

      $meta = $data['meta'] ?? null;
      if (is_array($meta) || is_object($meta)) {
         $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
      } elseif ($meta === null || $meta === '') {
         $metaJson = null;
      } else {
         $metaJson = (string) $meta;
      }

      $row = [
         'modul' => $modul,
         'aksi' => $aksi,
         'id_ref' => isset($data['id_ref']) ? (string) $data['id_ref'] : null,
         'ref' => isset($data['ref']) ? (string) $data['ref'] : null,
         'id_karyawan' => isset($data['id_karyawan']) ? (int) $data['id_karyawan'] : null,
         'nama_karyawan' => isset($data['nama_karyawan']) ? (string) $data['nama_karyawan'] : null,
         'id_user' => isset($data['id_user']) ? (int) $data['id_user'] : null,
         'id_cabang' => isset($data['id_cabang']) ? (int) $data['id_cabang'] : null,
         'catatan' => isset($data['catatan']) ? (string) $data['catatan'] : null,
         'meta' => $metaJson,
         'insertTime' => $data['insertTime'] ?? ($GLOBALS['now'] ?? date('Y-m-d H:i:s')),
      ];

      // Kosongkan field null agar insert tidak memaksa string "null"
      foreach ($row as $k => $v) {
         if ($v === null) {
            unset($row[$k]);
         }
      }

      return $this->db(0)->insert('activity_log', $row);
   }
}
