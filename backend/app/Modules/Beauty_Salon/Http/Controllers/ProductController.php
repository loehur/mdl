<?php

namespace App\Modules\Beauty_Salon\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private function generateId(): string
    {
        $prefix = (int) date('Y') - 2024;
        $suffix = date('mdHis') . (string) random_int(0, 9);
        return (string) $prefix . $suffix;
    }

    public function list(Request $request)
    {
        $businessId = (int) $request->query('business_id');
        $q = DB::connection('salon')->table('product')
            ->select(['product_id', 'name', 'type', 'price', 'business_id'])
            ->orderBy('product_id', 'desc')
            ->limit(500);
        if ($businessId > 0) {
            $q->where('business_id', $businessId);
        }
        $rows = $q->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'product_id' => (string) ($r->product_id ?? ''),
                'name' => (string) ($r->name ?? ''),
                'type' => (string) ($r->type ?? ''),
                'price' => isset($r->price) ? (float) $r->price : null,
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
        $businessId = (int) $request->input('business_id');
        $name = trim((string) ($request->input('name') ?? ''));
        $price = (float) ($request->input('price') ?? 0);
        $type = (string) ($request->input('type') ?? 'barang');
        if ($businessId <= 0 || $name === '' || $price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        $productId = $this->generateId();
        if (!in_array($type, ['barang', 'jasa'], true)) {
            $type = 'barang';
        }
        try {
            DB::connection('salon')->beginTransaction();
            DB::connection('salon')->table('product')->insert([
                'product_id' => $productId,
                'business_id' => $businessId,
                'name' => $name,
                'price' => (float) $price,
                'type' => (string) $type,
            ]);
            DB::connection('salon')->commit();
        } catch (\Throwable $e) {
            DB::connection('salon')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Produk dan harga tersimpan',
            'product_id' => $productId,
            'type' => $type,
        ]);
    }

    public function delete(Request $request)
    {
        $businessId = (int) $request->input('business_id');
        $productId = (string) ($request->input('product_id') ?? '');
        if ($businessId <= 0 || $productId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        try {
            DB::connection('salon')->beginTransaction();
            $p = DB::connection('salon')->table('product')
                ->select(['product_id', 'business_id'])
                ->where('product_id', $productId)
                ->first();
            if (!$p || (int) ($p->business_id ?? 0) !== $businessId) {
                DB::connection('salon')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan',
                ], 404);
            }
            $existsSale = DB::connection('salon')->table('sale_item')
                ->where('product_id', $productId)
                ->exists();
            if ($existsSale) {
                DB::connection('salon')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Produk sudah digunakan',
                ], 409);
            }
            DB::connection('salon')->table('product')
                ->where('product_id', $productId)
                ->delete();
            DB::connection('salon')->commit();
            return response()->json([
                'success' => true,
                'message' => 'Produk dihapus',
                'deleted_product' => true,
            ]);
        } catch (\Throwable $e) {
            DB::connection('salon')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus',
            ], 500);
        }
    }

    public function edit(Request $request)
    {
        $businessId = (int) $request->input('business_id');
        $productId = (string) ($request->input('product_id') ?? '');
        $price = $request->input('price');
        $type = $request->input('type');
        $name = $request->input('name');
        if ($businessId <= 0 || $productId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        if ($price !== null) {
            $price = (float) $price;
            if ($price <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Harga tidak valid',
                ], 422);
            }
        }
        if ($type !== null && !in_array((string) $type, ['barang', 'jasa'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Type tidak valid',
            ], 422);
        }
        try {
            DB::connection('salon')->beginTransaction();
            if ($name !== null) {
                $name = trim((string) $name);
                if ($name === '') {
                    DB::connection('salon')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Nama tidak valid',
                    ], 422);
                }
                $usedByOther = DB::connection('salon')->table('sale_item as si')
                    ->join('sale_ref as r', 'r.ref_id', '=', 'si.ref_id')
                    ->where('si.product_id', $productId)
                    ->where('r.business_id', '<>', $businessId)
                    ->exists();
                if ($usedByOther) {
                    DB::connection('salon')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Produk sudah digunakan bisnis lain, perubahan nama produk hanya dapat dilakukan oleh MDL Technical Support.',
                    ], 403);
                }
                DB::connection('salon')->table('product')
                    ->where('product_id', $productId)
                    ->where('business_id', $businessId)
                    ->update(['name' => $name]);
            }
            if ($type !== null) {
                DB::connection('salon')->table('product')
                    ->where('product_id', $productId)
                    ->where('business_id', $businessId)
                    ->update(['type' => (string) $type]);
            }
            if ($price !== null) {
                DB::connection('salon')->table('product')
                    ->where('product_id', $productId)
                    ->where('business_id', $businessId)
                    ->update(['price' => (float) $price]);
            }
            DB::connection('salon')->commit();
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diubah',
            ]);
        } catch (\Throwable $e) {
            DB::connection('salon')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah',
            ], 500);
        }
    }
}
