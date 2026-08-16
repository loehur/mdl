<?php

namespace App\Helpers\Jaggu_School;

use App\Config\AI;

/**
 * Chat completions — urutan Env::AI_PRIORITY (gemini|openai).
 */
class AiClient
{
    /**
     * @param array $messages [['role'=>'system|user|assistant','content'=>string], ...]
     * @return array{content:string,provider:string}
     */
    public static function chat(array $messages, int $maxTokens = 600, float $temperature = 0.7): array
    {
        $providers = AI::getProvidersInOrder();
        $timeout = max(20, (int) AI::getTimeout());
        if ($providers === []) {
            throw new \RuntimeException('AI belum dikonfigurasi (OPENAI_API_KEY / GEMINI_API_KEY)');
        }

        $lastError = null;
        foreach ($providers as $i => $p) {
            try {
                $content = self::request(
                    $p['url'],
                    $p['key'],
                    [
                        'model' => $p['model'],
                        'messages' => $messages,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ],
                    $p['label'],
                    $timeout
                );
                return ['content' => $content, 'provider' => $p['id']];
            } catch (\Throwable $e) {
                $lastError = $e;
                if (!isset($providers[$i + 1])) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new \RuntimeException('AI request failed');
    }

    private static function request(string $url, string $apiKey, array $data, string $label, int $timeout): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(30, max(15, $timeout)));
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \RuntimeException("{$label} cURL error: {$curlError}");
        }
        if ($httpCode !== 200) {
            $msg = "{$label} HTTP {$httpCode}";
            $decoded = json_decode((string) $result, true);
            if (!empty($decoded['error']['message'])) {
                $msg .= ' - ' . $decoded['error']['message'];
            }
            throw new \RuntimeException($msg);
        }

        $response = json_decode((string) $result, true);
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException("{$label}: respons kosong");
        }

        return trim($content);
    }
}
