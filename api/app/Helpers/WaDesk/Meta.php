<?php

namespace App\Helpers\WaDesk;

/** Lightweight WhatsApp Cloud API reader used by the WaDesk WABA catalogue. */
class Meta
{
    private string $accessToken;
    private string $graphVersion;

    public function __construct(?string $accessToken = null, ?string $graphVersion = null)
    {
        $this->accessToken = trim((string) ($accessToken ?? self::env('META_WA_ACCESS_TOKEN')));
        $this->graphVersion = trim((string) ($graphVersion ?? self::env('META_WA_GRAPH_VERSION', 'v23.0')));
    }

    public function configured(): bool
    {
        return $this->accessToken !== '';
    }

    /** @return array{success:bool,data:array,error:string,http_code:int} */
    public function listWabas(): array
    {
        $configuredIds = array_values(array_filter(array_map(
            'trim',
            explode(',', self::env('META_WA_WABA_IDS'))
        )));
        if ($configuredIds !== []) {
            $wabas = [];
            foreach ($configuredIds as $wabaId) {
                $res = $this->get('/' . rawurlencode($wabaId), ['fields' => 'id,name']);
                if (!$res['success']) {
                    return $res;
                }
                $row = $res['data'][0] ?? [];
                // Graph object reads return a top-level object, retained by get() as data only when paged.
                if ($row === [] && isset($res['object']) && is_array($res['object'])) {
                    $row = $res['object'];
                }
                $wabas[] = is_array($row) && $row !== [] ? $row : ['id' => $wabaId, 'name' => 'WABA ' . $wabaId];
            }
            return ['success' => true, 'data' => $wabas, 'error' => '', 'http_code' => 200];
        }

        return $this->get('/me', ['fields' => 'whatsapp_business_accounts{id,name}']);
    }

    /** @return array{success:bool,data:array,error:string,http_code:int} */
    public function listPhoneNumbers(string $wabaId): array
    {
        return $this->get('/' . rawurlencode($wabaId) . '/phone_numbers', [
            'fields' => 'id,display_phone_number,verified_name,code_verification_status,quality_rating,status,name_status,new_name_status',
        ]);
    }

    /** @return array{success:bool,data:array,error:string,http_code:int} */
    public function listTemplates(string $wabaId): array
    {
        $all = [];
        $path = '/' . rawurlencode($wabaId) . '/message_templates';
        $query = ['fields' => 'id,name,language,status,category,components', 'limit' => 100];

        for ($page = 0; $page < 50; $page++) {
            $res = $this->get($path, $query);
            if (!$res['success']) {
                return $res;
            }
            foreach ($res['data'] as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $next = trim((string) ($res['paging']['next'] ?? ''));
            if ($next === '') {
                break;
            }
            $path = $next;
            $query = [];
        }

        return ['success' => true, 'data' => $all, 'error' => '', 'http_code' => 200];
    }

    /** @return array{success:bool,data:array,error:string,http_code:int,external_id?:string} */
    public function sendFreeText(string $phoneNumberId, string $to, string $message, ?string $replyTo = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->phone($to),
            'type' => 'text',
            'text' => ['body' => $message],
        ];
        if ($replyTo) {
            $payload['context'] = ['message_id' => $replyTo];
        }
        return $this->post('/' . rawurlencode($phoneNumberId) . '/messages', $payload);
    }

    /** @return array{success:bool,data:array,error:string,http_code:int,external_id?:string} */
    public function sendTemplate(string $phoneNumberId, string $to, string $name, string $language, array $params = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->phone($to),
            'type' => 'template',
            'template' => ['name' => $name, 'language' => ['code' => $language]],
        ];
        $components = $this->templateComponents($params);
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }
        return $this->post('/' . rawurlencode($phoneNumberId) . '/messages', $payload);
    }

    public function addPhoneNumber(string $wabaId, string $countryCode, string $phoneNumber, string $verifiedName): array
    {
        $phoneNumber = $this->indonesianLocalNumber($phoneNumber);
        return $this->post('/' . rawurlencode($wabaId) . '/phone_numbers', [
            'cc' => preg_replace('/\D+/', '', $countryCode) ?: '62',
            'phone_number' => $phoneNumber,
            'verified_name' => trim($verifiedName),
        ]);
    }

    public function requestVerificationCode(string $phoneNumberId, string $method = 'SMS'): array
    {
        return $this->post('/' . rawurlencode($phoneNumberId) . '/request_code', [
            'code_method' => strtoupper($method) === 'VOICE' ? 'VOICE' : 'SMS',
            'language' => 'id',
        ]);
    }

    public function verifyCode(string $phoneNumberId, string $code): array
    {
        return $this->post('/' . rawurlencode($phoneNumberId) . '/verify_code', ['code' => trim($code)]);
    }

    public function registerPhoneNumber(string $phoneNumberId): array
    {
        return $this->post('/' . rawurlencode($phoneNumberId) . '/register', [
            'messaging_product' => 'whatsapp',
            'pin' => '123654',
        ]);
    }

    public function deletePhoneNumber(string $phoneNumberId): array
    {
        return $this->delete('/' . rawurlencode($phoneNumberId));
    }

    /** Create a Meta template using the positional body parameters required by Cloud API. */
    public function createTemplate(string $wabaId, string $name, string $language, string $category, string $body, array $paramLabels): array
    {
        $component = ['type' => 'BODY', 'text' => $body];
        if ($paramLabels !== []) {
            // Meta requires positional examples for {{1}}, {{2}}, etc. Labels remain
            // local to WaDesk and are never exposed to the Meta template schema.
            $component['example'] = ['body_text' => [array_fill(0, count($paramLabels), 'contoh')]];
        }
        return $this->post('/' . rawurlencode($wabaId) . '/message_templates', [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => [$component],
        ]);
    }

    public function deleteTemplate(string $wabaId, string $name, string $templateId = ''): array
    {
        $query = ['name' => $name];
        if ($templateId !== '') $query['hsm_id'] = $templateId;
        return $this->delete('/' . rawurlencode($wabaId) . '/message_templates?' . http_build_query($query));
    }

    /** @return array{success:bool,data:array,error:string,http_code:int,paging?:array} */
    private function get(string $path, array $query = []): array
    {
        if (!$this->configured()) {
            return ['success' => false, 'data' => [], 'error' => 'META_WA_ACCESS_TOKEN belum diatur.', 'http_code' => 0];
        }

        $url = str_starts_with($path, 'https://')
            ? $path
            : 'https://graph.facebook.com/' . rawurlencode($this->graphVersion) . $path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'data' => [], 'error' => $curlError ?: 'Respons Meta tidak valid.', 'http_code' => $httpCode];
        }
        if ($httpCode < 200 || $httpCode >= 300 || isset($decoded['error'])) {
            return [
                'success' => false,
                'data' => [],
                'error' => (string) ($decoded['error']['message'] ?? 'Meta request gagal.'),
                'http_code' => $httpCode,
            ];
        }

        $result = [
            'success' => true,
            'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            'error' => '',
            'http_code' => $httpCode,
            'paging' => is_array($decoded['paging'] ?? null) ? $decoded['paging'] : [],
        ];
        if (!isset($decoded['data'])) {
            $result['object'] = $decoded;
            if (isset($decoded['whatsapp_business_accounts']['data']) && is_array($decoded['whatsapp_business_accounts']['data'])) {
                $result['data'] = $decoded['whatsapp_business_accounts']['data'];
            }
        }
        return $result;
    }

    /** @return array{success:bool,data:array,error:string,http_code:int} */
    private function post(string $path, array $payload): array
    {
        if (!$this->configured()) {
            return ['success' => false, 'data' => [], 'error' => 'META_WA_ACCESS_TOKEN belum diatur.', 'http_code' => 0];
        }
        $url = 'https://graph.facebook.com/' . rawurlencode($this->graphVersion) . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken, 'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'data' => [], 'error' => $curlError ?: 'Respons Meta tidak valid.', 'http_code' => $httpCode];
        }
        if ($httpCode < 200 || $httpCode >= 300 || isset($decoded['error'])) {
            $metaError = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $detail = trim((string) ($metaError['error_user_msg'] ?? $metaError['error_data']['details'] ?? ''));
            $code = isset($metaError['code']) ? ' (#' . $metaError['code'] . ')' : '';
            return ['success' => false, 'data' => $decoded, 'error' => (string) ($metaError['message'] ?? 'Meta request gagal.') . $code . ($detail !== '' ? ': ' . $detail : ''), 'http_code' => $httpCode];
        }
        $message = is_array($decoded['messages'][0] ?? null) ? $decoded['messages'][0] : [];
        $data = $decoded;
        if ($message !== []) {
            $data['id'] = $message['id'] ?? null;
            $data['message_id'] = $message['id'] ?? null;
            $data['wamid'] = $message['id'] ?? null;
        }
        return ['success' => true, 'data' => $data, 'error' => '', 'http_code' => $httpCode];
    }

    private function delete(string $path): array
    {
        if (!$this->configured()) return ['success' => false, 'data' => [], 'error' => 'META_WA_ACCESS_TOKEN belum diatur.', 'http_code' => 0];
        $ch = curl_init('https://graph.facebook.com/' . rawurlencode($this->graphVersion) . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken], CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30]);
        $raw = curl_exec($ch); $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || $httpCode < 200 || $httpCode >= 300 || isset($decoded['error'])) {
            $error = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
            return ['success' => false, 'data' => is_array($decoded) ? $decoded : [], 'error' => $error ?: ($curlError ?: 'Meta request gagal.'), 'http_code' => $httpCode];
        }
        return ['success' => true, 'data' => $decoded, 'error' => '', 'http_code' => $httpCode];
    }

    private function templateComponents(array $params): array
    {
        $groups = [];
        foreach ($params as $index => $value) {
            $row = is_array($value) ? $value : ['component' => 'body', 'text' => (string) $value];
            $component = strtolower((string) ($row['component'] ?? 'body'));
            $parameter = ['type' => 'text', 'text' => (string) ($row['text'] ?? '')];
            // Meta Cloud API templates use positional parameters. WaDesk's friendly
            // param_name is only for the form/preview and must not be sent to Meta.
            if ($component === 'button') {
                $key = 'button:' . (string) ($row['button_sub_type'] ?? 'url') . ':' . (int) ($row['button_index'] ?? 0);
                $groups[$key] ??= ['type' => 'button', 'sub_type' => $row['button_sub_type'] ?? 'url', 'index' => (int) ($row['button_index'] ?? 0), 'parameters' => []];
            } else {
                $key = in_array($component, ['header', 'body'], true) ? $component : 'body';
                $groups[$key] ??= ['type' => $key, 'parameters' => []];
            }
            $groups[$key]['parameters'][] = $parameter;
        }
        return array_values($groups);
    }

    private function phone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
        if (str_starts_with($phone, '8')) $phone = '62' . $phone;
        return $phone;
    }

    private function indonesianLocalNumber(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '628')) return substr($phone, 2);
        if (str_starts_with($phone, '08')) return substr($phone, 1);
        return $phone;
    }

    private static function env(string $name, string $default = ''): string
    {
        return defined('Env::' . $name) ? (string) constant('Env::' . $name) : $default;
    }
}
