<?php

/**
 * Grant ongkir gratis antar/jemput via durasi -D (include delivery).
 * Sumber tunggal SQL — dipakai api (WA/CRM) dan laundry portal J.
 *
 * Bootstrap sekali via DeliveryTarifGrantCore::configure(callable $fetchRow).
 * $fetchRow(string $sql): ?array  — satu baris asosiatif atau null.
 */
class DeliveryTarifGrantCore
{
    public const MEMBER_SALDO_MIN = 3;

    /** @var callable|null */
    private static $fetchRow = null;

    /** @param callable(string): (?array) $fetchRow */
    public static function configure(callable $fetchRow): void
    {
        self::$fetchRow = $fetchRow;
    }

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

    private static function row(string $sql): ?array
    {
        if (self::$fetchRow === null) {
            throw new RuntimeException('DeliveryTarifGrantCore: fetchRow belum dikonfigurasi');
        }
        $row = (self::$fetchRow)($sql);

        return is_array($row) ? $row : null;
    }

    private static function hasOpenMemberOrderWithDeliveryDurasi(int $idPelanggan): bool
    {
        $id = (int) $idPelanggan;
        $row = self::row(
            "SELECT COUNT(*) AS n
             FROM sale s
             INNER JOIN durasi d ON d.id_durasi = s.id_durasi
             WHERE s.id_pelanggan = {$id}
               AND s.bin = 0
               AND s.tuntas = 0
               AND s.member = 1
               AND d.durasi LIKE '%-D%'"
        );

        return (int) ($row['n'] ?? 0) > 0;
    }

    private static function hasMemberSaldoDeliveryDurasiAboveMin(int $idPelanggan): bool
    {
        $id = (int) $idPelanggan;
        $min = (int) self::MEMBER_SALDO_MIN;
        $row = self::row(
            "SELECT m.id_harga,
                    (
                      SELECT COALESCE(SUM(m2.qty), 0)
                      FROM member m2
                      WHERE m2.id_pelanggan = {$id}
                        AND m2.id_harga = m.id_harga
                        AND m2.bin = 0
                        AND m2.lunas = 1
                    ) - (
                      SELECT COALESCE(SUM(s2.qty), 0)
                      FROM sale s2
                      WHERE s2.id_pelanggan = {$id}
                        AND s2.id_harga = m.id_harga
                        AND s2.bin = 0
                        AND s2.member = 1
                    ) AS saldo
             FROM member m
             INNER JOIN harga h ON h.id_harga = m.id_harga
             INNER JOIN durasi d ON d.id_durasi = h.id_durasi
             WHERE m.id_pelanggan = {$id}
               AND m.bin = 0
               AND m.lunas = 1
               AND d.durasi LIKE '%-D%'
             GROUP BY m.id_harga
             HAVING saldo > {$min}
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id_harga']);
    }
}
