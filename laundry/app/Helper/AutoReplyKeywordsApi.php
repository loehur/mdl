<?php

/**
 * Laundry → https://api.nalju.com/Laundry/AutoReplyKeywords
 * Seed file hanya di server API (open_basedir laundry tidak boleh baca folder api).
 */
class AutoReplyKeywordsApi
{
    private $apiUrl = 'https://api.nalju.com/Laundry/AutoReplyKeywords';

    /**
     * @return array{ok:bool,message?:string,intents?:int,patterns?:int,error?:string}
     */
    public function seed(bool $replace = false): array
    {
        $payload = json_encode(['replace' => $replace], JSON_UNESCAPED_UNICODE);
        return $this->request('seed', $payload, true);
    }

    /**
     * @return array{ok:bool,intents?:int,patterns?:int,file_exists?:bool,message?:string}
     */
    public function status(): array
    {
        return $this->request('status', null, false);
    }

    /**
     * @return array<string,mixed>
     */
    private function request(string $path, ?string $jsonBody, bool $post): array
    {
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($path, '/');
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
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
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
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
