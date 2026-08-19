<?php

namespace App\Helpers\WaDesk;

/**
 * AI moderation for WhatsApp template parameter values only.
 */
class TemplateParamModerator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda moderasi nilai parameter template WhatsApp bisnis Indonesia.
Hanya nilai parameter yang dikirim user — bukan teks template.

Tolak (safe=false) jika ADA nilai yang mengandung:
- kata kotor / umpatan / profanity
- hujatan, ejekan, atau penghinaan
- ujaran kebencian, ancaman, atau pelecehan

Izinkan (safe=true) nama orang, nama perusahaan, nominal, tanggal, dan teks bisnis sopan.

Balas HANYA JSON valid, tanpa markdown:
{"safe":true}
atau
{"safe":false,"reason":"penjelasan singkat dalam Bahasa Indonesia, sebut parameter yang bermasalah"}
PROMPT;

    private const BLAST_SYSTEM_PROMPT = <<<'PROMPT'
Anda moderasi nilai parameter template WhatsApp bisnis Indonesia untuk blast/campaign massal.
Semua nilai dari CSV dikumpulkan sekaligus — bukan teks template.

Format input: daftar nilai parameter dipisah "|".

Tolak (safe=false) jika ADA nilai yang mengandung:
- kata kotor / umpatan / profanity
- hujatan, ejekan, atau penghinaan
- ujaran kebencian, ancaman, atau pelecehan

Izinkan (safe=true) nama orang, nama perusahaan, nominal, tanggal, dan teks bisnis sopan.

Balas HANYA JSON valid, tanpa markdown:
{"safe":true}
atau
{"safe":false,"reason":"penjelasan singkat dalam Bahasa Indonesia, sebut nilai yang bermasalah"}
PROMPT;

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param array<int,array{label:string,key:string,value:string}> $entries
     * @return array{safe:bool,reason:string,skipped?:bool}
     */
    public function moderate(string $apiKey, array $entries): array
    {
        $entries = array_values(array_filter($entries, static function ($e) {
            return trim((string) ($e['value'] ?? '')) !== '';
        }));

        if ($entries === []) {
            return ['safe' => true, 'reason' => ''];
        }

        if (trim($apiKey) === '') {
            return ['safe' => true, 'reason' => '', 'skipped' => true];
        }

        $userPayload = json_encode(['parameters' => $entries], JSON_UNESCAPED_UNICODE);
        $client = new OpenAi($apiKey);
        $res = $client->chatJson(self::SYSTEM_PROMPT, $userPayload);

        if (!$res['success']) {
            return [
                'safe' => false,
                'reason' => 'Moderasi AI gagal: ' . ($res['error'] ?: 'unknown'),
            ];
        }

        $data = $res['data'];
        $safe = !empty($data['safe']);
        $reason = trim((string) ($data['reason'] ?? ''));

        if (!$safe && $reason === '') {
            $reason = 'Konten parameter tidak aman menurut moderasi AI.';
        }

        return [
            'safe' => $safe,
            'reason' => $safe ? '' : $reason,
        ];
    }

    /**
     * Collect all non-empty parameter values from blast rows (values only).
     *
     * @param array<int,array{phone?:string,params?:array}> $rows
     * @return array<int,string>
     */
    public static function collectBlastRowValues(array $rows, array $paramDefs): array
    {
        $values = [];
        foreach ($rows as $row) {
            $rawParams = is_array($row['params'] ?? null) ? $row['params'] : [];
            foreach (self::entriesFromDefs($paramDefs, $rawParams) as $entry) {
                $value = trim((string) ($entry['value'] ?? ''));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * One AI call for all blast parameter values (pipe-separated summary).
     *
     * @param array<int,string> $values
     * @return array{safe:bool,reason:string,skipped?:bool}
     */
    public function moderateBatchValues(string $apiKey, array $values): array
    {
        $values = array_values(array_filter(array_map(static function ($v) {
            return trim((string) $v);
        }, $values), static function ($v) {
            return $v !== '';
        }));

        if ($values === []) {
            return ['safe' => true, 'reason' => ''];
        }

        if (trim($apiKey) === '') {
            return ['safe' => true, 'reason' => '', 'skipped' => true];
        }

        $valuesText = '| ' . implode(' | ', $values) . ' |';
        $userPayload = json_encode([
            'values' => $values,
            'values_text' => $valuesText,
        ], JSON_UNESCAPED_UNICODE);

        $client = new OpenAi($apiKey);
        $res = $client->chatJson(self::BLAST_SYSTEM_PROMPT, $userPayload);

        if (!$res['success']) {
            return [
                'safe' => false,
                'reason' => 'Moderasi AI gagal: ' . ($res['error'] ?: 'unknown'),
            ];
        }

        $data = $res['data'];
        $safe = !empty($data['safe']);
        $reason = trim((string) ($data['reason'] ?? ''));

        if (!$safe && $reason === '') {
            $reason = 'Konten parameter blast tidak aman menurut moderasi AI.';
        }

        return [
            'safe' => $safe,
            'reason' => $safe ? '' : $reason,
        ];
    }

    /**
     * Build labeled param entries from template defs + raw request map.
     *
     * @return array<int,array{label:string,key:string,value:string}>
     */
    public static function entriesFromDefs(array $defs, array $rawParams): array
    {
        if ($defs === []) {
            return [];
        }

        $entries = [];
        $isList = $rawParams === [] || array_keys($rawParams) === range(0, count($rawParams) - 1);
        $listCursor = 0;

        foreach ($defs as $def) {
            $component = strtolower((string) ($def['component'] ?? 'body'));
            $paramName = trim((string) ($def['param_name'] ?? ''));
            $idx = (int) ($def['param_index'] ?? 0);
            $csvKey = $component . '_' . $idx;
            $label = trim((string) ($def['label'] ?? $paramName ?: $csvKey));

            $value = '';
            if ($paramName !== '' && !$isList && array_key_exists($paramName, $rawParams)) {
                $value = (string) $rawParams[$paramName];
            } elseif (!$isList && array_key_exists($csvKey, $rawParams)) {
                $value = (string) $rawParams[$csvKey];
            } elseif (!$isList && array_key_exists((string) $idx, $rawParams)) {
                $value = (string) $rawParams[(string) $idx];
            } elseif ($isList && array_key_exists($listCursor, $rawParams)) {
                $value = (string) $rawParams[$listCursor];
                $listCursor++;
            } elseif ($isList && array_key_exists($idx - 1, $rawParams)) {
                $value = (string) $rawParams[$idx - 1];
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $key = $paramName !== '' ? $paramName : $csvKey;
            $entries[] = [
                'label' => $label,
                'key' => $key,
                'value' => $value,
            ];
        }

        return $entries;
    }
}
