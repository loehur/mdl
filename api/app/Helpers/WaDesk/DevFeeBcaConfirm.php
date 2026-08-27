<?php

namespace App\Helpers\WaDesk;

/** Confirms a matched BCA Dev Fee payment and adds its purchased tenant quota once. */
class DevFeeBcaConfirm
{
    public static function approve($db, string $paymentRef): array
    {
        $payment = $db->query(
            "SELECT * FROM wa_tenant_dev_fee_payments
             WHERE payment_ref = ? AND payment_method = 'bca' AND payment_status = 'pending' LIMIT 1",
            [$paymentRef]
        )->row_array();
        if (!is_array($payment) || empty($payment['id'])) {
            return ['ok' => false, 'message' => 'payment Dev Fee BCA pending tidak ditemukan'];
        }
        $tenantId = (int) $payment['tenant_id'];
        $quotaAmount = (int) $payment['quota_amount'];
        if ($tenantId < 1 || $quotaAmount < 1) {
            return ['ok' => false, 'message' => 'data pembayaran Dev Fee tidak valid'];
        }

        if (!$db->beginTransaction()) {
            return ['ok' => false, 'message' => 'gagal memulai transaksi'];
        }
        try {
            $updated = $db->update('wa_tenant_dev_fee_payments', [
                'payment_status' => 'success',
                'paid_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $payment['id'], 'payment_status' => 'pending']);
            if (!$updated || (int) $db->affected_rows() < 1) {
                $db->rollback();
                return ['ok' => false, 'message' => 'payment sudah diproses'];
            }
            $db->query('INSERT IGNORE INTO wa_tenant_dev_fee_quotas (tenant_id) VALUES (?)', [$tenantId]);
            $db->query(
                'UPDATE wa_tenant_dev_fee_quotas SET quota_total = COALESCE(quota_total, 0) + ?, updated_at = NOW() WHERE tenant_id = ?',
                [$quotaAmount, $tenantId]
            );
            $db->commit();
            return ['ok' => true, 'tenant_id' => $tenantId, 'quota_added' => $quotaAmount];
        } catch (\Throwable $e) {
            $db->rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
