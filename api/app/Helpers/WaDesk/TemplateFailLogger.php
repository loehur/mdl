<?php

namespace App\Helpers\WaDesk;

/**
 * Persist failed template send attempts (provider rejection after API call).
 */
class TemplateFailLogger
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function tableExists(): bool
    {
        try {
            $row = $this->db->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wa_template_fail_logs'"
            )->row_array();
            return (int) ($row['cnt'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $ctx
     */
    public function log(array $ctx): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $request = $ctx['request'] ?? null;
        $response = $ctx['response'] ?? null;

        $this->db->insert('wa_template_fail_logs', [
            'tenant_id' => (int) ($ctx['tenant_id'] ?? 0),
            'team_id' => $this->nullableInt($ctx['team_id'] ?? null),
            'channel_id' => $this->nullableInt($ctx['channel_id'] ?? null),
            'user_id' => $this->nullableInt($ctx['user_id'] ?? null),
            'conversation_id' => $this->nullableInt($ctx['conversation_id'] ?? null),
            'blast_id' => $this->nullableInt($ctx['blast_id'] ?? null),
            'blast_recipient_id' => $this->nullableInt($ctx['blast_recipient_id'] ?? null),
            'source' => (($ctx['source'] ?? 'chat') === 'blast') ? 'blast' : 'chat',
            'phone' => mb_substr(trim((string) ($ctx['phone'] ?? '')), 0, 32),
            'template_id' => $this->nullableInt($ctx['template_id'] ?? null),
            'template_name' => mb_substr(trim((string) ($ctx['template_name'] ?? '')), 0, 150),
            'language' => mb_substr(trim((string) ($ctx['language'] ?? 'id')) ?: 'id', 0, 16),
            'device_id' => ($d = trim((string) ($ctx['device_id'] ?? ''))) !== '' ? mb_substr($d, 0, 64) : null,
            'preview' => isset($ctx['preview']) ? mb_substr((string) $ctx['preview'], 0, 2000) : null,
            'error_message' => mb_substr(trim((string) ($ctx['error_message'] ?? 'Unknown error')), 0, 5000),
            'error_code' => ($c = trim((string) ($ctx['error_code'] ?? ''))) !== '' ? mb_substr($c, 0, 64) : null,
            'http_code' => $this->nullableInt($ctx['http_code'] ?? null),
            'request_json' => $request !== null
                ? json_encode($request, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                : null,
            'response_json' => $response !== null
                ? json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                : null,
        ]);
    }

    /** @param array{success:bool,http_code?:int,data?:array,raw?:array} $result */
    public static function extractProviderError(array $result): array
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $raw = is_array($result['raw'] ?? null) ? $result['raw'] : [];
        $errBlock = is_array($data['error'] ?? null) ? $data['error'] : [];
        if ($errBlock === [] && is_array($raw['error'] ?? null)) {
            $errBlock = $raw['error'];
        }

        $message = trim((string) (
            $errBlock['message']
            ?? $data['message']
            ?? ($data['error'] ?? null)
            ?? 'Template send failed'
        ));
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        $code = trim((string) ($errBlock['code'] ?? ''));
        if ($code === '' && preg_match('/\(#(\d+)\)/', $message, $m)) {
            $code = $m[1];
        }

        return [
            'message' => $message,
            'code' => $code,
            'http_code' => (int) ($result['http_code'] ?? 0) ?: null,
        ];
    }

    private function nullableInt($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;
        return $n > 0 ? $n : null;
    }
}
