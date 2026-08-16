<?php

/**
 * Chat AI untuk laundry — urutan Env::AI_PRIORITY (groq|openai).
 */
class AiChat
{
    /**
     * @param list<array{role:string,content:string}> $messages
     */
    public function chat(array $messages, int $maxTokens = 200, float $temperature = 0.4, int $timeout = 12): string
    {
        $this->bootAiConfig();
        $providers = \App\Config\AI::getProvidersInOrder();
        $timeout = max(5, min(60, $timeout));
        if ($providers === []) {
            throw new \RuntimeException('AI belum dikonfigurasi (OPENAI_API_KEY / GROQ_API_KEY di api Env.php)');
        }

        $lastError = null;
        foreach ($providers as $i => $p) {
            try {
                return $this->request(
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
            } catch (\Throwable $e) {
                $lastError = $e;
                if (!isset($providers[$i + 1])) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new \RuntimeException('AI request failed');
    }

    private function bootAiConfig(): void
    {
        $envPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR
            . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Env.php';
        if (is_file($envPath) && !class_exists('Env', false)) {
            ob_start();
            require_once $envPath;
            ob_end_clean();
        }
        if (!class_exists('\\App\\Config\\AI', false)) {
            require_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR
                . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'AI.php';
        }
    }

    private function request(string $url, string $apiKey, array $data, string $label, int $timeout): string
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
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
