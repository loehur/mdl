<?php

namespace App\Helpers;

/**
 * Lightweight yCloud WhatsApp client for WaDesk (per-key credentials).
 * Does not touch mdl_main CRM tables.
 */
class WaDeskYCloud
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
}
