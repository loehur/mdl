<?php

namespace App\Modules\MDL\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MainUser;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    private function normalizeWaNumber(string $raw): array
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return [false, null, 'Nomor telepon tidak valid'];
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        if (strlen($digits) > 15) {
            return [false, null, 'Nomor WA maksimal 15 digit'];
        }
        return [true, $digits, null];
    }

    public function register(Request $request)
    {
        $input = $request->only(['name', 'phone_number', 'password', 'password_confirmation', 'otp']);
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                $input[$k] = trim($v);
            }
        }
        $request->merge($input);
        $payload = $request->except(['password']);
        Log::info('mdl_register', $payload);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20', 'starts_with:08', 'regex:/^08\d+$/', 'unique:mdl_main.users,phone_number'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'otp' => ['required', 'string'],
        ], [
            'name.required' => 'Nama wajib diisi',
            'name.max' => 'Nama terlalu panjang',
            'phone_number.required' => 'Nomor telepon wajib diisi',
            'phone_number.max' => 'Nomor telepon terlalu panjang',
            'phone_number.starts_with' => 'Nomor telepon harus dimulai dengan 08',
            'phone_number.regex' => 'Nomor telepon harus angka dan diawali 08',
            'phone_number.unique' => 'Nomor telepon sudah dipakai',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 6 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
            'otp.required' => 'OTP wajib diisi',
        ]);

        $digits = preg_replace('/\D+/', '', $validated['phone_number']);
        $otpKey = 'mdl_public_otp_code_' . $digits;
        $otpData = Cache::get($otpKey);
        if (!$otpData || !isset($otpData['code']) || $otpData['code'] !== $validated['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid',
                'errors' => ['otp' => ['OTP tidak valid']]
            ], 422);
        }

        try {
            $validated['active_until'] = now()->addMonth()->toDateString();
            $user = MainUser::create($validated);
        } catch (QueryException $e) {
            $msg = $e->getMessage();
            $errors = [];
            if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'phone_number')) {
                $errors['phone_number'] = ['Nomor telepon sudah dipakai'];
            }
            if (empty($errors)) {
                $errors['general'] = ['Registrasi gagal'];
            }
            $flat = implode(' ', Arr::flatten($errors));
            return response()->json([
                'success' => false,
                'message' => $flat,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $user->id,
        ]);
    }

    public function requestOtp(Request $request)
    {
        $input = $request->only(['phone_number']);
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                $input[$k] = trim($v);
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20', 'starts_with:08', 'regex:/^08\d+$/'],
        ], [
            'phone_number.required' => 'Nomor telepon wajib diisi',
            'phone_number.max' => 'Nomor telepon terlalu panjang',
            'phone_number.starts_with' => 'Nomor telepon harus dimulai dengan 08',
            'phone_number.regex' => 'Nomor telepon harus angka dan diawali 08',
        ]);

        $digits = preg_replace('/\D+/', '', $validated['phone_number']);
        $lastKey = 'mdl_public_otp_last_' . $digits;
        $last = Cache::get($lastKey);
        if ($last && (time() - (int) $last) < 300) {
            $remain = 300 - (time() - (int) $last);
            return response()->json([
                'success' => false,
                'message' => 'OTP sudah diminta, coba lagi nanti',
                'retry_after' => $remain,
            ], 429);
        }

        $code = (string) random_int(100000, 999999);
        $otpKey = 'mdl_public_otp_code_' . $digits;
        Cache::put($otpKey, ['code' => $code, 'requested_at' => time()], now()->addMinutes(10));
        Cache::put($lastKey, time(), now()->addMinutes(10));
        [$ok, $waNumber, $err] = $this->normalizeWaNumber($validated['phone_number']);
        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => $err,
            ], 422);
        }
        $sessionId = config('services.mdl_wa.auth_public');
        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'WA auth belum dikonfigurasi',
            ], 500);
        }

        $message = 'Kode OTP MDL: ' . $code . ' berlaku 10 menit.';
        $sentOk = false;
        try {
            $resp = Http::timeout(10)->post('http://127.0.0.1:8033/send-message', [
                'sessionId' => $sessionId,
                'number' => $waNumber,
                'message' => $message,
            ]);
            $sentOk = $resp->ok();
        } catch (\Throwable $e) {
            $sentOk = false;
        }

        if (!$sentOk) {
            $token = (string) config('services.fonnte.token', '');
            $base = (string) config('services.fonnte.base_url', 'https://api.fonnte.com');
            if ($token !== '') {
                try {
                    $req = Http::withHeaders(['Authorization' => $token])->timeout(15);
                    if (config('app.env') !== 'production') {
                        $req = $req->withOptions(['verify' => false]);
                    }
                    $resp2 = $req->asForm()->post(rtrim($base, '/') . '/send', [
                        'target' => $waNumber,
                        'message' => $message,
                        'countryCode' => '62',
                        'typing' => 'false',
                        'delay' => '0',
                        'schedule' => 0,
                    ]);
                    $sentOk = $resp2->ok();
                } catch (\Throwable $e) {
                    $sentOk = false;
                }
            }
        }

        if (!$sentOk) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP dikirim',
        ]);
    }

    public function forgotRequestOtp(Request $request)
    {
        $input = $request->only(['phone_number']);
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                $input[$k] = trim($v);
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20', 'starts_with:08', 'regex:/^08\d+$/'],
        ], [
            'phone_number.required' => 'Nomor telepon wajib diisi',
            'phone_number.max' => 'Nomor telepon terlalu panjang',
            'phone_number.starts_with' => 'Nomor telepon harus dimulai dengan 08',
            'phone_number.regex' => 'Nomor telepon harus angka dan diawali 08',
        ]);

        $user = MainUser::where('phone_number', $validated['phone_number'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor telepon tidak ditemukan',
            ], 404);
        }

        $digits = preg_replace('/\D+/', '', $validated['phone_number']);
        $lastKey = 'mdl_forgot_otp_last_' . $digits;
        $last = Cache::get($lastKey);
        if ($last && (time() - (int) $last) < 300) {
            $remain = 300 - (time() - (int) $last);
            return response()->json([
                'success' => false,
                'message' => 'OTP sudah diminta, coba lagi nanti',
                'retry_after' => $remain,
            ], 429);
        }

        $code = (string) random_int(100000, 999999);
        $otpKey = 'mdl_forgot_otp_code_' . $digits;
        Cache::put($otpKey, ['code' => $code, 'requested_at' => time()], now()->addMinutes(10));
        Cache::put($lastKey, time(), now()->addMinutes(10));

        [$ok, $waNumber, $err] = $this->normalizeWaNumber($validated['phone_number']);
        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => $err,
            ], 422);
        }
        $sessionId = config('services.mdl_wa.auth_public');
        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'WA auth belum dikonfigurasi',
            ], 500);
        }

        $message = 'Kode OTP Reset MDL: ' . $code . ' berlaku 10 menit.';
        $sentOk = false;
        try {
            $resp = Http::timeout(10)->post('http://127.0.0.1:8033/send-message', [
                'sessionId' => $sessionId,
                'number' => $waNumber,
                'message' => $message,
            ]);
            $sentOk = $resp->ok();
        } catch (\Throwable $e) {
            $sentOk = false;
        }

        if (!$sentOk) {
            $token = (string) config('services.fonnte.token', '');
            $base = (string) config('services.fonnte.base_url', 'https://api.fonnte.com');
            if ($token !== '') {
                try {
                    $req = Http::withHeaders(['Authorization' => $token])->timeout(15);
                    if (config('app.env') !== 'production') {
                        $req = $req->withOptions(['verify' => false]);
                    }
                    $resp2 = $req->asForm()->post(rtrim($base, '/') . '/send', [
                        'target' => $waNumber,
                        'message' => $message,
                        'countryCode' => '62',
                        'typing' => 'false',
                        'delay' => '0',
                        'schedule' => 0,
                    ]);
                    $sentOk = $resp2->ok();
                } catch (\Throwable $e) {
                    $sentOk = false;
                }
            }
        }

        if (!$sentOk) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP dikirim',
        ]);
    }

    public function forgotReset(Request $request)
    {
        $input = $request->only(['phone_number', 'otp', 'new_password', 'new_password_confirmation']);
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                $input[$k] = trim($v);
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20', 'starts_with:08', 'regex:/^08\d+$/'],
            'otp' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'phone_number.required' => 'Nomor telepon wajib diisi',
            'phone_number.max' => 'Nomor telepon terlalu panjang',
            'phone_number.starts_with' => 'Nomor telepon harus dimulai dengan 08',
            'phone_number.regex' => 'Nomor telepon harus angka dan diawali 08',
            'otp.required' => 'OTP wajib diisi',
            'new_password.required' => 'Password baru wajib diisi',
            'new_password.min' => 'Password baru minimal 6 karakter',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok',
        ]);

        $digits = preg_replace('/\D+/', '', $validated['phone_number']);

        $lockKey = 'mdl_forgot_lock_' . $digits;
        $lockUntil = Cache::get($lockKey);
        if ($lockUntil && time() < (int) $lockUntil) {
            $retry = max(0, (int) $lockUntil - time());
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan OTP, coba lagi nanti',
                'retry_after' => $retry,
            ], 429);
        }

        $otpKey = 'mdl_forgot_otp_code_' . $digits;
        $otpData = Cache::get($otpKey);
        if (!$otpData || !isset($otpData['code']) || $otpData['code'] !== $validated['otp']) {
            $failKey = 'mdl_forgot_otp_fail_' . $digits;
            $fails = (int) Cache::get($failKey, 0) + 1;
            Cache::put($failKey, $fails, now()->addMinutes(10));
            if ($fails >= 5) {
                $until = time() + 300;
                Cache::put($lockKey, $until, now()->addMinutes(5));
            }
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid',
                'errors' => ['otp' => ['OTP tidak valid']],
            ], 422);
        }

        $user = MainUser::where('phone_number', $validated['phone_number'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor telepon tidak ditemukan',
            ], 404);
        }

        $user->password = $validated['new_password'];
        $user->save();

        Cache::forget('mdl_forgot_otp_fail_' . $digits);
        Cache::forget($otpKey);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset',
        ]);
    }

    public function login(Request $request)
    {
        $input = $request->only(['phone_number', 'password']);
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                $input[$k] = trim($v);
            }
        }
        $request->merge($input);

        $validated = $request->validate([
            'phone_number' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'phone_number.required' => 'Nomor telepon wajib diisi',
            'password.required' => 'Password wajib diisi',
        ]);

        $lockKey = 'mdl_login_lock_' . preg_replace('/\D+/', '', $validated['phone_number']);
        $lockUntil = Cache::get($lockKey);
        if ($lockUntil && time() < (int) $lockUntil) {
            $retry = max(0, (int) $lockUntil - time());
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan login, coba lagi nanti',
                'retry_after' => $retry,
                'errors' => ['general' => ['Terlalu banyak percobaan login, coba lagi nanti']],
            ], 429);
        }

        $user = MainUser::where('phone_number', $validated['phone_number'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor telepon atau password salah',
                'errors' => ['general' => ['Nomor telepon atau password salah']],
            ], 422);
        }

        if (!password_verify($validated['password'], $user->password)) {
            $failKey = 'mdl_login_fail_' . preg_replace('/\D+/', '', $validated['phone_number']);
            $fails = (int) Cache::get($failKey, 0) + 1;
            Cache::put($failKey, $fails, now()->addMinutes(5));
            if ($fails >= 5) {
                $until = time() + 300;
                Cache::put($lockKey, $until, now()->addMinutes(5));
            }
            return response()->json([
                'success' => false,
                'message' => 'Nomor telepon atau password salah',
                'errors' => ['general' => ['Nomor telepon atau password salah']],
            ], 422);
        }

        Cache::forget('mdl_login_fail_' . preg_replace('/\D+/', '', $validated['phone_number']));

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'redirect' => '/member',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
                'active_until' => $user->active_until,
            ],
        ]);
    }
}
