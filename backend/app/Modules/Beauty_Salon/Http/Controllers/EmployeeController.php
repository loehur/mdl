<?php

namespace App\Modules\Beauty_Salon\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
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
        $q = DB::connection('salon')->table('employee')
            ->select(['employee_id', 'name', 'business_id'])
            ->orderBy('name')
            ->limit(500);
        if ($businessId > 0) {
            $q->where('business_id', $businessId);
        }
        $rows = $q->get();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'employee_id' => (string) ($r->employee_id ?? ''),
                'name' => (string) ($r->name ?? ''),
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
        if ($name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama wajib',
            ], 422);
        }
        $businessId = (int) $request->input('business_id');
        if ($businessId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'business_id wajib',
            ], 422);
        }
        $employeeId = $this->generateId();
        DB::connection('salon')->table('employee')->insert([
            'employee_id' => $employeeId,
            'name' => $name,
            'business_id' => $businessId,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Employee ditambahkan',
            'employee_id' => $employeeId,
        ]);
    }

    public function edit(Request $request)
    {
        $employeeId = (string) ($request->input('employee_id') ?? '');
        $name = trim((string) ($request->input('name') ?? ''));
        if ($employeeId === '' || $name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        $exists = DB::connection('salon')->table('employee')
            ->where('employee_id', $employeeId)
            ->exists();
        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Employee tidak ditemukan',
            ], 404);
        }
        DB::connection('salon')->table('employee')
            ->where('employee_id', $employeeId)
            ->update(['name' => $name]);
        return response()->json([
            'success' => true,
            'message' => 'Berhasil diubah',
        ]);
    }
}
