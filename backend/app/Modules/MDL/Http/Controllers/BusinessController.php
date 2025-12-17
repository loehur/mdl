<?php

namespace App\Modules\MDL\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function options(Request $request)
    {
        $rows = DB::connection('mdl_main')->table('business_list')
            ->select(['enum'])
            ->orderBy('enum')
            ->limit(200)
            ->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'enum' => (string) $r->enum,
                'business_name' => (string) $r->enum,
            ];
        }
        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function list(Request $request)
    {
        $userId = (int) $request->query('user_id');
        if ($userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'user_id wajib',
            ], 422);
        }
        $rows = DB::connection('mdl_main')->table('user_business')
            ->select(['id', 'user_id', 'business_enum', 'business_brand', 'area_code', 'branch_code', 'business_status', 'created_at'])
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int) $r->id,
                'user_id' => (int) $r->user_id,
                'business_enum' => (string) $r->business_enum,
                'business_brand' => (string) ($r->business_brand ?? ''),
                'area_code' => (string) $r->area_code,
                'branch_code' => (string) $r->branch_code,
                'business_status' => (string) $r->business_status,
                'created_at' => $r->created_at,
            ];
        }
        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function add(Request $request)
    {
        $userId = (int) $request->input('user_id');
        $businessEnum = (string) $request->input('business_enum');
        $area = (string) $request->input('area_code');
        $branch = (string) $request->input('branch_code');
        $brand = (string) $request->input('business_brand');
        $area = substr(trim($area), 0, 3);
        $branch = substr(trim($branch), 0, 3);
        if ($userId <= 0 || $businessEnum === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        // Resolve brand from business_list by enum if not provided
        $bizBrand = $brand;
        if ($bizBrand === '') {
            $bl = DB::connection('mdl_main')->table('business_list')
                ->select(['enum', 'name'])
                ->where('enum', $businessEnum)
                ->first();
            $bizBrand = $bl ? (string) ($bl->name ?? $businessEnum) : '';
        }
        if ($bizBrand === '') {
            return response()->json([
                'success' => false,
                'message' => 'Brand bisnis tidak ditemukan',
            ], 422);
        }
        // Insert into current table user_business
        $id = DB::connection('mdl_main')->table('user_business')->insertGetId([
            'user_id' => $userId,
            'business_enum' => $businessEnum,
            'business_brand' => $bizBrand,
            'area_code' => $area !== '' ? $area : null,
            'branch_code' => $branch !== '' ? $branch : null,
            'business_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Business ditambahkan',
        ]);
    }

    public function delete(Request $request)
    {
        $id = (int) $request->input('id');
        $userId = (int) $request->input('user_id');
        if ($id <= 0 || $userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        DB::connection('mdl_main')->table('user_business')->where('id', $id)->where('user_id', $userId)->delete();
        return response()->json([
            'success' => true,
            'deleted' => true,
        ]);
    }
}
