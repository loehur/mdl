<?php

namespace App\Modules\MDL\Http\Controllers;

use App\Http\Controllers\Controller;
//
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FonnteController extends Controller
{
    protected function resolveToken(?string $override = null): ?string
    {
        if ($override) return $override;
        $envToken = config('services.fonnte.token');
        return $envToken ?: null;
    }

    public function ping(Request $request)
    {
        $token = $this->resolveToken();
        return response()->json([
            'success' => true,
            'configured' => !empty($token),
        ]);
    }

    public function send(Request $request)
    {
        $input = $request->only([
            'target',
            'message',
            'url',
            'filename',
            'schedule',
            'typing',
            'delay',
            'country_code',
            'location',
            'followup',
            'token',
        ]);
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                $input[$k] = trim($v);
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'target' => ['required', 'string'],
            'message' => ['required', 'string'],
            'url' => ['nullable', 'string'],
            'filename' => ['nullable', 'string'],
            'schedule' => ['nullable', 'integer'],
            'typing' => ['nullable', 'boolean'],
            'delay' => ['nullable'],
            'country_code' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'followup' => ['nullable', 'integer'],
            'token' => ['nullable', 'string'],
        ]);

        $token = $this->resolveToken($validated['token'] ?? null);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Fonnte token belum dikonfigurasi',
            ], 422);
        }

        $base = (string) config('services.fonnte.base_url');
        $payload = [
            'target' => $validated['target'],
            'message' => $validated['message'],
        ];
        if (!empty($validated['url'])) $payload['url'] = $validated['url'];
        if (!empty($validated['filename'])) $payload['filename'] = $validated['filename'];
        if (isset($validated['schedule'])) $payload['schedule'] = $validated['schedule'];
        if (isset($validated['typing'])) $payload['typing'] = $validated['typing'] ? 'true' : 'false';
        if (isset($validated['delay'])) $payload['delay'] = $validated['delay'];
        if (!empty($validated['country_code'])) $payload['countryCode'] = $validated['country_code'];
        if (!empty($validated['location'])) $payload['location'] = $validated['location'];
        if (isset($validated['followup'])) $payload['followup'] = $validated['followup'];

        $req = Http::withHeaders(['Authorization' => $token])->timeout(20);
        if (config('app.env') !== 'production') {
            $req = $req->withOptions(['verify' => false]);
        }
        $file = $request->file('file');
        if ($file && $file->isValid()) {
            $req = $req->asMultipart()->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName());
        } else {
            $req = $req->asForm();
        }

        try {
            $resp = $req->post(rtrim($base, '/') . '/send', $payload);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Fonnte',
            ], 502);
        }

        $data = $resp->json() ?: [];
        if (!$resp->ok()) {
            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Gagal mengirim pesan',
                'response' => $data,
            ], $resp->status());
        }

        return response()->json([
            'success' => true,
            'message' => $data['message'] ?? 'Pesan dikirim',
            'response' => $data,
        ]);
    }
}
