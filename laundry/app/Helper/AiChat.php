<?php

/**
 * OpenAI chat (fallback Groq) untuk laundry — baca key dari api/app/Config/Env.php.
 */
class AiChat
{
    /**
     * @param list<array{role:string,content:string}> $messages
     */
    public function chat(array $messages, int $maxTokens = 200, float $temperature = 0.4): string
    {
        $keys = $this->loadKeys();
        $openaiKey = $keys['openai'];
        $groqKey = $keys['groq'];
        $timeout = 15;

        if ($openaiKey === '' && $groqKey === '') {
            throw new \RuntimeException('AI belum dikonfigurasi (OPENAI_API_KEY / GROQ_API_KEY di api Env.php)');
        }

        if ($openaiKey !== '') {
            try {
                return $this->request(
                    'https://api.openai.com/v1/chat/completions',
                    $openaiKey,
                    [
                        'model' => $keys['openai_model'] !== '' ? $keys['openai_model'] : 'gpt-4o-mini',
                        'messages' => $messages,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ],
                    'OpenAI',
                    $timeout
                );
            } catch (\Throwable $e) {
                if ($groqKey === '') {
                    throw $e;
                }
            }
        }

        return $this->request(
            'https://api.groq.com/openai/v1/chat/completions',
            $groqKey,
            [
                'model' => $keys['groq_model'] !== '' ? $keys['groq_model'] : 'llama-3.1-8b-instant',
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
            'Groq',
            $timeout
        );
    }

    /**
     * @return array{openai:string,groq:string,openai_model:string,groq_model:string}
     */
    private function loadKeys(): array
    {
        $out = [
            'openai' => '',
            'groq' => '',
            'openai_model' => 'gpt-4o-mini',
            'groq_model' => 'llama-3.1-8b-instant',
        ];

        $envPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR
            . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Env.php';
        if (!is_file($envPath)) {
            return $out;
        }

        if (!class_exists('Env', false)) {
            // Tangkap BOM/whitespace dari Env.php agar tidak merusak JSON response caller.
            ob_start();
            require_once $envPath;
            ob_end_clean();
        }
        if (!class_exists('Env', false)) {
            return $out;
        }

        if (\defined('Env::OPENAI_API_KEY')) {
            $out['openai'] = (string) \Env::OPENAI_API_KEY;
        }
        if (\defined('Env::OPENAI_MODEL') && (string) \Env::OPENAI_MODEL !== '') {
            $out['openai_model'] = (string) \Env::OPENAI_MODEL;
        }
        if (\defined('Env::GROQ_API_KEY')) {
            $out['groq'] = (string) \Env::GROQ_API_KEY;
        }
        if (\defined('Env::GROQ_MODEL') && (string) \Env::GROQ_MODEL !== '') {
            $out['groq_model'] = (string) \Env::GROQ_MODEL;
        }

        return $out;
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
