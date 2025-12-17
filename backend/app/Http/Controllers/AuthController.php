<?php

namespace App\Http\Controllers;

use App\Models\MainUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20', 'starts_with:08', 'regex:/^08\d+$/', 'unique:mdl_main.users,phone_number'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        try {
            $user = MainUser::create($validated);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $user->id,
        ]);
    }
}
