<?php

/**
 * Grant ongkir gratis antar/jemput via durasi -D.
 * Logika SQL di shared/php/DeliveryTarifGrantCore.php (sumber tunggal dengan api).
 */
class DeliveryTarifGrant
{
    private static $booted = false;

    private static function boot()
    {
        if (self::$booted) {
            return;
        }
        $path = dirname(__DIR__, 3) . '/shared/php/DeliveryTarifGrantCore.php';
        if (!is_file($path)) {
            throw new RuntimeException('DeliveryTarifGrantCore tidak ditemukan: ' . $path);
        }
        require_once $path;
        DeliveryTarifGrantCore::configure(static function (string $sql): ?array {
            require_once 'app/Core/DB.php';
            $rows = DB::getInstance(0)->query_array($sql);
            if (!is_array($rows) || $rows === []) {
                return null;
            }

            return $rows[0];
        });
        self::$booted = true;
    }

    public static function apply($idPelanggan, $tarif)
    {
        self::boot();

        return DeliveryTarifGrantCore::apply((int) $idPelanggan, (int) $tarif);
    }

    public static function hasGrant($idPelanggan)
    {
        self::boot();

        return DeliveryTarifGrantCore::hasGrant((int) $idPelanggan);
    }
}
