<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Resto API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('resto')->group(function () {
    // Resto routes here
    Route::get('/', function () {
        return response()->json([
            'message' => 'Resto API',
            'version' => '1.0'
        ]);
    });
});
