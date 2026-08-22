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
        $source = (string) ($ctx['source'] ?? 'chat');
        if (!in_array($source, ['chat', 'blast', 'webhook'], true)) {
            $source = 'chat';
        }

        $this->db->insert('wa_template_fail_logs', [
            'tenant_id' => (int) ($ctx['tenant_id'] ?? 0),
            'team_id' => $this->nullableInt($ctx['team_id'] ?? null),
            'channel_id' => $this->nullableInt($ctx['channel_id'] ?? null),
            'user_id' => $this->nullableInt($ctx['user_id'] ?? null),
            'conversation_id' => $this->nullableInt($ctx['conversation_id'] ?? null),
            'message_id' => $this->nullableInt($ctx['message_id'] ?? null),
            'blast_id' => $this->nullableInt($ctx['blast_id'] ?? null),
            'blast_recipient_id' => $this->nullableInt($ctx['blast_recipient_id'] ?? null),
            'source' => $source,
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

    public function hasLoggedForMessage(int $messageId): bool
    {
        if ($messageId <= 0 || !$this->tableExists()) {
            return false;
        }
        try {
            $row = $this->db->query(
                "SELECT id FROM wa_template_fail_logs WHERE message_id = ? LIMIT 1",
                [$messageId]
            )->row_array();
            return !empty($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @param array<string,mixed> $status @param array<string,mixed> $payload */
    public static function extractWebhookError(array $status, array $payload = []): array
    {
        $errBlock = $status['error'] ?? $payload['error'] ?? null;
        if (is_string($errBlock)) {
            $message = $errBlock;
            $code = '';
        } elseif (is_array($errBlock)) {
            $message = trim((string) (
                $errBlock['message']
                ?? $errBlock['title']
                ?? $errBlock['detail']
                ?? json_encode($errBlock, JSON_UNESCAPED_UNICODE)
            ));
            $code = trim((string) ($errBlock['code'] ?? ''));
        } else {
            $message = trim((string) (
                $status['error_message']
                ?? $status['failure_reason']
                ?? $payload['error_message']
                ?? $payload['failure_reason']
                ?? $payload['reason']
                ?? 'Template delivery failed (webhook)'
            ));
            $code = trim((string) ($status['error_code'] ?? $payload['error_code'] ?? ''));
        }

        if ($code === '' && preg_match('/\(#(\d+)\)/', $message, $m)) {
            $code = $m[1];
        }

        $errors = $status['errors'] ?? $payload['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            $extra = json_encode($errors, JSON_UNESCAPED_UNICODE);
            if ($message === '' || $message === 'Template delivery failed (webhook)') {
                $message = $extra;
            } elseif (!str_contains($message, $extra)) {
                $message .= ' | ' . $extra;
            }
        }

        if ($message === '') {
            $message = 'Template delivery failed (webhook)';
        }

        return [
            'message' => $message,
            'code' => $code,
            'http_code' => null,
        ];
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
