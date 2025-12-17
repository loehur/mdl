<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Modules\MDL\Http\Controllers\AuthController as MDLAuthController;
use App\Modules\MDL\Http\Controllers\WhatsAppController;
use App\Modules\MDL\Http\Controllers\SettingsController;
use App\Modules\MDL\Http\Controllers\FonnteController;
use App\Modules\MDL\Http\Controllers\BusinessController;
use App\Modules\MDL\Http\Controllers\TokopayController;
use App\Modules\Beauty_Salon\Http\Controllers\SalonUserController;
use App\Modules\Beauty_Salon\Http\Controllers\EmployeeController;
use App\Modules\Beauty_Salon\Http\Controllers\CustomerController;
use App\Modules\Beauty_Salon\Http\Controllers\SaleController;
use App\Modules\Beauty_Salon\Http\Controllers\ProductController;
use App\Models\MainUser;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'Backend API',
        'version' => '1.0',
        'modules' => [
            'admin' => '/api/admin',
            'laundry' => '/api/laundry',
            'resto' => '/api/resto',
            'depot' => '/api/depot'
        ]
    ]);
});

Route::post('/register', [AuthController::class, 'register']);

Route::prefix('mdl')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'message' => 'MDL API',
            'status' => 'ok',
        ]);
    });

    Route::post('/register', [MDLAuthController::class, 'register']);
    Route::post('/login', [MDLAuthController::class, 'login']);
    Route::post('/otp/request', [MDLAuthController::class, 'requestOtp']);
    Route::post('/forgot/request-otp', [MDLAuthController::class, 'forgotRequestOtp']);
    Route::post('/forgot/reset', [MDLAuthController::class, 'forgotReset']);

    Route::prefix('wa')->group(function () {
        Route::post('/create-session', [WhatsAppController::class, 'createSession']);
        Route::post('/cek-status', [WhatsAppController::class, 'cekStatus']);
        Route::get('/list-saved', [WhatsAppController::class, 'listSaved']);
        Route::post('/delete-session', [WhatsAppController::class, 'deleteSaved']);
        Route::post('/login-session', [WhatsAppController::class, 'loginSession']);
    });

    Route::prefix('businesses')->group(function () {
        Route::get('/options', [BusinessController::class, 'options']);
        Route::get('/list', [BusinessController::class, 'list']);
        Route::post('/add', [BusinessController::class, 'add']);
        Route::post('/delete', [BusinessController::class, 'delete']);
    });

    Route::prefix('salon')->group(function () {
        Route::post('/users/add', [SalonUserController::class, 'add']);
        Route::get('/users/list', [SalonUserController::class, 'list']);
        Route::post('/users/login', [SalonUserController::class, 'login']);
        Route::post('/users/change-password', [SalonUserController::class, 'changePassword']);
        Route::post('/users/add-cashier', [SalonUserController::class, 'addCashier']);
        Route::get('/product/list', [ProductController::class, 'list']);
        Route::post('/product/add', [ProductController::class, 'add']);
        Route::post('/product/delete', [ProductController::class, 'delete']);
        Route::post('/product/edit', [ProductController::class, 'edit']);

        Route::get('/employee/list', [EmployeeController::class, 'list']);
        Route::post('/employee/add', [EmployeeController::class, 'add']);
        Route::post('/employee/edit', [EmployeeController::class, 'edit']);

        Route::get('/customer/list', [CustomerController::class, 'list']);
        Route::post('/customer/add', [CustomerController::class, 'add']);
        Route::post('/customer/edit', [CustomerController::class, 'edit']);

        Route::post('/sale/create', [SaleController::class, 'create']);
        Route::get('/sale/list-running', [SaleController::class, 'listRunning']);
        Route::get('/sale/list-history', [SaleController::class, 'listHistory']);
        Route::get('/sale/detail', [SaleController::class, 'detail']);
        Route::post('/sale/pay', [SaleController::class, 'pay']);


        Route::get('/business/name', function (Request $request) {
            $userBusinessId = (int) ($request->query('user_business_id') ?? 0);
            $businessId = (int) ($request->query('business_id') ?? 0);
            $name = null;
            try {
                if ($userBusinessId > 0) {
                    $ub = DB::connection('mdl_main')->table('user_business')
                        ->select(['business_brand'])
                        ->where('id', $userBusinessId)
                        ->first();
                    if ($ub) {
                        $name = (string) ($ub->business_brand ?? '');
                    }
                } elseif ($businessId > 0) {
                    // Treat business_id as user_business.id (salon users store this)
                    $ubById = DB::connection('mdl_main')->table('user_business')
                        ->select(['business_brand'])
                        ->where('id', $businessId)
                        ->first();
                    if ($ubById) {
                        $name = (string) ($ubById->business_brand ?? '');
                    }
                }
            } catch (\Throwable $e) {
                $name = $name ?: '';
            }
            $ok = $name !== '';
            return response()->json([
                'success' => $ok,
                'business_name' => $ok ? $name : null,
            ], $ok ? 200 : 404);
        });
    });

    // settings feature removed; WA_AUTH managed internally by WA controller

    Route::prefix('fonnte')->group(function () {
        Route::get('/ping', [FonnteController::class, 'ping']);
        Route::post('/send', [FonnteController::class, 'send']);
    });

    Route::prefix('tokopay')->group(function () {
        Route::post('/webhook', [TokopayController::class, 'webhook']);
    });

    Route::get('/debug/enums', function () {
        try {
            $blType = \Illuminate\Support\Facades\DB::connection('mdl_main')
                ->select("SHOW COLUMNS FROM `business_list` LIKE 'enum'");
        } catch (\Throwable $e) {
            $blType = [];
        }
        try {
            $ubType = \Illuminate\Support\Facades\DB::connection('mdl_main')
                ->select("SHOW COLUMNS FROM `user_business` LIKE 'business_enum'");
        } catch (\Throwable $e) {
            $ubType = [];
        }
        $blEnums = [];
        $ubEnums = [];
        try {
            $blEnums = \Illuminate\Support\Facades\DB::connection('mdl_main')
                ->table('business_list')->select(['enum'])->limit(50)->get();
        } catch (\Throwable $e) {
        }
        try {
            $ubEnums = \Illuminate\Support\Facades\DB::connection('mdl_main')
                ->table('user_business')->select(['business_enum'])->limit(50)->get();
        } catch (\Throwable $e) {
        }
        return response()->json([
            'success' => true,
            'business_list_enum_type' => $blType[0]->Type ?? null,
            'user_business_enum_type' => $ubType[0]->Type ?? null,
            'business_list_enums' => array_values(array_unique(array_map(fn($r) => (string) ($r->enum ?? ''), $blEnums))),
            'user_business_enums' => array_values(array_unique(array_map(fn($r) => (string) ($r->business_enum ?? ''), $ubEnums))),
        ]);
    });

    Route::get('/debug/users', function (Request $request) {
        $u = $request->query('username');
        $p = $request->query('phone');
        $usernameCount = $u ? MainUser::where('username', $u)->count() : null;
        $phoneCount = $p ? MainUser::where('phone_number', $p)->count() : null;
        return response()->json([
            'username' => $u,
            'username_count' => $usernameCount,
            'phone_number' => $p,
            'phone_number_count' => $phoneCount,
        ]);
    });

    Route::post('/debug/create', function (Request $request) {
        $data = $request->only(['name', 'username', 'phone_number', 'password']);
        try {
            $user = MainUser::create($data);
            return response()->json(['created' => true, 'id' => $user->id]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['created' => false, 'error' => $e->getMessage()], 422);
        }
    });

    Route::post('/debug/echo', function (Request $request) {
        return response()->json(['received' => $request->all()]);
    });
});
