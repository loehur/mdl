<?php

namespace App\Helpers\Beauty_Salon;

/**
 * Helper untuk resolve & validasi referensi langkah kerja (work_step).
 */
class WorkStepHelper
{
    /**
     * @return array<int, array{id:int,name:string,fee:mixed}>
     */
    public static function loadMap($db, int $salon_id): array
    {
        $rows = $db->query(
            "SELECT id, name, fee FROM work_step WHERE salon_id = ?",
            [$salon_id]
        )->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = $row;
        }
        return $map;
    }

    /**
     * Pisahkan ID langkah kerja yang valid vs tidak ditemukan di master.
     *
     * @param mixed $work_steps
     * @return array{valid:int[], orphaned:int[]}
     */
    public static function splitWorkStepIds($work_steps, array $stepMap): array
    {
        if (!is_array($work_steps)) {
            return ['valid' => [], 'orphaned' => []];
        }

        $valid = [];
        $orphaned = [];

        foreach ($work_steps as $raw) {
            $stepId = self::extractStepId($raw);
            if (!$stepId) {
                continue;
            }

            if (isset($stepMap[$stepId])) {
                $valid[] = $stepId;
            } else {
                $orphaned[] = $stepId;
            }
        }

        return [
            'valid' => array_values(array_unique($valid)),
            'orphaned' => array_values(array_unique($orphaned)),
        ];
    }

    /**
     * Enrich produk: work_steps hanya ID valid, work_steps_orphaned untuk yang hilang.
     */
    public static function enrichProduct(array &$product, array $stepMap): void
    {
        $split = self::splitWorkStepIds($product['work_steps'] ?? [], $stepMap);
        $product['work_steps'] = $split['valid'];
        $product['work_steps_orphaned'] = $split['orphaned'];
    }

    /**
     * Hydrate nama langkah kerja pada order_items dari master work_step.
     */
    public static function hydrateOrderItems(array &$orderItems, array $stepMap): void
    {
        foreach ($orderItems as &$item) {
            if (!isset($item['work_steps']) || !is_array($item['work_steps'])) {
                continue;
            }

            foreach ($item['work_steps'] as &$step) {
                $stepId = self::extractStepId($step['step_id'] ?? $step['id'] ?? null);
                if (!$stepId) {
                    continue;
                }

                $step['step_id'] = $stepId;

                if (isset($stepMap[$stepId])) {
                    $step['step_name'] = $stepMap[$stepId]['name'];
                    if (!isset($step['fee']) || $step['fee'] === '' || $step['fee'] === null) {
                        $step['fee'] = $stepMap[$stepId]['fee'];
                    }
                    $step['step_missing'] = false;
                } else {
                    $step['step_missing'] = true;
                    $storedName = trim((string) ($step['step_name'] ?? ''));
                    if ($storedName === ''
                        || $storedName === 'undefined'
                        || preg_match('/^Step #?\d+$/i', $storedName)) {
                        $step['step_name'] = null;
                    }
                }
            }
            unset($step);
        }
        unset($item);
    }

    /**
     * Cek apakah langkah kerja dipakai di produk (format JSON array of IDs).
     */
    public static function isUsedInProducts($db, int $salon_id, int $step_id): bool
    {
        $row = $db->query(
            "SELECT COUNT(*) AS count
             FROM products
             WHERE salon_id = ?
               AND JSON_CONTAINS(work_steps, ?, '$')",
            [$salon_id, json_encode($step_id)]
        )->row_array();

        return !empty($row['count']);
    }

    private static function extractStepId($raw): ?int
    {
        if (is_array($raw)) {
            $raw = $raw['id'] ?? $raw['step_id'] ?? null;
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }
}
