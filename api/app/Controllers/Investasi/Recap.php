<?php

namespace App\Controllers\Investasi;

/**
 * Recap — GET summary (income, expense, net, breakdown per sumber)
 */
class Recap extends InvestasiController
{
    public function __construct()
    {
        parent::__construct();
        $this->verifyAuth();
    }

    public function summary()
    {
        $month = $this->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Format month tidak valid (YYYY-MM)', 400);
        }

        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $bySource = $this->db($this->db_index)->query(
            "SELECT d.source_id, s.name AS source_name, COALESCE(SUM(d.amount), 0) AS total
             FROM daily_incomes d
             LEFT JOIN income_sources s ON s.id = d.source_id
             WHERE d.record_date BETWEEN ? AND ?
             GROUP BY d.source_id, s.name
             ORDER BY total DESC, source_name ASC",
            [$start, $end]
        )->result_array();

        $incomeBySource = array_map(function ($row) {
            return [
                'source_id' => $row['source_id'] !== null ? (int) $row['source_id'] : null,
                'source_name' => $row['source_name'] ?: 'Tanpa sumber',
                'total' => (float) $row['total'],
            ];
        }, $bySource);

        $totalIncome = array_sum(array_column($incomeBySource, 'total'));

        $expenseByTarget = $this->expenseByTarget($start, $end);
        $totalExpense = array_sum(array_column($expenseByTarget, 'total'));

        $sourceIds = $this->parseSourceIds($this->query('source_ids', ''));
        $targetIds = $this->parseSourceIds($this->query('target_ids', ''));
        $filteredIncome = $this->sumIncomeForSources($incomeBySource, $sourceIds);
        $filteredExpense = $this->sumExpenseForTargets($expenseByTarget, $targetIds);

        $this->success([
            'month' => $month,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => $totalIncome - $totalExpense,
            'income_by_source' => $incomeBySource,
            'expense_by_target' => $expenseByTarget,
            'selected_source_ids' => $sourceIds,
            'selected_target_ids' => $targetIds,
            'filtered_income' => $filteredIncome,
            'filtered_expense' => $filteredExpense,
            'filtered_net' => $filteredIncome - $totalExpense,
            'filtered_net_expense' => $totalIncome - $filteredExpense,
        ]);
    }

    /** @return int[] */
    private function parseSourceIds($raw): array
    {
        if ($raw === '' || $raw === null) {
            return [];
        }

        $ids = [];
        foreach (explode(',', (string) $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param array<int, array{source_id: ?int, total: float}> $incomeBySource */
    private function sumIncomeForSources(array $incomeBySource, array $sourceIds): float
    {
        if (empty($sourceIds)) {
            return array_sum(array_column($incomeBySource, 'total'));
        }

        $allowed = array_flip($sourceIds);
        $sum = 0.0;

        foreach ($incomeBySource as $row) {
            $sid = $row['source_id'];
            if ($sid !== null && isset($allowed[$sid])) {
                $sum += $row['total'];
            }
        }

        return $sum;
    }

    /** @return array<int, array{target_id: ?int, target_name: string, total: float}> */
    private function expenseByTarget(string $start, string $end): array
    {
        try {
            $rows = $this->db($this->db_index)->query(
                "SELECT d.target_id, t.name AS target_name, COALESCE(SUM(d.amount), 0) AS total
                 FROM daily_expenses d
                 LEFT JOIN expense_targets t ON t.id = d.target_id
                 WHERE d.record_date BETWEEN ? AND ?
                 GROUP BY d.target_id, t.name
                 ORDER BY total DESC, target_name ASC",
                [$start, $end]
            )->result_array();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(function ($row) {
            return [
                'target_id' => $row['target_id'] !== null ? (int) $row['target_id'] : null,
                'target_name' => $row['target_name'] ?: 'Tanpa target',
                'total' => (float) $row['total'],
            ];
        }, $rows);
    }

    /** @param array<int, array{target_id: ?int, total: float}> $expenseByTarget */
    private function sumExpenseForTargets(array $expenseByTarget, array $targetIds): float
    {
        if (empty($targetIds)) {
            return array_sum(array_column($expenseByTarget, 'total'));
        }

        $allowed = array_flip($targetIds);
        $sum = 0.0;

        foreach ($expenseByTarget as $row) {
            $tid = $row['target_id'];
            if ($tid !== null && isset($allowed[$tid])) {
                $sum += $row['total'];
            }
        }

        return $sum;
    }
}
