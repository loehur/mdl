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
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->apiUrl, '/') . '/check',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
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
}
