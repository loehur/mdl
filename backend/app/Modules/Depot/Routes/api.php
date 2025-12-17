<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Depot API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('depot')->group(function () {
    // Depot routes here
    Route::get('/', function () {
        return response()->json([
            'message' => 'Depot API',
            'version' => '1.0'
        ]);
    });
});
