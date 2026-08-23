<?php

namespace App\Helpers\Laundry;

/**
 * Grant ongkir gratis antar/jemput via durasi -D.
 * Logika SQL di DeliveryTarifGrantCore.php (same folder, ikut deploy api).
 */
class DeliveryTarifGrant
{
    /** @var bool */
    private static $booted = false;

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        $path = __DIR__ . '/DeliveryTarifGrantCore.php';
        if (!is_file($path)) {
            throw new \RuntimeException('DeliveryTarifGrantCore tidak ditemukan: ' . $path);
        }
        require_once $path;
        \DeliveryTarifGrantCore::configure(static function (string $sql): ?array {
            try {
                $res = PelangganLokasiStore::laundryDb()->query($sql);
                if ($res === false || $res === null) {
                    return null;
                }
                if (is_object($res) && method_exists($res, 'row_array')) {
                    $row = $res->row_array();

                    return is_array($row) ? $row : null;
                }
            } catch (\Throwable $e) {
                if (class_exists('\Log')) {
                    \Log::write('DeliveryTarifGrant query: ' . $e->getMessage(), 'wa_error', 'DeliveryTarifGrant');
                }
            }

            return null;
        });
        self::$booted = true;
    }

    public static function apply(int $idPelanggan, int $tarif): int
    {
        try {
            self::boot();

            return \DeliveryTarifGrantCore::apply($idPelanggan, $tarif);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('DeliveryTarifGrant apply: ' . $e->getMessage(), 'wa_error', 'DeliveryTarifGrant');
            }

            return $tarif;
        }
    }

    public static function hasGrant(int $idPelanggan): bool
    {
        try {
            self::boot();

            return \DeliveryTarifGrantCore::hasGrant($idPelanggan);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('DeliveryTarifGrant hasGrant: ' . $e->getMessage(), 'wa_error', 'DeliveryTarifGrant');
            }

            return false;
        }
    }
}
