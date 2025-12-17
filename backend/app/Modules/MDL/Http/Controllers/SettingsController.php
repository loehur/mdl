<?php

namespace App\Modules\MDL\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MainSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = MainSetting::query()->orderBy('enum')->get(['enum', 'value']);
        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    public function show(string $enum)
    {
        $setting = MainSetting::query()->find($enum);
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting tidak ditemukan',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'enum' => $setting->enum,
            'value' => $setting->value,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only(['enum', 'value']);
        if (!isset($data['enum']) || !is_string($data['enum']) || $data['enum'] === '') {
            return response()->json([
                'success' => false,
                'message' => 'Field enum wajib diisi',
            ], 422);
        }

        $setting = MainSetting::query()->find($data['enum']);
        if (!$setting) {
            $setting = new MainSetting(['enum' => $data['enum']]);
        }
        $value = $data['value'] ?? null;
        if ($setting->enum === 'password' && is_string($value) && $value !== '') {
            $value = Hash::make($value);
        }
        $setting->value = $value;
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Setting disimpan',
            'enum' => $setting->enum,
            'value' => $setting->value,
        ]);
    }

    public function destroy(string $enum)
    {
        $setting = MainSetting::query()->find($enum);
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting tidak ditemukan',
            ], 404);
        }
        $setting->delete();
        return response()->json([
            'success' => true,
            'message' => 'Setting dihapus',
        ]);
    }
}
