<?php

namespace App\Modules\Beauty_Salon\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerController extends Controller
{
    public function assign(Request $request)
    {
        $businessId = (int) ($request->input('business_id') ?? 0);
        $userId = (int) ($request->input('id_user') ?? 0);
        $itemId = (int) ($request->input('item_id') ?? 0);
        $employeeId = (string) ($request->input('employee_id') ?? '');
        if ($businessId <= 0 || $userId <= 0 || $itemId <= 0 || $employeeId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        $u = DB::connection('salon')->table('users')
            ->select(['id', 'business_id'])
            ->where('id', $userId)
            ->first();
        if (!$u || (int) $u->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }
        $itemRef = DB::connection('salon')->table('sale_item as si')
            ->leftJoin('sale_ref as r', 'r.ref_id', '=', 'si.ref_id')
            ->select(['si.id', 'si.ref_id', 'r.business_id'])
            ->where('si.id', $itemId)
            ->first();
        if (!$itemRef || (int) ($itemRef->business_id ?? 0) !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan',
            ], 404);
        }
        $emp = DB::connection('salon')->table('employee')
            ->select(['employee_id', 'business_id'])
            ->where('employee_id', $employeeId)
            ->first();
        if (!$emp || (int) ($emp->business_id ?? 0) !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Employee tidak valid',
            ], 422);
        }
        DB::connection('salon')->table('worker')->insert([
            'item_id' => $itemId,
            'employee_id' => $employeeId,
        ]);
        $refId = (string) ($itemRef->ref_id ?? '');
        $rows = DB::connection('salon')->table('sale_item as si')
            ->select(['si.id as item_id', DB::raw('si.type as type')])
            ->where('si.ref_id', $refId)
            ->get();
        $need = [];
        foreach ($rows as $r) {
            $t = strtolower((string) ($r->type ?? ''));
            if ($t === 'jasa') {
                $iid = (int) ($r->item_id ?? 0);
                if ($iid > 0) $need[$iid] = true;
            }
        }
        $okCount = 0;
        if (!empty($need)) {
            $ids = array_keys($need);
            $ws = DB::connection('salon')->table('worker')
                ->select(['item_id'])
                ->whereIn('item_id', $ids)
                ->get();
            $okMap = [];
            foreach ($ws as $w) {
                $iid = (int) ($w->item_id ?? 0);
                if ($iid > 0) $okMap[$iid] = true;
            }
            $okCount = count($okMap);
        }
        $allWorkers = empty($need) || ($okCount === count($need));
        $sum = DB::connection('salon')->table('sale_item')
            ->where('ref_id', $refId)
            ->selectRaw('SUM(price * qty) as total_amount')
            ->value('total_amount');
        $total = (float) ($sum ?? 0);
        $sumPaid = DB::connection('salon')->table('ledger')
            ->where('business_id', $businessId)
            ->where('ref_id', $refId)
            ->where('type', 'sale')
            ->selectRaw('SUM(amount) as paid_total')
            ->value('paid_total');
        $paidTotal = (float) ($sumPaid ?? 0);
        $isPaid = $paidTotal >= $total && $total > 0;
        $finalized = false;
        if ($allWorkers && $isPaid) {
            DB::connection('salon')->table('sale_ref')
                ->where('ref_id', $refId)
                ->where('business_id', $businessId)
                ->update(['order_status' => 'selesai']);
            $finalized = true;
        }
        return response()->json([
            'success' => true,
            'message' => 'Worker ditetapkan',
            'finalized' => $finalized,
        ]);
    }
}
