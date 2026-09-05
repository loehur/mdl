<?php

namespace App\Helpers\Laundry;

use App\Core\DB;

/** Allocates a non-cash payment across the customer's oldest open balances. */
class PaymentAllocator
{
    public function allocate(int $customerId, int $amount, string $type, ?string $receiptDate = null, ?string $messageId = null): array
    {
        if ($customerId <= 0 || $amount <= 0) {
            return ['ok' => false, 'message' => 'Customer atau nominal tidak valid'];
        }
        $type = strtoupper(trim($type));
        if (!in_array($type, ['BCA', 'QRIS'], true)) {
            return ['ok' => false, 'message' => 'Tipe pembayaran tidak valid'];
        }

        $db = DB::getInstance(1);
        // Retry protection uses the inbound message marker in logs; ref_finance
        // must remain the short numeric reference expected by Laundry approval.
        $rows = $this->loadBalances($db, $customerId);
        if ($rows === []) {
            return ['ok' => false, 'message' => 'Tidak ada tagihan aktif'];
        }

        $remaining = $amount;
        $refFinance = (date('Y') - 2024) . date('mdHis') . random_int(10, 99) . str_pad((string) ($rows[0]['id_cabang'] ?? 0), 1, '0', STR_PAD_LEFT);
        $created = [];
        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }
            $pay = min($remaining, (int) $row['balance']);
            if ($pay <= 0) {
                continue;
            }

            $insert = $db->insert('kas', [
                'id_kas' => (date('Y') - 2020) . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6)),
                'id_cabang' => (int) $row['id_cabang'],
                'jenis_mutasi' => 1,
                'jenis_transaksi' => (int) $row['transaction_type'],
                'ref_transaksi' => (string) $row['reference'],
                'metode_mutasi' => 2,
                'note' => $type,
                'status_mutasi' => 2,
                'jumlah' => $pay,
                'id_user' => 0,
                'id_client' => $customerId,
                'ref_finance' => $refFinance,
                'insertTime' => date('Y-m-d H:i:s'),
            ]);
            if ($insert === false) {
                return ['ok' => false, 'message' => $db->lastError() ?: 'Gagal menyimpan pembayaran', 'created' => $created];
            }

            $created[] = [
                'type' => $row['transaction_type'] === 3 ? 'member' : 'sale',
                'reference' => $row['reference'],
                'amount' => $pay,
            ];
            $remaining -= $pay;
        }

        return [
            'ok' => $created !== [],
            'message' => $created !== [] ? 'Pembayaran dibuat sebagai pending' : 'Pembayaran tidak dialokasikan',
            'ref_finance' => $refFinance,
            'receipt_date' => $receiptDate,
            'message_id' => $messageId,
            'amount' => $amount,
            'allocated' => $amount - $remaining,
            'remaining' => $remaining,
            'created' => $created,
        ];
    }

    private function loadBalances(DB $db, int $customerId): array
    {
        $rows = [];
        $sales = $db->query(
            'SELECT no_ref AS reference, id_cabang, insertTime FROM sale WHERE id_pelanggan = ? AND tuntas = 0 AND bin = 0 GROUP BY no_ref, id_cabang, insertTime ORDER BY insertTime ASC, no_ref ASC',
            [$customerId]
        )->result_array();
        foreach ($sales as $sale) {
            $total = $this->saleTotal($db, $sale['reference'], (int) $sale['id_cabang']);
            $paid = $this->paidTotal($db, $sale['reference'], 1, (int) $sale['id_cabang']);
            if ($total > $paid) {
                $rows[] = ['transaction_type' => 1, 'reference' => $sale['reference'], 'id_cabang' => $sale['id_cabang'], 'balance' => $total - $paid, 'sort' => $sale['insertTime']];
            }
        }

        $members = $db->query(
            'SELECT id_member AS reference, id_cabang, harga, insertTime FROM member WHERE id_pelanggan = ? AND bin = 0 AND lunas = 0 ORDER BY insertTime ASC, id_member ASC',
            [$customerId]
        )->result_array();
        foreach ($members as $member) {
            $paid = $this->paidTotal($db, $member['reference'], 3, (int) $member['id_cabang']);
            if ((int) $member['harga'] > $paid) {
                $rows[] = ['transaction_type' => 3, 'reference' => $member['reference'], 'id_cabang' => $member['id_cabang'], 'balance' => (int) $member['harga'] - $paid, 'sort' => $member['insertTime']];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $a['sort'], (string) $b['sort']);
        });
        return $rows;
    }

    private function saleTotal(DB $db, string $reference, int $branchId): int
    {
        $items = $db->query('SELECT qty, min_order, harga, diskon_qty, diskon_partner, member FROM sale WHERE no_ref = ? AND id_cabang = ? AND bin = 0', [$reference, $branchId])->result_array();
        $total = 0;
        foreach ($items as $item) {
            if ((int) $item['member'] !== 0) continue;
            $qty = max((float) $item['qty'], (float) $item['min_order']);
            $line = (float) $item['harga'] * $qty;
            $line *= 1 - ((float) $item['diskon_qty'] / 100);
            $line *= 1 - ((float) $item['diskon_partner'] / 100);
            $total += (int) round($line);
        }
        $surcas = $db->query('SELECT jumlah FROM surcas WHERE id_cabang = ? AND no_ref = ?', [$branchId, $reference])->result_array();
        foreach ($surcas as $row) $total += (int) $row['jumlah'];
        return $total;
    }

    private function paidTotal(DB $db, $reference, int $transactionType, int $branchId): int
    {
        $row = $db->query('SELECT COALESCE(SUM(jumlah), 0) AS total FROM kas WHERE ref_transaksi = ? AND jenis_transaksi = ? AND id_cabang = ? AND status_mutasi IN (2, 3)', [$reference, $transactionType, $branchId])->row();
        return (int) ($row->total ?? 0);
    }
}
