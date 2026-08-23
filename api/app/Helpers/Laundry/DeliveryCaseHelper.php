<?php

namespace App\Helpers\Laundry;

use App\Core\DB;
use App\Helpers\CRM\CrmChatMergeHelper;
use App\Helpers\CRM\WaSenderContext;

/**
 * Tutup case CRM 2 (Delivery Request) bila tidak ada request aktif.
 */
class DeliveryCaseHelper
{
    /**
     * @return bool true jika case 2 ditutup
     */
    public static function maybeCloseCase2(string $waNumber, int $idPelanggan): bool
    {
        $phoneTail = self::phoneTailForPelanggan($idPelanggan, $waNumber);
        if ($phoneTail === '') {
            return false;
        }

        $dbLaundry = DB::getInstance(1);
        $active = $dbLaundry->query(
            "SELECT COUNT(*) AS n FROM delivery_request
             WHERE phone_tail = ?
               AND delivery_status IN ('berjalan','menunggu_pembayaran')",
            [$phoneTail]
        )->row_array();
        if ((int) ($active['n'] ?? 0) > 0) {
            return false;
        }

        return self::closeCase2ByWaNumber($waNumber);
    }

    /**
     * @return bool true jika case 2 ditutup
     */
    public static function maybeCloseCase2ByPhoneTail(string $phoneTail): bool
    {
        $phoneTail = WaSenderContext::key($phoneTail);
        if (strlen($phoneTail) < 8) {
            return false;
        }

        $dbLaundry = DB::getInstance(1);
        $active = $dbLaundry->query(
            "SELECT COUNT(*) AS n FROM delivery_request
             WHERE phone_tail = ?
               AND delivery_status IN ('berjalan','menunggu_pembayaran')",
            [$phoneTail]
        )->row_array();
        if ((int) ($active['n'] ?? 0) > 0) {
            return false;
        }

        $db = DB::getInstance(0);
        $like = '%' . $phoneTail;
        $rows = $db->query(
            "SELECT id, wa_number, conv_case FROM wa_conversations
             WHERE REPLACE(REPLACE(REPLACE(wa_number, '+', ''), ' ', ''), '-', '') LIKE ?
             LIMIT 10",
            [$like]
        )->result_array();

        $anyClosed = false;
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (self::closeCase2InRow($db, $row)) {
                $anyClosed = true;
            }
        }

        return $anyClosed;
    }

    public static function closeCase2ByWaNumber(string $waNumber): bool
    {
        $waNumber = trim($waNumber);
        if ($waNumber === '') {
            return false;
        }

        $db = DB::getInstance(0);
        [, $variants] = CrmChatMergeHelper::phoneInClause($waNumber);
        if ($variants === []) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $rows = $db->query(
            "SELECT id, wa_number, conv_case FROM wa_conversations
             WHERE wa_number IN ({$placeholders})",
            $variants
        )->result_array();

        $anyClosed = false;
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!self::closeCase2InRow($db, $row)) {
                continue;
            }
            $anyClosed = true;
        }

        return $anyClosed;
    }

    /** @param array<string,mixed> $row */
    private static function closeCase2InRow(DB $db, array $row): bool
    {
        $caseList = self::decodeCaseList((string) ($row['conv_case'] ?? ''));
        if ($caseList === []) {
            return false;
        }

        $modified = false;
        foreach ($caseList as &$item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int) ($item['case'] ?? 0) === 2 && ($item['status'] ?? 'open') !== 'closed') {
                $item['status'] = 'closed';
                $modified = true;
            }
        }
        unset($item);

        if (!$modified) {
            return false;
        }

        $id = (int) ($row['id'] ?? 0);
        $json = json_encode(array_values($caseList), JSON_UNESCAPED_UNICODE);
        if ($id > 0) {
            $db->update('wa_conversations', ['conv_case' => $json], ['id' => $id]);
        } else {
            $wa = (string) ($row['wa_number'] ?? '');
            if ($wa !== '') {
                $db->update('wa_conversations', ['conv_case' => $json], ['wa_number' => $wa]);
            }
        }

        return true;
    }

    /** @return list<array<string,mixed>> */
    private static function decodeCaseList(string $raw): array
    {
        $trim = trim($raw);
        if ($trim === '') {
            return [];
        }
        if ($trim[0] === '[' || $trim[0] === '{') {
            $decoded = json_decode($trim, true);
            if (!is_array($decoded)) {
                return [];
            }
            if (isset($decoded[0])) {
                return $decoded;
            }
            if (isset($decoded['case'])) {
                return [$decoded];
            }
        } elseif (is_numeric($trim)) {
            return [['case' => (int) $trim, 'status' => 'open']];
        }

        return [];
    }

    private static function phoneTailForPelanggan(int $idPelanggan, string $waNumber): string
    {
        if ($idPelanggan > 0) {
            $pel = PelangganLokasiStore::findPelanggan($idPelanggan);
            if ($pel !== null) {
                $fromPel = WaSenderContext::key((string) ($pel['nomor_pelanggan'] ?? ''));
                if (strlen($fromPel) >= 8) {
                    return $fromPel;
                }
            }
        }

        return WaSenderContext::key($waNumber);
    }
}
