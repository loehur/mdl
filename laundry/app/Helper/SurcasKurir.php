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

        if (count($out) === count($safe)) {
            return $out;
        }

        // Surcas kurir di no_ref (legacy / tanpa surcas_item): item nota dianggap sudah terikat
        try {
            $rows = $db->query_array(
                "SELECT DISTINCT s.id_penjualan
                 FROM sale s
                 INNER JOIN surcas sc ON sc.no_ref = s.no_ref
                   AND sc.id_jenis_surcas = " . (int) $jenisSurcas . "
                   AND sc.dari_delivery = 1
                 WHERE s.bin = 0
                   AND s.id_penjualan IN ($in)"
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
     * Item yang terikat ke satu baris surcas tertentu.
     *
     * @param object $db
     * @param int[] $ids
     * @return array<int,true>
     */
    public static function saleIdsOnSurcas($db, int $idSurcas, int $jenisSurcas, array $ids = []): array
    {
        $idSurcas = (int) $idSurcas;
        $jenisSurcas = (int) $jenisSurcas;
        if ($idSurcas <= 0 || $jenisSurcas <= 0) {
            return [];
        }
        $safe = self::safeIds($ids);
        $inClause = $safe !== [] ? ' AND id_penjualan IN (' . implode(',', $safe) . ')' : '';
        $out = [];

        try {
            $rows = $db->query_array(
                'SELECT DISTINCT id_penjualan FROM surcas_item
                 WHERE id_surcas = ' . $idSurcas
                    . ' AND id_jenis_surcas = ' . $jenisSurcas
                    . $inClause
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
            // surcas_item opsional
        }

        if ($out !== []) {
            return $out;
        }

        $sc = $db->get_where_row('surcas', 'id_surcas = ' . $idSurcas);
        if (!is_array($sc) || (int) ($sc['id_jenis_surcas'] ?? 0) !== $jenisSurcas) {
            return [];
        }
        $noRef = trim((string) ($sc['no_ref'] ?? ''));
        if ($noRef === '') {
            return [];
        }

        $noRefEsc = $db->escape($noRef);
        $otherCount = (int) ($db->count_where(
            'surcas',
            "no_ref = '$noRefEsc'"
                . ' AND id_jenis_surcas = ' . $jenisSurcas
                . ' AND id_surcas <> ' . $idSurcas
                . ' AND dari_delivery = 1'
        ) ?? 0);
        if ($otherCount > 0) {
            return [];
        }

        $saleWhere = "bin = 0 AND no_ref = '$noRefEsc'";
        if ($safe !== []) {
            $saleWhere .= ' AND id_penjualan IN (' . implode(',', $safe) . ')';
        }
        $sales = $db->get_where('sale', $saleWhere);
        if (!is_array($sales)) {
            return [];
        }
        foreach ($sales as $sr) {
            $sid = (int) ($sr['id_penjualan'] ?? 0);
            if ($sid > 0) {
                $out[$sid] = true;
            }
        }

        return $out;
    }

    /**
     * Item terikat surcas jenis ini di baris surcas lain (bukan $excludeSurcasId).
     *
     * @param object $db
     * @param int[] $ids
     * @return array<int,true>
     */
    public static function boundToOtherSurcas($db, array $ids, int $jenisSurcas, int $excludeSurcasId = 0): array
    {
        $all = self::boundSaleIds($db, $ids, $jenisSurcas);
        if ($excludeSurcasId <= 0) {
            return $all;
        }
        $mine = self::saleIdsOnSurcas($db, $excludeSurcasId, $jenisSurcas, $ids);
        $out = [];
        foreach ($all as $sid => $_) {
            if (!isset($mine[$sid])) {
                $out[$sid] = true;
            }
        }

        return $out;
    }

    /**
     * Baris surcas kurir yang sudah ada di no_ref (hindari dobel insert).
     */
    public static function findExistingSurcasOnRef($db, string $noRef, int $idCabang, int $jenisSurcas): int
    {
        $noRef = trim($noRef);
        if ($noRef === '' || $jenisSurcas <= 0) {
            return 0;
        }
        $noRefEsc = $db->escape($noRef);
        $where = "no_ref = '$noRefEsc'"
            . ' AND id_jenis_surcas = ' . (int) $jenisSurcas
            . ' AND dari_delivery = 1';
        if ($idCabang > 0) {
            $where .= ' AND id_cabang = ' . (int) $idCabang;
        }
        $row = $db->get_where_row('surcas', $where . ' ORDER BY id_surcas DESC');
        if (!is_array($row) || empty($row['id_surcas'])) {
            return 0;
        }

        return (int) $row['id_surcas'];
    }

    /**
     * Insert surcas ke no_ref item, hanya untuk id_penjualan yang belum terikat jenis ini.
     *
     * @param object $db
     * @param int[] $ids
     * @return array{no_ref:string,jumlah:int,updated:bool,skipped:bool,bound_ids:int[],id_surcas?:int}
     */
    public static function insertForSales(
        $db,
        int $idCabang,
        array $ids,
        int $jumlah,
        int $idUser,
        int $jenisSurcas,
        int $idDeliveryRequest,
        string $label,
        int $preferSurcasId = 0
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

        $other = self::boundToOtherSurcas($db, $ids, $jenisSurcas, $preferSurcasId);
        if ($other !== []) {
            $bad = (int) array_key_first($other);
            throw new Exception('Item #' . $bad . ' sudah terikat surcas ' . strtolower($label) . ' lain');
        }

        $bound = self::boundSaleIds($db, $ids, $jenisSurcas);
        $fresh = [];
        foreach ($ids as $id) {
            if (!isset($bound[$id])) {
                $fresh[] = $id;
            }
        }

        $preferSurcasId = (int) $preferSurcasId;
        if ($fresh === [] && $preferSurcasId > 0) {
            return [
                'no_ref' => $noRef,
                'jumlah' => $jumlah >= 0 ? $jumlah : 0,
                'updated' => false,
                'skipped' => true,
                'bound_ids' => array_keys($bound),
                'id_surcas' => $preferSurcasId,
            ];
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

        $existingId = $preferSurcasId > 0
            ? $preferSurcasId
            : self::findExistingSurcasOnRef($db, $noRef, $idCabang, $jenisSurcas);
        if ($existingId > 0) {
            self::bindItems($db, $existingId, $jenisSurcas, $fresh);
            if ($idDeliveryRequest > 0) {
                try {
                    $db->update(
                        'surcas',
                        ['id_delivery_request' => $idDeliveryRequest],
                        'id_surcas = ' . $existingId . ' AND (id_delivery_request IS NULL OR id_delivery_request = 0)'
                    );
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            return [
                'no_ref' => $noRef,
                'jumlah' => $jumlah >= 0 ? $jumlah : 0,
                'updated' => true,
                'skipped' => false,
                'bound_ids' => $fresh,
                'id_surcas' => $existingId,
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
            'id_surcas' => $idSurcas,
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
    public static function pickRefForIds($db, array $ids): ?string
    {
        return self::pickRef($db, $ids);
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
