<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MainSetting;
use App\Models\MainUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    private function normalizeWaTarget(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') return $raw;
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits;
    }
    public function login(Request $request)
    {
        $phone = trim((string) $request->input('phone_number'));
        $password = trim((string) $request->input('password'));

        if ($phone === '' || $password === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor telepon dan kata sandi wajib diisi',
            ], 422);
        }

        $phoneSetting = MainSetting::query()->find('phone_number');
        $passwordSetting = MainSetting::query()->find('password');
        $expectedPhone = $phoneSetting ? (string) $phoneSetting->value : '';
        $hashedPassword = $passwordSetting ? (string) $passwordSetting->value : '';

        if ($phone !== $expectedPhone || ($hashedPassword === '' || !Hash::check($password, $hashedPassword))) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal',
            ], 401);
        }

        $digits = preg_replace('/\D+/', '', $expectedPhone) ?: $expectedPhone;
        $otpCode = (string) random_int(100000, 999999);
        $otpKey = 'admin_login_otp_code_' . $digits;
        $lastKey = 'admin_login_last_otp_' . $digits;
        $last = Cache::get($lastKey, 0);
        if (time() - (int) $last < 30) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu sering meminta OTP, coba lagi sebentar',
            ], 429);
        }
        Cache::put($otpKey, ['code' => $otpCode, 'requested_at' => time()], now()->addMinutes(10));
        Cache::put($lastKey, time(), now()->addMinutes(10));

        $token = (string) config('services.fonnte.token', '');
        $base = (string) config('services.fonnte.base_url', 'https://api.fonnte.com');
        if ($token === '') {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi Fonnte belum tersedia',
            ], 500);
        }

        $message = 'Kode OTP Admin MDL: ' . $otpCode . ' berlaku 10 menit.';
        $sentOk = false;
        $target = $this->normalizeWaTarget($expectedPhone);
        try {
            $req = Http::withHeaders(['Authorization' => $token])->timeout(15);
            if (config('app.env') !== 'production') {
                $req = $req->withOptions(['verify' => false]);
            }
            $resp = $req->asForm()->post(rtrim($base, '/') . '/send', [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
                'typing' => 'false',
                'delay' => '0',
                'schedule' => 0,
            ]);
            $sentOk = $resp->ok();
            if (!$sentOk) {
                $data = $resp->json();
                \Log::warning('admin_fonnte_send_fail', ['status' => $resp->status(), 'data' => $data]);
            }
        } catch (\Throwable $e) {
            \Log::error('admin_fonnte_send_error', ['error' => $e->getMessage()]);
            $sentOk = false;
        }

        $payload = [
            'success' => true,
            'otp_required' => true,
        ];
        if ($sentOk) {
            $payload['message'] = 'OTP dikirim ke nomor terdaftar';
        } else {
            $payload['message'] = 'OTP disiapkan, namun gagal mengirim ke nomor';
        }
        if (config('app.debug')) {
            $payload['dev_otp'] = $otpCode;
        }
        return response()->json($payload);
    }

    public function verifyOtp(Request $request)
    {
        $phone = trim((string) $request->input('phone_number'));
        $otp = trim((string) $request->input('otp'));
        if ($phone === '' || $otp === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor telepon dan OTP wajib diisi',
            ], 422);
        }

        $phoneSetting = MainSetting::query()->find('phone_number');
        $expectedPhone = $phoneSetting ? (string) $phoneSetting->value : '';
        if ($phone !== $expectedPhone) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor telepon tidak cocok',
            ], 401);
        }

        $digits = preg_replace('/\D+/', '', $expectedPhone) ?: $expectedPhone;
        $otpKey = 'admin_login_otp_code_' . $digits;
        $data = Cache::get($otpKey);
        if (!$data || !is_array($data) || empty($data['code'])) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak ditemukan atau telah kedaluwarsa',
            ], 422);
        }
        if ((string) $data['code'] !== $otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP salah',
            ], 401);
        }

        Cache::forget($otpKey);

        $user = MainUser::query()->where('phone_number', $expectedPhone)->first();
        $payloadUser = $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone_number,
            'active_until' => $user->active_until,
        ] : [
            'id' => 0,
            'name' => 'Admin',
            'phone_number' => $expectedPhone,
            'active_until' => null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Verifikasi berhasil',
            'user' => $payloadUser,
            'redirect' => '/dashboard',
        ]);
    }
}
