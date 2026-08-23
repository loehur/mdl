<?php

namespace App\Helpers\Laundry;

/**
 * Grant ongkir gratis antar/jemput via durasi -D.
 * Logika SQL di shared/php/DeliveryTarifGrantCore.php.
 */
class DeliveryTarifGrant
{
    private static bool $booted = false;

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        $path = dirname(__DIR__, 4) . '/shared/php/DeliveryTarifGrantCore.php';
        if (!is_file($path)) {
            throw new \RuntimeException('DeliveryTarifGrantCore tidak ditemukan: ' . $path);
        }
        require_once $path;
        \DeliveryTarifGrantCore::configure(static function (string $sql): ?array {
            $res = PelangganLokasiStore::laundryDb()->query($sql);
            if ($res === false || $res === null) {
                return null;
            }
            if (is_object($res) && method_exists($res, 'row_array')) {
                $row = $res->row_array();
                return is_array($row) ? $row : null;
            }

            return null;
        });
        self::$booted = true;
    }

    public static function apply(int $idPelanggan, int $tarif): int
    {
        self::boot();

        return \DeliveryTarifGrantCore::apply($idPelanggan, $tarif);
    }

    public static function hasGrant(int $idPelanggan): bool
    {
        self::boot();

        return \DeliveryTarifGrantCore::hasGrant($idPelanggan);
    }
}
