<?php

namespace App\Helpers\Payment;

/**
 * Alokasi nominal transfer BCA unik — hindari collision antar pending (6 hari).
 */
class BcaUniqueNominal
{
    public const LOOKBACK_DAYS = 6;

    /**
     * @param object|null $invoiceDb db(6)
     * @param object|null $salonDb db(4)
     * @param object|null $laundryDb db(1)
     */
    public static function allocate(int $baseAmount, $invoiceDb = null, $salonDb = null, $laundryDb = null, $wadeskDb = null): int
    {
        $baseAmount = max(1, (int) round($baseAmount));
        $used = self::collectUsedAmounts($invoiceDb, $salonDb, $laundryDb, $wadeskDb);
        $candidate = $baseAmount;
        $guard = 0;
        while (in_array($candidate, $used, true) && $guard < 10000) {
            $candidate++;
            $guard++;
        }

        return $candidate;
    }

    /**
     * Nominal BCA yang masih pending / belum terkonfirmasi dalam 6 hari terakhir.
     *
     * @return int[]
     */
    public static function collectUsedAmounts($invoiceDb = null, $salonDb = null, $laundryDb = null, $wadeskDb = null): array
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . self::LOOKBACK_DAYS . ' days'));
        $amounts = [];

        if ($invoiceDb) {
            try {
                $rows = $invoiceDb->query(
                    "SELECT amount FROM invoice_payments
                     WHERE payment_method = 'bca'
                       AND payment_status = 'pending'
                       AND created_at >= ?",
                    [$since]
                )->result_array();
                foreach ($rows as $row) {
                    $amounts[] = (int) round((float) ($row['amount'] ?? 0));
                }
            } catch (\Throwable $e) {
                // table/column may not exist yet
            }
        }

        if ($salonDb) {
            try {
                $rows = $salonDb->query(
                    "SELECT amount FROM subscription_payments
                     WHERE payment_method = 'bca'
                       AND payment_status = 'pending'
                       AND created_at >= ?",
                    [$since]
                )->result_array();
                foreach ($rows as $row) {
                    $amounts[] = (int) round((float) ($row['amount'] ?? 0));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($laundryDb) {
            try {
                $rows = $laundryDb->query(
                    "SELECT ref_finance, SUM(jumlah) AS total
                     FROM kas
                     WHERE metode_mutasi = 2
                       AND status_mutasi = 2
                       AND UPPER(IFNULL(note, '')) = 'BCA'
                       AND ref_finance <> ''
                       AND insertTime >= ?
                     GROUP BY ref_finance",
                    [$since]
                )->result_array();
                foreach ($rows as $row) {
                    $amounts[] = (int) round((float) ($row['total'] ?? 0));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($wadeskDb) {
            try {
                $rows = $wadeskDb->query(
                    "SELECT amount FROM wa_tenant_dev_fee_payments
                     WHERE payment_method = 'bca' AND payment_status = 'pending' AND created_at >= ?",
                    [$since]
                )->result_array();
                foreach ($rows as $row) $amounts[] = (int) ($row['amount'] ?? 0);
            } catch (\Throwable $e) {
                // Dev Fee migration may not have run yet.
            }
        }

        try {
            $mainDb = \App\Core\DB::getInstance(0);
            $rows = $mainDb->query("SELECT amount FROM payment_manual_binds WHERE payment_method = 'bca' AND status = 'pending' AND expires_at >= NOW()")
                ->result_array();
            foreach ($rows ?: [] as $row) $amounts[] = (int) ($row['amount'] ?? 0);
        } catch (\Throwable $e) {
            // Migration bind manual belum tersedia.
        }

        $amounts = array_values(array_unique(array_filter($amounts, static function ($n) {
            return $n > 0;
        })));
        sort($amounts);

        return $amounts;
    }

    /**
     * Expire pending BCA lebih tua dari LOOKBACK_DAYS.
     *
     * @return array{invoice:int,salon:int}
     */
    public static function expireStalePending($invoiceDb = null, $salonDb = null): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::LOOKBACK_DAYS . ' days'));
        $stats = ['invoice' => 0, 'salon' => 0];

        if ($invoiceDb) {
            try {
                $pending = $invoiceDb->query(
                    "SELECT p.id, p.invoice_id, p.payment_ref
                     FROM invoice_payments p
                     WHERE p.payment_method = 'bca'
                       AND p.payment_status = 'pending'
                       AND p.created_at < ?",
                    [$cutoff]
                )->result_array();

                foreach ($pending as $row) {
                    $invoiceDb->update('invoice_payments', [
                        'payment_status' => 'expired',
                    ], ['id' => (int) $row['id']]);

                    $invoiceDb->query(
                        "UPDATE invoices i
                         SET i.payment_status = 'unpaid'
                         WHERE i.id = ?
                           AND i.payment_status = 'pending'
                           AND NOT EXISTS (
                             SELECT 1 FROM invoice_payments p2
                             WHERE p2.invoice_id = i.id
                               AND p2.payment_status = 'pending'
                           )",
                        [(int) $row['invoice_id']]
                    );
                    $stats['invoice']++;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($salonDb) {
            try {
                $pending = $salonDb->query(
                    "SELECT id, payment_ref FROM subscription_payments
                     WHERE payment_method = 'bca'
                       AND payment_status = 'pending'
                       AND created_at < ?",
                    [$cutoff]
                )->result_array();

                foreach ($pending as $row) {
                    $salonDb->update('subscription_payments', [
                        'payment_status' => 'failed',
                    ], ['id' => (int) $row['id']]);
                    $stats['salon']++;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $stats;
    }
}
