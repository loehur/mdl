<?php

namespace App\Modules\MDL\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SalonUserController extends Controller
{
    private function generateId(): string
    {
        $prefix = (int) date('Y') - 2024;
        $suffix = date('mdHis') . (string) random_int(0, 9);
        return (string) $prefix . $suffix;
    }

    public function add(Request $request)
    {
        $userId = (int) $request->input('user_id');
        $businessId = (int) $request->input('business_id');
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirm');

        if ($userId <= 0 || $businessId <= 0 || $password === '' || $confirm === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        if ($password !== $confirm) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi password tidak cocok',
            ], 422);
        }

        $belongs = DB::connection('mdl_main')->table('user_business')
            ->where('id', $businessId)
            ->where('user_id', $userId)
            ->exists();
        if (!$belongs) {
            return response()->json([
                'success' => false,
                'message' => 'Business tidak ditemukan',
            ], 404);
        }

        $existsAdmin = DB::connection('salon')->table('users')
            ->where('business_id', $businessId)
            ->where('role', 'admin')
            ->exists();
        if ($existsAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin sudah ada untuk business ini',
            ], 409);
        }

        $hashed = Hash::make($password);

        $id = DB::connection('salon')->table('users')->insertGetId([
            'business_id' => $businessId,
            'password' => $hashed,
            'role' => 'admin',
            'name' => 'Administrator',
        ]);

        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'User salon ditambahkan',
        ]);
    }

    public function login(Request $request)
    {
        $idUser = (int) $request->input('id_user');
        $password = (string) $request->input('password');
        if ($idUser <= 0 || $password === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        $row = DB::connection('salon')->table('users')
            ->select(['id', 'business_id', 'password', 'role', 'name'])
            ->where('id', $idUser)
            ->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 404);
        }
        if (!Hash::check($password, (string) $row->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah',
            ], 422);
        }
        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => [
                'id' => (int) $row->id,
                'business_id' => (int) $row->business_id,
                'role' => (string) $row->role,
                'name' => (string) ($row->name ?? ''),
            ],
            'redirect' => '/dashboard',
        ]);
    }

    public function list(Request $request)
    {
        $businessId = (int) $request->query('business_id');
        $requesterId = (int) $request->query('id_user');
        $userId = (int) $request->query('user_id');
        // If business_id is provided, list users for that business directly
        if ($businessId > 0) {
            if ($requesterId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'id_user wajib',
                ], 422);
            }
            $req = DB::connection('salon')->table('users')
                ->select(['id', 'business_id', 'role'])
                ->where('id', $requesterId)
                ->first();
            if (!$req || (int) $req->business_id !== $businessId || (string) $req->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden',
                ], 403);
            }
            $biz = DB::connection('mdl_main')->table('user_business')
                ->select(['id', 'business_brand', 'branch_code'])
                ->where('id', $businessId)
                ->first();
            if (!$biz) {
                return response()->json([
                    'success' => true,
                    'items' => [],
                    'total' => 0,
                ]);
            }
            $rows = DB::connection('salon')->table('users')
                ->select(['id', 'business_id', 'role', 'name'])
                ->where('business_id', $businessId)
                ->orderByDesc('id')
                ->limit(500)
                ->get();
            $items = [];
            foreach ($rows as $r) {
                $items[] = [
                    'id' => (int) $r->id,
                    'business_id' => (int) $r->business_id,
                    'business_name' => (string) ($biz->business_brand ?? ''),
                    'branch_code' => (string) ($biz->branch_code ?? ''),
                    'role' => (string) $r->role,
                    'name' => (string) ($r->name ?? ''),
                ];
            }
            return response()->json([
                'success' => true,
                'items' => $items,
                'total' => count($items),
            ]);
        }

        // Fallback: if user_id is provided, list users for all businesses belonging to the user
        if ($userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'business_id atau user_id wajib',
            ], 422);
        }
        $rowsBiz = DB::connection('mdl_main')->table('user_business')
            ->select(['id', 'business_brand', 'branch_code'])
            ->where('user_id', $userId)
            ->limit(500)
            ->get();
        $map = [];
        foreach ($rowsBiz as $b) {
            $map[(int) $b->id] = [
                'business_name' => (string) ($b->business_brand ?? ''),
                'branch_code' => (string) $b->branch_code,
            ];
        }
        $ids = array_keys($map);
        if (empty($ids)) {
            return response()->json([
                'success' => true,
                'items' => [],
                'total' => 0,
            ]);
        }
        $rows = DB::connection('salon')->table('users')
            ->select(['id', 'business_id', 'role', 'name'])
            ->whereIn('business_id', $ids)
            ->orderByDesc('id')
            ->limit(500)
            ->get();
        $items = [];
        foreach ($rows as $r) {
            $info = $map[(int) $r->business_id] ?? ['business_name' => '', 'branch_code' => ''];
            $items[] = [
                'id' => (int) $r->id,
                'business_id' => (int) $r->business_id,
                'business_name' => (string) $info['business_name'],
                'branch_code' => (string) $info['branch_code'],
                'role' => (string) $r->role,
                'name' => (string) ($r->name ?? ''),
            ];
        }
        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function changePassword(Request $request)
    {
        $idUser = (int) $request->input('id_user');
        $oldPassword = (string) $request->input('old_password');
        $newPassword = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirm');
        if ($idUser <= 0 || $oldPassword === '' || $newPassword === '' || $confirm === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        if ($newPassword !== $confirm) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi password tidak cocok',
            ], 422);
        }
        $row = DB::connection('salon')->table('users')
            ->select(['id', 'password'])
            ->where('id', $idUser)
            ->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
            ], 404);
        }
        if (!Hash::check($oldPassword, (string) $row->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama salah',
            ], 422);
        }
        $hashed = Hash::make($newPassword);
        DB::connection('salon')->table('users')
            ->where('id', $idUser)
            ->update(['password' => $hashed]);
        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah',
        ]);
    }

    public function addCashier(Request $request)
    {
        $businessId = (int) $request->input('business_id');
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirm');
        $name = trim((string) ($request->input('name') ?? ''));
        $requesterId = (int) $request->input('id_requester');
        if ($businessId <= 0 || $password === '' || $confirm === '' || $name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid (nama wajib)',
            ], 422);
        }
        if ($requesterId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'id_requester wajib',
            ], 422);
        }
        $req = DB::connection('salon')->table('users')
            ->select(['id', 'business_id', 'role'])
            ->where('id', $requesterId)
            ->first();
        if (!$req || (int) $req->business_id !== $businessId || (string) $req->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }
        if ($password !== $confirm) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi password tidak cocok',
            ], 422);
        }
        $hashed = Hash::make($password);
        $id = DB::connection('salon')->table('users')->insertGetId([
            'business_id' => $businessId,
            'password' => $hashed,
            'role' => 'cashier',
            'name' => $name,
        ]);
        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Cashier ditambahkan',
        ]);
    }

    public function productList(Request $request)
    {
        try {
            $rows = DB::connection('salon')->table('product')
                ->select(['product_id', 'name', 'type'])
                ->orderBy('name')
                ->limit(500)
                ->get();
        } catch (\Throwable $e) {
            $rows = DB::connection('salon')->table('product')
                ->select(['product_id', 'name'])
                ->orderBy('name')
                ->limit(500)
                ->get();
        }
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'product_id' => (string) ($r->product_id ?? ''),
                'name' => (string) ($r->name ?? ''),
                'type' => (string) ($r->type ?? ''),
            ];
        }
        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function addProduct(Request $request)
    {
        $businessId = (int) $request->input('business_id');
        $name = trim((string) ($request->input('name') ?? ''));
        $price = (float) ($request->input('price') ?? 0);
        $productId = (string) ($request->input('product_id') ?? '');
        $type = (string) ($request->input('type') ?? 'barang');
        if ($businessId <= 0 || $name === '' || $price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        if ($productId === '') {
            $productId = $this->generateId();
        }
        if (!in_array($type, ['barang', 'jasa'], true)) {
            $type = 'barang';
        }
        try {
            DB::connection('salon')->beginTransaction();
            try {
                DB::connection('salon')->table('product')->insert([
                    'product_id' => $productId,
                    'name' => $name,
                    'type' => $type,
                ]);
            } catch (\Throwable $e) {
                DB::connection('salon')->table('product')->insert([
                    'product_id' => $productId,
                    'name' => $name,
                ]);
            }
            DB::connection('salon')->table('price')->insert([
                'business_id' => $businessId,
                'product_id' => $productId,
                'price' => $price,
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
}
