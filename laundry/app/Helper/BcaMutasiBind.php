<?php

/**
 * Bind mutasi BCA (mdl_main db 100) ke kas laundry.
 */
class BcaMutasiBind
{
    public const ENTITY_KAS_LAUNDRY = 'kas_laundry';
    public const MAX_RANGE_DAYS = 6;

    /**
     * Rentang 6 hari terakhir (aturan server: max 6 hari inklusif, end = hari ini).
     *
     * @return array{start:string,end:string}
     */
    public static function listRange(): array
    {
        $end = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-' . (self::MAX_RANGE_DAYS - 1) . ' days'));

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param object $dbMain db(100) mdl_main
     * @return array<int,array<string,mixed>>
     */
    public static function listUnlinkedCr($dbMain, ?string $startYmd = null, ?string $endYmd = null): array
    {
        $range = self::listRange();
        $start = $startYmd ?: $range['start'];
        $end = $endYmd ?: $range['end'];

        $rows = $dbMain->query_array(
            "SELECT m.id, m.tanggal, m.tanggal_iso, m.keterangan, m.nominal, m.mutasi, m.created_at
             FROM bca_mutasi m
             LEFT JOIN bca_mutasi_link l ON l.bca_mutasi_id = m.id
             WHERE l.id IS NULL
               AND m.mutasi = 'CR'
               AND m.tanggal_iso IS NOT NULL
               AND m.tanggal_iso >= '" . $dbMain->escape($start) . "'
               AND m.tanggal_iso <= '" . $dbMain->escape($end) . "'
             ORDER BY m.tanggal_iso DESC, m.id DESC
             LIMIT 200"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param object $dbMain
     */
    public static function getMutasiRow($dbMain, int $mutasiId): ?array
    {
        if ($mutasiId < 1) {
            return null;
        }

        $row = $dbMain->get_where_row('bca_mutasi', "id = '" . (int) $mutasiId . "'");
        if (!$row || strtoupper((string) ($row['mutasi'] ?? '')) !== 'CR') {
            return null;
        }

        $linked = $dbMain->get_where_row(
            'bca_mutasi_link',
            "bca_mutasi_id = '" . (int) $mutasiId . "'"
        );

        if (!empty($linked['id'])) {
            return null;
        }

        return $row;
    }

    /**
     * @param object $dbMain
     */
    public static function bindMutasi($dbMain, int $mutasiId, string $refFinance): bool
    {
        $mutasiId = (int) $mutasiId;
        $refFinance = trim($refFinance);
        if ($mutasiId < 1 || $refFinance === '') {
            return false;
        }

        if (!self::getMutasiRow($dbMain, $mutasiId)) {
            return false;
        }

        $entityUsed = $dbMain->get_where_row(
            'bca_mutasi_link',
            "entity_type = '" . $dbMain->escape(self::ENTITY_KAS_LAUNDRY) . "'"
            . " AND entity_ref = '" . $dbMain->escape($refFinance) . "'"
        );
        if (!empty($entityUsed['id'])) {
            return false;
        }

        $ins = $dbMain->insert('bca_mutasi_link', [
            'bca_mutasi_id' => $mutasiId,
            'entity_type' => self::ENTITY_KAS_LAUNDRY,
            'entity_ref' => $refFinance,
        ]);

        return isset($ins['errno']) && (int) $ins['errno'] === 0;
    }

    /**
     * @param object $dbMain
     */
    public static function unbindEntity($dbMain, string $refFinance): bool
    {
        $refFinance = trim($refFinance);
        if ($refFinance === '') {
            return false;
        }

        $del = $dbMain->delete(
            'bca_mutasi_link',
            "entity_type = '" . $dbMain->escape(self::ENTITY_KAS_LAUNDRY) . "'"
            . " AND entity_ref = '" . $dbMain->escape($refFinance) . "'"
        );

        return isset($del['errno']) && (int) $del['errno'] === 0;
    }

    /**
     * @param mixed $nominal
     */
    public static function formatNominal($nominal): string
    {
        if (is_string($nominal)) {
            $nominal = str_replace(',', '', $nominal);
        }

        return number_format((float) $nominal, 2, '.', '');
    }
}
