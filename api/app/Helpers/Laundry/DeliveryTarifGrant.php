<?php

namespace App\Helpers\Laundry;

/**
 * Grant ongkir gratis antar/jemput via durasi -D (include delivery).
 * Aturan mengikuti laundry: order member terbuka -D, atau saldo paket member -D > 3.
 */
class DeliveryTarifGrant
{
    /** Saldo paket member -D minimal agar ongkir gratis. */
    private const MEMBER_SALDO_MIN = 3;

    /**
     * Terapkan grant: kembalikan 0 jika pelanggan berhak gratis, else tarif asli.
     */
    public static function apply(int $idPelanggan, int $tarif): int
    {
        if ($tarif <= 0 || $idPelanggan <= 0) {
            return $tarif;
        }

        return self::hasGrant($idPelanggan) ? 0 : $tarif;
    }

    public static function hasGrant(int $idPelanggan): bool
    {
        if ($idPelanggan <= 0) {
            return false;
        }
        if (self::hasOpenMemberOrderWithDeliveryDurasi($idPelanggan)) {
            return true;
        }

        return self::hasMemberSaldoDeliveryDurasiAboveMin($idPelanggan);
    }

    /** Order terbuka (tuntas=0) pakai saldo member dengan durasi -D. */
    private static function hasOpenMemberOrderWithDeliveryDurasi(int $idPelanggan): bool
    {
        try {
            $row = PelangganLokasiStore::laundryDb()->query(
                "SELECT COUNT(*) AS n
                 FROM sale s
                 INNER JOIN durasi d ON d.id_durasi = s.id_durasi
                 WHERE s.id_pelanggan = ?
                   AND s.bin = 0
                   AND s.tuntas = 0
                   AND s.member = 1
                   AND d.durasi LIKE '%-D%'",
                [$idPelanggan]
            )->row_array();

            return (int) ($row['n'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Saldo paket member durasi -D masih > 3. */
    private static function hasMemberSaldoDeliveryDurasiAboveMin(int $idPelanggan): bool
    {
        try {
            $row = PelangganLokasiStore::laundryDb()->query(
                "SELECT m.id_harga,
                        (
                          SELECT COALESCE(SUM(m2.qty), 0)
                          FROM member m2
                          WHERE m2.id_pelanggan = ?
                            AND m2.id_harga = m.id_harga
                            AND m2.bin = 0
                            AND m2.lunas = 1
                        ) - (
                          SELECT COALESCE(SUM(s2.qty), 0)
                          FROM sale s2
                          WHERE s2.id_pelanggan = ?
                            AND s2.id_harga = m.id_harga
                            AND s2.bin = 0
                            AND s2.member = 1
                        ) AS saldo
                 FROM member m
                 INNER JOIN harga h ON h.id_harga = m.id_harga
                 INNER JOIN durasi d ON d.id_durasi = h.id_durasi
                 WHERE m.id_pelanggan = ?
                   AND m.bin = 0
                   AND m.lunas = 1
                   AND d.durasi LIKE '%-D%'
                 GROUP BY m.id_harga
                 HAVING saldo > ?
                 LIMIT 1",
                [$idPelanggan, $idPelanggan, $idPelanggan, self::MEMBER_SALDO_MIN]
            )->row_array();

            return is_array($row) && !empty($row['id_harga']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
