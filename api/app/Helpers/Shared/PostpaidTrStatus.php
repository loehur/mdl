<?php

namespace App\Helpers\Shared;

/**
 * postpaid.tr_status (MDL):
 * 0 = sudah cek / belum dibayar
 * 1 = sukses
 * 2 = gagal
 * 3 = dalam proses
 *
 * Selaras dengan field status IAK (jika ada) dan response_code.
 */
class PostpaidTrStatus
{
    /**
     * @param array $d Data respons IAK (payment / cek / webhook)
     * @param array $a Baris DB sebelumnya (fallback tr_status)
     * @param string $rc response_code sudah dinormalisasi (dua digit untuk < 100)
     */
    public static function resolve(array $d, array $a, string $rc): int
    {
        $hasStatus = array_key_exists('status', $d)
            && $d['status'] !== null
            && $d['status'] !== '';

        if ($hasStatus) {
            $s = (int) $d['status'];
            if ($s >= 0 && $s <= 3) {
                return $s;
            }
        }

        if ($rc === '04' || $rc === '17') {
            return 2;
        }

        if ($rc === '00' && !$hasStatus) {
            return 1;
        }

        if (in_array($rc, ['01', '34', '40'], true)) {
            return 1;
        }

        if (array_key_exists('tr_status', $a) && $a['tr_status'] !== null && $a['tr_status'] !== '') {
            $t = (int) $a['tr_status'];
            if ($t >= 0 && $t <= 3) {
                return $t;
            }
        }

        if ($rc === '00') {
            return 1;
        }

        return 3;
    }
}
