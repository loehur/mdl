<?php

namespace App\Helpers\WaDesk;

/**
 * Minimal OpenAI Chat Completions client (JSON responses).
 */
class OpenAi
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, ?string $baseUrl = null)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim($baseUrl ?? 'https://api.openai.com/v1', '/');
    }

    /**
     * @return array{success:bool,http_code:int,data:array,error:string}
     */
    public function chatJson(
        string $systemPrompt,
        string $userPrompt,
        string $model = 'gpt-4o-mini',
        float $temperature = 0
    ): array {
        if ($this->apiKey === '') {
            return ['success' => false, 'http_code' => 0, 'data' => [], 'error' => 'OpenAI API key kosong'];
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => $temperature,
        ];

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 45,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'http_code' => $httpCode, 'data' => [], 'error' => $err ?: 'OpenAI request failed'];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'http_code' => $httpCode, 'data' => [], 'error' => 'OpenAI response invalid'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            return ['success' => false, 'http_code' => $httpCode, 'data' => $decoded, 'error' => (string) $msg];
        }

        $content = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));
        $json = json_decode($content, true);
        if (!is_array($json)) {
            return ['success' => false, 'http_code' => $httpCode, 'data' => $decoded, 'error' => 'OpenAI JSON parse gagal'];
        }

        return ['success' => true, 'http_code' => $httpCode, 'data' => $json, 'error' => ''];
    }
}
