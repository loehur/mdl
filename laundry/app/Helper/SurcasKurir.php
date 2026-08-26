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
        $jenis = (int) $jenisSurcas;
        $out = [];
        $refMatch = self::sqlRefEquals('s.no_ref', 'sc.no_ref');
        $cabangMatch = self::sqlCabangEquals('s.id_cabang', 'sc.id_cabang');

        try {
            $rows = $db->query_array(
                "SELECT DISTINCT si.id_penjualan
                 FROM surcas_item si
                 INNER JOIN surcas sc ON sc.id_surcas = si.id_surcas
                   AND sc.id_jenis_surcas = si.id_jenis_surcas
                 INNER JOIN sale s ON s.id_penjualan = si.id_penjualan
                   AND s.bin = 0
                   AND $refMatch
                   AND $cabangMatch
                 WHERE si.id_jenis_surcas = $jenis
                   AND si.id_penjualan IN ($in)"
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
                   AND sc.id_jenis_surcas = $jenis
                   AND sc.id_delivery_request IS NOT NULL
                   AND sc.id_delivery_request > 0
                 INNER JOIN sale s ON s.id_penjualan = dri.id_penjualan
                   AND s.bin = 0
                   AND $refMatch
                   AND $cabangMatch
                 WHERE dri.id_penjualan IN ($in)"
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

        // Legacy: hanya jika baris surcas belum memakai surcas_item (binding per nota penuh)
        try {
            $rows = $db->query_array(
                "SELECT DISTINCT s.id_penjualan
                 FROM sale s
                 INNER JOIN surcas sc ON $refMatch
                   AND sc.id_jenis_surcas = $jenis
                   AND sc.dari_delivery = 1
                   AND $cabangMatch
                 WHERE s.bin = 0
                   AND s.id_penjualan IN ($in)
                   AND NOT EXISTS (
                     SELECT 1 FROM surcas_item si
                     WHERE si.id_surcas = sc.id_surcas
                       AND si.id_jenis_surcas = $jenis
                   )"
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
     * Binding surcas item yang dapat dilepas dari badge karena masih memiliki
     * item lain pada baris surcas yang sama. Dihitung sekaligus agar halaman
     * Operasi tidak melakukan query per nota.
     *
     * @param object $db
     * @param int[] $ids
     * @param int[] $jenisSurcas
     * @return array<int,array<int,true>> [id_penjualan => [id_jenis_surcas => true]]
     */
    public static function unbindableSaleIds($db, array $ids, array $jenisSurcas): array
    {
        $safe = self::safeIds($ids);
        $jenis = self::safeIds($jenisSurcas);
        if ($safe === [] || $jenis === []) {
            return [];
        }

        $in = implode(',', $safe);
        $jenisIn = implode(',', $jenis);
        $refMatch = self::sqlRefEquals('s.no_ref', 'sc.no_ref');
        $cabangMatch = self::sqlCabangEquals('s.id_cabang', 'sc.id_cabang');
        $out = [];

        try {
            $rows = $db->query_array(
                "SELECT si.id_penjualan, si.id_jenis_surcas
                 FROM surcas_item si
                 INNER JOIN (
                    SELECT id_surcas, id_jenis_surcas, COUNT(*) AS total_item
                    FROM surcas_item
                    WHERE id_jenis_surcas IN ($jenisIn)
                    GROUP BY id_surcas, id_jenis_surcas
                    HAVING COUNT(*) > 1
                 ) sibling ON sibling.id_surcas = si.id_surcas
                    AND sibling.id_jenis_surcas = si.id_jenis_surcas
                 INNER JOIN surcas sc ON sc.id_surcas = si.id_surcas
                    AND sc.id_jenis_surcas = si.id_jenis_surcas
                 INNER JOIN sale s ON s.id_penjualan = si.id_penjualan
                    AND s.bin = 0
                    AND $refMatch
                    AND $cabangMatch
                 WHERE si.id_penjualan IN ($in)
                   AND si.id_jenis_surcas IN ($jenisIn)"
            );
            foreach ((array) $rows as $row) {
                $idSale = (int) ($row['id_penjualan'] ?? 0);
                $idJenis = (int) ($row['id_jenis_surcas'] ?? 0);
                if ($idSale > 0 && $idJenis > 0) {
                    $out[$idSale][$idJenis] = true;
                }
            }
        } catch (\Throwable $e) {
            // Tabel belum tersedia atau query gagal: badge tetap aman, hanya tidak dapat di-unbind.
        }

        return $out;
    }

    /**
     * Hapus surcas_item invalid (orphan / no_ref atau cabang tidak cocok).
     *
     * @param object $db
     * @param int[] $ids id_penjualan scope
     * @return int jumlah baris dihapus
     */
    public static function purgeInvalidSurcasItems($db, array $ids): int
    {
        $safe = self::safeIds($ids);
        if ($safe === []) {
            return 0;
        }
        $in = implode(',', $safe);
        $purged = 0;

        try {
            $rows = $db->query_array(
                "SELECT si.id_penjualan, si.id_surcas, si.id_jenis_surcas
                 FROM surcas_item si
                 WHERE si.id_penjualan IN ($in)"
            );
        } catch (\Throwable $e) {
            return 0;
        }

        if (!is_array($rows) || $rows === []) {
            return 0;
        }

        foreach ($rows as $r) {
            $idSale = (int) ($r['id_penjualan'] ?? 0);
            $idSurcas = (int) ($r['id_surcas'] ?? 0);
            $jenis = (int) ($r['id_jenis_surcas'] ?? 0);
            if ($idSale <= 0 || $idSurcas <= 0 || $jenis <= 0) {
                continue;
            }
            if (self::isValidSurcasItemBinding($db, $idSale, $idSurcas, $jenis)) {
                continue;
            }
            try {
                $db->delete(
                    'surcas_item',
                    'id_penjualan = ' . $idSale
                        . ' AND id_surcas = ' . $idSurcas
                        . ' AND id_jenis_surcas = ' . $jenis
                );
                $purged++;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $purged;
    }

    /**
     * @param object $db
     */
    public static function isValidSurcasItemBinding($db, int $idPenjualan, int $idSurcas, int $jenisSurcas): bool
    {
        if ($idPenjualan <= 0 || $idSurcas <= 0 || $jenisSurcas <= 0) {
            return false;
        }

        $sale = $db->get_where_row('sale', 'id_penjualan = ' . $idPenjualan . ' AND bin = 0');
        $sc = $db->get_where_row('surcas', 'id_surcas = ' . $idSurcas);
        if (!is_array($sale) || !is_array($sc)) {
            return false;
        }
        if ((int) ($sc['id_jenis_surcas'] ?? 0) !== $jenisSurcas) {
            return false;
        }
        if (self::normalizeRef($sale['no_ref'] ?? '') !== self::normalizeRef($sc['no_ref'] ?? '')) {
            return false;
        }

        $saleCabang = (int) ($sale['id_cabang'] ?? 0);
        $scCabang = (int) ($sc['id_cabang'] ?? 0);
        if ($saleCabang > 0 && $scCabang > 0 && $saleCabang !== $scCabang) {
            return false;
        }

        return true;
    }

    private static function normalizeRef($ref): string
    {
        return trim((string) $ref);
    }

    private static function sqlRefEquals(string $left, string $right): string
    {
        return 'TRIM(CAST(' . $left . ' AS CHAR)) = TRIM(CAST(' . $right . ' AS CHAR))';
    }

    private static function sqlCabangEquals(string $left, string $right): string
    {
        return '(' . $left . ' = ' . $right . ' OR ' . $left . ' = 0 OR ' . $right . ' = 0)';
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
                'SELECT DISTINCT si.id_penjualan
                 FROM surcas_item si
                 INNER JOIN surcas sc ON sc.id_surcas = si.id_surcas
                   AND sc.id_jenis_surcas = si.id_jenis_surcas
                 INNER JOIN sale s ON s.id_penjualan = si.id_penjualan
                   AND s.bin = 0
                   AND ' . self::sqlRefEquals('s.no_ref', 'sc.no_ref') . '
                   AND ' . self::sqlCabangEquals('s.id_cabang', 'sc.id_cabang') . '
                 WHERE si.id_surcas = ' . $idSurcas
                    . ' AND si.id_jenis_surcas = ' . $jenisSurcas
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
