<?php

/**
 * Saldo non-tunai per channel (BCA / QRIS) per cabang.
 */
class SaldoNonTunai
{
    public const CHANNELS = ['BCA', 'QRIS'];

    /**
     * @param object $db db(0)
     */
    public static function getSaldo($db, int $idCabang, string $channel): int
    {
        $channel = strtoupper(trim($channel));
        if ($idCabang < 1 || !in_array($channel, self::CHANNELS, true)) {
            return 0;
        }

        $noteEsc = $db->escape($channel);
        $idCabang = (int) $idCabang;

        $row = $db->query_array(
            "SELECT
                COALESCE(SUM(CASE
                    WHEN jenis_mutasi = 1 AND status_mutasi = 3 THEN jumlah
                    ELSE 0
                END), 0) AS kredit,
                COALESCE(SUM(CASE
                    WHEN jenis_mutasi = 2
                         AND jenis_transaksi IN (2, 5)
                         AND status_mutasi IN (2, 3)
                    THEN jumlah
                    ELSE 0
                END), 0) AS debit
             FROM kas
             WHERE id_cabang = {$idCabang}
               AND metode_mutasi = 2
               AND UPPER(IFNULL(note, '')) = '{$noteEsc}'"
        );

        $kredit = 0;
        $debit = 0;
        if (is_array($row) && isset($row[0])) {
            $kredit = (int) ($row[0]['kredit'] ?? 0);
            $debit = (int) ($row[0]['debit'] ?? 0);
        }

        return max(0, $kredit - $debit);
    }

    /**
     * @param object $db
     * @return array{BCA:int,QRIS:int}
     */
    public static function getSaldoMap($db, int $idCabang): array
    {
        $map = [];
        foreach (self::CHANNELS as $ch) {
            $map[$ch] = self::getSaldo($db, $idCabang, $ch);
        }

        return $map;
    }
}
