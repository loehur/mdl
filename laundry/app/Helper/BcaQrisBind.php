<?php

/**
 * Bind transaksi QRIS merchant BCA (mdl_main db 100) ke kas laundry.
 */
class BcaQrisBind
{
    public const ENTITY_KAS_LAUNDRY = 'kas_laundry';
    public const MAX_RANGE_DAYS = 6;
    public const NOMINAL_TOLERANCE = 10000;

    /**
     * Rentang 6 hari inklusif dari hari ini (selaras BcaScrapper::listRange / cron).
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
     * Transaksi QRMS belum ter-link dalam rentang 6 hari.
     *
     * @param object $dbMain db(100) mdl_main
     * @return array<int,array<string,mixed>>
     */
    public static function listUnlinked($dbMain, ?string $startYmd = null, ?string $endYmd = null): array
    {
        $range = self::listRange();
        $start = $startYmd ?: $range['start'];
        $end = $endYmd ?: $range['end'];

        $rows = $dbMain->query_array(
            "SELECT t.id, t.tanggal, t.waktu, t.rrn, t.nominal, t.status, t.keterangan, t.outlet_name, t.created_at
             FROM bca_qris_transaksi t
             LEFT JOIN bca_qris_link l ON l.bca_qris_id = t.id
             WHERE l.id IS NULL
               AND t.tanggal >= '" . $dbMain->escape($start) . "'
               AND t.tanggal <= '" . $dbMain->escape($end) . "'
             ORDER BY t.tanggal DESC, t.waktu DESC, t.id DESC
             LIMIT 200"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param object $dbMain
     */
    public static function getQrisRow($dbMain, int $qrisId): ?array
    {
        if ($qrisId < 1) {
            return null;
        }

        $row = $dbMain->get_where_row('bca_qris_transaksi', "id = '" . (int) $qrisId . "'");
        if (!$row || empty($row['id'])) {
            return null;
        }

        $linked = $dbMain->get_where_row(
            'bca_qris_link',
            "bca_qris_id = '" . (int) $qrisId . "'"
        );

        if (!empty($linked['id'])) {
            return null;
        }

        return $row;
    }

    /**
     * @param mixed $a
     * @param mixed $b
     */
    public static function nominalDiff($a, $b): float
    {
        return abs((float) self::formatNominal($a) - (float) self::formatNominal($b));
    }

    /**
     * @param mixed $kasNominal
     * @param mixed $qrisNominal
     */
    public static function isNominalWithinTolerance($kasNominal, $qrisNominal): bool
    {
        return self::nominalDiff($kasNominal, $qrisNominal) <= self::NOMINAL_TOLERANCE;
    }

    /**
     * @param object $dbMain
     */
    public static function bindQris($dbMain, int $qrisId, string $refFinance, $kasNominal = null): bool
    {
        $qrisId = (int) $qrisId;
        $refFinance = trim($refFinance);
        if ($qrisId < 1 || $refFinance === '') {
            return false;
        }

        $row = self::getQrisRow($dbMain, $qrisId);
        if (!$row) {
            return false;
        }

        if ($kasNominal !== null && !self::isNominalWithinTolerance($kasNominal, $row['nominal'] ?? 0)) {
            return false;
        }

        $entityUsed = $dbMain->get_where_row(
            'bca_qris_link',
            "entity_type = '" . $dbMain->escape(self::ENTITY_KAS_LAUNDRY) . "'"
            . " AND entity_ref = '" . $dbMain->escape($refFinance) . "'"
        );
        if (!empty($entityUsed['id'])) {
            return false;
        }

        $ins = $dbMain->insert('bca_qris_link', [
            'bca_qris_id' => $qrisId,
            'entity_type' => self::ENTITY_KAS_LAUNDRY,
            'entity_ref' => $refFinance,
            'bill_nominal' => $kasNominal !== null && $kasNominal !== ''
                ? self::formatNominal($kasNominal)
                : null,
            'bind_nominal' => self::formatNominal($row['nominal'] ?? 0),
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
            'bca_qris_link',
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
