<?php

namespace App\Helpers\WaDesk;

/**
 * Per-team template message balance (shared by TL + agents on that team).
 * Consume only after a successful YCloud template send.
 */
class TemplateQuota
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getBalance(int $teamId): int
    {
        $row = $this->db->query(
            "SELECT balance FROM wa_team_template_quotas WHERE team_id = ? LIMIT 1",
            [$teamId]
        )->row_array();

        return (int) ($row['balance'] ?? 0);
    }

    public function ensureRow(int $teamId, int $tenantId): void
    {
        $existing = $this->db->query(
            "SELECT team_id FROM wa_team_template_quotas WHERE team_id = ? LIMIT 1",
            [$teamId]
        )->row_array();

        if ($existing) {
            return;
        }

        try {
            $this->db->insert('wa_team_template_quotas', [
                'team_id' => $teamId,
                'tenant_id' => $tenantId,
                'balance' => 0,
            ]);
        } catch (\Throwable $e) {
            // Parallel insert race — ignore
        }
    }

    public function canConsume(int $teamId, int $n = 1): bool
    {
        if ($n < 1) {
            return true;
        }
        return $this->getBalance($teamId) >= $n;
    }

    /**
     * Atomically deduct 1 from balance if available.
     *
     * @return array{ok:bool,balance:int,error:string}
     */
    public function consume(
        int $teamId,
        int $tenantId,
        ?int $userId = null,
        string $source = 'chat',
        ?string $refType = null,
        ?int $refId = null,
        ?string $note = null
    ): array {
        $this->ensureRow($teamId, $tenantId);

        $this->db->query(
            "UPDATE wa_team_template_quotas
             SET balance = balance - 1, updated_at = NOW()
             WHERE team_id = ? AND balance >= 1",
            [$teamId]
        );

        if ((int) $this->db->affected_rows() < 1) {
            return [
                'ok' => false,
                'balance' => $this->getBalance($teamId),
                'error' => 'Kuota template team habis',
            ];
        }

        $balance = $this->getBalance($teamId);
        $this->insertLog([
            'tenant_id' => $tenantId,
            'team_id' => $teamId,
            'type' => 'consume',
            'amount' => -1,
            'balance_after' => $balance,
            'user_id' => $userId ?: null,
            'source' => $source,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'note' => $note,
        ]);

        return ['ok' => true, 'balance' => $balance, 'error' => ''];
    }

    /**
     * @return array{ok:bool,balance:int,error:string}
     */
    public function topUp(
        int $teamId,
        int $tenantId,
        int $amount,
        ?int $adminUserId = null,
        ?string $note = null
    ): array {
        if ($amount < 1) {
            return ['ok' => false, 'balance' => $this->getBalance($teamId), 'error' => 'amount harus > 0'];
        }

        $this->ensureRow($teamId, $tenantId);

        $this->db->query(
            "UPDATE wa_team_template_quotas
             SET balance = balance + ?, updated_at = NOW()
             WHERE team_id = ?",
            [$amount, $teamId]
        );

        $balance = $this->getBalance($teamId);
        $this->insertLog([
            'tenant_id' => $tenantId,
            'team_id' => $teamId,
            'type' => 'topup',
            'amount' => $amount,
            'balance_after' => $balance,
            'user_id' => $adminUserId ?: null,
            'source' => 'admin_topup',
            'ref_type' => null,
            'ref_id' => null,
            'note' => $note,
        ]);

        return ['ok' => true, 'balance' => $balance, 'error' => ''];
    }

    private function insertLog(array $data): void
    {
        $this->db->insert('wa_team_template_quota_logs', [
            'tenant_id' => (int) $data['tenant_id'],
            'team_id' => (int) $data['team_id'],
            'type' => $data['type'],
            'amount' => (int) $data['amount'],
            'balance_after' => (int) $data['balance_after'],
            'user_id' => $data['user_id'] !== null ? (int) $data['user_id'] : null,
            'source' => $data['source'] ?? null,
            'ref_type' => $data['ref_type'] ?? null,
            'ref_id' => $data['ref_id'] !== null ? (int) $data['ref_id'] : null,
            'note' => $data['note'] ?? null,
        ]);
    }
}
