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

    public function sendTemplate(string $to, string $templateName, string $language, array $parameters = []): array
    {
        $components = [];
        if (!empty($parameters)) {
            $bodyParams = [];
            foreach ($parameters as $param) {
                $bodyParams[] = ['type' => 'text', 'text' => (string) $param];
            }
            $components[] = ['type' => 'body', 'parameters' => $bodyParams];
        }

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
