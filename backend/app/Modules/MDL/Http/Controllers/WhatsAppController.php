<?php

namespace App\Modules\MDL\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\MainSetting;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    protected string $serviceBase = 'http://127.0.0.1:8033';

    public function createSession(Request $request)
    {
        $sessionId = 'mdl_' . Str::random(12);

        try {
            $resp = Http::timeout(10)->post($this->serviceBase . '/create-session', [
                'sessionId' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'WA service tidak tersedia',
            ], 502);
        }

        if (!$resp->ok()) {
            $data = $resp->json() ?: [];
            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Gagal membuat session',
            ], $resp->status());
        }

        return response()->json([
            'success' => true,
            'message' => 'Session dibuat',
            'session_id' => $sessionId,
        ]);
    }

    public function cekStatus(Request $request)
    {
        $sessionId = (string) $request->input('session_id');
        $userId = (int) $request->input('user_id');
        $deviceName = (string) $request->input('device_name');
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada session',
            ], 404);
        }

        try {
            $resp = Http::timeout(10)->post($this->serviceBase . '/cek-status', [
                'sessionId' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'WA service tidak tersedia',
            ], 502);
        }

        $data = $resp->json() ?: [];
        if (!$resp->ok()) {
            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Gagal cek status',
            ], $resp->status());
        }

        $loggedIn = (bool)($data['logged_in'] ?? false);
        $previous = (string) optional(MainSetting::query()->find('WA_AUTH'))->value;
        $response = [
            'success' => true,
            'logged_in' => $loggedIn,
            'qr_ready' => (bool)($data['qr_ready'] ?? false),
        ];
        if (!empty($data['qr_string'])) {
            $response['qr_string'] = $data['qr_string'];
        }

        if ($loggedIn) {
            // Admin single-session flow
            if ($previous !== '' && $previous !== $sessionId && $userId === 0) {
                try {
                    Http::timeout(10)->post($this->serviceBase . '/delete-session', [
                        'sessionId' => $previous,
                    ]);
                } catch (\Throwable $e) {
                }
            }
            if ($userId > 0) {
                // Upsert ke tabel whatsapp untuk user
                try {
                    $exists = DB::connection('mdl_main')->table('whatsapp')
                        ->where('user_id', $userId)
                        ->where('auth', $sessionId)
                        ->exists();
                    if ($exists) {
                        DB::connection('mdl_main')->table('whatsapp')
                            ->where('user_id', $userId)
                            ->where('auth', $sessionId)
                            ->update(['wa_status' => 'active']);
                    } else {
                        DB::connection('mdl_main')->table('whatsapp')->insert([
                            'user_id' => $userId,
                            'device_name' => $deviceName !== '' ? $deviceName : 'Perangkat',
                            'auth' => $sessionId,
                            'wa_status' => 'active',
                            'created_at' => now(),
                        ]);
                    }
                } catch (\Throwable $e) {
                }
            } else {
                MainSetting::query()->updateOrCreate([
                    'enum' => 'WA_AUTH'
                ], [
                    'value' => $sessionId
                ]);
            }
            $response['saved'] = true;
        } else {
            $response['saved'] = false;
            // Simpan status pending untuk user jika diminta
            if ($userId > 0) {
                try {
                    $exists = DB::connection('mdl_main')->table('whatsapp')
                        ->where('user_id', $userId)
                        ->where('auth', $sessionId)
                        ->exists();
                    if ($exists) {
                        DB::connection('mdl_main')->table('whatsapp')
                            ->where('user_id', $userId)
                            ->where('auth', $sessionId)
                            ->update(['wa_status' => 'pending']);
                    } else {
                        DB::connection('mdl_main')->table('whatsapp')->insert([
                            'user_id' => $userId,
                            'device_name' => $deviceName !== '' ? $deviceName : 'Perangkat',
                            'auth' => $sessionId,
                            'wa_status' => 'pending',
                            'created_at' => now(),
                        ]);
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return response()->json($response);
    }

    public function listSaved(Request $request)
    {
        $userId = (int) $request->query('user_id');
        $sessions = [];
        if ($userId > 0) {
            // Ambil dari tabel whatsapp berdasarkan user
            $rows = DB::connection('mdl_main')->table('whatsapp')
                ->select(['device_name', 'auth', 'wa_status', 'created_at'])
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
            foreach ($rows as $row) {
                $status = (string) $row->wa_status;
                try {
                    $resp = Http::timeout(5)->post($this->serviceBase . '/cek-status', [
                        'sessionId' => (string) $row->auth,
                    ]);
                    if ($resp->ok()) {
                        $data = $resp->json() ?: [];
                        if (!empty($data['logged_in'])) {
                            $status = 'active';
                        } else if (!empty($data['qr_ready'])) {
                            $status = 'pending';
                        } else {
                            $status = 'inactive';
                        }
                    }
                } catch (\Throwable $e) {
                }
                $sessions[] = [
                    'device_name' => (string) $row->device_name,
                    'auth' => (string) $row->auth,
                    'wa_status' => $status,
                    'created_at' => $row->created_at,
                ];
            }
        } else {
            // Admin single-session fallback
            $sessionId = (string) optional(MainSetting::query()->find('WA_AUTH'))->value;
            if ($sessionId !== '') {
                $status = 'inactive';
                try {
                    $resp = Http::timeout(8)->post($this->serviceBase . '/cek-status', [
                        'sessionId' => $sessionId,
                    ]);
                    if ($resp->ok()) {
                        $data = $resp->json() ?: [];
                        if (!empty($data['logged_in'])) {
                            $status = 'active';
                        } else if (!empty($data['qr_ready'])) {
                            $status = 'pending';
                        }
                    }
                } catch (\Throwable $e) {
                }
                $sessions[] = [
                    'device_name' => 'Admin WA',
                    'auth' => $sessionId,
                    'wa_status' => $status,
                    'created_at' => null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
            'total' => count($sessions),
        ]);
    }

    public function deleteSaved(Request $request)
    {
        $auth = (string) $request->input('auth');
        $userId = (int) $request->input('user_id');
        if ($auth === '' && $userId === 0) {
            $auth = (string) optional(MainSetting::query()->find('WA_AUTH'))->value;
        }
        if ($auth === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada session',
            ], 404);
        }

        try {
            Http::timeout(10)->post($this->serviceBase . '/delete-session', [
                'sessionId' => $auth,
            ]);
        } catch (\Throwable $e) {
        }

        if ($userId > 0) {
            try {
                DB::connection('mdl_main')->table('whatsapp')
                    ->where('user_id', $userId)
                    ->where('auth', $auth)
                    ->delete();
            } catch (\Throwable $e) {
            }
        } else {
            MainSetting::query()->where('enum', 'WA_AUTH')->delete();
        }

        return response()->json([
            'success' => true,
            'deleted' => true,
        ]);
    }

    // duplicate, invalid method removed

    public function loginSession(Request $request)
    {
        $auth = (string) $request->input('auth');
        $userId = (int) $request->input('user_id');
        $deviceName = (string) $request->input('device_name');
        if ($auth === '') {
            $auth = (string) optional(MainSetting::query()->find('WA_AUTH'))->value;
        }
        if ($auth === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada session',
            ], 404);
        }

        try {
            Http::timeout(10)->post($this->serviceBase . '/reset-session', [
                'sessionId' => $auth,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'WA service tidak tersedia',
            ], 502);
        }

        if ($userId > 0) {
            try {
                $exists = DB::connection('mdl_main')->table('whatsapp')
                    ->where('user_id', $userId)
                    ->where('auth', $auth)
                    ->exists();
                if ($exists) {
                    DB::connection('mdl_main')->table('whatsapp')
                        ->where('user_id', $userId)
                        ->where('auth', $auth)
                        ->update([
                            'device_name' => $deviceName !== '' ? $deviceName : DB::raw('device_name'),
                            'wa_status' => 'pending',
                        ]);
                } else {
                    DB::connection('mdl_main')->table('whatsapp')->insert([
                        'user_id' => $userId,
                        'device_name' => $deviceName !== '' ? $deviceName : 'Perangkat',
                        'auth' => $auth,
                        'wa_status' => 'pending',
                        'created_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Session siap untuk login ulang',
        ]);
    }
}
