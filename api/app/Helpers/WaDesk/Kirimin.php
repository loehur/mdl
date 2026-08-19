<?php

namespace App\Helpers\WaDesk;

/**
 * Kirimin.id WhatsApp client for WaDesk (global Env credentials + per-channel device_id).
 */
class Kirimin
{
    private string $userCode;
    private string $secret;
    private string $baseUrl;

    public function __construct(?string $userCode = null, ?string $secret = null, ?string $baseUrl = null)
    {
        $this->userCode = $userCode ?? (defined('\Env::KIRIMIN_USER_CODE') ? (string) \Env::KIRIMIN_USER_CODE : '');
        $this->secret = $secret ?? (defined('\Env::KIRIMIN_SECRET') ? (string) \Env::KIRIMIN_SECRET : '');
        $this->baseUrl = rtrim($baseUrl ?? (defined('\Env::KIRIMIN_BASE_URL') ? (string) \Env::KIRIMIN_BASE_URL : 'https://api.kirimi.id'), '/');
    }

    public static function isWithinCsw(?string $lastMessageAt, int $hours = 23): bool
    {
        return YCloud::isWithinCsw($lastMessageAt, $hours);
    }

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

    /**
     * @return array{success:bool,http_code:int,data:array,error:string,devices:array}
     */
    public function listDevices(): array
    {
        $res = $this->postJson('/v1/list-devices', $this->authPayload());
        if (!$res['success']) {
            return array_merge($res, ['devices' => [], 'error' => $this->extractError($res)]);
        }

        $data = $res['data'];
        $devices = [];
        if (isset($data['devices']) && is_array($data['devices'])) {
            $devices = $data['devices'];
        } elseif (isset($data[0]) || $data === []) {
            $devices = is_array($data) ? $data : [];
        } elseif ($data !== []) {
            $devices = [$data];
        }

        return array_merge($res, ['devices' => $devices, 'error' => '']);
    }

    /**
     * @return array{success:bool,error:string,templates:array<int,array>}
     */
    public function listAllTemplates(array $opts = []): array
    {
        $status = strtoupper(trim((string) ($opts['status'] ?? 'APPROVED')));
        $payload = array_merge($this->authPayload(), [
            'status' => $status !== '' ? $status : 'APPROVED',
        ]);
        if (!empty($opts['device_id'])) {
            $payload['device_id'] = (string) $opts['device_id'];
        }

        $paths = ['/v1/waba/list-templates', '/v1/waba/templates', '/v1/list-templates'];
        $lastErr = 'unknown';
        foreach ($paths as $path) {
            $res = $this->postJson($path, $payload);
            if (!$res['success']) {
                $lastErr = $this->extractError($res);
                continue;
            }
            $templates = $this->normalizeTemplateList($res['data']);
            if ($templates !== [] || $path === $paths[count($paths) - 1]) {
                return ['success' => true, 'error' => '', 'templates' => $templates];
            }
        }

        return ['success' => false, 'error' => $lastErr, 'templates' => []];
    }

    public function sendFreeText(string $deviceId, string $to, string $message, ?string $replyToMessageId = null): array
    {
        $payload = array_merge($this->authPayload(), [
            'device_id' => $deviceId,
            'phone' => $this->formatPhone($to),
            'receiver' => $this->formatPhone($to),
            'message' => $message,
        ]);
        if ($replyToMessageId) {
            $payload['reply_to'] = $replyToMessageId;
            $payload['context'] = ['message_id' => $replyToMessageId];
        }

        $externalId = 'wd_' . bin2hex(random_bytes(8));
        $payload['external_id'] = $externalId;

        $res = $this->postJson('/v1/waba/send-message', $payload);
        if (!$res['success']) {
            $res = $this->postJson('/v1/send-message', $payload);
        }

        $res['external_id'] = $externalId;
        return $res;
    }

    public function sendTemplate(
        string $deviceId,
        string $to,
        string $templateName,
        string $language,
        array $parameters = []
    ): array {
        $components = YCloud::buildSendComponents($parameters);
        $payload = array_merge($this->authPayload(), [
            'device_id' => $deviceId,
            'phone' => $this->formatPhone($to),
            'receiver' => $this->formatPhone($to),
            'template_name' => $templateName,
            'name' => $templateName,
            'language' => $language,
            'language_code' => $language,
            'components' => $components,
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);

        $externalId = 'wd_' . bin2hex(random_bytes(8));
        $payload['external_id'] = $externalId;

        \Log::write('KIRIMIN_PAYLOAD: ' . json_encode($payload, JSON_UNESCAPED_UNICODE), 'wadesk', 'send_template_payload');

        $res = $this->postJson('/v1/waba/send-template', $payload);
        if (!$res['success']) {
            $res = $this->postJson('/v1/waba/send-message-template', $payload);
        }

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

    /** @return array<string,string> */
    private function authPayload(): array
    {
        return [
            'user_code' => $this->userCode,
            'secret' => $this->secret,
        ];
    }

    /** @return array{success:bool,http_code:int,data:array} */
    private function postJson(string $path, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $raw, 'curl_error' => $err];
        }

        $success = $httpCode >= 200 && $httpCode < 300;
        if ($success && array_key_exists('success', $decoded) && $decoded['success'] === false) {
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
        ];
    }

    /** @param array{success:bool,data:array} $res */
    private function extractError(array $res): string
    {
        $data = $res['data'] ?? [];
        if (!is_array($data)) {
            return 'Kirimin HTTP ' . ($res['http_code'] ?? 0);
        }
        return (string) (
            $data['error']['message']
            ?? $data['message']
            ?? $data['error']
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
