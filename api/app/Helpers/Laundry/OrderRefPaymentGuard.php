<?php

namespace App\Helpers\Laundry;

use App\Core\DB;

/**
 * Cek overpay saat surcas dihapus dari nota (logic sama Operasi::hapusSurcasKurir).
 */
class OrderRefPaymentGuard
{
    /**
     * @return array{ok:bool,message?:string}
     */
    public static function canRemoveSurcas(int $idCabang, int $idSurcas): array
    {
        if ($idCabang <= 0 || $idSurcas <= 0) {
            return ['ok' => false, 'message' => 'Surcas tidak valid'];
        }

        $db = DB::getInstance(1);
        $sc = $db->query(
            'SELECT id_surcas, no_ref, jumlah FROM surcas WHERE id_cabang = ? AND id_surcas = ? LIMIT 1',
            [$idCabang, $idSurcas]
        )->row_array();
        if (!is_array($sc) || empty($sc['id_surcas'])) {
            return ['ok' => false, 'message' => 'Surcas tidak ditemukan'];
        }

        $ref = trim((string) ($sc['no_ref'] ?? ''));
        if ($ref === '') {
            return ['ok' => false, 'message' => 'Nota surcas tidak valid'];
        }

        $sales = $db->query(
            'SELECT id_penjualan, qty, min_order, harga, member, diskon_qty, diskon_partner, bin, tuntas
             FROM sale WHERE id_cabang = ? AND no_ref = ? AND bin = 0',
            [$idCabang, $ref]
        )->result_array();
        if (!is_array($sales) || $sales === []) {
            return ['ok' => false, 'message' => 'Nota tidak ditemukan atau sudah dihapus'];
        }

        foreach ($sales as $sale) {
            if ((int) ($sale['tuntas'] ?? 0) !== 0) {
                return ['ok' => false, 'message' => 'Order sudah tuntas — surcas tidak dapat dihapus'];
            }
        }

        $dibayar = self::getRefDibayar($db, $idCabang, $ref);
        $currentSubTotal = self::getRefSubTotal($db, $idCabang, $ref);
        if ($dibayar > $currentSubTotal) {
            return ['ok' => false, 'message' => 'Surcas tidak dapat dihapus karena order overpay'];
        }

        $newSubTotal = self::getRefSubTotal($db, $idCabang, $ref, [$idSurcas]);
        if ($dibayar > 0 && (int) round($newSubTotal) < $dibayar) {
            return [
                'ok' => false,
                'message' => 'Surcas tidak dapat dihapus karena order akan overpay'
                    . ' (dibayar Rp' . number_format($dibayar, 0, ',', '.')
                    . ', total baru Rp' . number_format($newSubTotal, 0, ',', '.') . ')',
            ];
        }

        return ['ok' => true];
    }

    /**
     * @param int[] $excludeSurcasIds
     */
    public static function getRefSubTotal(DB $db, int $idCabang, string $ref, array $excludeSurcasIds = []): int
    {
        $sales = $db->query(
            'SELECT id_penjualan, qty, min_order, harga, member, diskon_qty, diskon_partner
             FROM sale WHERE id_cabang = ? AND no_ref = ? AND bin = 0',
            [$idCabang, $ref]
        )->result_array();

        $exclude = [];
        foreach ($excludeSurcasIds as $sid) {
            $sid = (int) $sid;
            if ($sid > 0) {
                $exclude[$sid] = true;
            }
        }

        $subTotal = 0;
        foreach (is_array($sales) ? $sales : [] as $s) {
            $subTotal += self::calcSaleItemTotal($s);
        }

        $surcasRows = $db->query(
            'SELECT id_surcas, jumlah FROM surcas WHERE id_cabang = ? AND no_ref = ?',
            [$idCabang, $ref]
        )->result_array();
        foreach (is_array($surcasRows) ? $surcasRows : [] as $sc) {
            $sid = (int) ($sc['id_surcas'] ?? 0);
            if ($sid > 0 && isset($exclude[$sid])) {
                continue;
            }
            $subTotal += (int) ($sc['jumlah'] ?? 0);
        }

        return (int) round($subTotal);
    }

    public static function getRefDibayar(DB $db, int $idCabang, string $ref): int
    {
        $rows = $db->query(
            "SELECT jumlah, status_mutasi FROM kas
             WHERE id_cabang = ? AND jenis_transaksi = 1 AND ref_transaksi = ?",
            [$idCabang, $ref]
        )->result_array();

        $dibayar = 0;
        foreach (is_array($rows) ? $rows : [] as $ka) {
            $st = (int) ($ka['status_mutasi'] ?? 0);
            if ($st === 2 || $st === 3) {
                $dibayar += (int) ($ka['jumlah'] ?? 0);
            }
        }

        return $dibayar;
    }

    /** @param array<string,mixed> $sale */
    private static function calcSaleItemTotal(array $sale): int
    {
        if ((int) ($sale['member'] ?? 0) !== 0) {
            return 0;
        }

        $qty = round((float) ($sale['qty'] ?? 0), 2);
        $minOrder = round((float) ($sale['min_order'] ?? 0), 2);
        $qtyReal = ($qty < $minOrder) ? $minOrder : $qty;
        $total = (float) ($sale['harga'] ?? 0) * $qtyReal;

        $diskonQty = (float) ($sale['diskon_qty'] ?? 0);
        $diskonPartner = (float) ($sale['diskon_partner'] ?? 0);
        if ($diskonQty > 0) {
            $total -= $total * ($diskonQty / 100);
        }
        if ($diskonPartner > 0) {
            $total -= $total * ($diskonPartner / 100);
        }

        return (int) round($total);
    }
}
