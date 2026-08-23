<?php

namespace App\Helpers\CRM;

/**
 * Aturan case CRM — case 4 (Follow Up / ungu) tidak boleh bergandengan case lain.
 * Case 4 selalu mengalah; case 1/2/3 boleh coexist.
 */
class CrmCaseHelper
{
    public const CASE_FOLLOW_UP = 4;

    /**
     * @return list<array<string,mixed>>
     */
    public static function decodeList($raw): array
    {
        if (is_array($raw)) {
            if (isset($raw[0])) {
                return $raw;
            }
            if (isset($raw['case'])) {
                return [$raw];
            }

            return [];
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
                $parsed = json_decode($trimmed, true);
                if (is_array($parsed)) {
                    if (isset($parsed[0])) {
                        return $parsed;
                    }
                    if (isset($parsed['case'])) {
                        return [$parsed];
                    }
                }
            }
        }

        if (is_numeric($raw)) {
            $n = (int) $raw;
            if ($n > 0) {
                return [['case' => $n, 'status' => 'open']];
            }
        }

        return [];
    }

    /**
     * ID case open untuk WS/API — tidak pernah mengembalikan 4 jika ada case open lain.
     *
     * @return list<int>
     */
    public static function extractOpenCaseIds($raw): array
    {
        $openIds = [];
        foreach (self::decodeList($raw) as $c) {
            if (!isset($c['case'])) {
                continue;
            }
            if (($c['status'] ?? 'open') === 'closed') {
                continue;
            }
            $id = (int) $c['case'];
            if ($id > 0) {
                $openIds[] = $id;
            }
        }

        $openIds = array_values(array_unique($openIds));
        $nonFollowUp = array_values(array_filter(
            $openIds,
            static fn (int $id): bool => $id !== self::CASE_FOLLOW_UP
        ));

        if ($nonFollowUp !== []) {
            return $nonFollowUp;
        }

        return $openIds;
    }

    public static function hasOtherOpenCases(array $caseList, int $excludeCase = self::CASE_FOLLOW_UP): bool
    {
        foreach ($caseList as $c) {
            $id = (int) ($c['case'] ?? 0);
            if ($id <= 0 || $id === $excludeCase) {
                continue;
            }
            if (($c['status'] ?? 'open') !== 'closed') {
                return true;
            }
        }

        return false;
    }

    /**
     * Tutup case 4 bila ada case open selain 4.
     *
     * @param list<array<string,mixed>> $caseList
     * @return list<array<string,mixed>>
     */
    public static function enforceCaseFourExclusivity(array $caseList): array
    {
        if (!self::hasOtherOpenCases($caseList)) {
            return $caseList;
        }

        foreach ($caseList as &$c) {
            if ((int) ($c['case'] ?? 0) === self::CASE_FOLLOW_UP && ($c['status'] ?? 'open') !== 'closed') {
                $c['status'] = 'closed';
                unset($c['timestamp'], $c['resolved_at'], $c['resolved_by']);
            }
        }
        unset($c);

        return array_values($caseList);
    }

    /**
     * Buka/tambah satu case dengan aturan case 4.
     *
     * @param list<array<string,mixed>> $caseList
     * @return array{list:list<array<string,mixed>>,changed:bool,skipped:bool}
     */
    public static function mergeOpenCase(array $caseList, int $case): array
    {
        $case = (int) $case;
        if ($case <= 0) {
            return ['list' => $caseList, 'changed' => false, 'skipped' => true];
        }

        $caseList = array_values($caseList);

        if ($case === self::CASE_FOLLOW_UP) {
            if (self::hasOtherOpenCases($caseList)) {
                return ['list' => $caseList, 'changed' => false, 'skipped' => true];
            }
        } else {
            $caseList = self::enforceCaseFourExclusivity($caseList);
        }

        $found = false;
        foreach ($caseList as &$existing) {
            if ((int) ($existing['case'] ?? 0) === $case) {
                $existing['status'] = 'open';
                unset($existing['timestamp'], $existing['resolved_at'], $existing['resolved_by']);
                $found = true;
                break;
            }
        }
        unset($existing);

        if (!$found) {
            $caseList[] = ['case' => $case, 'status' => 'open'];
        }

        return ['list' => array_values($caseList), 'changed' => true, 'skipped' => false];
    }

    /**
     * @param list<array<string,mixed>> $caseList
     */
    public static function encodeList(array $caseList): string
    {
        return json_encode(array_values($caseList), JSON_UNESCAPED_UNICODE);
    }
}
