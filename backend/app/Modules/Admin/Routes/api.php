<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {
    // Admin routes here
    Route::get('/', function () {
        return response()->json([
            'message' => 'Admin API',
            'version' => '1.0'
        ]);
    });
    Route::post('/login', [\App\Modules\Admin\Http\Controllers\AuthController::class, 'login']);
    Route::post('/verify-otp', [\App\Modules\Admin\Http\Controllers\AuthController::class, 'verifyOtp']);
});
