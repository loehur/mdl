<?php

namespace App\Helpers\Jaggu_School;

use App\Config\AI;

/**
 * OpenAI chat completions dengan fallback Groq (sama pola CRM/WA).
 */
class AiClient
{
    /**
     * @param array $messages [['role'=>'system|user|assistant','content'=>string], ...]
     * @return array{content:string,provider:string}
     */
    public static function chat(array $messages, int $maxTokens = 600, float $temperature = 0.7): array
    {
        $openaiKey = AI::getOpenAIApiKey();
        $groqKey = AI::getGroqApiKey();
        $timeout = max(20, (int) AI::getTimeout());

        if ($openaiKey === '' && $groqKey === '') {
            throw new \RuntimeException('AI belum dikonfigurasi (OPENAI_API_KEY / GROQ_API_KEY)');
        }

        $openaiData = [
            'model' => AI::getOpenAIModel(),
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($openaiKey !== '') {
            try {
                $content = self::request(
                    'https://api.openai.com/v1/chat/completions',
                    $openaiKey,
                    $openaiData,
                    'OpenAI',
                    $timeout
                );
                return ['content' => $content, 'provider' => 'openai'];
            } catch (\Throwable $e) {
                if ($groqKey === '') {
                    throw $e;
                }
            }
        }

        $groqData = [
            'model' => AI::getGroqModel(),
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $content = self::request(
            'https://api.groq.com/openai/v1/chat/completions',
            $groqKey,
            $groqData,
            'Groq',
            $timeout
        );

        return ['content' => $content, 'provider' => 'groq'];
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
