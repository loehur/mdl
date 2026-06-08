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

        $totalExpense = $this->safeExpenseSum(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE record_date BETWEEN ? AND ?",
            [$start, $end]
        );

        $sourceIds = $this->parseSourceIds($this->query('source_ids', ''));
        $filteredIncome = $this->sumIncomeForSources($incomeBySource, $sourceIds);

        $this->success([
            'month' => $month,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => $totalIncome - $totalExpense,
            'income_by_source' => $incomeBySource,
            'selected_source_ids' => $sourceIds,
            'filtered_income' => $filteredIncome,
            'filtered_net' => $filteredIncome - $totalExpense,
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
}
