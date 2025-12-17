<?php

namespace App\Modules\Beauty_Salon\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
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
        $q = DB::connection('salon')->table('customer')
            ->select(['customer_id', 'name', 'phone_number', 'business_id'])
            ->orderBy('name')
            ->limit(500);
        if ($businessId > 0) {
            $q->where('business_id', $businessId);
        }
        $rows = $q->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'customer_id' => (string) ($r->customer_id ?? ''),
                'name' => (string) ($r->name ?? ''),
                'phone_number' => (string) ($r->phone_number ?? ''),
                'business_id' => (int) ($r->business_id ?? 0),
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
        $name = trim((string) ($request->input('name') ?? ''));
        $phone = trim((string) ($request->input('phone_number') ?? ''));
        if ($name === '' || $phone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama dan nomor HP wajib',
            ], 422);
        }
        $businessId = (int) $request->input('business_id');
        if ($businessId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'business_id wajib',
            ], 422);
        }
        $customerId = $this->generateId();
        DB::connection('salon')->table('customer')->insert([
            'customer_id' => $customerId,
            'name' => $name,
            'phone_number' => $phone,
            'business_id' => $businessId,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Customer ditambahkan',
            'customer_id' => $customerId,
        ]);
    }

    public function edit(Request $request)
    {
        $customerId = (string) ($request->input('customer_id') ?? '');
        $name = $request->input('name');
        $phone = $request->input('phone_number');
        if ($customerId === '' || ($name === null && $phone === null)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        $exists = DB::connection('salon')->table('customer')
            ->where('customer_id', $customerId)
            ->exists();
        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Customer tidak ditemukan',
            ], 404);
        }
        $payload = [];
        if ($name !== null) $payload['name'] = trim((string) $name);
        if ($phone !== null) $payload['phone_number'] = trim((string) $phone);
        if (!empty($payload)) {
            DB::connection('salon')->table('customer')
                ->where('customer_id', $customerId)
                ->update($payload);
        }
        return response()->json([
            'success' => true,
            'message' => 'Berhasil diubah',
        ]);
    }
}
