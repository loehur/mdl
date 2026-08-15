<?php

/**
 * Surcas kurir (pengantaran / penjemputan) terikat ke id_penjualan.
 * Satu no_ref boleh banyak baris; satu id_penjualan hanya 1 surcas per jenis.
 */
class SurcasKurir
{
    /**
     * id_penjualan yang sudah terikat surcas jenis ini.
     *
     * @param object $db
     * @param int[] $ids
     * @return array<int,true>
     */
    public static function boundSaleIds($db, array $ids, int $jenisSurcas): array
    {
        $safe = self::safeIds($ids);
        if ($safe === [] || $jenisSurcas <= 0) {
            return [];
        }
        $in = implode(',', $safe);
        $out = [];

        try {
            $rows = $db->query_array(
                "SELECT DISTINCT id_penjualan
                 FROM surcas_item
                 WHERE id_jenis_surcas = " . (int) $jenisSurcas . "
                   AND id_penjualan IN ($in)"
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $sid = (int) ($r['id_penjualan'] ?? 0);
                    if ($sid > 0) {
                        $out[$sid] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Tabel belum ada — fallback request di bawah
        }

        if (count($out) === count($safe)) {
            return $out;
        }

        try {
            $rows = $db->query_array(
                "SELECT DISTINCT dri.id_penjualan
                 FROM delivery_request_item dri
                 INNER JOIN surcas sc ON sc.id_delivery_request = dri.id_request
                 WHERE sc.id_jenis_surcas = " . (int) $jenisSurcas . "
                   AND sc.id_delivery_request IS NOT NULL
                   AND sc.id_delivery_request > 0
                   AND dri.id_penjualan IN ($in)"
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $sid = (int) ($r['id_penjualan'] ?? 0);
                    if ($sid > 0) {
                        $out[$sid] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }

    /**
     * Insert surcas ke no_ref item, hanya untuk id_penjualan yang belum terikat jenis ini.
     *
     * @param object $db
     * @param int[] $ids
     * @return array{no_ref:string,jumlah:int,updated:bool,skipped:bool,bound_ids:int[]}
     */
    public static function insertForSales(
        $db,
        int $idCabang,
        array $ids,
        int $jumlah,
        int $idUser,
        int $jenisSurcas,
        int $idDeliveryRequest,
        string $label
    ): array {
        $jumlah = (int) $jumlah;
        $ids = self::safeIds($ids);
        if ($ids === []) {
            throw new Exception('Tidak ada item untuk surcas ' . $label);
        }

        $noRef = self::pickRef($db, $ids);
        if ($noRef === null || $noRef === '') {
            throw new Exception('Tidak ada ref dari item yang dipilih');
        }

        $bound = self::boundSaleIds($db, $ids, $jenisSurcas);
        $fresh = [];
        foreach ($ids as $id) {
            if (!isset($bound[$id])) {
                $fresh[] = $id;
            }
        }
        if ($fresh === []) {
            return [
                'no_ref' => $noRef,
                'jumlah' => 0,
                'updated' => false,
                'skipped' => true,
                'bound_ids' => array_keys($bound),
            ];
        }

        if ($jumlah < 0) {
            throw new Exception('Isi Surcas ' . $label . ' (isi 0 untuk gratis)');
        }

        $set = [
            'jumlah' => $jumlah,
            'id_user' => (int) $idUser,
            'dari_delivery' => 1,
        ];
        if ($idDeliveryRequest > 0) {
            $set['id_delivery_request'] = $idDeliveryRequest;
        }

        $ins = $db->insert('surcas', array_merge([
            'id_cabang' => (int) $idCabang,
            'transaksi_jenis' => 1,
            'id_jenis_surcas' => $jenisSurcas,
            'no_ref' => is_numeric($noRef) ? (0 + $noRef) : $noRef,
        ], $set));
        if (is_array($ins) && isset($ins['errno']) && (int) $ins['errno'] !== 0) {
            throw new Exception($ins['error'] ?? ('Gagal insert surcas ' . strtolower($label)));
        }
        $idSurcas = (int) ($ins['insert_id'] ?? 0);
        if ($idSurcas > 0) {
            self::bindItems($db, $idSurcas, $jenisSurcas, $fresh);
        }

        return [
            'no_ref' => $noRef,
            'jumlah' => $jumlah,
            'updated' => false,
            'skipped' => false,
            'bound_ids' => $fresh,
        ];
    }

    /**
     * @param object $db
     * @param int[] $ids
     */
    public static function bindItems($db, int $idSurcas, int $jenisSurcas, array $ids): void
    {
        $idSurcas = (int) $idSurcas;
        $jenisSurcas = (int) $jenisSurcas;
        if ($idSurcas <= 0 || $jenisSurcas <= 0) {
            return;
        }
        foreach (self::safeIds($ids) as $idSale) {
            try {
                $db->insertIgnore('surcas_item', [
                    'id_surcas' => $idSurcas,
                    'id_penjualan' => $idSale,
                    'id_jenis_surcas' => $jenisSurcas,
                ]);
            } catch (\Throwable $e) {
                // Tabel belum ada / unique — item tetap tidak didobel di PHP
            }
        }
    }

    /**
     * @param object $db
     * @param int[] $ids
     */
    private static function pickRef($db, array $ids): ?string
    {
        $safe = self::safeIds($ids);
        if ($safe === []) {
            return null;
        }
        $rows = $db->get_where(
            'sale',
            'bin = 0 AND id_penjualan IN (' . implode(',', $safe) . ') ORDER BY id_penjualan ASC'
        );
        if (!is_array($rows) || empty($rows)) {
            return null;
        }
        $noRef = trim((string) ($rows[0]['no_ref'] ?? ''));

        return $noRef !== '' ? $noRef : null;
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private static function safeIds(array $ids): array
    {
        $safe = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $safe[$id] = $id;
            }
        }

        return array_values($safe);
    }
}
