<?php

/**
 * Riwayat paket member: topup + pemakaian.
 * Handle data migrasi di mana banyak baris member.insertTime identik/berdekatan
 * (mis. 2025-07-16 15:36:2x) sehingga merge by insertTime membuat
 * pemakaian awal diproses dulu → running saldo minus palsu.
 */
class PaketHistory
{
   /**
    * Tetapkan _mergeTime untuk tiap topup.
    * Klaster ≥3 topup pada hari kalender yang sama diurutkan
    * sebelum pemakaian earliest agar kredit dianggap tersedia.
    *
    * @param array $topups rows member (sudah ASC id/insertTime)
    * @param array $sales rows sale member
    * @return array topups + _mergeTime
    */
   public static function withMergeTimes(array $topups, array $sales)
   {
      $firstSaleTs = PHP_INT_MAX;
      foreach ($sales as $s) {
         $ts = strtotime($s['insertTime'] ?? '');
         if ($ts !== false && $ts < $firstSaleTs) {
            $firstSaleTs = $ts;
         }
      }

      $groups = [];
      foreach ($topups as $i => $t) {
         $raw = (string) ($t['insertTime'] ?? '');
         $key = substr($raw, 0, 10); // Y-m-d — migrasi sering beda 1–2 detik
         if ($key === '') {
            $key = '_empty';
         }
         $groups[$key][] = $i;
         $ts = strtotime($raw);
         $topups[$i]['_mergeTime'] = ($ts !== false) ? $ts : 0;
      }

      foreach ($groups as $indexes) {
         if (count($indexes) < 3) {
            continue;
         }
         $n = count($indexes);
         $base = ($firstSaleTs === PHP_INT_MAX ? time() : $firstSaleTs) - $n - 1;
         foreach ($indexes as $j => $i) {
            $topups[$i]['_mergeTime'] = $base + $j;
         }
      }

      return $topups;
   }
}
