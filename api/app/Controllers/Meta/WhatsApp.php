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

    /**
     * Tambahkan nomor baru ke WABA Meta.
     *
     * POST /Meta/WhatsApp/add-phone-number
     * Setelah berhasil, Meta dapat meminta langkah verifikasi nomor/OTP lanjutan.
     */
    public function add_phone_number()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST.', 405);
        }

        $this->authorize('META_WA_ADMIN_API_KEY', 'HTTP_X_META_WA_ADMIN_KEY');
        $body = $this->getBody();
        $this->validate($body, ['waba_id', 'country_code', 'phone_number', 'verified_name']);

        $accessToken = $this->config('META_WA_ACCESS_TOKEN');
        if ($accessToken === '') {
            $this->error('Meta WhatsApp belum dikonfigurasi. Isi META_WA_ACCESS_TOKEN.', 503);
        }

        $wabaId = $this->strFromBody($body, 'waba_id');
        $countryCode = preg_replace('/\D+/', '', $this->strFromBody($body, 'country_code')) ?? '';
        $phoneNumber = preg_replace('/\D+/', '', $this->strFromBody($body, 'phone_number')) ?? '';
        $verifiedName = $this->strFromBody($body, 'verified_name');

        if (!preg_match('/^\d{5,32}$/', $wabaId)
            || !preg_match('/^\d{1,4}$/', $countryCode)
            || !preg_match('/^\d{5,20}$/', $phoneNumber)
            || $verifiedName === '') {
            $this->error('waba_id, country_code, phone_number, atau verified_name tidak valid.', 400);
        }

        // Endpoint Meta menerima nomor nasional tanpa simbol +; nol depan dibuang.
        $phoneNumber = ltrim($phoneNumber, '0');
        if ($phoneNumber === '') {
            $this->error('phone_number tidak valid.', 400);
        }

        $version = $this->config('META_WA_GRAPH_VERSION', 'v23.0');
        $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/'
            . rawurlencode($wabaId) . '/phone_numbers';
        $result = $this->postJson($url, $accessToken, [
            'cc' => $countryCode,
            'phone_number' => $phoneNumber,
            'verified_name' => $verifiedName,
        ]);

        \Log::write(
            'Add WABA phone number: waba_id=' . $wabaId
                . ', cc=' . $countryCode
                . ', phone=' . $phoneNumber
                . ', http=' . $result['http_code']
                . ', response=' . $result['body'],
            'wa_meta',
            'add_phone_number'
        );

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->error('Meta menolak penambahan nomor ke WABA.', 502, [
                'meta_response' => $result['json'] ?? ['raw' => $result['body']],
            ]);
        }

        $this->success([
            'waba_id' => $wabaId,
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
            'meta_response' => $result['json'] ?? ['raw' => $result['body']],
        ], 'Permintaan tambah nomor ke WABA berhasil dikirim ke Meta.');
    }

    /**
     * Registrasikan nomor WABA yang berstatus Pending ke WhatsApp Cloud API.
     *
     * POST /Meta/WhatsApp/register-phone-number
     */
    public function register_phone_number()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST.', 405);
        }

        $this->authorize('META_WA_ADMIN_API_KEY', 'HTTP_X_META_WA_ADMIN_KEY');
        $body = $this->getBody();
        $this->validate($body, ['phone_number_id', 'pin']);

        $accessToken = $this->config('META_WA_ACCESS_TOKEN');
        if ($accessToken === '') {
            $this->error('Meta WhatsApp belum dikonfigurasi. Isi META_WA_ACCESS_TOKEN.', 503);
        }

        $phoneNumberId = $this->strFromBody($body, 'phone_number_id');
        $pin = $this->strFromBody($body, 'pin');
        if (!preg_match('/^\d{5,32}$/', $phoneNumberId)) {
            $this->error('phone_number_id tidak valid.', 400);
        }
        if (!preg_match('/^\d{6}$/', $pin)) {
            $this->error('pin harus terdiri dari tepat 6 digit.', 400);
        }

        $version = $this->config('META_WA_GRAPH_VERSION', 'v23.0');
        $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/'
            . rawurlencode($phoneNumberId) . '/register';
        $result = $this->postJson($url, $accessToken, [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);

        \Log::write(
            'Register WABA phone number: phone_number_id=' . $phoneNumberId
                . ', http=' . $result['http_code']
                . ', response=' . $result['body'],
            'wa_meta',
            'register_phone_number'
        );

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->error('Meta menolak registrasi nomor WhatsApp.', 502, [
                'meta_response' => $result['json'] ?? ['raw' => $result['body']],
            ]);
        }

        $this->success([
            'phone_number_id' => $phoneNumberId,
            'meta_response' => $result['json'] ?? ['raw' => $result['body']],
        ], 'Registrasi nomor WhatsApp telah dikirim ke Meta.');
    }

    private function authorize(
        string $configName = 'META_WA_SEND_API_KEY',
        string $headerName = 'HTTP_X_META_WA_SEND_KEY'
    ): void
    {
        $expected = $this->config($configName);
        $provided = (string) ($_SERVER[$headerName] ?? '');

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
