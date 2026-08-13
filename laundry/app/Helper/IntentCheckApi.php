<?php

/**
 * Intent Lab → https://api.nalju.com/Laundry/IntentCheck
 */
class IntentCheckApi
{
    private $apiUrl = 'https://api.nalju.com/Laundry/IntentCheck';

    /**
     * @return array{ok:bool,intent?:?string,source?:?string,case?:mixed,notify?:bool,trace?:list<string>,message?:string}
     */
    public function check(string $text): array
    {
        $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
        return $this->request('check', $payload, true);
    }

    /**
     * Usulan AI: pattern + prompt_append agar text masuk intent.
     * @return array<string,mixed>
     */
    public function proposeTeach(string $text, string $intent): array
    {
        $payload = json_encode([
            'text' => $text,
            'intent' => $intent,
        ], JSON_UNESCAPED_UNICODE);
        return $this->request('proposeTeach', $payload, true);
    }

    /**
     * Usulan AI: keluarkan text dari intent (pattern match + pengecualian prompt).
     * @return array<string,mixed>
     */
    public function proposeUntouch(string $text, string $intent): array
    {
        $payload = json_encode([
            'text' => $text,
            'intent' => $intent,
        ], JSON_UNESCAPED_UNICODE);
        return $this->request('proposeUntouch', $payload, true);
    }

    /**
     * @return array<string,mixed>
     */
    private function request(string $path, ?string $jsonBody, bool $post): array
    {
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($path, '/');
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $secret = $this->cronSecret();
        if ($secret !== '') {
            $headers[] = 'X-Cron-Secret: ' . $secret;
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
        if ($post) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $jsonBody ?? '{}';
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            return ['ok' => false, 'message' => 'Koneksi API gagal: ' . $curlErr];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Respon API tidak valid (HTTP ' . $http . ')',
            ];
        }

        if ($http >= 400 && empty($decoded['ok'])) {
            $decoded['ok'] = false;
            if (empty($decoded['message'])) {
                $decoded['message'] = 'HTTP ' . $http;
            }
        }

        return $decoded;
    }

    private function cronSecret(): string
    {
        if (class_exists('URL') && defined('URL::API_CRON_SECRET')) {
            $s = trim((string) URL::API_CRON_SECRET);
            if ($s !== '') {
                return $s;
            }
        }
        foreach (['API_CRON_SECRET', 'CRON_SECRET'] as $envKey) {
            $s = trim((string) (getenv($envKey) ?: ''));
            if ($s !== '') {
                return $s;
            }
        }
        return '';
    }
}
