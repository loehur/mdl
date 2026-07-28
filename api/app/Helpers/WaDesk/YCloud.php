<?php

namespace App\Helpers\WaDesk;

/**
 * Lightweight yCloud WhatsApp client for WaDesk (per-key credentials).
 * Does not touch mdl_main CRM tables.
 */
class YCloud
{
    private string $apiKey;
    private string $whatsappNumber;
    private string $baseUrl = 'https://api.ycloud.com/v2';

    public function __construct(string $apiKey, string $whatsappNumber)
    {
        $this->apiKey = $apiKey;
        $this->whatsappNumber = $whatsappNumber;
    }

    public static function isWithinCsw(?string $lastMessageAt, int $hours = 23): bool
    {
        if (empty($lastMessageAt)) {
            return false;
        }
        $diff = (time() - strtotime($lastMessageAt)) / 3600;
        return $diff >= 0 && $diff <= $hours;
    }

    public function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return $phone;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return '+' . $digits;
    }

    public function sendFreeText(string $to, string $message, ?string $replyToMessageId = null): array
    {
        $payload = [
            'from' => $this->formatPhone($this->whatsappNumber),
            'to' => $this->formatPhone($to),
            'type' => 'text',
            'externalId' => 'wd_' . bin2hex(random_bytes(8)),
            'text' => ['body' => $message],
        ];
        if ($replyToMessageId) {
            $payload['context'] = ['message_id' => $replyToMessageId];
        }
        return $this->request('/whatsapp/messages', $payload);
    }

    /**
     * Send WhatsApp template.
     *
     * $parameters supports:
     * - flat list of strings → all body positional (legacy)
     * - list of maps:
     *   ['component' => 'header'|'body'|'button', 'text' => '...', 'param_name' => 'customer'|null]
     * - associative map name => value (treated as body named params, laundry-style keys)
     *
     * @param array $parameters
     */
    public function sendTemplate(string $to, string $templateName, string $language, array $parameters = []): array
    {
        $components = $this->buildTemplateComponents($parameters);

        $payload = [
            'from' => $this->formatPhone($this->whatsappNumber),
            'to' => $this->formatPhone($to),
            'type' => 'template',
            'externalId' => 'wd_' . bin2hex(random_bytes(8)),
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];

        return $this->request('/whatsapp/messages', $payload);
    }

    /**
     * List WhatsApp templates from YCloud (GET /whatsapp/templates).
     *
     * @param array{page?:int,limit?:int,status?:string,name?:string,language?:string,waba_id?:string} $opts
     * @return array{success:bool,http_code:int,data:array}
     */
    public function listTemplates(array $opts = []): array
    {
        $query = [
            'page' => max(1, (int) ($opts['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($opts['limit'] ?? 100))),
            'includeTotal' => 'true',
        ];
        $status = trim((string) ($opts['status'] ?? 'APPROVED'));
        if ($status !== '') {
            $query['filter.status'] = $status;
        }
        if (!empty($opts['name'])) {
            $query['filter.name'] = (string) $opts['name'];
        }
        if (!empty($opts['language'])) {
            $query['filter.language'] = (string) $opts['language'];
        }
        if (!empty($opts['waba_id'])) {
            $query['filter.wabaId'] = (string) $opts['waba_id'];
        }

        return $this->requestGet('/whatsapp/templates', $query);
    }

    /**
     * Fetch all pages of APPROVED templates (or given status).
     *
     * @return array{success:bool,error:string,templates:array<int,array>}
     */
    public function listAllTemplates(array $opts = []): array
    {
        $page = 1;
        $all = [];
        $maxPages = 50;

        while ($page <= $maxPages) {
            $res = $this->listTemplates(array_merge($opts, [
                'page' => $page,
                'limit' => 100,
            ]));
            if (!$res['success']) {
                $err = $res['data']['error']['message']
                    ?? ($res['data']['message'] ?? ('YCloud HTTP ' . $res['http_code']));
                return ['success' => false, 'error' => (string) $err, 'templates' => $all];
            }

            $data = $res['data'];
            $items = [];
            if (isset($data['items']) && is_array($data['items'])) {
                $items = $data['items'];
            } elseif (isset($data[0]) || $data === []) {
                // Some responses may be a bare list
                $items = is_array($data) && (isset($data[0]) || $data === []) ? $data : [];
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $all[] = $item;
                }
            }

            $length = (int) ($data['length'] ?? count($items));
            $limit = (int) ($data['limit'] ?? 100);
            if ($length < $limit || $items === []) {
                break;
            }
            $page++;
        }

        return ['success' => true, 'error' => '', 'templates' => $all];
    }

    /**
     * Map a YCloud WhatsApp template object → WaDesk fields.
     *
     * @return array{template_name:string,language:string,status:string,body_preview:?string,params:array<int,array>}
     */
    public static function mapTemplateToWaDesk(array $tpl): array
    {
        $name = trim((string) ($tpl['name'] ?? ''));
        $language = trim((string) ($tpl['language'] ?? 'id')) ?: 'id';
        $status = strtoupper(trim((string) ($tpl['status'] ?? '')));
        $components = $tpl['components'] ?? [];
        if (!is_array($components)) {
            $components = [];
        }

        $bodyPreview = null;
        $params = [];

        foreach ($components as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $type = strtoupper((string) ($comp['type'] ?? ''));

            if ($type === 'BODY') {
                $text = (string) ($comp['text'] ?? '');
                if ($text !== '') {
                    $bodyPreview = $text;
                }
                $examples = self::extractComponentExamples($comp, 'body');
                foreach (self::extractPlaceholders($text) as $i => $ph) {
                    $params[] = self::makeParamRow('body', $i + 1, $ph, $examples[$i] ?? ($examples[$ph] ?? ''));
                }
            } elseif ($type === 'HEADER') {
                $format = strtoupper((string) ($comp['format'] ?? 'TEXT'));
                if ($format === 'TEXT') {
                    $text = (string) ($comp['text'] ?? '');
                    $examples = self::extractComponentExamples($comp, 'header');
                    foreach (self::extractPlaceholders($text) as $i => $ph) {
                        $params[] = self::makeParamRow('header', $i + 1, $ph, $examples[$i] ?? ($examples[$ph] ?? ''));
                    }
                }
            } elseif ($type === 'BUTTONS') {
                $buttons = $comp['buttons'] ?? [];
                if (!is_array($buttons)) {
                    continue;
                }
                $btnIndex = 0;
                foreach ($buttons as $btn) {
                    if (!is_array($btn)) {
                        continue;
                    }
                    $url = (string) ($btn['url'] ?? '');
                    $text = (string) ($btn['text'] ?? '');
                    $hay = $url !== '' ? $url : $text;
                    $placeholders = self::extractPlaceholders($hay);
                    if ($placeholders === []) {
                        continue;
                    }
                    $examples = self::extractComponentExamples($comp, 'button');
                    foreach ($placeholders as $i => $ph) {
                        $btnIndex++;
                        $params[] = self::makeParamRow(
                            'button',
                            $btnIndex,
                            $ph,
                            $examples[$i] ?? ($examples[$ph] ?? ''),
                            $text !== '' ? ('Tombol: ' . $text) : null
                        );
                    }
                }
            }
        }

        return [
            'template_name' => $name,
            'language' => $language,
            'status' => $status,
            'body_preview' => $bodyPreview,
            'params' => $params,
        ];
    }

    /**
     * @return list<string> placeholder names in order (e.g. ["customer","1"] or ["1","2"])
     */
    private static function extractPlaceholders(string $text): array
    {
        if ($text === '' || !preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $m)) {
            return [];
        }
        return array_values($m[1]);
    }

    /**
     * @return array<int|string,string>
     */
    private static function extractComponentExamples(array $comp, string $kind): array
    {
        $example = $comp['example'] ?? null;
        if (!is_array($example)) {
            return [];
        }

        $out = [];

        // Named params (Meta / YCloud)
        $namedKey = $kind === 'header' ? 'header_text_named_params' : 'body_text_named_params';
        if (!empty($example[$namedKey]) && is_array($example[$namedKey])) {
            foreach ($example[$namedKey] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $n = trim((string) ($row['param_name'] ?? ''));
                $v = (string) ($row['example'] ?? '');
                if ($n !== '') {
                    $out[$n] = $v;
                }
            }
        }

        // Positional: body_text: [["a","b"]], header_text: ["a"]
        if ($kind === 'body' && !empty($example['body_text']) && is_array($example['body_text'])) {
            $row = $example['body_text'][0] ?? $example['body_text'];
            if (is_array($row)) {
                foreach (array_values($row) as $i => $v) {
                    $out[$i] = (string) $v;
                }
            }
        }
        if ($kind === 'header' && !empty($example['header_text']) && is_array($example['header_text'])) {
            foreach (array_values($example['header_text']) as $i => $v) {
                $out[$i] = (string) $v;
            }
        }

        return $out;
    }

    private static function makeParamRow(
        string $component,
        int $index,
        string $placeholder,
        string $example = '',
        ?string $labelOverride = null
    ): array {
        $isNamed = $placeholder !== '' && !ctype_digit($placeholder);
        $paramName = $isNamed ? $placeholder : null;
        $label = $labelOverride;
        if ($label === null || $label === '') {
            if ($isNamed) {
                $label = ucwords(str_replace('_', ' ', $placeholder));
            } else {
                $label = strtoupper($component) . ' {{' . $placeholder . '}}';
            }
        }

        return [
            'component' => $component,
            'param_index' => $index,
            'param_name' => $paramName,
            'label' => $label,
            'example_value' => $example !== '' ? $example : null,
            'is_required' => 1,
        ];
    }

    /**
     * Replace {{name}} and {{1}} placeholders in preview text.
     *
     * @param array<string,string> $named  e.g. ['customer' => 'Rangga']
     * @param array<int,string>    $indexed e.g. [1 => 'Rangga']
     */
    public static function renderPreview(?string $preview, array $named = [], array $indexed = []): string
    {
        $text = (string) $preview;
        if ($text === '') {
            return '';
        }

        // Named first: {{customer}}
        foreach ($named as $name => $value) {
            $name = (string) $name;
            if ($name === '') {
                continue;
            }
            $text = str_replace('{{' . $name . '}}', (string) $value, $text);
        }

        // Positional: {{1}}, {{2}}
        foreach ($indexed as $idx => $value) {
            $text = str_replace('{{' . (int) $idx . '}}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * @param array $parameters
     * @return array<int,array>
     */
    private function buildTemplateComponents(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        // Associative name => value (no numeric 0 key) → body named params
        if (!$this->isListArray($parameters)) {
            $grouped = ['body' => []];
            foreach ($parameters as $name => $value) {
                $item = ['type' => 'text', 'text' => (string) $value];
                if (is_string($name) && $name !== '' && !ctype_digit((string) $name)) {
                    $item['parameter_name'] = $name;
                }
                $grouped['body'][] = $item;
            }
            return $this->componentsFromGrouped($grouped);
        }

        // List of strings → body positional
        if (isset($parameters[0]) && !is_array($parameters[0])) {
            $bodyParams = [];
            foreach ($parameters as $param) {
                $bodyParams[] = ['type' => 'text', 'text' => (string) $param];
            }
            return [['type' => 'body', 'parameters' => $bodyParams]];
        }

        // List of structured maps
        $grouped = [];
        foreach ($parameters as $p) {
            if (!is_array($p)) {
                continue;
            }
            $text = (string) ($p['text'] ?? $p['value'] ?? '');
            $component = strtolower((string) ($p['component'] ?? 'body'));
            if (!in_array($component, ['header', 'body', 'button'], true)) {
                $component = 'body';
            }
            $item = ['type' => 'text', 'text' => $text];
            $paramName = trim((string) ($p['param_name'] ?? $p['name'] ?? ''));
            if ($paramName !== '') {
                $item['parameter_name'] = $paramName;
            }
            if (!isset($grouped[$component])) {
                $grouped[$component] = [];
            }
            $grouped[$component][] = $item;
        }

        return $this->componentsFromGrouped($grouped);
    }

    /** @param array<string,array> $grouped */
    private function componentsFromGrouped(array $grouped): array
    {
        $order = ['header', 'body', 'button'];
        $out = [];
        foreach ($order as $type) {
            if (empty($grouped[$type])) {
                continue;
            }
            $out[] = [
                'type' => $type,
                'parameters' => $grouped[$type],
            ];
        }
        return $out;
    }

    private function isListArray(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    private function request(string $endpoint, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw, 'curl_error' => $err];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $data,
            'external_id' => $payload['externalId'] ?? null,
        ];
    }

    /**
     * @param array<string,scalar> $query
     * @return array{success:bool,http_code:int,data:array}
     */
    private function requestGet(string $endpoint, array $query = []): array
    {
        $url = $this->baseUrl . $endpoint;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-API-Key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw, 'curl_error' => $err];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $data,
        ];
    }
}
