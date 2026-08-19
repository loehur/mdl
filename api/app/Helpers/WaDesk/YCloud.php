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

        \Log::write('YCLOUD_PAYLOAD: ' . json_encode($payload, JSON_UNESCAPED_UNICODE), 'wadesk', 'send_template_payload');

        $result = $this->request('/whatsapp/messages', $payload);

        if (!$result['success']) {
            \Log::write('YCLOUD_REJECT: ' . json_encode($result, JSON_UNESCAPED_UNICODE), 'wadesk', 'send_template_payload');
        }

        return $result;
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
        $headerPreview = null;
        $params = [];
        $buttonParamOrdinal = 0; // shared across all BUTTONS components

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
                    if ($text !== '') {
                        $headerPreview = $text;
                    }
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
                foreach ($buttons as $metaIndex => $btn) {
                    if (!is_array($btn)) {
                        continue;
                    }
                    $btnType = strtoupper((string) ($btn['type'] ?? ''));
                    $url = (string) ($btn['url'] ?? '');
                    $text = (string) ($btn['text'] ?? '');
                    // COPY_CODE may expose example/code instead of {{n}} in url/text
                    $hay = $url !== '' ? $url : $text;
                    $placeholders = self::extractPlaceholders($hay);
                    if ($placeholders === [] && $btnType === 'COPY_CODE') {
                        $placeholders = ['1'];
                    }
                    if ($placeholders === []) {
                        continue;
                    }
                    $subType = self::mapButtonSubType($btnType);
                    $examples = self::extractComponentExamples($comp, 'button');
                    foreach ($placeholders as $i => $ph) {
                        $buttonParamOrdinal++;
                        $row = self::makeParamRow(
                            'button',
                            $buttonParamOrdinal,
                            $ph,
                            $examples[$i] ?? ($examples[$ph] ?? ''),
                            $text !== '' ? ('Tombol: ' . $text) : null
                        );
                        $row['button_sub_type'] = $subType;
                        $row['button_index'] = (int) $metaIndex;
                        $params[] = $row;
                    }
                }
            }
        }

        // Include TEXT header in preview so named vars like {{customer}} are visible/live-editable
        $previewParts = [];
        if ($headerPreview !== null && $headerPreview !== '') {
            $previewParts[] = $headerPreview;
        }
        if ($bodyPreview !== null && $bodyPreview !== '') {
            $previewParts[] = $bodyPreview;
        }
        $combinedPreview = $previewParts !== [] ? implode("\n\n", $previewParts) : null;

        return [
            'template_name' => $name,
            'language' => $language,
            'status' => $status,
            'body_preview' => $combinedPreview,
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
            'button_sub_type' => null,
            'button_index' => null,
        ];
    }

    /** Map YCloud/Meta button type → send payload sub_type. */
    private static function mapButtonSubType(string $btnType): string
    {
        return match ($btnType) {
            'URL' => 'url',
            'QUICK_REPLY' => 'quick_reply',
            'COPY_CODE' => 'copy_code',
            'CATALOG' => 'catalog',
            'MPM' => 'mpm',
            'FLOW' => 'flow',
            'ORDER_DETAILS' => 'order_details',
            default => 'url',
        };
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

        // Named first: {{customer}} (allow optional spaces)
        foreach ($named as $name => $value) {
            $name = (string) $name;
            if ($name === '') {
                continue;
            }
            $text = preg_replace(
                '/\{\{\s*' . preg_quote($name, '/') . '\s*\}\}/',
                (string) $value,
                $text
            ) ?? $text;
        }

        // Positional: {{1}}, {{2}}
        foreach ($indexed as $idx => $value) {
            $text = preg_replace(
                '/\{\{\s*' . (int) $idx . '\s*\}\}/',
                (string) $value,
                $text
            ) ?? $text;
        }

        return $text;
    }

    private static function placeholderInPreview(string $text, array $def): bool
    {
        $name = trim((string) ($def['param_name'] ?? ''));
        if ($name !== '' && preg_match('/\{\{\s*' . preg_quote($name, '/') . '\s*\}\}/', $text)) {
            return true;
        }
        $idx = (int) ($def['param_index'] ?? 0);
        if ($idx > 0 && preg_match('/\{\{\s*' . $idx . '\s*\}\}/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Build chat/blast preview text with values filled in.
     * Prepends missing header/body placeholders (common when body_preview
     * was synced without the TEXT header that holds {{customer}}).
     *
     * @param array<int,array>     $paramDefs
     * @param array<string,string> $named
     * @param array<int,string>    $indexed
     */
    public static function buildFilledPreview(
        ?string $preview,
        array $paramDefs,
        array $named = [],
        array $indexed = []
    ): string {
        $text = (string) $preview;
        $missing = [];

        foreach ($paramDefs as $def) {
            if (!is_array($def)) {
                continue;
            }
            $component = strtolower((string) ($def['component'] ?? 'body'));
            if ($component !== 'body' && $component !== 'header') {
                continue;
            }
            if (self::placeholderInPreview($text, $def)) {
                continue;
            }

            $token = trim((string) ($def['param_name'] ?? ''));
            if ($token === '') {
                $idx = (int) ($def['param_index'] ?? 0);
                if ($idx <= 0) {
                    continue;
                }
                $token = (string) $idx;
            }
            $missing[] = '{{' . $token . '}}';
        }

        if ($missing !== []) {
            $synthetic = implode(' ', array_unique($missing));
            $text = $text !== '' ? ($synthetic . "\n\n" . $text) : $synthetic;
        }

        $filled = self::renderPreview($text, $named, $indexed);
        return $filled !== '' ? $filled : $text;
    }

    /**
     * @param array $parameters
     * @return array<int,array>
     */
    /** @param array $parameters */
    public static function buildSendComponents(array $parameters): array
    {
        return (new self('unused', 'unused'))->buildTemplateComponents($parameters);
    }

    private function buildTemplateComponents(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        // Associative name => value → body named params
        if (!$this->isListArray($parameters)) {
            $grouped = ['body' => []];
            foreach ($parameters as $name => $value) {
                $item = ['type' => 'text', 'text' => (string) $value];
                if (is_string($name) && $name !== '' && !ctype_digit($name)) {
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
        $buttonComponents = [];
        foreach ($parameters as $p) {
            if (!is_array($p)) {
                continue;
            }
            $text = (string) ($p['text'] ?? $p['value'] ?? '');
            $component = strtolower((string) ($p['component'] ?? 'body'));
            if (!in_array($component, ['header', 'body', 'button'], true)) {
                $component = 'body';
            }

            // Each button variable is its own component (Meta/YCloud require sub_type + index)
            if ($component === 'button') {
                $subType = strtolower(trim((string) ($p['button_sub_type'] ?? 'url'))) ?: 'url';
                $btnIndex = $p['button_index'] ?? null;
                if ($btnIndex === null || $btnIndex === '') {
                    $btnIndex = max(0, ((int) ($p['param_index'] ?? 1)) - 1);
                }
                $param = ['type' => 'text', 'text' => $text];
                if ($subType === 'copy_code') {
                    $param = ['type' => 'coupon_code', 'coupon_code' => $text];
                } elseif ($subType === 'quick_reply') {
                    $param = ['type' => 'payload', 'payload' => $text];
                }
                $buttonComponents[] = [
                    'type' => 'button',
                    'sub_type' => $subType,
                    'index' => (int) $btnIndex,
                    'parameters' => [$param],
                ];
                continue;
            }

            $item = ['type' => 'text', 'text' => $text];
            $paramName = trim((string) ($p['param_name'] ?? $p['name'] ?? ''));
            if ($paramName !== '' && !ctype_digit($paramName)) {
                $item['parameter_name'] = $paramName;
            }
            if (!isset($grouped[$component])) {
                $grouped[$component] = [];
            }
            $grouped[$component][] = $item;
        }

        return array_merge($this->componentsFromGrouped($grouped), $buttonComponents);
    }

    /** @param array<string,array> $grouped */
    private function componentsFromGrouped(array $grouped): array
    {
        $order = ['header', 'body'];
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
