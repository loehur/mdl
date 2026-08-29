<?php

namespace App\Controllers\Meta;

use App\Core\Controller;

/**
 * WhatsApp Cloud API (Meta) outbound endpoint.
 *
 * POST /Meta/WhatsApp/send-template
 */
class WhatsApp extends Controller
{
    public function index()
    {
        $this->success([
            'name' => 'Meta WhatsApp Template API',
            'send_template_url' => '/Meta/WhatsApp/send-template',
        ], 'Meta WhatsApp API ready');
    }

    public function send_template()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST.', 405);
        }

        $this->authorize();
        $body = $this->getBody();
        $this->validate($body, ['to', 'template_name', 'phone_number_id']);

        $accessToken = $this->config('META_WA_ACCESS_TOKEN');
        if ($accessToken === '') {
            $this->error('Meta WhatsApp belum dikonfigurasi. Isi META_WA_ACCESS_TOKEN.', 503);
        }

        // Phone Number ID dipilih per request agar satu endpoint dapat menangani
        // banyak nomor yang diizinkan untuk Access Token Meta ini.
        $phoneNumberId = $this->strFromBody($body, 'phone_number_id');
        if (!preg_match('/^\d{5,32}$/', $phoneNumberId)) {
            $this->error('phone_number_id tidak valid.', 400);
        }

        $to = $this->normalizePhone($this->strFromBody($body, 'to'));
        if ($to === '') {
            $this->error('Nomor tujuan tidak valid.', 400);
        }

        $templateName = $this->strFromBody($body, 'template_name');
        if (!preg_match('/^[a-z0-9_]+$/', $templateName)) {
            $this->error('template_name hanya boleh berisi huruf kecil, angka, dan underscore.', 400);
        }

        $language = $this->strFromBody($body, 'language', 'id');
        if (!preg_match('/^[A-Za-z_-]{2,20}$/', $language)) {
            $this->error('language tidak valid.', 400);
        }

        $components = $this->components($body);
        if ($components === null) {
            $this->error('components harus berupa array sesuai format Meta.', 400);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $version = $this->config('META_WA_GRAPH_VERSION', 'v23.0');
        $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/'
            . rawurlencode($phoneNumberId) . '/messages';
        $result = $this->postJson($url, $accessToken, $payload);

        \Log::write(
            'Template send: to=' . $to
                . ', template=' . $templateName
                . ', http=' . $result['http_code']
                . ', response=' . $result['body'],
            'wa_meta',
            'send_template'
        );

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->error('Meta WhatsApp menolak pengiriman template.', 502, [
                'meta_response' => $result['json'] ?? ['raw' => $result['body']],
            ]);
        }

        $this->success([
            'to' => $to,
            'template_name' => $templateName,
            'meta_response' => $result['json'] ?? ['raw' => $result['body']],
        ], 'Template WhatsApp Meta berhasil dikirim.');
    }

    private function authorize(): void
    {
        $expected = $this->config('META_WA_SEND_API_KEY');
        $provided = (string) ($_SERVER['HTTP_X_META_WA_SEND_KEY'] ?? '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            $this->error('Unauthorized.', 401);
        }
    }

    /**
     * Accept the exact Meta `components` format, or a simple body_parameters
     * list which is converted to a BODY text component.
     */
    private function components(array $body): ?array
    {
        if (array_key_exists('components', $body)) {
            return is_array($body['components']) ? array_values($body['components']) : null;
        }

        if (!array_key_exists('body_parameters', $body)) {
            return [];
        }
        if (!is_array($body['body_parameters'])) {
            return null;
        }

        $parameters = [];
        foreach ($body['body_parameters'] as $value) {
            if (!is_scalar($value) && $value !== null) {
                return null;
            }
            $parameters[] = ['type' => 'text', 'text' => (string) $value];
        }

        return $parameters === [] ? [] : [[
            'type' => 'body',
            'parameters' => $parameters,
        ]];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        return strlen($phone) >= 8 && strlen($phone) <= 15 ? $phone : '';
    }

    private function postJson(string $url, string $accessToken, array $payload): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false) {
            $body = json_encode(['error' => ['message' => $error]]);
        }

        return [
            'http_code' => $httpCode,
            'body' => (string) $body,
            'json' => json_decode((string) $body, true),
        ];
    }

    private function config(string $name, string $default = ''): string
    {
        return defined('Env::' . $name) ? (string) constant('Env::' . $name) : $default;
    }
}
