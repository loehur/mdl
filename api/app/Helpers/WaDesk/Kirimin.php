<?php

namespace App\Helpers\WaDesk;

/**
 * Kirimin.id (apiapp.kirimin.id) WhatsApp client for WaDesk.
 * Global Bearer API key in Env + per-channel whatsapp_device_id.
 */
class Kirimin
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ?? $this->resolveApiKey();
        $this->baseUrl = rtrim(
            $baseUrl ?? (defined('\Env::KIRIMIN_BASE_URL') ? (string) \Env::KIRIMIN_BASE_URL : 'https://apiapp.kirimin.id'),
            '/'
        );
    }

    public static function isWithinCsw(?string $lastMessageAt, int $hours = 23): bool
    {
        return YCloud::isWithinCsw($lastMessageAt, $hours);
    }

    /** Digits-only (62...) for DB matching. */
    public function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return $phone;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        if (!str_starts_with($digits, '62') && strlen($digits) >= 9) {
            $digits = '62' . ltrim($digits, '0');
        }
        return $digits;
    }

    /** E.164 (+62...) for Kirimin public API. */
    public function formatPhoneE164(string $phone): string
    {
        $digits = $this->formatPhone($phone);
        if ($digits === '' || !preg_match('/^\d+$/', $digits)) {
            return $phone;
        }
        return '+' . $digits;
    }

    /**
     * @return array{success:bool,http_code:int,data:array,error:string,devices:array}
     */
    public function listDevices(): array
    {
        $res = $this->getJson('/api/v1/public/whatsapp/devices');
        if (!$res['success']) {
            return array_merge($res, ['devices' => [], 'error' => $this->extractError($res)]);
        }

        $raw = $res['data'];
        if (isset($raw['devices']) && is_array($raw['devices'])) {
            $raw = $raw['devices'];
        } elseif (!isset($raw[0]) && $raw !== []) {
            $raw = [$raw];
        }

        $devices = [];
        foreach ($raw as $dev) {
            if (!is_array($dev)) {
                continue;
            }
            $devices[] = $this->normalizeDevice($dev);
        }

        return array_merge($res, ['devices' => $devices, 'error' => '']);
    }

    /**
     * @return array{success:bool,error:string,templates:array<int,array>}
     */
    public function listAllTemplates(array $opts = []): array
    {
        $status = strtoupper(trim((string) ($opts['status'] ?? 'APPROVED')));
        $limit = min(100, max(1, (int) ($opts['limit'] ?? 100)));
        $offset = max(0, (int) ($opts['offset'] ?? 0));
        $all = [];
        $maxPages = 50;

        for ($page = 0; $page < $maxPages; $page++) {
            $query = ['limit' => $limit, 'offset' => $offset];
            if ($status !== '') {
                $query['status'] = $status;
            }
            if (!empty($opts['device_id'])) {
                $query['whatsapp_device_id'] = (string) $opts['device_id'];
            }

            $res = $this->getJson('/api/v1/public/templates', $query);
            if (!$res['success']) {
                return [
                    'success' => false,
                    'error' => $this->extractError($res),
                    'templates' => $all,
                ];
            }

            $batch = $this->normalizeTemplateList($res['data']);
            foreach ($batch as $tpl) {
                $all[] = $tpl;
            }

            $pagination = is_array($res['raw']['pagination'] ?? null) ? $res['raw']['pagination'] : [];
            $hasMore = !empty($pagination['has_more']) || !empty($pagination['hasMore']);
            if (!$hasMore || $batch === []) {
                break;
            }
            $offset += $limit;
        }

        return ['success' => true, 'error' => '', 'templates' => $all];
    }

    public function sendFreeText(
        string $deviceId,
        string $to,
        string $message,
        ?string $replyToMessageId = null,
        ?string $userName = null
    ): array {
        $payload = [
            'phone_number' => $this->formatPhoneE164($to),
            'channel' => 'whatsapp',
            'whatsapp_device_id' => $deviceId,
            'message_type' => 'text',
            'message' => $message,
        ];
        if ($userName !== null && trim($userName) !== '') {
            $payload['user_name'] = trim($userName);
        }
        if ($replyToMessageId) {
            $payload['reply_to_message_id'] = $replyToMessageId;
        }

        $externalId = 'wd_' . bin2hex(random_bytes(8));
        $payload['external_id'] = $externalId;

        $res = $this->postJson('/api/v1/public/messages/send', $payload);
        $res['external_id'] = $externalId;
        return $res;
    }

    public function sendTemplate(
        string $deviceId,
        string $to,
        string $templateName,
        string $language,
        array $parameters = [],
        ?string $userName = null
    ): array {
        $components = YCloud::buildSendComponents($parameters);
        $payload = [
            'phone_number' => $this->formatPhoneE164($to),
            'channel' => 'whatsapp',
            'whatsapp_device_id' => $deviceId,
            'message_type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];
        if ($userName !== null && trim($userName) !== '') {
            $payload['user_name'] = trim($userName);
        }

        $externalId = 'wd_' . bin2hex(random_bytes(8));
        $payload['external_id'] = $externalId;

        \Log::write('KIRIMIN_PAYLOAD: ' . json_encode($payload, JSON_UNESCAPED_UNICODE), 'wadesk', 'send_template_payload');

        $res = $this->postJson('/api/v1/public/messages/send', $payload);

        if (!$res['success']) {
            \Log::write('KIRIMIN_REJECT: ' . json_encode($res, JSON_UNESCAPED_UNICODE), 'wadesk', 'send_template_payload');
        }

        $res['external_id'] = $externalId;
        return $res;
    }

    /**
     * @return array{template_name:string,language:string,status:string,body_preview:?string,params:array<int,array>}
     */
    public static function mapTemplateToWaDesk(array $tpl): array
    {
        if (isset($tpl['template_name']) || isset($tpl['content'])) {
            return self::mapPublicApiTemplate($tpl);
        }

        return YCloud::mapTemplateToWaDesk($tpl);
    }

    public static function buildFilledPreview(
        ?string $preview,
        array $paramDefs,
        array $named = [],
        array $indexed = []
    ): string {
        return YCloud::buildFilledPreview($preview, $paramDefs, $named, $indexed);
    }

    private static function mapPublicApiTemplate(array $tpl): array
    {
        $name = trim((string) ($tpl['template_name'] ?? $tpl['name'] ?? ''));
        $language = trim((string) ($tpl['language'] ?? 'id')) ?: 'id';
        $status = strtoupper(trim((string) ($tpl['status'] ?? '')));

        $bodyPreview = trim((string) ($tpl['content'] ?? ''));
        $headerPreview = trim((string) ($tpl['header_content'] ?? ''));

        $params = [];
        $variables = $tpl['variables'] ?? [];
        if (!is_array($variables)) {
            $variables = [];
        }

        if ($bodyPreview !== '' && preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyPreview, $m)) {
            foreach ($m[1] as $num) {
                $idx = (int) $num;
                $varName = (string) ($variables[$idx - 1] ?? $num);
                $params[] = [
                    'component' => 'body',
                    'param_index' => $idx,
                    'param_name' => $varName,
                    'label' => $varName,
                    'is_required' => 1,
                ];
            }
        } elseif ($variables !== []) {
            foreach (array_values($variables) as $i => $varName) {
                $params[] = [
                    'component' => 'body',
                    'param_index' => $i + 1,
                    'param_name' => (string) $varName,
                    'label' => (string) $varName,
                    'is_required' => 1,
                ];
            }
        }

        $previewParts = [];
        if ($headerPreview !== '') {
            $previewParts[] = $headerPreview;
        }
        if ($bodyPreview !== '') {
            $previewParts[] = $bodyPreview;
        }

        return [
            'template_name' => $name,
            'language' => $language,
            'status' => $status,
            'body_preview' => $previewParts !== [] ? implode("\n\n", $previewParts) : null,
            'params' => $params,
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeDevice(array $dev): array
    {
        $deviceId = trim((string) ($dev['device_id'] ?? $dev['id'] ?? $dev['deviceId'] ?? ''));
        $phone = '';
        $label = trim((string) ($dev['name'] ?? $dev['label'] ?? ''));

        $phoneNumbers = $dev['phone_numbers'] ?? [];
        if (is_array($phoneNumbers) && $phoneNumbers !== []) {
            $primary = null;
            foreach ($phoneNumbers as $pn) {
                if (!is_array($pn)) {
                    continue;
                }
                if (!empty($pn['is_primary'])) {
                    $primary = $pn;
                    break;
                }
                if ($primary === null) {
                    $primary = $pn;
                }
            }
            if ($primary !== null) {
                $phone = trim((string) ($primary['display_phone_number'] ?? $primary['phone_number'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($primary['verified_name'] ?? ''));
                }
            }
        }

        if ($phone === '') {
            $phone = trim((string) (
                $dev['phone'] ?? $dev['phone_number'] ?? $dev['number'] ?? $dev['device_number'] ?? ''
            ));
        }
        if ($label === '') {
            $label = $deviceId;
        }

        $typeRaw = strtolower((string) ($dev['type'] ?? $dev['device_type'] ?? 'waba'));
        $channelType = str_contains($typeRaw, 'device') && !str_contains($typeRaw, 'waba') ? 'device' : 'waba';

        return [
            'device_id' => $deviceId,
            'id' => $deviceId,
            'phone_number' => $phone,
            'phone' => $phone,
            'name' => $label,
            'label' => $label,
            'status' => (string) ($dev['status'] ?? $dev['connection_status'] ?? ''),
            'connection_status' => (string) ($dev['connection_status'] ?? $dev['status'] ?? ''),
            'channel_type' => $channelType,
            'waba_id' => $dev['waba_id'] ?? null,
            'phone_numbers' => is_array($phoneNumbers) ? $phoneNumbers : [],
        ];
    }

    private function resolveApiKey(): string
    {
        if (defined('\Env::KIRIMIN_API_KEY')) {
            return (string) \Env::KIRIMIN_API_KEY;
        }
        // Legacy fallback if someone still uses old env names
        if (defined('\Env::KIRIMIN_SECRET') && str_starts_with((string) \Env::KIRIMIN_SECRET, 'kc_')) {
            return (string) \Env::KIRIMIN_SECRET;
        }
        return '';
    }

    /** @return array{success:bool,http_code:int,data:array,raw:array} */
    private function getJson(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return $this->decodeResponse($raw, $httpCode, $err);
    }

    /** @return array{success:bool,http_code:int,data:array,raw:array} */
    private function postJson(string $path, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return $this->decodeResponse($raw, $httpCode, $err);
    }

    /** @return list<string> */
    private function headers(): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        return $headers;
    }

    /**
     * @return array{success:bool,http_code:int,data:array,raw:array}
     */
    private function decodeResponse($raw, int $httpCode, string $err): array
    {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $raw, 'curl_error' => $err];
        }

        $success = $httpCode >= 200 && $httpCode < 300;
        if (array_key_exists('success', $decoded) && $decoded['success'] === false) {
            $success = false;
        }
        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $success = false;
        }

        $data = $decoded['data'] ?? $decoded;
        if (!is_array($data)) {
            $data = ['value' => $data];
        }

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $data,
            'raw' => $decoded,
        ];
    }

    /** @param array{success:bool,http_code:int,data:array,raw?:array} $res */
    private function extractError(array $res): string
    {
        $raw = $res['raw'] ?? [];
        if (is_array($raw) && isset($raw['error']) && is_array($raw['error'])) {
            $msg = trim((string) ($raw['error']['message'] ?? ''));
            $code = trim((string) ($raw['error']['code'] ?? ''));
            if ($msg !== '' && $code !== '') {
                return $code . ': ' . $msg;
            }
            if ($msg !== '') {
                return $msg;
            }
        }

        $data = $res['data'] ?? [];
        if (!is_array($data)) {
            return 'Kirimin HTTP ' . ($res['http_code'] ?? 0);
        }

        return (string) (
            $data['error']['message']
            ?? $data['message']
            ?? (is_string($data['error'] ?? null) ? $data['error'] : null)
            ?? ('Kirimin HTTP ' . ($res['http_code'] ?? 0))
        );
    }

    /** @return array<int,array> */
    private function normalizeTemplateList(array $data): array
    {
        if (isset($data['templates']) && is_array($data['templates'])) {
            return array_values(array_filter($data['templates'], 'is_array'));
        }
        if (isset($data['items']) && is_array($data['items'])) {
            return array_values(array_filter($data['items'], 'is_array'));
        }
        if (isset($data[0]) || $data === []) {
            return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
        }
        return [];
    }
}
