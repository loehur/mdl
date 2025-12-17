<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laundry API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('laundry')->group(function () {
    // Laundry routes here
    Route::get('/', function () {
        return response()->json([
            'message' => 'Laundry API',
            'version' => '1.0'
        ]);
    });
});
