<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\TenantDevFee;
use App\Helpers\Payment\BankAccountGuide;
use App\Helpers\Payment\BcaUniqueNominal;

/** Read-only tenant-wide template usage for the Dev Fee admin tab. */
class DevFee extends WaDeskController
{
    public function summary()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];
        $fee = new TenantDevFee($this->db($this->db_index));
        if (!$fee->tableReady()) {
            $this->success(['table_ready' => false]);
            return;
        }
        $fee->ensureRow($tenantId);
        $row = $this->db($this->db_index)->query(
            'SELECT quota_total, quota_used, updated_at FROM wa_tenant_dev_fee_quotas WHERE tenant_id = ? LIMIT 1',
            [$tenantId]
        )->row_array() ?: [];
        $total = $row['quota_total'] === null ? null : (int) $row['quota_total'];
        $used = (int) ($row['quota_used'] ?? 0);
        $this->success([
            'table_ready' => true,
            'quota_total' => $total,
            'quota_used' => $used,
            'quota_remaining' => $total === null ? null : max(0, $total - $used),
            'updated_at' => $row['updated_at'] ?? null,
            'pending_payment' => ($pending = $this->pendingPayment($tenantId))
                ? $this->paymentPayload($pending)
                : null,
        ]);
    }

    /** Create a BCA-only Dev Fee top-up order. Rp50 buys one quota. */
    public function createTopup()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) $this->error('Method not allowed', 405);

        $fee = new TenantDevFee($this->db($this->db_index));
        if (!$fee->tableReady()) $this->error('Tabel Dev Fee belum tersedia', 409);
        $tenantId = (int) $admin['tenant_id'];
        $quota = (int) (($this->getBody()['quota_amount'] ?? 0));
        if ($quota < 1 || $quota > 10000000) {
            $this->error('Jumlah quota harus antara 1 dan 10.000.000', 400);
        }
        $existing = $this->pendingPayment($tenantId);
        if ($existing) {
            $this->success($this->paymentPayload($existing), 'Masih ada pembayaran BCA yang menunggu konfirmasi');
            return;
        }
        $base = $quota * 50;
        $db = $this->db($this->db_index);
        $amount = BcaUniqueNominal::allocate($base, $this->db(6), $this->db(4), $this->db(1), $db);
        $ref = 'MDFEE_' . $tenantId . '_' . time() . '_' . random_int(100, 999);
        $db->insert('wa_tenant_dev_fee_payments', [
            'tenant_id' => $tenantId,
            'created_by' => (int) $admin['id'],
            'payment_ref' => $ref,
            'quota_amount' => $quota,
            'base_amount' => $base,
            'amount' => $amount,
            'payment_method' => 'bca',
            'payment_status' => 'pending',
        ]);
        $payment = $db->query('SELECT * FROM wa_tenant_dev_fee_payments WHERE payment_ref = ? LIMIT 1', [$ref])->row_array();
        $this->success($this->paymentPayload($payment ?: []), 'Silakan transfer sesuai nominal BCA');
    }

    /** Cancel the current tenant's pending BCA top-up before it is confirmed. */
    public function cancelTopup()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        if (!$this->isPost()) $this->error('Method not allowed', 405);
        $ref = trim((string) (($this->getBody()['payment_ref'] ?? '')));
        if ($ref === '') $this->error('payment_ref wajib', 400);
        $updated = $this->db($this->db_index)->update('wa_tenant_dev_fee_payments', [
            'payment_status' => 'cancelled',
        ], [
            'tenant_id' => (int) $admin['tenant_id'],
            'payment_ref' => $ref,
            'payment_status' => 'pending',
        ]);
        if (!$updated || (int) $this->db($this->db_index)->affected_rows() < 1) {
            $this->error('Pembayaran pending tidak ditemukan atau sudah diproses', 404);
        }
        $this->success([], 'Pembayaran BCA dibatalkan');
    }

    public function logs()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $tenantId = (int) $admin['tenant_id'];
        $fee = new TenantDevFee($this->db($this->db_index));
        if (!$fee->tableReady()) {
            $this->success(['table_ready' => false, 'logs' => [], 'total' => 0, 'page' => 1]);
            return;
        }
        $page = max(1, (int) $this->query('page', 1));
        $limit = min(50, max(1, (int) $this->query('limit', 20)));
        $offset = ($page - 1) * $limit;
        $db = $this->db($this->db_index);
        $rows = $db->query(
            "SELECT l.*, u.name AS user_name, t.name AS team_name, c.label AS channel_label
             FROM wa_tenant_dev_fee_logs l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN teams t ON t.id = l.team_id
             LEFT JOIN wa_channels c ON c.id = l.channel_id
             WHERE l.tenant_id = ? ORDER BY l.id DESC LIMIT ? OFFSET ?",
            [$tenantId, $limit, $offset]
        )->result_array();
        $total = (int) ($db->query(
            'SELECT COUNT(*) AS cnt FROM wa_tenant_dev_fee_logs WHERE tenant_id = ?', [$tenantId]
        )->row_array()['cnt'] ?? 0);
        $this->success(['table_ready' => true, 'logs' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /** Successful and pending BCA top-up history for the current tenant. */
    public function payments()
    {
        $this->verifyAuth();
        $admin = $this->requireAdmin();
        $db = $this->db($this->db_index);
        try {
            $rows = $db->query(
                "SELECT p.*, u.name AS user_name FROM wa_tenant_dev_fee_payments p
                 LEFT JOIN users u ON u.id = p.created_by
                 WHERE p.tenant_id = ? ORDER BY p.id DESC LIMIT 50",
                [(int) $admin['tenant_id']]
            )->result_array();
            $this->success(['payments' => $rows]);
        } catch (\Throwable $e) {
            $this->success(['payments' => []]);
        }
    }

    private function pendingPayment(int $tenantId): ?array
    {
        try {
            $row = $this->db($this->db_index)->query(
                "SELECT * FROM wa_tenant_dev_fee_payments
                 WHERE tenant_id = ? AND payment_method = 'bca' AND payment_status = 'pending'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY id DESC LIMIT 1",
                [$tenantId]
            )->row_array();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function paymentPayload(array $payment): array
    {
        $base = (int) ($payment['base_amount'] ?? 0);
        $amount = (int) ($payment['amount'] ?? 0);
        return [
            'payment_ref' => (string) ($payment['payment_ref'] ?? ''),
            'payment_method' => 'bca',
            'payment_status' => (string) ($payment['payment_status'] ?? 'pending'),
            'quota_amount' => (int) ($payment['quota_amount'] ?? 0),
            'base_amount' => $base,
            'amount' => $amount,
            'unique_nominal' => $amount !== $base,
            'created_at' => $payment['created_at'] ?? null,
            'bank_account' => BankAccountGuide::bcaAccount(),
        ];
    }
}
